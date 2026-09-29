<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// NEW 22 Aug 2026 — per Chris: the five reports the Event/Donation/
// Sponsorship/Financial Management Module must produce — Official
// Donation Register, Donation Summary, Sponsorship Report, Auction
// Report, and Event Income & Expenditure Statement. All built the same
// raw-PhpSpreadsheet way as AnnualReportController, since that's the
// only Excel export pattern already in this codebase.
//
// UPDATED 28 Aug 2026 — per Chris: "develop all the program, all the
// program that label with the word soon." See ResolvesCbeActiveNode.
class EventReportController extends Controller
{
    use ResolvesCbeActiveNode;

    private function eventFor(string $eventId)
    {
        $agent = auth('agent')->user();
        return DB::table('cbe_events')->where('event_id', $eventId)->where('cbe_node_id', $this->resolveCbeNodeId($agent))->firstOrFail();
    }

    public function index(string $eventId)
    {
        $event = $this->eventFor($eventId);
        return view('cbe.event-reports.index', compact('event'));
    }

    private function contributionRows(string $eventId, ?string $type = null)
    {
        $q = DB::table('cbe_contributions as c')
            ->join('cbe_donors as d', 'd.donor_id', '=', 'c.donor_id')
            ->where('c.event_id', $eventId);
        if ($type) {
            $q->where('c.contribution_type', $type);
        }
        return $q->select('c.*', 'd.donor_name', 'd.donor_type', 'd.phone', 'd.email')
            ->orderBy('c.created_at')->get();
    }

    private function download(Spreadsheet $spreadsheet, string $filenamePrefix, string $eventName)
    {
        $writer = new Xlsx($spreadsheet);
        $filename = $filenamePrefix . '_' . preg_replace('/[^A-Za-z0-9]+/', '', $eventName) . '_' . now()->format('dMY') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function writeSheet(Spreadsheet $spreadsheet, string $title, array $rows): void
    {
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle($title);
        foreach ($rows as $rowIdx => $row) {
            foreach ($row as $colIdx => $val) {
                $coord = Coordinate::stringFromColumnIndex($colIdx + 1) . ($rowIdx + 1);
                if (is_float($val) || is_int($val)) {
                    $ws->getCell($coord)->setValueExplicit($val, DataType::TYPE_NUMERIC);
                } else {
                    $ws->getCell($coord)->setValue($val);
                }
            }
        }
        $lastCol = 'A';
        foreach ($rows as $row) {
            $c = Coordinate::stringFromColumnIndex(count($row));
            if (strlen($c) > strlen($lastCol) || $c > $lastCol) {
                $lastCol = $c;
            }
        }
        foreach (range('A', $lastCol) as $col) {
            $ws->getColumnDimension($col)->setAutoSize(true);
        }
    }

    // 1. Official Donation Register — every contribution, full detail.
    public function donationRegister(string $eventId)
    {
        $event = $this->eventFor($eventId);
        $rows = $this->contributionRows($eventId);

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Official Donation Register'];
        $sheet[] = [$event->event_name . ' — ' . \Carbon\Carbon::parse($event->event_start_date)->format('d M Y')];
        $sheet[] = ['Downloaded: ' . now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Donor', 'Type', 'Contribution Type', 'Item / Description', 'Pledged (RM)', 'Received (RM)', 'Estimated Value (RM)', 'Status', 'Receipt No', 'Notes'];
        foreach ($rows as $r) {
            $sheet[] = [
                $r->donor_name, $r->donor_type, $r->contribution_type, $r->item_description ?? '',
                (float) ($r->pledged_amount ?? 0), (float) $r->received_amount, (float) ($r->estimated_value ?? 0),
                $r->status, $r->receipt_no ?? '', $r->notes ?? '',
            ];
        }
        if ($rows->isEmpty()) {
            $sheet[] = ['No contributions recorded for this event yet.'];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Donation Register', $sheet);
        $spreadsheet->getActiveSheet()->getStyle('A1:A3')->getFont()->setBold(true);
        $spreadsheet->getActiveSheet()->getStyle('A5:J5')->getFont()->setBold(true);

        return $this->download($spreadsheet, 'GeneralLink_DonationRegister', $event->event_name);
    }

    // 2. Donation Summary — totals grouped by contribution type + status.
    public function donationSummary(string $eventId)
    {
        $event = $this->eventFor($eventId);
        $rows = DB::table('cbe_contributions')
            ->where('event_id', $eventId)
            ->select('contribution_type', 'status', DB::raw('COUNT(*) as cnt'), DB::raw('COALESCE(SUM(pledged_amount),0) as pledged'), DB::raw('COALESCE(SUM(received_amount),0) as received'), DB::raw('COALESCE(SUM(estimated_value),0) as estimated'))
            ->groupBy('contribution_type', 'status')
            ->orderBy('contribution_type')->orderBy('status')
            ->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Donation Summary'];
        $sheet[] = [$event->event_name . ' — ' . \Carbon\Carbon::parse($event->event_start_date)->format('d M Y')];
        $sheet[] = ['Downloaded: ' . now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Contribution Type', 'Status', 'Count', 'Total Pledged (RM)', 'Total Received (RM)', 'Total Estimated Value (RM)'];
        foreach ($rows as $r) {
            $sheet[] = [$r->contribution_type, $r->status, (int) $r->cnt, (float) $r->pledged, (float) $r->received, (float) $r->estimated];
        }
        $sheet[] = [];
        $sheet[] = ['GRAND TOTAL', '', (int) $rows->sum('cnt'), (float) $rows->sum('pledged'), (float) $rows->sum('received'), (float) $rows->sum('estimated')];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Donation Summary', $sheet);
        $spreadsheet->getActiveSheet()->getStyle('A1:A3')->getFont()->setBold(true);
        $spreadsheet->getActiveSheet()->getStyle('A5:F5')->getFont()->setBold(true);

        return $this->download($spreadsheet, 'GeneralLink_DonationSummary', $event->event_name);
    }

    // 3. Sponsorship Report — SPONSORSHIP type only.
    public function sponsorshipReport(string $eventId)
    {
        $event = $this->eventFor($eventId);
        $rows = $this->contributionRows($eventId, 'SPONSORSHIP');

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Sponsorship Report'];
        $sheet[] = [$event->event_name . ' — ' . \Carbon\Carbon::parse($event->event_start_date)->format('d M Y')];
        $sheet[] = ['Downloaded: ' . now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Sponsor', 'Package / Description', 'Pledged (RM)', 'Received (RM)', 'Status', 'Contact Person', 'Phone', 'Email'];
        foreach ($rows as $r) {
            $sheet[] = [
                $r->donor_name, $r->item_description ?? '', (float) ($r->pledged_amount ?? 0), (float) $r->received_amount,
                $r->status, $r->donor_type, $r->phone ?? '', $r->email ?? '',
            ];
        }
        if ($rows->isEmpty()) {
            $sheet[] = ['No sponsorships recorded for this event yet.'];
        }
        $sheet[] = [];
        $sheet[] = ['Total Sponsorship Pledged (RM)', (float) $rows->sum('pledged_amount')];
        $sheet[] = ['Total Sponsorship Received (RM)', (float) $rows->sum('received_amount')];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Sponsorship Report', $sheet);
        $spreadsheet->getActiveSheet()->getStyle('A1:A3')->getFont()->setBold(true);
        $spreadsheet->getActiveSheet()->getStyle('A5:H5')->getFont()->setBold(true);

        return $this->download($spreadsheet, 'GeneralLink_SponsorshipReport', $event->event_name);
    }

    // 4. Auction Report — AUCTION_ITEM type only.
    public function auctionReport(string $eventId)
    {
        $event = $this->eventFor($eventId);
        $rows = $this->contributionRows($eventId, 'AUCTION_ITEM');

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Auction Report'];
        $sheet[] = [$event->event_name . ' — ' . \Carbon\Carbon::parse($event->event_start_date)->format('d M Y')];
        $sheet[] = ['Downloaded: ' . now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Item', 'Winning Bidder', 'Winning Bid (RM)', 'Received (RM)', 'Status', 'Receipt No'];
        foreach ($rows as $r) {
            $sheet[] = [
                $r->item_description ?? '', $r->donor_name, (float) ($r->pledged_amount ?? 0), (float) $r->received_amount,
                $r->status, $r->receipt_no ?? '',
            ];
        }
        if ($rows->isEmpty()) {
            $sheet[] = ['No auction items recorded for this event yet.'];
        }
        $sheet[] = [];
        $sheet[] = ['Total Auction Proceeds (RM)', (float) $rows->sum('received_amount')];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Auction Report', $sheet);
        $spreadsheet->getActiveSheet()->getStyle('A1:A3')->getFont()->setBold(true);
        $spreadsheet->getActiveSheet()->getStyle('A5:F5')->getFont()->setBold(true);

        return $this->download($spreadsheet, 'GeneralLink_AuctionReport', $event->event_name);
    }

    // 5. Event Income & Expenditure Statement — contributions received
    // minus event expenses, for this event only (separate from the
    // Temple's regular annual Income & Expenditure Report).
    public function incomeExpenditure(string $eventId)
    {
        $event = $this->eventFor($eventId);

        $incomeRows = DB::table('cbe_contributions')
            ->where('event_id', $eventId)->where('status', '!=', 'CANCELLED')
            ->select('contribution_type', DB::raw('COALESCE(SUM(received_amount),0) as total'))
            ->groupBy('contribution_type')->orderBy('contribution_type')->get();
        $incomeTotal = (float) $incomeRows->sum('total');

        $expenseRows = DB::table('cbe_event_expenses as x')
            ->leftJoin('cbe_transaction_categories as c', 'c.category_id', '=', 'x.category_id')
            ->where('x.event_id', $eventId)
            ->select(DB::raw("COALESCE(c.category_name, 'Uncategorized') as category_name"), DB::raw('SUM(x.amount) as total'))
            ->groupBy('c.category_name')->orderBy('c.category_name')->get();
        $expenseTotal = (float) $expenseRows->sum('total');

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Event Income & Expenditure Statement'];
        $sheet[] = [$event->event_name . ' — ' . \Carbon\Carbon::parse($event->event_start_date)->format('d M Y')];
        $sheet[] = ['Downloaded: ' . now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['INCOME (Contributions Received)'];
        $sheet[] = ['Contribution Type', 'Amount (RM)'];
        foreach ($incomeRows as $r) {
            $sheet[] = [$r->contribution_type, (float) $r->total];
        }
        $sheet[] = ['Total Income', $incomeTotal];
        $sheet[] = [];
        $sheet[] = ['EXPENDITURE'];
        $sheet[] = ['Category', 'Amount (RM)'];
        foreach ($expenseRows as $r) {
            $sheet[] = [$r->category_name, (float) $r->total];
        }
        $sheet[] = ['Total Expenditure', $expenseTotal];
        $sheet[] = [];
        $sheet[] = ['NET SURPLUS / (DEFICIT)', $incomeTotal - $expenseTotal];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Event Income Expenditure', $sheet);
        $spreadsheet->getActiveSheet()->getStyle('A1:A3')->getFont()->setBold(true);
        $spreadsheet->getActiveSheet()->getStyle('A5')->getFont()->setBold(true);
        $lastRow = $spreadsheet->getActiveSheet()->getHighestRow();
        $spreadsheet->getActiveSheet()->getStyle('B1:B' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');

        return $this->download($spreadsheet, 'GeneralLink_EventIncomeExpenditure', $event->event_name);
    }
}
