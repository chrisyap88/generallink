<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

// -------------------------------------------------------
// NEW 29 Jul 2026 — reads back the file produced by
// commission:export-template (COMMISSION_STRUCTURE_TEMPLATE.xlsx) once
// Chris has filled in the yellow columns, and bulk creates/updates
// Earning Income Structures in one pass instead of using the Add
// Earning Income Structure screen one vendor+product at a time.
//
// Same validation the screen itself enforces: Group Leader % + Team
// Leader % + Introducer % must add up exactly to Total Commission %.
// A row that fails this, or is left blank, is SKIPPED and reported —
// never guessed or forced through.
// -------------------------------------------------------
class CommissionStructureImportTemplate extends Command
{
    protected $signature = 'commission:import-template {file=COMMISSION_STRUCTURE_TEMPLATE.xlsx}';
    protected $description = 'Bulk-create/update Earning Income Structures from the filled-in COMMISSION_STRUCTURE_TEMPLATE.xlsx';

    public function handle(): int
    {
        $filePath = base_path($this->argument('file'));
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            $this->line('Run GENERATE_COMMISSION_TEMPLATE.bat first, fill it in, then try again.');
            return self::FAILURE;
        }

        $spreadsheet = IOFactory::load($filePath);
        $ws = $spreadsheet->getActiveSheet();
        $highestRow = $ws->getHighestRow();

        $created = 0;
        $updated = 0;
        $skippedBlank = 0;
        $skippedMismatch = [];
        $skippedInvalid = [];

        for ($row = 6; $row <= $highestRow; $row++) {
            $vendorName  = trim((string) $ws->getCell("A{$row}")->getValue());
            $productName = trim((string) $ws->getCell("B{$row}")->getValue());
            $basisRaw    = trim((string) $ws->getCell("E{$row}")->getValue());
            $totalPct    = $ws->getCell("F{$row}")->getValue();
            $glPct       = $ws->getCell("G{$row}")->getValue();
            $tlPct       = $ws->getCell("H{$row}")->getValue();
            $introPct    = $ws->getCell("I{$row}")->getValue();
            $validFrom   = trim((string) $ws->getCell("J{$row}")->getValue());
            $productId   = trim((string) $ws->getCell("K{$row}")->getValue());
            $vendorId    = trim((string) $ws->getCell("L{$row}")->getValue());

            if ($productId === '' || $vendorId === '') {
                continue; // blank/decorative row
            }

            if ($totalPct === null || $totalPct === '' || (float)$totalPct <= 0) {
                $skippedBlank++;
                continue; // nothing filled in for this row — leave it alone
            }

            $glPct    = (float)($glPct ?: 0);
            $tlPct    = (float)($tlPct ?: 0);
            $introPct = (float)($introPct ?: 0);
            $totalPct = (float)$totalPct;

            $allocated = $glPct + $tlPct + $introPct;
            if (abs($allocated - $totalPct) > 0.001) {
                $skippedMismatch[] = "{$vendorName} / {$productName} — GL+TL+Introducer = {$allocated}%, but Total Commission % = {$totalPct}%";
                continue;
            }

            $basis = strtoupper(str_replace(' ', '_', $basisRaw));
            if (!in_array($basis, ['PREMIUM', 'PREMIUM_PCT', 'SUM_INSURED', 'SUM_INSURED_PCT'])) {
                $skippedInvalid[] = "{$vendorName} / {$productName} — unrecognized Commission Basis \"{$basisRaw}\" (must be PREMIUM or SUM_INSURED)";
                continue;
            }
            $basis = str_starts_with($basis, 'SUM_INSURED') ? 'SUM_INSURED_PCT' : 'PREMIUM_PCT';

            $validFromDate = $validFrom !== '' ? $validFrom : now()->format('Y-m-d');
            try {
                $validFromDate = \Illuminate\Support\Carbon::parse($validFromDate)->format('Y-m-d');
            } catch (\Exception $e) {
                $skippedInvalid[] = "{$vendorName} / {$productName} — unreadable Valid From date \"{$validFrom}\"";
                continue;
            }

            $existing = DB::table('commission_structures')
                ->where('vendor_id', $vendorId)
                ->where('product_id', $productId)
                ->where('is_active', true)
                ->orderByDesc('valid_from')
                ->first();

            if ($existing) {
                DB::table('commission_structures')->where('structure_id', $existing->structure_id)->update([
                    'commission_basis'     => $basis,
                    'total_commission_pct' => $totalPct,
                    'group_leader_pct'     => $glPct,
                    'team_leader_pct'      => $tlPct,
                    'introducer_pct'       => $introPct,
                    'valid_from'           => $validFromDate,
                    'updated_at'           => now(),
                ]);
                $updated++;
                $this->line("  Updated: {$vendorName} / {$productName}");
            } else {
                DB::table('commission_structures')->insert([
                    'structure_id'         => (string) Str::uuid(),
                    'vendor_id'            => $vendorId,
                    'product_id'           => $productId,
                    'commission_basis'     => $basis,
                    'total_commission_pct' => $totalPct,
                    'group_leader_pct'     => $glPct,
                    'team_leader_pct'      => $tlPct,
                    'introducer_pct'       => $introPct,
                    'valid_from'           => $validFromDate,
                    'valid_to'             => null,
                    'is_active'            => true,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
                $created++;
                $this->line("  Created: {$vendorName} / {$productName}");
            }
        }

        $this->line('');
        $this->info('========================================================');
        $this->info(' RESULT');
        $this->info('========================================================');
        $this->line("  Created : {$created}");
        $this->line("  Updated : {$updated}");
        $this->line("  Left blank (no % entered) : {$skippedBlank}");

        if (!empty($skippedMismatch)) {
            $this->warn('  Skipped — percentages do not add up:');
            foreach ($skippedMismatch as $m) {
                $this->line("    - {$m}");
            }
        }
        if (!empty($skippedInvalid)) {
            $this->warn('  Skipped — invalid value:');
            foreach ($skippedInvalid as $m) {
                $this->line("    - {$m}");
            }
        }

        return self::SUCCESS;
    }
}
