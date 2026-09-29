<?php

namespace App\Console\Commands;

use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 24 Jul 2026 — Chris: create a 10% Earning Income Structure (Mgt 2.5% /
// Ope 2.5% / Aff 5.0%) for every Motor-type product (Commercial Vehicle,
// Motor, Motorbike — all stored as product_type = 'MOTOR') under every
// insurance company vendor (vendors.industry = 'INSURANCE'), effective
// 01 Jan 2026 to 31 Dec 2026. Chris will fill in the Rank Allocation /
// Override breakdown himself afterward on the Edit screen — this command
// only creates the base structure row, leaving rank_pct/override_pct at 0
// (untouched) for every rank.
//
// Safe to re-run: any vendor/product pair that already has an existing
// structure overlapping this date range is skipped, not duplicated. Shows
// a full preview and asks for confirmation before writing anything.
//
// Run via: php artisan commissions:seed-motor-structures
class SeedMotorEarningStructures extends Command
{
    protected $signature = 'commissions:seed-motor-structures';
    protected $description = 'Create a 10% (Mgt 2.5 / Ope 2.5 / Aff 5.0) Earning Income Structure, 01 Jan 2026 - 31 Dec 2026, for every Motor product under every insurance company vendor';

    private const VALID_FROM = '2026-01-01';
    private const VALID_TO   = '2026-12-31';
    private const TOTAL_PCT  = 10.0;
    private const MGT_PCT    = 2.5;
    private const OPE_PCT    = 2.5;
    private const AFF_PCT    = 5.0;

    public function handle(): int
    {
        $vendors = DB::table('vendors')
            ->where('industry', 'INSURANCE')
            ->where('is_active', true)
            ->orderBy('vendor_name')
            ->get(['vendor_id', 'vendor_name']);

        if ($vendors->isEmpty()) {
            $this->warn('No active vendors with industry = INSURANCE found — nothing to do.');
            return self::SUCCESS;
        }

        $toCreate = [];
        $skipped  = [];

        foreach ($vendors as $vendor) {
            $products = DB::table('products')
                ->where('vendor_id', $vendor->vendor_id)
                ->where('product_type', 'MOTOR')
                ->where('is_active', true)
                ->orderBy('product_name')
                ->get(['product_id', 'product_name']);

            foreach ($products as $product) {
                // Skip if a structure already exists for this vendor+product
                // that overlaps the 2026-01-01 to 2026-12-31 window.
                $exists = DB::table('commission_structures')
                    ->where('vendor_id', $vendor->vendor_id)
                    ->where('product_id', $product->product_id)
                    ->where('valid_from', '<=', self::VALID_TO)
                    ->where(function ($q) {
                        $q->whereNull('valid_to')->orWhere('valid_to', '>=', self::VALID_FROM);
                    })
                    ->exists();

                if ($exists) {
                    $skipped[] = "{$vendor->vendor_name} — {$product->product_name}";
                    continue;
                }

                $toCreate[] = [
                    'vendor_id'   => $vendor->vendor_id,
                    'vendor_name' => $vendor->vendor_name,
                    'product_id'  => $product->product_id,
                    'product_name'=> $product->product_name,
                ];
            }
        }

        if (empty($toCreate)) {
            $this->info('Nothing to create — every Motor product under an insurance vendor already has a structure covering this period.');
            if (!empty($skipped)) {
                $this->line('Skipped (' . count($skipped) . '): ' . implode(', ', array_slice($skipped, 0, 10)) . (count($skipped) > 10 ? ' ...' : ''));
            }
            return self::SUCCESS;
        }

        $this->info(count($toCreate) . ' Earning Income Structure(s) will be created (Total 10% — Mgt 2.5 / Ope 2.5 / Aff 5.0, valid ' . self::VALID_FROM . ' to ' . self::VALID_TO . '):');
        $this->line('');
        foreach ($toCreate as $row) {
            $this->line("  {$row['vendor_name']} — {$row['product_name']}");
        }
        if (!empty($skipped)) {
            $this->line('');
            $this->warn(count($skipped) . ' already have a structure for this period and will be skipped:');
            foreach (array_slice($skipped, 0, 10) as $s) {
                $this->line("  - {$s}");
            }
            if (count($skipped) > 10) {
                $this->line('  ... and ' . (count($skipped) - 10) . ' more.');
            }
        }
        $this->line('');
        $this->warn('Rank Allocation / Overrides are NOT set by this command — you fill those in yourself afterward on each structure\'s Edit screen.');
        $this->line('');

        if (!$this->confirm('Create these ' . count($toCreate) . ' structure(s) now?', false)) {
            $this->warn('Cancelled — nothing created.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($toCreate) {
            foreach ($toCreate as $row) {
                $id = (string) Str::uuid();
                DB::table('commission_structures')->insert([
                    'structure_id'         => $id,
                    'vendor_id'            => $row['vendor_id'],
                    'product_id'           => $row['product_id'],
                    'commission_basis'     => 'PREMIUM_PCT',
                    'total_commission_pct' => self::TOTAL_PCT,
                    'group_leader_pct'     => self::MGT_PCT,
                    'team_leader_pct'      => self::OPE_PCT,
                    'introducer_pct'       => self::AFF_PCT,
                    'valid_from'           => self::VALID_FROM,
                    'valid_to'             => self::VALID_TO,
                    'is_active'            => true,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
                AuditService::logChange('commission_structures', $id, 'COMMISSION_STRUCTURE_CREATED', null, [
                    'source'   => 'commissions:seed-motor-structures command',
                    'vendor'   => $row['vendor_name'],
                    'product'  => $row['product_name'],
                ]);
            }
        });

        $this->info('Done — ' . count($toCreate) . ' structure(s) created. Go to Earning Income Structures > Search to fill in Rank Allocation / Overrides on each one.');
        return self::SUCCESS;
    }
}
