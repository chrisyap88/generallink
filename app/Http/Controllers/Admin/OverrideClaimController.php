<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApprovalService;
use App\Services\AuditService;
use App\Services\OverrideLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 2 Aug 2026 — Override Claim Report / workflow. Per Chris: the
// Vendor Override Member feature had a calculation engine
// (override:calculate console command, writes to
// override_commission_claims) but no screen anywhere to actually see,
// submit, approve, or pay those calculated claims — the command's own
// closing message pointed at a page that never existed. This closes
// that gap.
//
// Real stages now: CALCULATED (auto-computed by the command, sitting
// there unreviewed) -> SUBMITTED (Admin puts it forward, creates a
// pending_approvals row via the SAME 4-eye ApprovalService already used
// for Withdrawal Approval — a DIFFERENT Admin must approve) -> APPROVED
// -> PAID (Admin confirms the vendor settlement actually happened,
// recording an optional payment reference). REJECTED covers a claim a
// second Admin declines.
// -------------------------------------------------------
class OverrideClaimController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', '');
        $vendorId = $request->input('vendor_id', '');
        $memberId = $request->input('member_id', '');

        $query = DB::table('override_commission_claims as c')
            ->join('override_members as m', 'm.override_member_id', '=', 'c.override_member_id')
            ->join('vendors as v', 'v.vendor_id', '=', 'm.vendor_id')
            ->join('group_labels as gl', 'gl.group_label_id', '=', 'm.group_label_id')
            ->leftJoin('products as p', 'p.product_id', '=', 'c.product_id')
            ->select(
                'c.*', 'm.full_name as member_name', 'm.override_member_code',
                'v.vendor_name', 'gl.group_name', 'p.product_name'
            );

        if ($status) $query->where('c.status', $status);
        if ($vendorId) $query->where('m.vendor_id', $vendorId);
        if ($memberId) $query->where('c.override_member_id', $memberId);

        $claims = $query->orderByDesc('c.created_at')->paginate(6)->withQueryString();

        $vendors = DB::table('vendors')->where('is_active', true)->orderBy('vendor_name')->get(['vendor_id', 'vendor_name']);
        $members = DB::table('override_members')->where('is_active', true)->orderBy('full_name')->get(['override_member_id', 'full_name', 'override_member_code']);

        // NEW 2 Aug 2026 — per Chris: show WHO a submitted claim is
        // routed to right on the report, not just "awaiting approval".
        // Static pool for this action type (FINANCE dept + Director) —
        // the actual excluded-requester logic only matters at submit
        // time, this is just for display.
        $approverNames = DB::table('agents')
            ->where('role', 'ADMIN')->where('is_deleted', false)
            ->where(fn($q) => $q->where('department', 'FINANCE')->orWhere('department', 'DIRECTOR'))
            ->orderBy('department')->pluck('full_name')->implode(', ');

        $summary = DB::table('override_commission_claims')
            ->selectRaw("
                SUM(CASE WHEN status = 'CALCULATED' THEN 1 ELSE 0 END) as awaiting_submission,
                SUM(CASE WHEN status = 'SUBMITTED' THEN 1 ELSE 0 END) as awaiting_approval,
                SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as awaiting_payment,
                SUM(CASE WHEN status = 'PAID' THEN calculated_amount ELSE 0 END) as total_paid
            ")->first();

        return view('masterfile.override-claims-index', compact('claims', 'vendors', 'members', 'status', 'vendorId', 'memberId', 'summary', 'approverNames'));
    }

    // -------------------------------------------------------
    // NEW 2 Aug 2026 — per Chris: "you should have an override member
    // ledger to keep track the status and history and audit log."
    // Unlike index() above (which is the operational worklist — what
    // needs action right now), this shows EVERY claim's complete
    // lifecycle in one row: who calculated it, who submitted it and
    // when, who approved/rejected it and when (+ reason if rejected),
    // who paid it and when + the payment reference. Nothing here is
    // ever overwritten as a claim moves through its stages — each
    // column is stamped once and kept, so this row IS the audit trail,
    // not a reconstruction of one.
    // -------------------------------------------------------
    public function ledger(Request $request)
    {
        $status = $request->input('status', '');
        $vendorId = $request->input('vendor_id', '');
        $memberId = $request->input('member_id', '');

        $query = DB::table('override_commission_claims as c')
            ->join('override_members as m', 'm.override_member_id', '=', 'c.override_member_id')
            ->join('vendors as v', 'v.vendor_id', '=', 'm.vendor_id')
            ->leftJoin('products as p', 'p.product_id', '=', 'c.product_id')
            ->leftJoin('agents as sub', 'sub.agent_id', '=', 'c.submitted_by')
            ->leftJoin('agents as app', 'app.agent_id', '=', 'c.approved_by')
            ->leftJoin('agents as pay', 'pay.agent_id', '=', 'c.paid_by')
            ->select(
                'c.*', 'm.full_name as member_name', 'm.override_member_code', 'v.vendor_name', 'p.product_name',
                'sub.full_name as submitted_by_name', 'app.full_name as approved_by_name', 'pay.full_name as paid_by_name'
            );

        if ($status) $query->where('c.status', $status);
        if ($vendorId) $query->where('m.vendor_id', $vendorId);
        if ($memberId) $query->where('c.override_member_id', $memberId);

        $claims = $query->orderByDesc('c.created_at')->paginate(6)->withQueryString();

        $vendors = DB::table('vendors')->where('is_active', true)->orderBy('vendor_name')->get(['vendor_id', 'vendor_name']);
        $members = DB::table('override_members')->orderBy('full_name')->get(['override_member_id', 'full_name', 'override_member_code']);

        return view('masterfile.override-ledger', compact('claims', 'vendors', 'members', 'status', 'vendorId', 'memberId'));
    }

    public function submit(Request $request, string $claimId)
    {
        $claim = DB::table('override_commission_claims')->where('claim_id', $claimId)->first();
        abort_if(!$claim, 404);

        if (!in_array($claim->status, ['CALCULATED', 'REJECTED'])) {
            return back()->with('error', 'Only a Calculated or Rejected claim can be submitted for approval.');
        }

        $member = DB::table('override_members')->where('override_member_id', $claim->override_member_id)->first();
        $adminId = Auth::guard('agent')->id();

        $approvalId = app(ApprovalService::class)->requestApproval(
            'OVERRIDE_CLAIM_APPROVAL',
            null,
            ['claim_id' => $claimId],
            $adminId,
            null,
            $request->input('notes') ?: "Override claim for {$member->full_name} ({$member->override_member_code}), period {$claim->period_start} to {$claim->period_end}, RM " . number_format($claim->calculated_amount, 2)
        );

        DB::table('override_commission_claims')->where('claim_id', $claimId)->update([
            'status'        => 'SUBMITTED',
            'submitted_by'  => $adminId,
            'submitted_at'  => now(),
            'approval_id'   => $approvalId,
            'rejection_reason' => null,
            'updated_at'    => now(),
        ]);

        AuditService::logChange('override_commission_claims', $claimId, 'OVERRIDE_CLAIM_SUBMITTED', ['status' => $claim->status], ['status' => 'SUBMITTED']);

        // NEW 2 Aug 2026 — per Chris: post the Debit ledger entry the
        // moment a claim is submitted ("debit means submitted"). Every
        // posting gets its own permanent voucher number — never reused,
        // never overwritten.
        $periodLabel = \Carbon\Carbon::parse($claim->period_start)->format('d M Y') . ' – ' . \Carbon\Carbon::parse($claim->period_end)->format('d M Y');
        app(OverrideLedgerService::class)->post(
            $claim->override_member_id, $claimId, 'DEBIT',
            "Override claim submitted — {$periodLabel}",
            (float) $claim->calculated_amount, (float) ($claim->sales_basis_amount ?? 0), $adminId
        );

        // NEW 2 Aug 2026 — per Chris: "submit to who?" was unclear. Name
        // the actual eligible approvers in the confirmation message
        // instead of a generic "a different Admin".
        $approverNames = collect(app(ApprovalService::class)->getEligibleApprovers($adminId, 'OVERRIDE_CLAIM_APPROVAL'))
            ->pluck('full_name')->implode(', ');
        $who = $approverNames ?: 'another Admin (Finance department or Admin Director)';

        return back()->with('success', "Claim submitted for approval — routed to: {$who}. It shows in their Action Center > Approvals until a DIFFERENT Admin than you approves or rejects it (4-eye policy).");
    }

    public function markPaid(Request $request, string $claimId)
    {
        $claim = DB::table('override_commission_claims')->where('claim_id', $claimId)->first();
        abort_if(!$claim, 404);

        if ($claim->status !== 'APPROVED') {
            return back()->with('error', 'Only an Approved claim can be marked Paid.');
        }

        $request->validate([
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $adminId = Auth::guard('agent')->id();

        $paymentReference = $request->input('payment_reference') ?: null;

        DB::table('override_commission_claims')->where('claim_id', $claimId)->update([
            'status'             => 'PAID',
            'paid_by'            => $adminId,
            'settled_at'         => now(), // reused as "paid at" — see migration note
            'payment_reference'  => $paymentReference,
            'updated_at'         => now(),
        ]);

        AuditService::logChange('override_commission_claims', $claimId, 'OVERRIDE_CLAIM_PAID', ['status' => 'APPROVED'], ['status' => 'PAID']);

        // NEW 2 Aug 2026 — per Chris: "credit means paid". Posts the
        // matching Credit entry so the ledger balance settles back to
        // zero for this claim.
        app(OverrideLedgerService::class)->post(
            $claim->override_member_id, $claimId, 'CREDIT',
            'Override claim paid' . ($paymentReference ? " — Ref: {$paymentReference}" : ''),
            (float) $claim->calculated_amount, null, $adminId
        );

        return back()->with('success', 'Claim marked as Paid.');
    }

    // -------------------------------------------------------
    // NEW 2 Aug 2026 — the actual "accounting ledger" screen per Chris:
    // clean, no filter bar, boxed Debit/Credit statement for ONE
    // Override Member "as at" today, with a running balance. Reached
    // via the View Statement button on the searchable ledger list
    // (index/ledger above) rather than its own sidebar entry.
    // -------------------------------------------------------
    public function statement(Request $request)
    {
        $memberId = $request->query('member_id');
        abort_if(!$memberId, 404);

        $member = DB::table('override_members')->where('override_member_id', $memberId)->first();
        abort_if(!$member, 404);

        $allEntries = DB::table('override_ledger_entries')
            ->where('override_member_id', $memberId)
            ->orderBy('entry_date')->orderBy('created_at')
            ->get();

        // NEW 2 Aug 2026 — per Chris: "start with Opening Balance and
        // closing balance, like a Maybank statement". Opening Balance =
        // 0 the moment this member had zero history (very first entry
        // ever), and the running balance carries forward from there.
        // Because pagination can split a long history across screens,
        // the Opening Balance shown on any given page = the running
        // balance right after the LAST entry of the previous page (0 on
        // page 1) — so the Balance column always continues correctly
        // no matter which page you're looking at.
        $running = 0;
        $totalDebit = 0;
        $totalCredit = 0;
        $withBalance = $allEntries->map(function ($e) use (&$running, &$totalDebit, &$totalCredit) {
            if ($e->entry_type === 'DEBIT') {
                $running += (float) $e->amount;
                $totalDebit += (float) $e->amount;
            } else {
                $running -= (float) $e->amount;
                $totalCredit += (float) $e->amount;
            }
            $e->running_balance = $running;
            return $e;
        });

        // REDUCED 2 Aug 2026 — per Chris, same fix applied to the
        // Earning Income Ledger statement: 15 rows overflowed the
        // one-screen no-scroll layout once a Page Sub Total row and the
        // taller two-row summary bar were added.
        $perPage = 9;
        $page = (int) $request->query('page', 1);

        $openingBalance = $page > 1
            ? (float) ($withBalance->get(($page - 1) * $perPage - 1)?->running_balance ?? 0)
            : 0.0;

        $pageItems = $withBalance->forPage($page, $perPage)->values();
        $closingBalance = $pageItems->isNotEmpty() ? (float) $pageItems->last()->running_balance : $openingBalance;

        $entries = new \Illuminate\Pagination\LengthAwarePaginator(
            $pageItems,
            $withBalance->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('masterfile.override-ledger-statement', compact('member', 'entries', 'totalDebit', 'totalCredit', 'openingBalance', 'closingBalance'));
    }

    public function export(Request $request)
    {
        $status = $request->input('status', '');
        $vendorId = $request->input('vendor_id', '');
        $memberId = $request->input('member_id', '');

        $query = DB::table('override_commission_claims as c')
            ->join('override_members as m', 'm.override_member_id', '=', 'c.override_member_id')
            ->join('vendors as v', 'v.vendor_id', '=', 'm.vendor_id')
            ->join('group_labels as gl', 'gl.group_label_id', '=', 'm.group_label_id')
            ->leftJoin('products as p', 'p.product_id', '=', 'c.product_id')
            ->select(
                'c.*', 'm.full_name as member_name', 'm.override_member_code',
                'v.vendor_name', 'gl.group_name', 'p.product_name'
            );

        if ($status) $query->where('c.status', $status);
        if ($vendorId) $query->where('m.vendor_id', $vendorId);
        if ($memberId) $query->where('c.override_member_id', $memberId);

        $rows = $query->orderByDesc('c.created_at')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Override Claim Report'];
        $sheet[] = ['Downloaded: ' . now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Member', 'Code', 'Vendor', 'Group', 'Product', 'Period Start', 'Period End', 'Sales Basis (RM)', 'Calculated Amount (RM)', 'Settlement Method', 'Status', 'Payment Reference'];
        foreach ($rows as $r) {
            $sheet[] = [
                $r->member_name, $r->override_member_code, $r->vendor_name, $r->group_name,
                $r->product_name ?? '(any product)',
                \Carbon\Carbon::parse($r->period_start)->format('d M Y'),
                \Carbon\Carbon::parse($r->period_end)->format('d M Y'),
                (float) ($r->sales_basis_amount ?? 0),
                (float) $r->calculated_amount,
                $r->settlement_method,
                $r->status,
                $r->payment_reference ?? '—',
            ];
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('Override Claims');
        foreach ($sheet as $rowIdx => $row) {
            foreach ($row as $colIdx => $val) {
                $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1) . ($rowIdx + 1);
                if (is_float($val) || is_int($val)) {
                    $ws->getCell($coord)->setValueExplicit($val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                } else {
                    $ws->getCell($coord)->setValue($val);
                }
            }
        }
        $ws->getStyle('A1:A2')->getFont()->setBold(true);
        $ws->getStyle('A4:L4')->getFont()->setBold(true);
        $lastRow = $ws->getHighestRow();
        $ws->getStyle('H5:I' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A', 'L') as $col) {
            $ws->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'GeneralLink_OverrideClaims_' . now()->format('dMY') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
