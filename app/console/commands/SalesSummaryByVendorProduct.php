<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

// -------------------------------------------------------
// NEW 29 Jul 2026 — Chris asked to "summarize the sales transaction
// file by vendor and product type". Same grouping already used inside
// simulation:reorganize / simulation:audit / commission:report, but
// pulled out as its own standalone Excel file since that's the actual
// deliverable he asked for this time — one clean summary, not buried
// inside a bigger report.
// -------------------------------------------------------
class SalesSummaryByVendorProduct extends Command
{
    protected $signature = 'sales:summary-by-vendor-product';
    protected $description = 'Export an Excel summary of sales transactions grouped by vendor and product type';

    public function handle(): int
    {
        $this->info('Reading sales transactions...');

        $groups = DB::table('sales_transactions as st')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->where('st.is_deleted', false)
            ->selectRaw('
                v.vendor_id, v.vendor_name, p.product_id, p.product_name,
                COUNT(*) as txn_count,
                SUM(st.premium_amount) as total_premium,
                AVG(st.premium_amount) as avg_premium,
                SUM(st.sum_insured) as total_sum_insured,
                MIN(st.created_at) as earliest_txn,
                MAX(st.created_at) as latest_txn
            ')
            ->groupBy('v.vendor_id', 'v.vendor_name', 'p.product_id', 'p.product_name')
            ->orderBy('v.vendor_name')
            ->orderBy('p.product_name')
            ->get();

        if ($groups->isEmpty()) {
            $this->warn('No sales transactions found.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($groups as $g) {
            $structure = DB::table('commission_structures')
                ->where('vendor_id', $g->vendor_id)
                ->where('product_id', $g->product_id)
                ->where('is_active', true)
                ->orderByDesc('valid_from')
                ->first();

            $rows[] = [
                $g->vendor_name,
                $g->product_name,
                (int)$g->txn_count,
                (float)$g->total_premium,
                (float)$g->avg_premium,
                $g->total_sum_insured !== null ? (float)$g->total_sum_insured : 0,
                $structure ? 'Yes' : 'No',
                $structure ? (float)$structure->total_commission_pct : '',
                $structure ? (float)$structure->introducer_pct : '',
                $structure ? (float)$structure->team_leader_pct : '',
                $structure ? (float)$structure->group_leader_pct : '',
                \Illuminate\Support\Carbon::parse($g->earliest_txn)->format('d M Y'),
                \Illuminate\Support\Carbon::parse($g->latest_txn)->format('d M Y'),
            ];
        }

        $this->buildExcel($rows, $groups->sum('txn_count'), $groups->sum('total_premium'));

        $this->info('Done. Saved as SALES_SUMMARY_BY_VENDOR_PRODUCT.xlsx in your GeneralLink folder.');
        return self::SUCCESS;
    }

    private function buildExcel(array $rows, int $totalTxns, float $totalPremium): void
    {
        $spreadsheet = new Spreadsheet();
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('Summary');

        $ws->getCell('A1')->setValue('GeneralLink — Sales Transaction Summary by Vendor & Product');
        $ws->getCell('A2')->setValue('Generated: ' . now()->format('d M Y, g:ia'));
        $ws->getCell('A3')->setValue('Total transactions: ' . $totalTxns . '   |   Total premium: RM ' . number_format($totalPremium, 2));
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $headers = [
            'Vendor', 'Product', 'Transaction Count', 'Total Premium (RM)', 'Average Premium (RM)',
            'Total Sum Insured (RM)', 'Earning Income Structure Set?', 'Total Commission %',
            'Introducer %', 'Team Leader %', 'Group Leader %', 'Earliest Transaction', 'Latest Transaction',
        ];
        foreach ($headers as $colIdx => $val) {
            $coord = Coordinate::stringFromColumnIndex($colIdx + 1) . '5';
            $ws->getCell($coord)->setValue($val);
        }
        $ws->getStyle('A5:M5')->getFont()->setBold(true);

        foreach ($rows as $rowIdx => $row) {
            foreach ($row as $colIdx => $val) {
                $coord = Coordinate::stringFromColumnIndex($colIdx + 1) . ($rowIdx + 6);
                if (is_float($val) || is_int($val)) {
                    $ws->getCell($coord)->setValueExplicit($val, DataType::TYPE_NUMERIC);
                } else {
                    $ws->getCell($coord)->setValue($val);
                }
            }
        }

        $lastRow = count($rows) + 5;
        foreach (['D', 'E', 'F'] as $col) {
            $ws->getStyle("{$col}6:{$col}{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
        foreach (['H', 'I', 'J', 'K'] as $col) {
            $ws->getStyle("{$col}6:{$col}{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        }

        // Totals row
        $totalRow = $lastRow + 2;
        $ws->getCell("B{$totalRow}")->setValue('Total');
        $ws->getStyle("B{$totalRow}")->getFont()->setBold(true);
        $ws->getCell("C{$totalRow}")->setValue("=SUM(C6:C{$lastRow})");
        $ws->getCell("D{$totalRow}")->setValue("=SUM(D6:D{$lastRow})");
        $ws->getCell("F{$totalRow}")->setValue("=SUM(F6:F{$lastRow})");
        $ws->getStyle("C{$totalRow}:F{$totalRow}")->getFont()->setBold(true);
        $ws->getStyle("D{$totalRow}:F{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');

        foreach (range('A', 'M') as $col) {
            $ws->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save(base_path('SALES_SUMMARY_BY_VENDOR_PRODUCT.xlsx'));
    }
}
