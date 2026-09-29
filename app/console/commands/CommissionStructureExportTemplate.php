<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// -------------------------------------------------------
// NEW 29 Jul 2026 — Chris: "if I set one by one it will be a lot of
// work, can you suggest how you can set for me?" I can't invent the
// actual % rates (those are real commercial terms he agreed with each
// vendor — not something to guess), but I CAN remove all the repeated
// screen navigation: one Excel file, one row per vendor+product, fill
// in a handful of numbers, then one import command applies all of
// them at once instead of clicking through the Add form dozens of
// times.
//
// Pairs with CommissionStructureImportTemplate below.
//
// FIXED 31 Jul 2026 — this only listed products.is_active = true, so
// any product that's since been marked inactive/discontinued in the
// catalog — but still has real historical sales_transactions against
// it — never got a row in the template at all. Chris filled in every
// row he could see, twice, and commission:missing-structures kept
// reporting the exact same 314 combos / 1450 transactions unchanged,
// because those rows simply didn't exist for him to fill in. Now the
// template includes the union of (a) every active product, so new
// products can still be set up before any sale happens, and (b) every
// vendor+product that has at least one real sales transaction,
// regardless of the product's current active flag.
// -------------------------------------------------------
class CommissionStructureExportTemplate extends Command
{
    protected $signature = 'commission:export-template';
    protected $description = 'Export an editable Excel template (one row per vendor+product) for bulk-setting up Earning Income Structures';

    public function handle(): int
    {
        $activeProducts = DB::table('products as p')
            ->join('vendors as v', 'p.vendor_id', '=', 'v.vendor_id')
            ->where('p.is_active', true)
            ->get(['p.product_id', 'p.product_name', 'p.product_type', 'v.vendor_id', 'v.vendor_name']);

        $productsWithSales = DB::table('sales_transactions as st')
            ->join('products as p', 'p.product_id', '=', 'st.product_id')
            ->join('vendors as v', 'v.vendor_id', '=', 'st.vendor_id')
            ->where('st.is_deleted', false)
            ->distinct()
            ->get(['p.product_id', 'p.product_name', 'p.product_type', 'v.vendor_id', 'v.vendor_name']);

        $products = $activeProducts->concat($productsWithSales)
            ->unique(fn($p) => $p->vendor_id . '|' . $p->product_id)
            ->sortBy([['vendor_name', 'asc'], ['product_name', 'asc']])
            ->values();

        if ($products->isEmpty()) {
            $this->warn('No active products found — nothing to export.');
            return self::SUCCESS;
        }

        $spreadsheet = new Spreadsheet();
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('Earning Income Setup');

        $ws->getCell('A1')->setValue('GeneralLink — Earning Income Structure Bulk Setup');
        $ws->getCell('A2')->setValue('Fill in the yellow columns for each row, save this file, then run IMPORT_COMMISSION_STRUCTURES.bat');
        $ws->getCell('A3')->setValue('Rule: Group Leader % + Team Leader % + Introducer % MUST add up exactly to Total Commission %');
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $ws->getStyle('A2:A3')->getFont()->setItalic(true);

        $headers = [
            'Vendor', 'Product', 'Product Type', 'Already Set Up?',
            'Commission Basis (PREMIUM or SUM_INSURED)', 'Total Commission %',
            'Group Leader %', 'Team Leader %', 'Introducer %', 'Valid From (YYYY-MM-DD)',
            'Product ID (do not edit)', 'Vendor ID (do not edit)',
        ];
        foreach ($headers as $colIdx => $val) {
            $coord = Coordinate::stringFromColumnIndex($colIdx + 1) . '5';
            $ws->getCell($coord)->setValue($val);
        }
        $ws->getStyle('A5:L5')->getFont()->setBold(true);
        $ws->getStyle('A5:L5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDDDDD');

        $rowIdx = 6;
        foreach ($products as $p) {
            $existing = DB::table('commission_structures')
                ->where('vendor_id', $p->vendor_id)
                ->where('product_id', $p->product_id)
                ->where('is_active', true)
                ->orderByDesc('valid_from')
                ->first();

            $ws->getCell("A{$rowIdx}")->setValue($p->vendor_name);
            $ws->getCell("B{$rowIdx}")->setValue($p->product_name);
            $ws->getCell("C{$rowIdx}")->setValue($p->product_type);
            $ws->getCell("D{$rowIdx}")->setValue($existing ? 'Yes — will UPDATE on import' : 'No — will CREATE on import');

            if ($existing) {
                $ws->getCell("E{$rowIdx}")->setValue($existing->commission_basis === 'SUM_INSURED_PCT' ? 'SUM_INSURED' : 'PREMIUM');
                $ws->getCell("F{$rowIdx}")->setValueExplicit((float)$existing->total_commission_pct, DataType::TYPE_NUMERIC);
                $ws->getCell("G{$rowIdx}")->setValueExplicit((float)$existing->group_leader_pct, DataType::TYPE_NUMERIC);
                $ws->getCell("H{$rowIdx}")->setValueExplicit((float)$existing->team_leader_pct, DataType::TYPE_NUMERIC);
                $ws->getCell("I{$rowIdx}")->setValueExplicit((float)$existing->introducer_pct, DataType::TYPE_NUMERIC);
                $ws->getCell("J{$rowIdx}")->setValue(\Illuminate\Support\Carbon::parse($existing->valid_from)->format('Y-m-d'));
            } else {
                $ws->getCell("E{$rowIdx}")->setValue('PREMIUM');
                $ws->getCell("J{$rowIdx}")->setValue(now()->format('Y-m-d'));
            }

            $ws->getCell("K{$rowIdx}")->setValue($p->product_id);
            $ws->getCell("L{$rowIdx}")->setValue($p->vendor_id);

            // Highlight the editable cells (yellow) so it's obvious what to fill in
            $ws->getStyle("E{$rowIdx}:J{$rowIdx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF9C4');

            $rowIdx++;
        }

        $lastRow = $rowIdx - 1;
        $ws->getStyle("F6:I{$lastRow}")->getNumberFormat()->setFormatCode('0.0000');
        foreach (range('A', 'L') as $col) {
            $ws->getColumnDimension($col)->setAutoSize(true);
        }
        // Grey out the "do not edit" ID columns so they read as reference-only
        $ws->getStyle("K5:L{$lastRow}")->getFont()->getColor()->setRGB('999999');

        $writer = new Xlsx($spreadsheet);
        $writer->save(base_path('COMMISSION_STRUCTURE_TEMPLATE.xlsx'));

        $this->info('Done. Saved as COMMISSION_STRUCTURE_TEMPLATE.xlsx in your GeneralLink folder.');
        $this->line("  {$products->count()} product(s) listed — " . $products->count() . ' rows to review.');
        return self::SUCCESS;
    }
}
