<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// NEW 24 Jul 2026 — Chris: "remove CA teow" — the Organization Category
// Group dropdown showed "CA TEOW's Group" 6 times. Rather than blindly
// deleting rows (a wrong delete here could orphan real agents — agents.
// group_id has no foreign key, so deleting a group a real agent still
// points to would silently break screens that expect that group to
// exist), this SAFELY splits the matches into two buckets:
//   - 0 agents currently linked (agent_id via agents.group_id)  -> safe
//     to delete, shown for confirmation
//   - 1+ agents linked -> always KEPT, never deleted, shown separately
//     so Chris can see which one is the real PVATM group
// Only ever touches rows whose group_name matches "CA TEOW" — does not
// touch any other group.
//
// Run via: php artisan groups:cleanup-ca-teow
class CleanupCaTeowGroups extends Command
{
    protected $signature = 'groups:cleanup-ca-teow';
    protected $description = 'Show every group named like "CA TEOW" and safely delete only the empty duplicates (never one with real agents linked)';

    public function handle(): int
    {
        $groups = DB::table('groups')->where('group_name', 'like', '%CA TEOW%')->orderBy('created_at')->get();

        if ($groups->isEmpty()) {
            $this->info('No groups matching "CA TEOW" found — nothing to do.');
            return self::SUCCESS;
        }

        $safeToDelete = [];
        $mustKeep = [];

        foreach ($groups as $g) {
            $memberCount = DB::table('agents')->where('group_id', $g->group_id)->where('is_deleted', false)->count();
            $leader = DB::table('agents')
                ->where('group_id', $g->group_id)
                ->where('role', 'GROUP_LEADER')
                ->where('is_deleted', false)
                ->first(['full_name', 'email']);

            $row = [
                'group_id'   => $g->group_id,
                'group_code' => $g->group_code,
                'group_name' => $g->group_name,
                'created_at' => $g->created_at,
                'members'    => $memberCount,
                'leader'     => $leader ? "{$leader->full_name} ({$leader->email})" : null,
            ];

            if ($memberCount > 0) {
                $mustKeep[] = $row;
            } else {
                $safeToDelete[] = $row;
            }
        }

        $this->info(count($groups) . ' group(s) found matching "CA TEOW":');
        $this->line('');

        if (!empty($mustKeep)) {
            $this->warn('KEEPING (has real agent(s) linked — never deleted):');
            foreach ($mustKeep as $r) {
                $this->line("  {$r['group_code']} — {$r['group_name']} — {$r['members']} member(s) — GL: " . ($r['leader'] ?? '— none —'));
            }
            $this->line('');
        }

        if (empty($safeToDelete)) {
            $this->info('No empty duplicate groups found — nothing to delete.');
            return self::SUCCESS;
        }

        $this->info('SAFE TO DELETE (0 agents linked):');
        foreach ($safeToDelete as $r) {
            $this->line("  {$r['group_code']} — {$r['group_name']} — created {$r['created_at']}");
        }
        $this->line('');

        if (!$this->confirm('Delete these ' . count($safeToDelete) . ' empty duplicate group(s) now?', false)) {
            $this->warn('Cancelled — nothing deleted.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($safeToDelete) {
            foreach ($safeToDelete as $r) {
                DB::table('groups')->where('group_id', $r['group_id'])->delete();
            }
        });

        $this->info('Done — ' . count($safeToDelete) . ' empty duplicate group(s) deleted. The real PVATM group (with its linked agents) was left untouched.');
        return self::SUCCESS;
    }
}
