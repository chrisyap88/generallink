<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\CommissionEngine;
use App\Services\HierarchyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 2 Aug 2026 — per Chris: a narrowly-scoped version of
// commission:recalculate-pvatm for when only a HANDFUL of specific
// policies changed (e.g. moved into a different month / premium bumped
// for a demo) and re-running the recalculation across the whole PVATM
// group would be unnecessary. Same reverse-then-recalculate-then-
// reconfirm flow as that command, just scoped to an explicit list of
// policy IDs passed on the command line instead of a whole group — no
// confirmation prompt, since the caller already named the exact
// policies being touched.
class RecalculateSpecificPolicies extends Command
{
    protected $signature = 'commission:recalculate-policies {policy_ids* : One or more policy_id values to recalculate}';

    protected $description = 'Reverse and re-run earning income calculation for a specific, named list of policies.';

    public function handle(CommissionEngine $engine, HierarchyService $hierarchyService): int
    {
        $policyIds = $this->argument('policy_ids');

        $policies = DB::table('sales_transactions')
            ->whereIn('policy_id', $policyIds)
            ->where('is_deleted', false)
            ->get(['policy_id', 'policy_number', 'status']);

        if ($policies->isEmpty()) {
            $this->error('None of the given policy_id values matched a real policy.');
            return self::FAILURE;
        }

        $alreadyConfirmedStatuses = ['ACTIVE', 'PENDING_RENEWAL', 'RENEWED', 'LAPSED'];
        $agentIdsToRankCheck = [];

        foreach ($policies as $policy) {
            $this->line("Recalculating {$policy->policy_number}...");

            DB::transaction(function () use ($policy, $engine, $alreadyConfirmedStatuses, &$agentIdsToRankCheck) {
                $hadConfirmedRows = DB::table('commission_transactions')
                    ->where('policy_id', $policy->policy_id)
                    ->where('status', 'CONFIRMED')
                    ->exists();

                if ($hadConfirmedRows) {
                    $engine->reverse($policy->policy_id, Str::uuid()->toString());
                }

                // Leftover PENDING rows were never paid — safe to just
                // remove before recalculating fresh ones.
                DB::table('commission_transactions')
                    ->where('policy_id', $policy->policy_id)
                    ->where('status', 'PENDING')
                    ->delete();

                $engine->calculate($policy->policy_id);

                if (in_array($policy->status, $alreadyConfirmedStatuses)) {
                    $paidAgentIds = $engine->confirmPolicy($policy->policy_id, false);
                    foreach ($paidAgentIds as $agentId) {
                        $agentIdsToRankCheck[$agentId] = true;
                    }
                }
            });
        }

        foreach (array_keys($agentIdsToRankCheck) as $agentId) {
            $agent = Agent::find($agentId);
            if ($agent) {
                $hierarchyService->evaluateRankChange($agent);
            }
        }

        $this->info('Done — recalculated ' . $policies->count() . ' polic(ies): ' . $policies->pluck('policy_number')->implode(', '));

        return self::SUCCESS;
    }
}
