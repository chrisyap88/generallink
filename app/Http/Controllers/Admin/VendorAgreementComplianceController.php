<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * NEW 14 Aug 2026 — per Chris: "there must be a program to retrieve all
 * past email OTP records to comply the Malaysia's Electronic Commerce
 * Act 2006." A dedicated, Admin-wide register of every vendor's
 * Registration Activation Agreement acceptance — the same Clause 10.4
 * evidence trail already shown one-vendor-at-a-time on the Vendor
 * Registration Detail screen, but here searchable/browsable across ALL
 * vendors, plus the full append-only OTP send/verify history (see
 * vendor_agreement_otp_log) behind each one — not just the latest
 * attempt.
 */
class VendorAgreementComplianceController extends Controller
{
    /** Every vendor that has actually accepted — newest first, paginated per the no-scroll house rule. */
    public function index()
    {
        $acceptances = DB::table('vendor_agreement_acceptances as a')
            ->join('vendors as v', 'v.vendor_id', '=', 'a.vendor_id')
            ->whereNotNull('a.accepted_at')
            ->orderByDesc('a.accepted_at')
            ->select('a.acceptance_id', 'a.agreement_number', 'a.accepted_by_name', 'a.accepted_by_email', 'a.accepted_at', 'v.vendor_name')
            ->paginate(8);

        return view('admin.vendors.agreement-compliance-index', compact('acceptances'));
    }

    /** One vendor's full evidence record + complete OTP event history. */
    public function show(string $acceptanceId)
    {
        $acceptance = DB::table('vendor_agreement_acceptances as a')
            ->join('vendors as v', 'v.vendor_id', '=', 'a.vendor_id')
            ->where('a.acceptance_id', $acceptanceId)
            ->select('a.*', 'v.vendor_name', 'v.vendor_id')
            ->firstOrFail();

        $otpLog = DB::table('vendor_agreement_otp_log')
            ->where('vendor_id', $acceptance->vendor_id)
            ->orderBy('created_at')
            ->get();

        return view('admin.vendors.agreement-compliance-show', compact('acceptance', 'otpLog'));
    }
}
