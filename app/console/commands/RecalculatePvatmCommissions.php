<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\CommissionEngine;
use App\Services\HierarchyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 8 Jan 2026 — per Chris: "now need to rerun earning commission
// calculation for PVATM" — after ranks were just filled in for PVATM's
// Introducers ("Ali"), the existing commission:recalculate-all command
// is NOT enough here, because it deliberately SKIPS any policy that
// already has commission_transactions rows (to avoid double-paying).
// Most PVATM policies were already calculated (and many already
// confirmed/paid) BEFORE ranks existed, so recalculate-all would just
// leave their old, pre-rank amounts in place untouched.
//
// This command instead, for every PVATM policy:
//   1. Properly REVERSES any already-CONFIRMED commission rows (using
//      the same CommissionEngine::reverse() used for policy
//      cancellations — decrements the wallet, reverses reward points,
//      marks the old rows REVERSED for audit trail, never just deletes
//      paid history).
//   2. Deletes any leftover PENDING rows (never paid, safe to remove).
//   3. Re-runs CommissionEngine::calculate() to generate fresh PENDING
//      rows using the CURRENT Earning Income Structure and each agent's
//      now-assigned rank_id.
//   4. If the policy was already confirmed before (ACTIVE,
//      PENDING_RENEWAL, RENEWED, LAPSED), re-confirms it immediately
//      (credits the wallet with the corrected amount) — restoring its
//      already-decided paid state with the right numbers, not making a
//      new business decision. If it was still SUBMITTED, leaves the
//      fresh rows as PENDING for a real Confirm decision, same as
//      calculate() always has.
//
// Scoped to PVATM only: only touches policies whose selling agent
// (sales_transactions.agent_id) belongs to the PVATM group_label_id —
// no other group's agents or wallets are touched by this command.
class RecalculatePvatmCommissions extends Command
{
    protected $signature = 'commission:recalculate-pvatm {--dry-run : Show what would be affected without changing anything}';

    protected $description = 'Reverse and re-run earning income calculation for every PVATM policy, using current rank assignments';

    public function handle(CommissionEngine $engine, HierarchyService $hierarchyService): int
    {
        $group = DB::table('group_labels')->where('group_name', 'like', '%PVATM%')->first();
        if (!$group) {
            $this->error('No group_labels row matching "PVATM" found.');
            return self::FAILURE;
        }

        $policies = DB::table('sales_transactions as st')
            ->join('agents as a', 'a.agent_id', '=', 'st.agent_id')
            ->where('a.group_label_id', $group->group_label_id)
            ->where('st.is_deleted', false)
            ->whereNotIn('st.status', ['CANCELLED'])
            ->orderBy('st.created_at')
            ->get(['st.policy_id', 'st.policy_number', 'st.status']);

        $this->info("Found {$policies->count()} PVATM policy(ies) to recalculate.");

        if ($policies->isEmpty()) {
            $this->info('Nothing to do.');
            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            foreach ($policies as $p) {
                $this->line("  - {$p->policy_number} (status: {$p->status})");
            }
            $this->comment('Dry-run only — no changes were made. Re-run without --dry-run to apply.');
            return self::SUCCESS;
        }

        $confirm = $this->ask('This will reverse and recalculate earning income for all ' . $policies->count() . ' PVATM polic(ies) above, touching real wallet balances. Type YES to proceed, anything else to cancel');
        if (strtoupper(trim((string) $confirm)) !== 'YES') {
            $this->line('Cancelled — nothing was changed.');
            return self::SUCCESS;
        }

        $alreadyConfirmedStatuses = ['ACTIVE', 'PENDING_RENEWAL', 'RENEWED', 'LAPSED'];
        $reversed = 0;
        $recalculated = 0;
        $reconfirmed = 0;
        $agentIdsToRankCheck = [];

        $progressBar = $this->output->createProgressBar($policies->count());
        $progressBar->setFormat(" %current%/%max% [%bar%] %percent:3s%%  now on: %message%");
        $progressBar->start();

        foreach ($policies as $policy) {
            $progressBar->setMessage($policy->policy_number);
            $progressBar->advance();

            DB::transaction(function () use ($policy, $engine, $alreadyConfirmedStatuses, &$reversed, &$recalculated, &$reconfirmed, &$agentIdsToRankCheck) {
                $hadConfirmedRows = DB::table('commission_transactions')
                    ->where('policy_id', $policy->policy_id)
                    ->where('status', 'CONFIRMED')
                    ->exists();

                if ($hadConfirmedRows) {
                    $engine->reverse($policy->policy_id, Str::uuid()->toString());
                    $reversed++;
                }

                // Leftover PENDING rows were never paid — safe to just
                // remove before recalculating fresh ones.
                DB::table('commission_transactions')
                    ->where('policy_id', $policy->policy_id)
                    ->where('status', 'PENDING')
                    ->delete();

                $engine->calculate($policy->policy_id);
                $recalculated++;

                if (in_array($policy->status, $alreadyConfirmedStatuses)) {
                    $paidAgentIds = $engine->confirmPolicy($policy->policy_id, false);
                    foreach ($paidAgentIds as $agentId) {
                        $agentIdsToRankCheck[$agentId] = true;
                    }
                    $reconfirmed++;
                }
            });
        }

        $progressBar->finish();
        $this->line('');
        $this->line('');

        $uniqueAgentCount = count($agentIdsToRankCheck);
        if ($uniqueAgentCount > 0) {
            $this->info("Checking Promotion & Demotion Rules for {$uniqueAgentCount} agent(s) who were paid (once each)...");
            $rankBar = $this->output->createProgressBar($uniqueAgentCount);
            $rankBar->start();
            foreach (array_keys($agentIdsToRankCheck) as $agentId) {
                $agent = Agent::find($agentId);
                if ($agent) {
                    $hierarchyService->evaluateRankChange($agent);
                }
                $rankBar->advance();
            }
            $rankBar->finish();
            $this->line('');
            $this->line('');
        }

        $this->info('========================================================');
        $this->info(' PVATM RECALCULATION COMPLETE');
        $this->info('========================================================');
        $this->line("  Policies with previously-paid commission reversed first : {$reversed}");
        $this->line("  Policies recalculated (fresh PENDING rows created)      : {$recalculated}");
        $this->line("  Of those, re-confirmed and re-credited to wallet        : {$reconfirmed}");
        $this->line("  Left as PENDING, awaiting a real Confirm decision       : " . ($recalculated - $reconfirmed));

        return self::SUCCESS;
    }
}
