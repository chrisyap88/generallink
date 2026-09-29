<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

// -------------------------------------------------------
// NEW 29 Jul 2026 — Chris asked to "list down existing in DB how many
// sales transaction and earning income currently store, sort by GL,
// TL, Introducer and transaction date". Exports an Excel workbook
// (same PhpSpreadsheet library already used elsewhere, e.g.
// Admin\NetworkController's downline report) with:
//   - Sheet 1 "Summary": counts of sales transactions and commission
//     rows, broken down by status.
//   - Sheet 2 "Detail": one row per commission_transactions record,
//     with the Group Leader / Team Leader / Introducer names resolved
//     by walking each policy's actual upline hierarchy (regardless of
//     active/inactive — this shows who STRUCTURALLY sits in each tier;
//     the separate "Paid To" / "Status" columns show who was actually
//     credited, which can differ if a tier was inactive at the time).
//     Sorted by Group Leader, Team Leader, Introducer, then
//     Transaction Date, exactly as asked.
// -------------------------------------------------------
class CommissionReport extends Command
{
    protected $signature = 'commission:report';
    protected $description = 'Export every sales transaction and its earning income (commission) rows to an Excel file, sorted by Group Leader / Team Leader / Introducer / transaction date';

    // Cache of agent_id => agent row, so the upline walk doesn't
    // re-query the same agent over and over across many transactions.
    private array $agentCache = [];

    public function handle(): int
    {
        $this->info('Reading sales transactions and earning income records...');

        $transactions = DB::table('sales_transactions as st')
            ->leftJoin('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->leftJoin('products as p', 'st.product_id', '=', 'p.product_id')
            ->where('st.is_deleted', false)
            ->orderBy('st.created_at')
            ->get([
                'st.policy_id', 'st.policy_number', 'st.document_reference_number',
                'st.agent_id', 'st.premium_amount', 'st.sum_insured', 'st.status',
                'st.created_at', 'v.vendor_name', 'p.product_name',
            ]);

        if ($transactions->isEmpty()) {
            $this->warn('No sales transactions found in the database.');
            return self::SUCCESS;
        }

        $commissionRows = DB::table('commission_transactions')
            ->get()
            ->groupBy('policy_id');

        $detailRows = [];
        $statusCounts = ['PENDING' => 0, 'CONFIRMED' => 0, 'REVERSED' => 0];
        $totalsByStatus = ['PENDING' => 0.0, 'CONFIRMED' => 0.0, 'REVERSED' => 0.0];

        foreach ($transactions as $txn) {
            $closer = $this->getAgent($txn->agent_id);
            [$glName, $tlName, $introName] = $this->resolveTierNames($closer);

            $rows = $commissionRows->get($txn->policy_id, collect());

            if ($rows->isEmpty()) {
                // No earning income calculated at all for this policy yet
                // (e.g. no active commission structure was found) — still
                // list the policy so "how many sales transactions" is a
                // true count, just with blank earning income columns.
                $detailRows[] = [
                    $glName, $tlName, $introName,
                    \Illuminate\Support\Carbon::parse($txn->created_at)->format('Y-m-d'),
                    $txn->policy_number, $txn->document_reference_number ?? '',
                    $txn->vendor_name ?? '', $txn->product_name ?? '',
                    (float)$txn->premium_amount,
                    '(no earning income structure found)', '', '', '', $txn->status, '',
                ];
                continue;
            }

            foreach ($rows as $row) {
                $paidTo = $this->getAgent($row->agent_id);
                $statusCounts[$row->status] = ($statusCounts[$row->status] ?? 0) + 1;
                $totalsByStatus[$row->status] = ($totalsByStatus[$row->status] ?? 0) + (float)$row->commission_amount;

                $detailRows[] = [
                    $glName, $tlName, $introName,
                    \Illuminate\Support\Carbon::parse($txn->created_at)->format('Y-m-d'),
                    $txn->policy_number, $txn->document_reference_number ?? '',
                    $txn->vendor_name ?? '', $txn->product_name ?? '',
                    (float)$txn->premium_amount,
                    $row->role_at_transaction,
                    $paidTo->full_name ?? '(SYSTEM)',
                    (float)$row->entitlement_pct,
                    (float)$row->commission_amount,
                    $row->status,
                    $row->is_breakage ? 'Yes' : '',
                ];
            }
        }

        // Sort by Group Leader, Team Leader, Introducer, then Transaction
        // Date — exactly the order Chris asked for.
        usort($detailRows, function ($a, $b) {
            return [$a[0], $a[1], $a[2], $a[3]] <=> [$b[0], $b[1], $b[2], $b[3]];
        });

        $this->buildExcel($transactions->count(), $commissionRows->flatten()->count(), $statusCounts, $totalsByStatus, $detailRows);

        $this->info('Done. Saved as EARNING_INCOME_REPORT.xlsx in your GeneralLink folder.');
        return self::SUCCESS;
    }

    private function getAgent(?string $agentId): ?object
    {
        if (!$agentId) {
            return null;
        }
        if (!array_key_exists($agentId, $this->agentCache)) {
            $this->agentCache[$agentId] = DB::table('agents')->where('agent_id', $agentId)->first();
        }
        return $this->agentCache[$agentId];
    }

    /**
     * Walk the upline from the closer upward and return whoever
     * structurally occupies each of the three tiers (Group Leader,
     * Team Leader, Introducer) — regardless of ACTIVE/INACTIVE status.
     * This deliberately mirrors CommissionEngine::resolveDistributions()'s
     * tier-assignment walk, minus the isActive() filter, so the report
     * shows the real org structure even where a tier is inactive (the
     * separate "Paid To" column already shows who actually got credited
     * in that case).
     */
    private function resolveTierNames(?object $closer): array
    {
        $names = ['GROUP_LEADER' => null, 'TEAM_LEADER' => null, 'INTRODUCER' => null];

        $current = $closer;
        $depth = 0;
        while ($current && $depth < 50) {
            $depth++;
            if (isset($names[$current->role]) && $names[$current->role] === null) {
                $names[$current->role] = $current->full_name;
            }
            $current = $current->parent_id ? $this->getAgent($current->parent_id) : null;
        }

        if ($closer && $closer->role === 'INTRODUCER' && $names['INTRODUCER'] === null) {
            $names['INTRODUCER'] = $closer->full_name;
        }

        return [
            $names['GROUP_LEADER'] ?? '(none in upline)',
            $names['TEAM_LEADER'] ?? '(none in upline)',
            $names['INTRODUCER'] ?? '(none in upline)',
        ];
    }

    private function buildExcel(int $totalTransactions, int $totalCommissionRows, array $statusCounts, array $totalsByStatus, array $detailRows): void
    {
        $spreadsheet = new Spreadsheet();

        // ---- Sheet 1: Summary ----
        $ws1 = $spreadsheet->getActiveSheet();
        $ws1->setTitle('Summary');
        $summary = [
            ['GeneralLink — Sales Transaction & Earning Income Report'],
            ['Generated: ' . now()->format('d M Y, g:ia')],
            [],
            ['Total Sales Transactions (not deleted)', $totalTransactions],
            ['Total Earning Income Rows (commission_transactions)', $totalCommissionRows],
            [],
            ['Earning Income Rows by Status', 'Count', 'Total Amount (RM)'],
            ['PENDING (calculated, not yet paid out)', $statusCounts['PENDING'] ?? 0, $totalsByStatus['PENDING'] ?? 0],
            ['CONFIRMED (actually paid to wallet)', $statusCounts['CONFIRMED'] ?? 0, $totalsByStatus['CONFIRMED'] ?? 0],
            ['REVERSED (cancelled policy, credited back)', $statusCounts['REVERSED'] ?? 0, $totalsByStatus['REVERSED'] ?? 0],
        ];
        foreach ($summary as $rowIdx => $row) {
            foreach ($row as $colIdx => $val) {
                $coord = Coordinate::stringFromColumnIndex($colIdx + 1) . ($rowIdx + 1);
                if (is_float($val) || is_int($val)) {
                    $ws1->getCell($coord)->setValueExplicit($val, DataType::TYPE_NUMERIC);
                } else {
                    $ws1->getCell($coord)->setValue($val);
                }
            }
        }
        $ws1->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $ws1->getStyle('A7:C7')->getFont()->setBold(true);
        $ws1->getStyle('C8:C10')->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A', 'C') as $col) {
            $ws1->getColumnDimension($col)->setAutoSize(true);
        }

        // ---- Sheet 2: Detail ----
        $ws2 = $spreadsheet->createSheet();
        $ws2->setTitle('Detail');
        $headers = [
            'Group Leader', 'Team Leader', 'Introducer', 'Transaction Date',
            'Policy Number', 'Reference No.', 'Vendor', 'Product', 'Premium (RM)',
            'Role Paid', 'Paid To', 'Entitlement %', 'Earning Income (RM)', 'Status', 'Breakage?',
        ];
        foreach ($headers as $colIdx => $val) {
            $coord = Coordinate::stringFromColumnIndex($colIdx + 1) . '1';
            $ws2->getCell($coord)->setValue($val);
        }
        $ws2->getStyle('A1:O1')->getFont()->setBold(true);

        foreach ($detailRows as $rowIdx => $row) {
            foreach ($row as $colIdx => $val) {
                $coord = Coordinate::stringFromColumnIndex($colIdx + 1) . ($rowIdx + 2);
                if (is_float($val) || is_int($val)) {
                    $ws2->getCell($coord)->setValueExplicit($val, DataType::TYPE_NUMERIC);
                } else {
                    $ws2->getCell($coord)->setValue($val);
                }
            }
        }
        $lastRow = count($detailRows) + 1;
        if ($lastRow >= 2) {
            $ws2->getStyle("I2:I{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $ws2->getStyle("M2:M{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
        foreach (range('A', 'O') as $col) {
            $ws2->getColumnDimension($col)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $writer->save(base_path('EARNING_INCOME_REPORT.xlsx'));
    }
}
