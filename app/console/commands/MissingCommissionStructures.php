<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// -------------------------------------------------------
// NEW 31 Jul 2026 — per Chris: "quick list of exactly which
// vendors/products are still missing an Earning Income %", after
// commission:recalculate-all showed 2220 of his 2510 transactions
// couldn't be calculated because their vendor+product combo has no
// active Earning Income Structure yet. Read-only, prints straight to
// the terminal (no Excel needed) — every active product that has zero
// active commission_structures row today, alongside how many real sales
// transactions are sitting on it, sorted so the highest-impact gaps
// (most transactions affected) show up first.
//
// FIXED 31 Jul 2026 — the first version of this command checked whether
// a structure was valid AS OF TODAY, which is not the same question
// commission:recalculate-all actually asks. That command checks whether
// a structure was valid AS OF EACH POLICY'S OWN coverage_start date —
// which for an older policy can be years before a structure Chris just
// created today. A structure with valid_from = today passes the "as of
// today" check but still can't cover a 2023 policy, so the old version
// wrongly reported "nothing missing" right after Chris finished the
// AIA template, while commission:recalculate-all still skipped 2220
// transactions. This version checks every real transaction against its
// own coverage_start, exactly like commission:recalculate-all does, so
// the two always agree.
//
// UPDATED 31 Jul 2026 — this ended up finding 314 vendor+product
// combos (Chris's real data has a lot of vendors/products), which is
// unreadable as a wrapped terminal table. Now always also writes
// MISSING_COMMISSION_STRUCTURES.xlsx (same PhpSpreadsheet pattern used
// by the other Excel reports in this app) sorted by impact, with the
// terminal output trimmed down to just the totals.
// -------------------------------------------------------
class MissingCommissionStructures extends Command
{
    protected $signature = 'commission:missing-structures';
    protected $description = 'List every vendor+product combo with transactions that have no Earning Income Structure valid as of their own coverage start date';

    public function handle(): int
    {
        $transactions = DB::table('sales_transactions')
            ->where('is_deleted', false)
            ->whereNotIn('status', ['CANCELLED'])
            ->get(['vendor_id', 'product_id', 'coverage_start']);

        $this->info("Checking {$transactions->count()} transaction(s) against their vendor+product's Earning Income Structure history...");

        $groups = []; // "vendorId|productId" => ['total' => n, 'missing' => n]

        foreach ($transactions as $txn) {
            $key = $txn->vendor_id . '|' . $txn->product_id;
            $groups[$key]['vendor_id'] ??= $txn->vendor_id;
            $groups[$key]['product_id'] ??= $txn->product_id;
            $groups[$key]['total'] = ($groups[$key]['total'] ?? 0) + 1;

            $hasStructure = DB::table('commission_structures')
                ->where('vendor_id', $txn->vendor_id)
                ->where('product_id', $txn->product_id)
                ->where('is_active', true)
                ->where('valid_from', '<=', $txn->coverage_start)
                ->where(fn($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $txn->coverage_start))
                ->exists();

            if (!$hasStructure) {
                $groups[$key]['missing'] = ($groups[$key]['missing'] ?? 0) + 1;
            }
        }

        $missingGroups = array_filter($groups, fn($g) => ($g['missing'] ?? 0) > 0);

        if (empty($missingGroups)) {
            $this->info('Every real sales transaction is covered by a valid Earning Income Structure. Nothing missing.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($missingGroups as $g) {
            $product = DB::table('products')->where('product_id', $g['product_id'])->first(['product_name', 'product_type']);
            $vendor = DB::table('vendors')->where('vendor_id', $g['vendor_id'])->value('vendor_name');
            $rows[] = [
                'vendor'    => $vendor ?? '(deleted vendor)',
                'product'   => $product->product_name ?? '(deleted product)',
                'type'      => $product->product_type ?? '',
                'missing'   => $g['missing'],
                'total'     => $g['total'],
            ];
        }

        usort($rows, fn($a, $b) => $b['missing'] <=> $a['missing']);

        $totalMissing = array_sum(array_column($rows, 'missing'));

        $this->line('');
        $this->warn(count($rows) . " vendor+product combo(s) have transactions with no valid Earning Income Structure, affecting {$totalMissing} transaction(s) in total.");

        $filePath = $this->writeExcel($rows);

        $this->line('');
        $this->info("Full list written to: {$filePath}");
        $this->info('Fill these in via GENERATE_COMMISSION_TEMPLATE.bat -> edit the Excel -> IMPORT_COMMISSION_STRUCTURES.bat -> then re-run RECALCULATE_ALL_EARNING_INCOME.bat.');
        $this->line('Note: if a product still shows a small number of "missing" while its own structure IS set up, check the Valid From date on that structure — it may be set later than some of these older policies\' coverage start dates.');

        return self::SUCCESS;
    }

    private function writeExcel(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Missing Structures');

        $headers = ['Vendor', 'Product', 'Product Type', 'Transactions Still Missing a Structure', 'Total Transactions for this Vendor+Product'];
        foreach ($headers as $i => $header) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}1", $header);
            $sheet->getStyle("{$col}1")->getFont()->setBold(true);
            $sheet->getStyle("{$col}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
        }

        $rowNum = 2;
        foreach ($rows as $r) {
            $sheet->setCellValue("A{$rowNum}", $r['vendor']);
            $sheet->setCellValue("B{$rowNum}", $r['product']);
            $sheet->setCellValue("C{$rowNum}", $r['type']);
            $sheet->setCellValueExplicit("D{$rowNum}", $r['missing'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("E{$rowNum}", $r['total'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $rowNum++;
        }

        foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->setAutoFilter('A1:E' . ($rowNum - 1));
        $sheet->freezePane('A2');

        $filePath = base_path('MISSING_COMMISSION_STRUCTURES.xlsx');
        (new Xlsx($spreadsheet))->save($filePath);

        return $filePath;
    }
}
