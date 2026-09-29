<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// UPDATED 28 Aug 2026 — per Chris: "develop all the program, all the
// program that label with the word soon." See ResolvesCbeActiveNode.
// NEW 22 Aug 2026 — per Chris: the CBE Annual Report has two Excel parts,
// both generated for one node + one year at a time (the agent's own
// cbe_node_id — their Temple/Branch/State/HQ):
//
// 1. Secretary Activity Report — cbe_meeting_minutes + cbe_activities for
//    the year, combined and sorted by date into one log.
// 2. Income & Expenditure Report — cbe_transactions for the year, totaled
//    by category within INCOME/EXPENSE, with a net surplus/deficit line.
//    Built the same raw-PhpSpreadsheet way as
//    Admin\OverrideClaimController's export, since that's the only Excel
//    export pattern already in this codebase (no maatwebsite/excel here).
class AnnualReportController extends Controller
{
    use ResolvesCbeActiveNode;

    public function index(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);

        if (! $nodeId && $agent->role === 'ADMIN') {
            return $this->renderCbeNodePicker('cbe.annual-report.index', __('cbe_records.annual_report_page_title'), leafOnly: true);
        }

        $selectedYear = (int) $request->get('year', now()->year);

        return view('cbe.annual-report.index', [
            'hasNode'      => (bool) $nodeId,
            'selectedYear' => $selectedYear,
        ]);
    }

    public function secretaryReport(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        abort_unless($nodeId, 404);

        $year = (int) $request->get('year', now()->year);

        $minutes = DB::table('cbe_meeting_minutes')
            ->where('cbe_node_id', $nodeId)
            ->whereYear('meeting_date', $year)
            ->get()
            ->map(fn ($m) => (object) ['date' => $m->meeting_date, 'time' => $m->meeting_time, 'venue' => $m->venue, 'type' => 'Meeting Minutes', 'title' => $m->title, 'notes' => $m->summary]);

        $activities = DB::table('cbe_activities')
            ->where('cbe_node_id', $nodeId)
            ->whereYear('activity_date', $year)
            ->get()
            ->map(fn ($a) => (object) ['date' => $a->activity_date, 'time' => $a->activity_time ?? null, 'venue' => $a->venue ?? null, 'type' => 'Activity', 'title' => $a->title, 'notes' => $a->description]);

        // NEW 18 Sep 2026 — per Chris's uploaded requirement: this report
        // ("association diary") must also carry the year's planned
        // events, not just what already happened.
        $planned = DB::table('cbe_temple_calendar_events')
            ->where('cbe_node_id', $nodeId)
            ->where('is_active', true)
            ->whereYear('event_date', $year)
            ->get()
            ->map(fn ($e) => (object) ['date' => $e->event_date, 'time' => $e->event_time ?? null, 'venue' => $e->venue ?? null, 'type' => 'Planned Event', 'title' => $e->title, 'notes' => $e->description]);

        $rows = $minutes->concat($activities)->concat($planned)->sortBy('date')->values();

        $nodeName = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->value('node_name') ?? '';

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — CBE Secretary Activity Report'];
        $sheet[] = [$nodeName . ' — Year ' . $year];
        $sheet[] = ['Downloaded: ' . now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Date', 'Time', 'Venue', 'Type', 'Title', 'Notes / Summary'];
        foreach ($rows as $r) {
            $sheet[] = [
                \Carbon\Carbon::parse($r->date)->format('d M Y'),
                $r->time ? \Carbon\Carbon::parse($r->time)->format('g:i A') : '',
                $r->venue ?? '',
                $r->type,
                $r->title,
                $r->notes ?? '',
            ];
        }
        if ($rows->isEmpty()) {
            $sheet[] = ['No meeting minutes or activities recorded for ' . $year . '.'];
        }

        $spreadsheet = new Spreadsheet();
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('Secretary Activity Report');
        foreach ($sheet as $rowIdx => $row) {
            foreach ($row as $colIdx => $val) {
                $coord = Coordinate::stringFromColumnIndex($colIdx + 1) . ($rowIdx + 1);
                $ws->getCell($coord)->setValue($val);
            }
        }
        $ws->getStyle('A1:A3')->getFont()->setBold(true);
        $ws->getStyle('A5:F5')->getFont()->setBold(true);
        foreach (range('A', 'F') as $col) {
            $ws->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'GeneralLink_CBE_SecretaryActivityReport_' . $year . '_' . now()->format('dMY') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function incomeExpenditureReport(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        abort_unless($nodeId, 404);

        $year = (int) $request->get('year', now()->year);

        $rows = DB::table('cbe_transactions as t')
            ->join('cbe_transaction_categories as c', 'c.category_id', '=', 't.category_id')
            ->where('t.cbe_node_id', $nodeId)
            ->whereYear('t.transaction_date', $year)
            ->select('c.category_name', 'c.type', DB::raw('SUM(t.amount) as total'))
            ->groupBy('c.category_name', 'c.type')
            ->orderBy('c.type')->orderBy('c.category_name')
            ->get();

        $income = $rows->where('type', 'INCOME');
        $expense = $rows->where('type', 'EXPENSE');
        $incomeTotal = (float) $income->sum('total');
        $expenseTotal = (float) $expense->sum('total');

        $nodeName = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->value('node_name') ?? '';

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — CBE Income & Expenditure Report'];
        $sheet[] = [$nodeName . ' — Year ' . $year];
        $sheet[] = ['Downloaded: ' . now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['INCOME'];
        $sheet[] = ['Category', 'Amount (RM)'];
        foreach ($income as $r) {
            $sheet[] = [$r->category_name, (float) $r->total];
        }
        $sheet[] = ['Total Income', $incomeTotal];
        $sheet[] = [];
        $sheet[] = ['EXPENDITURE'];
        $sheet[] = ['Category', 'Amount (RM)'];
        foreach ($expense as $r) {
            $sheet[] = [$r->category_name, (float) $r->total];
        }
        $sheet[] = ['Total Expenditure', $expenseTotal];
        $sheet[] = [];
        $sheet[] = ['NET SURPLUS / (DEFICIT)', $incomeTotal - $expenseTotal];

        $spreadsheet = new Spreadsheet();
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('Income & Expenditure');
        foreach ($sheet as $rowIdx => $row) {
            foreach ($row as $colIdx => $val) {
                $coord = Coordinate::stringFromColumnIndex($colIdx + 1) . ($rowIdx + 1);
                if (is_float($val) || is_int($val)) {
                    $ws->getCell($coord)->setValueExplicit($val, DataType::TYPE_NUMERIC);
                } else {
                    $ws->getCell($coord)->setValue($val);
                }
            }
        }
        $ws->getStyle('A1:A3')->getFont()->setBold(true);
        $ws->getStyle('A5')->getFont()->setBold(true);
        $lastRow = $ws->getHighestRow();
        $ws->getStyle('B1:B' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A', 'B') as $col) {
            $ws->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'GeneralLink_CBE_IncomeExpenditureReport_' . $year . '_' . now()->format('dMY') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
