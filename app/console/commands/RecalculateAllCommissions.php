<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\CommissionEngine;
use App\Services\HierarchyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 29 Jul 2026 — the natural next step after Chris reset all
// earning income to zero and set up proper Earning Income Structures:
// there was never a "recalculate everything" button, since
// CommissionEngine::calculate() is normally only ever called once, at
// the moment a Sales Transaction is first submitted. This command
// fills that gap — re-runs calculate() for every existing transaction
// against the now-correct % structures.
//
// One deliberate distinction, to avoid silently making a business
// decision that was never actually made:
//   - If a transaction's status shows it was ALREADY confirmed/paid
//     before the reset (ACTIVE, PENDING_RENEWAL, RENEWED, LAPSED —
//     anything CommissionEngine::confirmPolicy() would have set, or a
//     natural progression from it), this command also re-confirms it
//     (credits the wallet) — that's just restoring its already-decided
//     paid state with the corrected numbers, not a new decision.
//   - If a transaction was still SUBMITTED (never confirmed before the
//     reset), this command calculates it as PENDING only and leaves
//     the real Confirm decision to Admin, exactly as originally
//     designed — it does NOT auto-confirm a sale nobody had actually
//     approved yet.
//
// Idempotent and safe to re-run: any policy that already has
// commission_transactions rows is skipped, so nothing gets double-paid.
//
// UPDATED 30 Jul 2026 — fixed "progress bar sits at 0% for a long time".
// Root cause: confirmPolicy() re-checks Promotion & Demotion Rules for
// every paid agent right after each policy, and that re-check sums the
// agent's WHOLE downline's sales/earning volume from scratch every
// single time. A busy Group/Team Leader shows up on hundreds of
// policies, so their entire team's volume was being re-summed hundreds
// of times over during one run — that repeated, redundant work was the
// actual delay, not just console output being slow to render.
// Fix: pass recheckRanks=false to confirmPolicy() during this bulk run
// (it still confirms + credits the wallet immediately, just skips its
// own per-policy rank re-check), collect every distinct agent it paid,
// then rank-check each of those agents exactly ONCE at the very end.
//
// UPDATED 30 Jul 2026 (2) — Chris still hit "stuck at 0%" on the full
// 2510-transaction run even after the fix above, and hierarchy:detect-
// cycles came back clean (no circular or deep parent_id chains), so
// that wasn't the cause after all. Since we can't watch his screen live,
// this now writes which exact policy it's working on to
// storage/logs/recalculate_progress.log on every single transaction
// (overwritten each time, not appended, so the file stays tiny). If it
// ever looks stuck again, open that one line and it names the exact
// policy_number to investigate — instead of guessing blind.
//
// UPDATED 30 Jul 2026 (3) — the run then crashed partway through on an
// unrelated real bug (RewardPointsService writing an invalid value into
// a foreign-key column — fixed separately). That crash happened INSIDE
// confirmPolicy(), for a policy whose calculate() step had already
// committed its PENDING commission_transactions rows moments earlier.
// The old "skip if any rows already exist" check would have treated
// that policy as fully done forever after, silently leaving it PENDING
// with its agent never actually paid. Now the two steps are decoupled:
// creating rows is still skipped if they already exist (no duplicates),
// but the confirm/credit step is attempted independently, keyed off
// whether PENDING rows for that policy still exist — safe to call even
// on a fully already-confirmed policy since confirmPolicy() is itself a
// no-op when there's nothing left in PENDING status.
// -------------------------------------------------------
class RecalculateAllCommissions extends Command
{
    protected $signature = 'commission:recalculate-all';
    protected $description = 'Re-run earning income calculation for every existing sales transaction against the current Earning Income Structures';

    public function handle(CommissionEngine $engine, HierarchyService $hierarchyService): int
    {
        $alreadyConfirmedStatuses = ['ACTIVE', 'PENDING_RENEWAL', 'RENEWED', 'LAPSED'];

        $transactions = DB::table('sales_transactions')
            ->where('is_deleted', false)
            ->whereNotIn('status', ['CANCELLED'])
            ->orderBy('created_at')
            ->get(['policy_id', 'policy_number', 'vendor_id', 'product_id', 'coverage_start', 'status']);

        $calculated = 0;
        $reconfirmed = 0;
        $skippedAlreadyHasRows = 0;
        $skippedNoStructure = [];
        $agentIdsToRankCheck = [];

        $this->info("Processing {$transactions->count()} sales transaction(s)...");
        $logFile = storage_path('logs/recalculate_progress.log');
        $progressBar = $this->output->createProgressBar($transactions->count());
        $progressBar->setFormat(" %current%/%max% [%bar%] %percent:3s%%  now on: %message%");
        $progressBar->setMessage('(starting)');
        $progressBar->start();

        foreach ($transactions as $txn) {
            $progressBar->setMessage($txn->policy_number);
            @file_put_contents($logFile, now() . " — working on policy {$txn->policy_number} (policy_id {$txn->policy_id})\n");
            $progressBar->advance();
            $existingRows = DB::table('commission_transactions')->where('policy_id', $txn->policy_id)->count();

            if ($existingRows === 0) {
                $hasStructure = DB::table('commission_structures')
                    ->where('vendor_id', $txn->vendor_id)
                    ->where('product_id', $txn->product_id)
                    ->where('is_active', true)
                    ->where('valid_from', '<=', $txn->coverage_start)
                    ->where(function ($q) use ($txn) {
                        $q->whereNull('valid_to')->orWhere('valid_to', '>=', $txn->coverage_start);
                    })
                    ->exists();

                if (!$hasStructure) {
                    $skippedNoStructure[] = $txn->policy_number;
                    continue;
                }

                $engine->calculate($txn->policy_id);
                $calculated++;
            } else {
                $skippedAlreadyHasRows++;
            }

            // Attempt the confirm/credit step independently of whether rows
            // were just created above or already existed from an earlier
            // (possibly interrupted) run — see note (3) above.
            if (in_array($txn->status, $alreadyConfirmedStatuses)) {
                $stillPending = DB::table('commission_transactions')
                    ->where('policy_id', $txn->policy_id)
                    ->where('status', 'PENDING')
                    ->exists();

                if ($stillPending) {
                    $paidAgentIds = $engine->confirmPolicy($txn->policy_id, false);
                    foreach ($paidAgentIds as $agentId) {
                        $agentIdsToRankCheck[$agentId] = true;
                    }
                    $reconfirmed++;
                }
            }
        }

        $progressBar->finish();
        @file_put_contents($logFile, now() . " — transaction loop finished, moving to rank checks\n");
        $this->line('');
        $this->line('');

        $uniqueAgentCount = count($agentIdsToRankCheck);
        if ($uniqueAgentCount > 0) {
            $this->info("Checking Promotion & Demotion Rules for {$uniqueAgentCount} agent(s) who were paid (once each, not once per policy)...");
            $rankBar = $this->output->createProgressBar($uniqueAgentCount);
            $rankBar->start();
            foreach (array_keys($agentIdsToRankCheck) as $agentId) {
                $agent = Agent::find($agentId);
                if ($agent) {
                    $hierarchyService->evaluateRankChange($agent);
                    // REMOVED 31 Jul 2026 — per Chris, automatic per-agent
                    // rank_id evaluation is retired; rank is now bucket-level
                    // (Role + Organization Rewards Group) via Rank Assignment.
                }
                $rankBar->advance();
            }
            $rankBar->finish();
            $this->line('');
            $this->line('');
        }
        $this->info('========================================================');
        $this->info(' RECALCULATION COMPLETE');
        $this->info('========================================================');
        $this->line("  Transactions calculated (new PENDING earning income created) : {$calculated}");
        $this->line("  Of those, re-confirmed and credited to wallet (were already ACTIVE/RENEWED/LAPSED before) : {$reconfirmed}");
        $this->line("  Left as PENDING, awaiting a real Confirm decision (were still SUBMITTED)                  : " . ($calculated - $reconfirmed));
        $this->line("  Skipped — already had earning income rows (not touched again)                             : {$skippedAlreadyHasRows}");

        if (!empty($skippedNoStructure)) {
            $this->warn('  Skipped — still no Earning Income Structure for that vendor+product:');
            foreach ($skippedNoStructure as $policyNumber) {
                $this->line("    - {$policyNumber}");
            }
        } else {
            $this->line('  No transactions were skipped for missing a structure — every vendor+product is covered.');
        }

        return self::SUCCESS;
    }
}
