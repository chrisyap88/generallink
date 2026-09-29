<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// -------------------------------------------------------
// NEW 31 Jul 2026 — per Chris, instead of filling in the bulk template
// row by row for the (now correctly visible) remaining missing
// vendor+products, he gave explicit default splits per Product Type:
// "PERSONAL_ACCIDENT 25 6,6,13" and "OTHERS 25 6,6,13" (Total 25%, GL
// 6%, TL 6%, Introducer 13%). This is his own real business decision,
// not an invented number — this command just applies it in bulk to
// every vendor+product of that type that still has no valid Earning
// Income Structure, instead of him retyping the same four numbers
// dozens of times in Excel.
//
// For each matching vendor+product:
//   - If NO active structure exists yet for it: creates one, with
//     Valid From = the EARLIEST real sales transaction's coverage_start
//     for that exact vendor+product (so it immediately covers every
//     historical policy, same logic as commission:fix-valid-dates).
//   - If an active structure already exists (e.g. from an earlier
//     import) but doesn't cover every transaction: updates its %s to
//     the given split and pushes Valid From back the same way, but
//     ONLY backward, never forward, so nothing that already worked
//     gets a later, worse date.
// Never touches a vendor+product whose Product Type isn't in the given
// list — everything else (Motor, Fire, etc.) is untouched.
// -------------------------------------------------------
class ApplyCommissionDefaultsByType extends Command
{
    protected $signature = 'commission:apply-defaults {mappings* : e.g. PERSONAL_ACCIDENT=25,6,6,13 meaning Product Type=Total%,GL%,TL%,Introducer%}';
    protected $description = 'Apply a given commission % split in bulk to every vendor+product of a given Product Type that still has no valid Earning Income Structure';

    public function handle(): int
    {
        $parsed = [];
        foreach ($this->argument('mappings') as $mapping) {
            if (!preg_match('/^([A-Za-z_]+)=([\d.]+),([\d.]+),([\d.]+),([\d.]+)$/', trim($mapping), $m)) {
                $this->error("Could not understand \"{$mapping}\" — expected format like PERSONAL_ACCIDENT=25,6,6,13");
                return self::FAILURE;
            }
            $type = strtoupper($m[1]);
            // Normalize a plural typed by hand ("OTHERS") to the real enum value ("OTHER").
            if ($type !== 'OTHERS' && str_ends_with($type, 'S') && DB::table('products')->where('product_type', substr($type, 0, -1))->exists()) {
                $type = substr($type, 0, -1);
            } elseif ($type === 'OTHERS') {
                $type = 'OTHER';
            }

            [$total, $gl, $tl, $intro] = [(float)$m[2], (float)$m[3], (float)$m[4], (float)$m[5]];
            if (abs(($gl + $tl + $intro) - $total) > 0.001) {
                $this->error("\"{$mapping}\" doesn't add up: GL+TL+Introducer = " . ($gl + $tl + $intro) . ", but Total = {$total}");
                return self::FAILURE;
            }
            $parsed[$type] = compact('total', 'gl', 'tl', 'intro');
        }

        $this->info('Applying: ' . collect($parsed)->map(fn($v, $type) => "{$type} -> Total {$v['total']}% (GL {$v['gl']} / TL {$v['tl']} / Introducer {$v['intro']})")->implode('; '));
        $this->line('');

        $created = 0;
        $updated = 0;

        foreach ($parsed as $type => $split) {
            // Every vendor+product of this Product Type that has at least
            // one real transaction not already fully covered by a valid
            // structure — same test used by commission:missing-structures.
            $combos = DB::table('sales_transactions as st')
                ->join('products as p', 'p.product_id', '=', 'st.product_id')
                ->where('p.product_type', $type)
                ->where('st.is_deleted', false)
                ->whereNotIn('st.status', ['CANCELLED'])
                ->select('st.vendor_id', 'st.product_id')
                ->distinct()
                ->get();

            foreach ($combos as $combo) {
                $comboTxnDates = DB::table('sales_transactions')
                    ->where('vendor_id', $combo->vendor_id)
                    ->where('product_id', $combo->product_id)
                    ->where('is_deleted', false)
                    ->whereNotIn('status', ['CANCELLED'])
                    ->pluck('coverage_start');

                if ($comboTxnDates->isEmpty()) {
                    continue;
                }

                $earliestTxnDate = $comboTxnDates->min();

                $allCovered = $comboTxnDates->every(function ($coverageStart) use ($combo) {
                    return DB::table('commission_structures')
                        ->where('vendor_id', $combo->vendor_id)
                        ->where('product_id', $combo->product_id)
                        ->where('is_active', true)
                        ->where('valid_from', '<=', $coverageStart)
                        ->where(fn($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $coverageStart))
                        ->exists();
                });

                if ($allCovered) {
                    continue; // already fully valid, leave it alone
                }

                $vendorName = DB::table('vendors')->where('vendor_id', $combo->vendor_id)->value('vendor_name');
                $productName = DB::table('products')->where('product_id', $combo->product_id)->value('product_name');

                $existing = DB::table('commission_structures')
                    ->where('vendor_id', $combo->vendor_id)
                    ->where('product_id', $combo->product_id)
                    ->where('is_active', true)
                    ->orderByDesc('valid_from')
                    ->first();

                if ($existing) {
                    $newValidFrom = $earliestTxnDate < $existing->valid_from ? $earliestTxnDate : $existing->valid_from;
                    DB::table('commission_structures')->where('structure_id', $existing->structure_id)->update([
                        'commission_basis'     => 'PREMIUM_PCT',
                        'total_commission_pct' => $split['total'],
                        'group_leader_pct'     => $split['gl'],
                        'team_leader_pct'      => $split['tl'],
                        'introducer_pct'       => $split['intro'],
                        'valid_from'           => $newValidFrom,
                        'updated_at'           => now(),
                    ]);
                    $updated++;
                    $this->line("  Updated: {$vendorName} / {$productName}");
                } else {
                    DB::table('commission_structures')->insert([
                        'structure_id'         => (string) Str::uuid(),
                        'vendor_id'            => $combo->vendor_id,
                        'product_id'           => $combo->product_id,
                        'commission_basis'     => 'PREMIUM_PCT',
                        'total_commission_pct' => $split['total'],
                        'group_leader_pct'     => $split['gl'],
                        'team_leader_pct'      => $split['tl'],
                        'introducer_pct'       => $split['intro'],
                        'valid_from'           => $earliestTxnDate,
                        'valid_to'             => null,
                        'is_active'            => true,
                        'created_at'           => now(),
                        'updated_at'           => now(),
                    ]);
                    $created++;
                    $this->line("  Created: {$vendorName} / {$productName}");
                }
            }
        }

        $this->line('');
        $this->info('========================================================');
        $this->info(' DONE');
        $this->info('========================================================');
        $this->line("  Created : {$created}");
        $this->line("  Updated : {$updated}");
        $this->line('');
        $this->info('Now re-run RECALCULATE_ALL_EARNING_INCOME.bat.');

        return self::SUCCESS;
    }
}
