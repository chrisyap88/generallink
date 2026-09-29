<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\HierarchyService;
use Illuminate\Console\Command;

// NEW 24 Jul 2026 — Configurable Promotion & Demotion Rules add two
// criteria (Sales Volume, Tenure) that can change WITHOUT a new recruit
// ever joining — e.g. an agent's trailing sales volume can cross a
// threshold just from selling more, with nobody being recruited that
// day. The existing trigger (HierarchyService::evaluateRankChange())
// only fires when a new recruit activates their account, so it would
// miss these. This command re-checks every currently active agent
// against whatever rules are turned on right now, so Chris can run it
// periodically (see the `schedule` feature) to catch those cases too.
// Safe to run any time — evaluateRankChange() only changes something
// when a rule actually crosses a threshold; otherwise it's a no-op.
class EvaluatePromotions extends Command
{
    protected $signature = 'agents:evaluate-promotions';
    protected $description = 'Re-check every active Introducer/Team Leader/Group Leader against the current Promotion & Demotion Rules (needed for Sales Volume / Tenure criteria, which can change without a new recruit joining)';

    public function handle(HierarchyService $hierarchy): int
    {
        $agents = Agent::where('status', 'ACTIVE')
            ->whereIn('role', ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER'])
            ->get();

        $this->info("Re-evaluating {$agents->count()} active agent(s) against the current Promotion & Demotion Rules...");

        $bar = $this->output->createProgressBar($agents->count());
        $bar->start();

        foreach ($agents as $agent) {
            $hierarchy->evaluateRankChange($agent);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done. Any promotions/demotions this triggered are recorded in role_history and Audit Logs, same as usual.');

        return self::SUCCESS;
    }
}
