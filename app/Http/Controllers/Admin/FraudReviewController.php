<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FraudDetectionService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// NEW 22 Jul 2026 — Admin's "Risk Review Queue" for fraud_review_flags
// (see FraudDetectionService + migration 2026_07_22_000001). Unlike
// Agent Balances or Document Credit's "nothing loads until filtered"
// rule, this screen DOES show items by default — it's a worklist
// ("here is what needs your attention"), same reasoning as the
// existing Approvals screen defaulting to pending items, not a
// browse-everyone screen that could return an unbounded result set.
class FraudReviewController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'OPEN');
        $riskLevel = $request->get('risk_level');

        $query = DB::table('fraud_review_flags as f')
            ->leftJoin('agents as a', 'f.agent_id', '=', 'a.agent_id')
            ->leftJoin('agents as r', 'f.reviewed_by', '=', 'r.agent_id')
            ->select('f.*', 'a.full_name as agent_name', 'a.agent_code', 'r.full_name as reviewer_name');

        if ($status !== 'ALL') {
            $query->where('f.status', $status);
        }
        if ($riskLevel) {
            $query->where('f.risk_level', $riskLevel);
        }

        $flags = $query->orderByRaw("FIELD(f.risk_level, 'CRITICAL','HIGH','MEDIUM','LOW')")
            ->orderByDesc('f.created_at')
            ->paginate(15);

        $counts = DB::table('fraud_review_flags')
            ->whereIn('status', ['OPEN', 'UNDER_REVIEW'])
            ->select('risk_level', DB::raw('count(*) as total'))
            ->groupBy('risk_level')
            ->pluck('total', 'risk_level');

        return view('admin.fraud-review.index', compact('flags', 'status', 'riskLevel', 'counts'));
    }

    public function show(string $flagId)
    {
        $flag = DB::table('fraud_review_flags as f')
            ->leftJoin('agents as a', 'f.agent_id', '=', 'a.agent_id')
            ->leftJoin('agents as r', 'f.reviewed_by', '=', 'r.agent_id')
            ->where('f.flag_id', $flagId)
            ->select('f.*', 'a.full_name as agent_name', 'a.agent_code', 'a.email as agent_email', 'r.full_name as reviewer_name')
            ->firstOrFail();

        $anomalies = json_decode($flag->anomalies, true) ?: [];

        // Pull a human-readable summary of the flagged record itself —
        // best-effort per flaggable_type, since this queue is meant to
        // cover multiple document types with one screen.
        $flaggableSummary = match ($flag->flaggable_type) {
            'SALES_TRANSACTION' => DB::table('sales_transactions as st')
                ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
                ->where('st.policy_id', $flag->flaggable_id)
                ->select('st.document_reference_number', 'st.premium_amount', 'c.full_name as customer_name')
                ->first(),
            'DOCUMENT_CREDIT_TOPUP' => DB::table('document_credit_topup_requests')
                ->where('request_id', $flag->flaggable_id)
                ->select('amount_requested', 'status')
                ->first(),
            default => null,
        };

        return view('admin.fraud-review.show', compact('flag', 'anomalies', 'flaggableSummary'));
    }

    public function clear(Request $request, string $flagId, FraudDetectionService $fraud)
    {
        $request->validate(['decision_notes' => 'nullable|string|max:2000']);
        $admin = Auth::guard('agent')->user();

        $flag = DB::table('fraud_review_flags')->where('flag_id', $flagId)->firstOrFail();

        DB::table('fraud_review_flags')->where('flag_id', $flagId)->update([
            'status'         => 'CLEARED',
            'reviewed_by'    => $admin->agent_id,
            'reviewed_at'    => now(),
            'decision_notes' => $request->input('decision_notes'),
            'updated_at'     => now(),
        ]);

        // Mirror the existing Sales Transaction "clear flagged" action
        // so a cleared fraud-queue flag also lifts the record's own
        // flagged_for_review boolean, keeping the two in sync.
        if ($flag->flaggable_type === 'SALES_TRANSACTION') {
            DB::table('sales_transactions')->where('policy_id', $flag->flaggable_id)->update([
                'flagged_for_review' => false,
                'reviewed_by'        => $admin->agent_id,
                'reviewed_at'        => now(),
                'updated_at'         => now(),
            ]);
        }

        $submitter = DB::table('agents')->where('agent_id', $flag->agent_id)->first();
        if ($submitter) {
            app(NotificationService::class)->notify(
                [$submitter],
                'FRAUD_REVIEW_CLEARED',
                'Submission Cleared',
                'Your flagged submission has been reviewed and cleared by Admin. It can now proceed normally.'
            );
        }

        return redirect()->route('admin.fraud-review.index')->with('success', 'Flag cleared.');
    }

    public function confirmFraud(Request $request, string $flagId)
    {
        $request->validate(['decision_notes' => 'required|string|max:2000']);
        $admin = Auth::guard('agent')->user();

        $flag = DB::table('fraud_review_flags')->where('flag_id', $flagId)->firstOrFail();

        DB::table('fraud_review_flags')->where('flag_id', $flagId)->update([
            'status'         => 'CONFIRMED_FRAUD',
            'reviewed_by'    => $admin->agent_id,
            'reviewed_at'    => now(),
            'decision_notes' => $request->input('decision_notes'),
            'updated_at'     => now(),
        ]);

        $submitter = DB::table('agents')->where('agent_id', $flag->agent_id)->first();
        if ($submitter) {
            app(NotificationService::class)->notify(
                [$submitter],
                'FRAUD_REVIEW_CONFIRMED',
                'Submission Rejected — Verification Failed',
                'Your submission did not pass Admin verification and has been rejected. Please contact Admin if you believe this is a mistake.'
            );
        }

        return redirect()->route('admin.fraud-review.index')->with('success', 'Marked as confirmed fraud. The submission remains blocked.');
    }

    public function markUnderReview(string $flagId)
    {
        $admin = Auth::guard('agent')->user();
        DB::table('fraud_review_flags')->where('flag_id', $flagId)->where('status', 'OPEN')->update([
            'status'     => 'UNDER_REVIEW',
            'updated_at' => now(),
        ]);
        return redirect()->route('admin.fraud-review.show', $flagId)->with('success', 'Marked as under review.');
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'medium_threshold'   => ['required', 'integer', 'min:1', 'max:99'],
            'high_threshold'     => ['required', 'integer', 'min:1', 'max:99'],
            'critical_threshold' => ['required', 'integer', 'min:1', 'max:100'],
            'reminder_hours'     => ['required', 'integer', 'min:1'],
            'escalation_hours'   => ['required', 'integer', 'min:1'],
        ]);
        $admin = Auth::guard('agent')->user();

        $settings = [
            'fraud_risk_medium_threshold'   => $request->input('medium_threshold'),
            'fraud_risk_high_threshold'     => $request->input('high_threshold'),
            'fraud_risk_critical_threshold' => $request->input('critical_threshold'),
            'fraud_review_reminder_hours'   => $request->input('reminder_hours'),
            'fraud_review_escalation_hours' => $request->input('escalation_hours'),
        ];

        foreach ($settings as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'updated_by' => $admin->agent_id, 'updated_at' => now()]
            );
        }

        return back()->with('success', 'Fraud review settings updated.');
    }
}
