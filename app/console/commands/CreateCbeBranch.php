<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 10 Sep 2026 (Task #397 follow-up) — per Chris: "Branch has its own
// bank account number... HQ, State, Branch, Temple, Members... all have
// its own bank account number." The original hierarchy design (17/21
// Aug 2026) always allowed HQ -> State -> Branch -> Temple as a level
// structure ("nothing here is hardcoded to 3"), but the real 591-temple
// import the next day (ImportCbeTemples.php, 22 Aug) deliberately
// skipped creating a real Branch entity and stored Klang/Kapar/Meru/
// Pulau Ketam/Pelabuhan Klang as a flat "city" label directly on each
// Temple instead. Chris has now reversed that call: Branch must be a
// real entity with its own bank account, sitting between State and
// Temple. This command creates ONE Branch node under a given State and
// reparents every Temple in that State whose city matches the given
// list under it — reusable for future branches (Penang, Johor Bahru,
// etc.) without hardcoding Klang specifically.
//
// Every "scope to this node and everything below it" query in this app
// filters on hierarchy_path LIKE 'prefix%' (confirmed by reading
// AdminCbeKpiController before writing this), not a single parent_node_id
// hop — so rewriting hierarchy_path correctly for the branch and every
// moved temple is what keeps dashboards/reports correct after this
// change, with no other screen needing to change.
class CreateCbeBranch extends Command
{
    protected $signature = 'cbe:create-branch
        {--group= : group_label_id of the CBE community}
        {--state= : Exact node_name of the State node (e.g. Selangor)}
        {--name= : Branch name, English (e.g. "Klang Branch")}
        {--name-zh= : Branch name, Chinese (optional)}
        {--cities=* : City values as stored on the Temple nodes, one per --cities flag}';

    protected $description = 'Create a real Branch entity under a State and reparent matching Temples under it';

    public function handle(): int
    {
        $groupId = $this->option('group');
        $stateName = $this->option('state');
        $branchName = $this->option('name');
        $branchNameZh = $this->option('name-zh');
        $cities = array_values(array_filter(array_map('trim', $this->option('cities'))));

        if (! $groupId || ! $stateName || ! $branchName || empty($cities)) {
            $this->error('Usage: cbe:create-branch --group=<group_label_id> --state="Selangor" --name="Klang Branch" --cities=Klang --cities=Kapar ...');

            return self::FAILURE;
        }

        $group = DB::table('group_labels')->where('group_label_id', $groupId)->first();
        if (! $group) {
            $this->error("No group_label found for group_label_id: $groupId");

            return self::FAILURE;
        }

        $stateNode = DB::table('cbe_hierarchy_nodes as n')
            ->join('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
            ->where('n.group_label_id', $groupId)
            ->where('l.level_name', 'State')
            ->where('n.node_name', $stateName)
            ->select('n.*')
            ->first();

        if (! $stateNode) {
            $this->error("No State node named \"$stateName\" found for this group.");

            return self::FAILURE;
        }

        // Normalize the requested city list the exact same way
        // ImportCbeTemples::canonicalizeCity() stored them, so a plain
        // "klang" typed on the command line still matches the stored
        // "Klang" — never guessing, just matching the same known rule.
        $normalizedCities = array_map(fn ($c) => ucwords(strtolower(trim($c))), $cities);

        $matchingTemples = DB::table('cbe_hierarchy_nodes as n')
            ->join('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
            ->where('n.group_label_id', $groupId)
            ->where('l.level_name', 'Temple')
            ->where('n.parent_node_id', $stateNode->node_id)
            ->whereIn('n.city', $normalizedCities)
            ->select('n.node_id', 'n.node_name', 'n.city', 'n.hierarchy_path')
            ->get();

        if ($matchingTemples->isEmpty()) {
            $this->error('No Temple nodes found under "'.$stateName.'" matching cities: '.implode(', ', $normalizedCities));
            $this->warn('Nothing was changed. Check the exact city spelling stored on the Temple records (Entity Maintenance search can confirm it).');

            return self::FAILURE;
        }

        $this->info('Found '.$matchingTemples->count().' temple(s) to move: '.$matchingTemples->pluck('node_name')->implode(', '));

        DB::beginTransaction();
        try {
            // Ensure a 'Branch' level exists for this group, sitting
            // between State and Temple. Existing Temple level_order is
            // bumped up by 1 to make room — level_id values (the actual
            // foreign key stored on every node row) are untouched, so no
            // node row needs updating for this part.
            $stateLevelOrder = DB::table('cbe_hierarchy_levels')
                ->where('group_label_id', $groupId)->where('level_name', 'State')->value('level_order');
            $templeLevel = DB::table('cbe_hierarchy_levels')
                ->where('group_label_id', $groupId)->where('level_name', 'Temple')->first();
            $branchLevel = DB::table('cbe_hierarchy_levels')
                ->where('group_label_id', $groupId)->where('level_name', 'Branch')->first();

            if (! $branchLevel) {
                $branchOrder = $stateLevelOrder + 1;
                if ($templeLevel && $templeLevel->level_order <= $branchOrder) {
                    DB::table('cbe_hierarchy_levels')->where('level_id', $templeLevel->level_id)
                        ->update(['level_order' => $branchOrder + 1, 'updated_at' => now()]);
                }
                $branchLevelId = (string) Str::uuid();
                DB::table('cbe_hierarchy_levels')->insert([
                    'level_id' => $branchLevelId, 'group_label_id' => $groupId,
                    'level_order' => $branchOrder, 'level_name' => 'Branch',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->info("Added 'Branch' as a new level (order $branchOrder) for this group.");
            } else {
                $branchLevelId = $branchLevel->level_id;
            }

            // Create the Branch node itself, under the State.
            $branchId = (string) Str::uuid();
            $branchPath = $stateNode->hierarchy_path.$branchId.'/';
            DB::table('cbe_hierarchy_nodes')->insert([
                'node_id' => $branchId, 'group_label_id' => $groupId, 'level_id' => $branchLevelId,
                'parent_node_id' => $stateNode->node_id, 'node_code' => Str::upper(Str::slug($branchName, '')),
                'node_name' => $branchName, 'node_name_zh' => $branchNameZh ?: null,
                'city' => null, 'postcode' => null, 'address' => null,
                'contact_person_1' => null, 'contact_person_2' => null,
                'external_reference_no' => null, 'hierarchy_path' => $branchPath,
                'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);

            // Reparent each matching Temple under the new Branch, and
            // rewrite its hierarchy_path to insert the Branch segment.
            foreach ($matchingTemples as $temple) {
                $newPath = $branchPath.$temple->node_id.'/';
                DB::table('cbe_hierarchy_nodes')->where('node_id', $temple->node_id)->update([
                    'parent_node_id' => $branchId,
                    'hierarchy_path' => $newPath,
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Failed, rolled back: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Done — \"$branchName\" created under \"$stateName\", ".$matchingTemples->count().' temple(s) moved under it.');

        return self::SUCCESS;
    }
}
