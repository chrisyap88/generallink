<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// NEW 8 Jan 2026 — per Chris: "PVATM have rank assignment and many
// records in PVATM have no rank name and rank no... for Ali group you
// random set 2 different rank and make every ali has the rank name and
// rank number." This randomly distributes every currently-unranked
// PVATM Introducer ("Ali") across exactly 2 of PVATM's own active
// Introducer ranks, so nobody is left showing "No Rank" anymore.
// Only ever touches agents.rank_id where it is currently NULL — an
// agent who already has a rank set is never overwritten.
class FillPvatmIntroducerRanks extends Command
{
    protected $signature = 'rank:fill-pvatm-introducer-ranks {--dry-run : Show what would change without saving anything}';

    protected $description = "Randomly assign one of PVATM's 2 entry-level Introducer ranks to every unranked PVATM Introducer (Ali)";

    public function handle()
    {
        $group = DB::table('group_labels')->where('group_name', 'like', '%PVATM%')->first();
        if (!$group) {
            $this->error('No group_labels row matching "PVATM" found.');
            return 1;
        }

        $ranks = DB::table('role_ranks')
            ->where('group_label_id', $group->group_label_id)
            ->where('role', 'INTRODUCER')
            ->where('is_active', true)
            ->get()
            ->sortBy(fn ($r) => $r->rank_no, SORT_NATURAL)
            ->values();

        if ($ranks->count() < 2) {
            $this->error("PVATM only has {$ranks->count()} active Introducer rank(s) defined. Need at least 2 — set them up in Rank Hierarchy Maintenance first.");
            return 1;
        }

        // Use the two lowest (entry-level) ranks — the ones a bulk of
        // unranked, otherwise-unremarkable Ali agents would realistically
        // sit at.
        $twoRanks = $ranks->take(2)->values();
        $this->info('Using ranks: ' . $twoRanks->map(fn ($r) => "{$r->rank_no} — {$r->rank_name}")->implode(', '));

        $agents = DB::table('agents')
            ->where('group_label_id', $group->group_label_id)
            ->where('role', 'INTRODUCER')
            ->where('is_deleted', false)
            ->whereNull('rank_id')
            ->get(['agent_id', 'agent_code', 'full_name']);

        $this->info("Found {$agents->count()} unranked Ali agent(s).");

        if ($agents->isEmpty()) {
            $this->info('Nothing to do — every Ali already has a rank.');
            return 0;
        }

        $dryRun = $this->option('dry-run');
        $counts = [
            $twoRanks[0]->rank_id => 0,
            $twoRanks[1]->rank_id => 0,
        ];

        foreach ($agents as $agent) {
            $pick = $twoRanks[array_rand([0, 1])];
            $counts[$pick->rank_id]++;

            if (!$dryRun) {
                DB::table('agents')->where('agent_id', $agent->agent_id)->update([
                    'rank_id'    => $pick->rank_id,
                    'updated_at' => now(),
                ]);
            }
        }

        foreach ($twoRanks as $r) {
            $tag = $dryRun ? ' (dry-run, not saved)' : '';
            $this->info("{$r->rank_no} — {$r->rank_name}: {$counts[$r->rank_id]} agent(s){$tag}");
        }

        if ($dryRun) {
            $this->comment('Dry-run only — no changes were saved. Re-run without --dry-run to apply.');
        } else {
            $this->info('Done — every PVATM Ali now has a rank.');
        }

        return 0;
    }
}
