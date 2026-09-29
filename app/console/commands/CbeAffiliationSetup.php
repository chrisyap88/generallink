<?php

// NEW 27 Sep 2026 — per Chris: one command that does the whole CBE
// affiliation setup on his own computer and prints the real result, so he
// never has to click through screens to find out whether it worked.
//
//   php artisan gl:cbe-affiliation
//
// 1. Runs any pending migrations (affiliation column, postcode + district
//    reference).
// 2. Links the Branch (default: Cawangan Bandar Di Raja Klang) under its HQ
//    (default: 马来西亚道教总会) — same as picking "Affiliated To (Parent)".
// 3. Affiliates every entity of that HQ which sits in the district
//    (default: Klang, Selangor — by postcode OR town name) to the Branch —
//    same as CBE Entity Affiliation › District › GO › Save. Entities
//    already affiliated to another branch are left alone.
// 4. Clears / rebuilds the caches.
// 5. Prints the Box 4 figures for the HQ and the Branch from the database.
//
// Safe to run again: it never duplicates, it only fills what is missing.

namespace App\Console\Commands;

use App\Services\CbeHierarchyLinkService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CbeAffiliationSetup extends Command
{
    protected $signature = 'gl:cbe-affiliation
        {--branch=Cawangan Bandar Di Raja Klang : Branch entity name}
        {--hq=马来西亚道教总会 : HQ CBE group name}
        {--district=Klang : District name}
        {--state=Selangor : State of the district}';

    protected $description = 'Link a CBE Branch under its HQ and affiliate the HQ entities of a district to it, then report the counts';

    public function handle(): int
    {
        $this->line('');
        $this->info('STEP 1  Database update (migrate)');
        Artisan::call('migrate', ['--force' => true]);
        $this->line(trim(Artisan::output()) ?: 'Nothing to migrate.');

        foreach ([['cbe_hierarchy_nodes', 'affiliated_node_id'], ['postcode_localities', 'district']] as [$t, $c]) {
            if (! Schema::hasTable($t) || ! Schema::hasColumn($t, $c)) {
                $this->error("FAILED: $t.$c is missing — send this screen to Claude.");

                return self::FAILURE;
            }
        }
        $districtRows = DB::table('postcode_localities')->whereNotNull('district')->where('district', '!=', '')->count();
        $this->line("OK  affiliation column present; postcode reference rows with district: $districtRows");

        // ---- find the Branch and the HQ ----
        $this->line('');
        $this->info('STEP 2  Link the Branch under the HQ');
        $branch = DB::table('cbe_hierarchy_nodes')->where('node_name', $this->option('branch'))->first();
        if (! $branch) {
            $this->error('FAILED: Branch "'.$this->option('branch').'" not found.');

            return self::FAILURE;
        }
        $hqGroup = DB::table('group_labels')->where('group_type', 'CBE')->where('group_name', $this->option('hq'))->first();
        if (! $hqGroup) {
            $this->error('FAILED: CBE group "'.$this->option('hq').'" not found.');

            return self::FAILURE;
        }
        $hq = DB::table('cbe_hierarchy_nodes as n')
            ->join('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
            ->where('n.group_label_id', $hqGroup->group_label_id)
            ->orderBy('l.level_order')->select('n.*')->first();
        if (! $hq) {
            $this->error('FAILED: CBE group "'.$this->option('hq').'" has no HQ entity.');

            return self::FAILURE;
        }

        $curHq = \App\Services\CbeTierService::hqOf($branch);
        if ($branch->parent_node_id === $hq->node_id || ($curHq && $curHq->node_id === $hq->node_id)) {
            $this->line('OK  already linked: '.$branch->node_name.'  ->  '.$hq->node_name);
        } elseif (CbeHierarchyLinkService::setParent($branch->node_id, $hq->node_id)) {
            $this->line('DONE  linked: '.$branch->node_name.'  ->  '.$hq->node_name);
        } else {
            $this->error('FAILED: could not link the Branch under the HQ.');

            return self::FAILURE;
        }
        // Upline rule: a real State of the Branch's postcode wins over the HQ.
        if (\App\Services\CbeTierService::refreshFamily($hq->node_id)) {
            $this->line('DONE  upline rule applied (Branch moved under its real State)');
        }
        $branch = DB::table('cbe_hierarchy_nodes')->where('node_id', $branch->node_id)->first();

        // ---- affiliate the district's HQ entities ----
        $this->line('');
        $district = $this->option('district');
        $state = $this->option('state');
        $this->info("STEP 3  Affiliate the $district, $state entities of ".$hqGroup->group_name.' to the Branch');
        $ref = DB::table('postcode_localities')->where('district', $district)->where('state', $state);
        $postcodes = (clone $ref)->distinct()->pluck('postcode')->all();
        $towns = (clone $ref)->distinct()->pluck('post_office')->all();
        if (! $postcodes) {
            $this->error("FAILED: district $district, $state not found in the postcode reference.");

            return self::FAILURE;
        }
        $this->line('District towns: '.implode(', ', $towns));

        $bottomLevelId = DB::table('cbe_hierarchy_levels')->where('group_label_id', $hqGroup->group_label_id)
            ->orderByDesc('level_order')->value('level_id');
        $inDistrict = DB::table('cbe_hierarchy_nodes as n')
            ->where('n.group_label_id', $hqGroup->group_label_id)
            ->where('n.level_id', $bottomLevelId)
            ->where('n.hierarchy_path', 'like', '/'.$hq->node_id.'/%')
            ->where(fn ($w) => $w->whereIn('n.postcode', $postcodes)->orWhereIn('n.city', $towns));

        $found = (clone $inDistrict)->count();
        $elsewhere = (clone $inDistrict)->whereNotNull('n.affiliated_node_id')->where('n.affiliated_node_id', '!=', $branch->node_id)->count();
        // never re-add an entity the user unticked in the Affiliate Group tab
        if (Schema::hasTable('cbe_affiliation_exclusions')) {
            $inDistrict->whereNotExists(fn ($x) => $x->select(DB::raw(1))->from('cbe_affiliation_exclusions as e')
                ->whereColumn('e.node_id', 'n.node_id')->where('e.anchor_node_id', $branch->node_id));
        }
        $added = (clone $inDistrict)->whereNull('n.affiliated_node_id')
            ->update(['n.affiliated_node_id' => $branch->node_id, 'n.updated_at' => now()]);
        $linked = DB::table('cbe_hierarchy_nodes')->where('affiliated_node_id', $branch->node_id)->count();
        $this->line("Found in district: $found   newly affiliated: $added   already under another branch: $elsewhere");
        $this->line("DONE  entities affiliated to ".$branch->node_name.": $linked");

        // ---- caches ----
        $this->line('');
        $this->info('STEP 4  Refresh caches');
        Artisan::call('view:clear');
        Artisan::call('config:cache');
        $this->line('OK  views cleared, config cached');

        // ---- report ----
        $this->line('');
        $this->info('RESULT  (CBE KPI Dashboard › Box 4)');
        $this->table(['CBE', 'Linked under', 'Entities under it', 'Affiliated to it'], [
            [$hqGroup->group_name, '—', DB::table('cbe_hierarchy_nodes')->where('group_label_id', $hqGroup->group_label_id)->where('level_id', $bottomLevelId)->count(), DB::table('cbe_hierarchy_nodes')->where('hierarchy_path', 'like', $hq->hierarchy_path.'%')->where('group_label_id', '!=', $hqGroup->group_label_id)->count().' branch(es)'],
            [$branch->node_name, $hq->node_name, '—', $linked],
        ]);
        $this->line('Open: http://localhost/generallink/public/admin/cbe-kpi?node='.$branch->node_id);
        $this->line('');

        return self::SUCCESS;
    }
}
