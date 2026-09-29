<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 23 Jul 2026 — companion cleanup for
// SeedChrisYap2026Transactions. Removes exactly that demo batch and
// nothing else: every sales_transactions row whose
// document_reference_number starts with "DEMO2026-", plus their
// linked insurance_renewal_schedules, commission_transactions, and the
// dummy "(Demo) ..." customers created only for those transactions.
// Real data is never touched — the DEMO2026- prefix is the only
// selector used anywhere in this command.
//
// Run via: php artisan demo:remove-2026-transactions
// Shows exactly how many rows of each type will be deleted and asks
// for a y/n confirmation before deleting anything.
// -------------------------------------------------------
class RemoveChrisYap2026Transactions extends Command
{
    protected $signature = 'demo:remove-2026-transactions';
    protected $description = 'Remove the demo 2026 Sales Transactions batch created by demo:seed-2026-transactions (and only that batch)';

    private const REF_PREFIX = 'DEMO2026-';
    private const NAME_PREFIX = '(Demo) ';

    public function handle(): int
    {
        $transactions = DB::table('sales_transactions')
            ->where('document_reference_number', 'like', self::REF_PREFIX . '%')
            ->get(['policy_id', 'customer_id']);

        if ($transactions->isEmpty()) {
            $this->info('No demo transactions found — nothing to remove.');
            return self::SUCCESS;
        }

        $policyIds = $transactions->pluck('policy_id')->all();
        $customerIds = $transactions->pluck('customer_id')->unique()->all();

        $commissionCount = DB::table('commission_transactions')->whereIn('policy_id', $policyIds)->count();
        $renewalCount = DB::table('insurance_renewal_schedules')->whereIn('policy_id', $policyIds)->count();
        $customerCount = DB::table('customers')
            ->whereIn('customer_id', $customerIds)
            ->where('full_name', 'like', self::NAME_PREFIX . '%')
            ->count();

        $this->info('Will remove:');
        $this->line('  ' . count($policyIds) . ' Sales Transaction(s) — reference starting "' . self::REF_PREFIX . '..."');
        $this->line("  {$renewalCount} renewal schedule row(s)");
        $this->line("  {$commissionCount} commission record(s)");
        $this->line("  {$customerCount} dummy customer(s) — name starting \"" . self::NAME_PREFIX . '..."');
        $this->line('');

        if (!$this->confirm('Delete all of this now?', false)) {
            $this->warn('Cancelled — nothing removed.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($policyIds, $customerIds) {
            DB::table('commission_transactions')->whereIn('policy_id', $policyIds)->delete();
            DB::table('insurance_renewal_schedules')->whereIn('policy_id', $policyIds)->delete();
            DB::table('sales_transactions')->whereIn('policy_id', $policyIds)->delete();
            DB::table('customers')
                ->whereIn('customer_id', $customerIds)
                ->where('full_name', 'like', self::NAME_PREFIX . '%')
                ->delete();
        });

        $this->info('Done — the demo 2026 batch has been removed.');
        return self::SUCCESS;
    }
}
