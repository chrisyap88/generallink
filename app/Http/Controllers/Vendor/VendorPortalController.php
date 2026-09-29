<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\PhoneNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// NEW 8 Aug 2026 (Task #93) — Vendor Portal home. Originally: a vendor's
// whole world here was submit-an-offer / see-its-status (the Offer
// Request feature). REMOVED 8 Aug 2026 per Chris: "remove everything ...
// i want to redo and revamp" — that feature and its offer_requests table
// are gone.
//
// REBUILT 8 Aug 2026 — Vendor Management Phase 1 (spec Section 12): real
// dashboard showing only what Phase 1 actually has — account/verification
// status. No Campaign/Rebate/Communication cards yet (those are Phase 3+
// and would be fake links if shown now, which is exactly what confused
// Chris about the old Offer Request build).
class VendorPortalController extends Controller
{
    public function index()
    {
        $vendor = Auth::guard('vendor')->user();

        return view('vendor.portal', compact('vendor'));
    }

    // NEW 8 Aug 2026 — Vendor Management Phase 1 (spec Section 10, Vendor
    // Profile). Restricted fields (legal identity — name, code, industry,
    // vendor type, SSM docs) are shown read-only with a "contact Admin"
    // note rather than a change-request ticket flow — the Communication
    // Centre that would properly route such a request doesn't exist until
    // a later phase (see design doc Section 13), so this is intentionally
    // simple rather than half-building a ticket system now.
    // UPDATED 9 Aug 2026 — per Chris ("does all this vendor information
    // store in there profile"): it did not. entity_type, nature_of_business,
    // Contact 2/3, and the uploaded documents list were collected at
    // registration but never surfaced back to the vendor afterward. Now
    // fetched and passed through so the vendor can actually see everything
    // that was captured about them.
    public function profile()
    {
        $vendor = Auth::guard('vendor')->user();
        $documents = DB::table('vendor_documents')->where('vendor_id', $vendor->vendor_id)->orderBy('created_at')->get();
        return view('vendor.profile', compact('vendor', 'documents'));
    }

    public function updateProfile(Request $request)
    {
        $vendor = Auth::guard('vendor')->user();

        // CHANGED 9 Aug 2026 — pic_name/pic_phone (Contact 1) moved to the
        // read-only "locked" block: Contact 1's email doubles as the login
        // ID, so it (and its paired name/phone) now needs the same
        // "contact Admin to change" protection as the rest of the vetted
        // registration identity, instead of being silently editable here.
        $request->validate([
            'vendor_phone'  => ['required', 'string', PhoneNumberService::rule()],
            'vendor_website' => ['nullable', 'string', 'max:200'],
            'fb_page_url'   => ['nullable', 'url', 'max:255'],
        ]);

        $before = DB::table('vendors')->where('vendor_id', $vendor->vendor_id)->first();

        DB::table('vendors')->where('vendor_id', $vendor->vendor_id)->update([
            'vendor_phone'  => PhoneNumberService::normalize($request->vendor_phone),
            'vendor_website' => $request->vendor_website,
            'fb_page_url'   => $request->fb_page_url,
            'updated_at'    => now(),
        ]);

        \App\Services\AuditService::logChange('vendors', $vendor->vendor_id, 'VENDOR_PROFILE_SELF_UPDATED', (array) $before, $request->only(['vendor_phone', 'vendor_website', 'fb_page_url']));

        return redirect()->route('vendor.profile')->with('success', 'Your profile has been updated.');
    }
}
