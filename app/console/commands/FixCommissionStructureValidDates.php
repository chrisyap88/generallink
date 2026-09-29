<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 31 Jul 2026 — per Chris, after bulk-importing the commission
// structure template: commission:recalculate-all still skipped almost
// everything for "no valid structure", even for vendor+products that
// now DO have a structure row. Root cause: commission_structures.
// valid_from was set to a recent date (e.g. today, from the template),
// but a structure only covers a policy whose own coverage_start is ON
// OR AFTER that date — so every older historical policy Chris is
// simulating against still fails the check.
//
// This is a purely mechanical, safe fix — it NEVER touches any % value,
// never invents a business decision, and can only ever make MORE
// transactions covered, never fewer:
//   For each vendor+product, find its EARLIEST active commission
//   structure (by valid_from) and, if any real sales transaction for
//   that same vendor+product has a coverage_start earlier than that
//   structure's valid_from, pushes valid_from back to match the
//   earliest such transaction. Later structures for the same
//   vendor+product (i.e. a genuine rate change partway through) are
//   left completely untouched, since their valid_from is meaningful
//   business data, not a data-entry default.
// -------------------------------------------------------
class FixCommissionStructureValidDates extends Command
{
    protected $signature = 'commission:fix-valid-dates';
    protected $description = 'Push each commission structure\'s earliest Valid From date back to cover its oldest real sales transaction, without touching any percentages';

    public function handle(): int
    {
        $structures = DB::table('commission_structures')
            ->where('is_active', true)
            ->orderBy('vendor_id')
            ->orderBy('product_id')
            ->orderBy('valid_from')
            ->get(['structure_id', 'vendor_id', 'product_id', 'valid_from']);

        // Keep only the earliest structure per vendor+product.
        $earliestPerGroup = [];
        foreach ($structures as $s) {
            $key = $s->vendor_id . '|' . $s->product_id;
            if (!isset($earliestPerGroup[$key])) {
                $earliestPerGroup[$key] = $s;
            }
        }

        $fixed = 0;
        $alreadyFine = 0;
        $noTransactions = 0;

        foreach ($earliestPerGroup as $s) {
            $earliestTxnDate = DB::table('sales_transactions')
                ->where('vendor_id', $s->vendor_id)
                ->where('product_id', $s->product_id)
                ->where('is_deleted', false)
                ->whereNotIn('status', ['CANCELLED'])
                ->min('coverage_start');

            if (!$earliestTxnDate) {
                $noTransactions++;
                continue;
            }

            if ($earliestTxnDate < $s->valid_from) {
                DB::table('commission_structures')
                    ->where('structure_id', $s->structure_id)
                    ->update(['valid_from' => $earliestTxnDate, 'updated_at' => now()]);

                $product = DB::table('products')->where('product_id', $s->product_id)->value('product_name');
                $vendor = DB::table('vendors')->where('vendor_id', $s->vendor_id)->value('vendor_name');
                $this->line("  Pushed back: {$vendor} / {$product} — Valid From {$s->valid_from} -> {$earliestTxnDate}");
                $fixed++;
            } else {
                $alreadyFine++;
            }
        }

        $this->line('');
        $this->info('========================================================');
        $this->info(' DONE');
        $this->info('========================================================');
        $this->line("  Structures pushed back to cover older policies : {$fixed}");
        $this->line("  Already covered every existing policy          : {$alreadyFine}");
        $this->line("  No real transactions for that vendor+product yet (nothing to check) : {$noTransactions}");
        $this->line('');
        $this->info('Now re-run RECALCULATE_ALL_EARNING_INCOME.bat — the "Calculated" number should be much higher this time.');

        return self::SUCCESS;
    }
}
