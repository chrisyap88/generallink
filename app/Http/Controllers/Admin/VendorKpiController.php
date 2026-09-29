<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// NEW 9 Aug 2026 — Vendor KPI Dashboard. Per Chris: "in admin dash board
// admin should have Vendor kpi dash board all kpi should in one main
// menu overview KPI, Vendor KPI and Customer kpi." Admin-only (vendors
// are company-wide, not owned per-agent, so unlike Customer KPI there is
// no GL/TL/Introducer cascade to scope by). Every number here is a real
// query against tables already live in this app — nothing is a
// placeholder/fake metric.
class VendorKpiController extends Controller
{
    public function index(Request $request)
    {
        // ── Vendor Overview — mirrors the login_status lifecycle used
        // throughout Vendor Management Phase 1/2. ──
        $totalVendors   = DB::table('vendors')->where('is_active', true)->count();
        $activeLogins   = DB::table('vendors')->where('login_status', 'ACTIVE')->count();
        $pendingLogins  = DB::table('vendors')->where('login_status', 'PENDING')->count();
        $awaitingPwd    = DB::table('vendors')->where('login_status', 'AWAITING_PASSWORD')->count();
        $restrictedLogins = DB::table('vendors')->where('login_status', 'RESTRICTED')->count();
        $rejectedLogins = DB::table('vendors')->where('login_status', 'REJECTED')->count();

        // ── Document Verification — per-document status set by Admin on
        // the Pending Logins screen (Task #59). ──
        $docStatusRows = DB::table('vendor_documents')
            ->select('verification_status', DB::raw('COUNT(*) as total'))
            ->groupBy('verification_status')->pluck('total', 'verification_status');
        $docPending  = (int) ($docStatusRows['PENDING'] ?? 0);
        $docVerified = (int) ($docStatusRows['VERIFIED'] ?? 0);
        $docRejected = (int) ($docStatusRows['REJECTED'] ?? 0);

        // ── AI Due Diligence Risk — latest assessment per vendor only
        // (a vendor can have more than one historical run). ──
        $latestAssessmentIds = DB::table('vendor_due_diligence_assessments as a')
            ->select(DB::raw('MAX(a.run_at) as max_run'), 'a.vendor_id')
            ->groupBy('a.vendor_id');
        $riskRows = DB::table('vendor_due_diligence_assessments as a')
            ->joinSub($latestAssessmentIds, 'latest', function ($join) {
                $join->on('a.vendor_id', '=', 'latest.vendor_id')->on('a.run_at', '=', 'latest.max_run');
            })
            ->select('a.overall_recommendation', DB::raw('COUNT(*) as total'))
            ->groupBy('a.overall_recommendation')->pluck('total', 'overall_recommendation');
        $riskApprove       = (int) ($riskRows['APPROVE'] ?? 0);
        $riskReview        = (int) ($riskRows['APPROVE_WITH_REVIEW'] ?? 0);
        $riskEscalate      = (int) ($riskRows['HIGH_RISK_ESCALATE'] ?? 0);

        // ── Rebate Offers — Task #64. ──
        $rebateActive   = DB::table('vendor_rebate_offers')->where('is_active', true)->count();
        $rebateInactive = DB::table('vendor_rebate_offers')->where('is_active', false)->count();

        // ── Vendors by Entity Type — real SSM classification breakdown. ──
        $byEntityType = DB::table('vendors')
            ->where('is_active', true)
            ->select(DB::raw("COALESCE(entity_type,'Unspecified') as entity_type"), DB::raw('COUNT(*) as total'))
            ->groupBy('entity_type')->orderByDesc('total')->get();

        // ── Top 5 Vendors by Sales Volume — real transactional data,
        // company-wide (never a per-agent view, hence no scoping needed). ──
        $topVendorsBySales = DB::table('sales_transactions as st')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->where('st.is_deleted', false)
            ->select('v.vendor_id', 'v.vendor_name', DB::raw('SUM(st.premium_amount) as total'), DB::raw('COUNT(*) as tx_count'))
            ->groupBy('v.vendor_id', 'v.vendor_name')
            ->orderByDesc('total')->limit(5)->get();

        return view('admin.vendor-kpi.index', compact(
            'totalVendors', 'activeLogins', 'pendingLogins', 'awaitingPwd', 'restrictedLogins', 'rejectedLogins',
            'docPending', 'docVerified', 'docRejected',
            'riskApprove', 'riskReview', 'riskEscalate',
            'rebateActive', 'rebateInactive',
            'byEntityType', 'topVendorsBySales'
        ));
    }
}
