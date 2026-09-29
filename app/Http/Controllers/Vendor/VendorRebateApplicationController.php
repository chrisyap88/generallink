<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\RebateNumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 12 Aug 2026 — per Chris: proper redo of the vendor rebate program
// application flow (the old "Offer Request" feature was scrapped 8 Aug
// for being confusing). Vendor submits a proposed rebate program; the
// system auto-generates a submission number; if they resubmit the SAME
// program (e.g. after a rejection, or to update terms), it's tracked as
// a new version under the same submission number, never a disconnected
// duplicate.
class VendorRebateApplicationController extends Controller
{
    public function index(Request $request)
    {
        $vendor = Auth::guard('vendor')->user();

        // Only the CURRENT version of each program (superseded_at null)
        // — older versions are reachable via "View History", not shown
        // twice in the main list.
        $applications = DB::table('vendor_rebate_applications as a')
            ->leftJoin('products as p', 'a.product_id', '=', 'p.product_id')
            ->where('a.vendor_id', $vendor->vendor_id)
            ->whereNull('a.superseded_at')
            ->select('a.*', 'p.product_name')
            ->orderByDesc('a.created_at')
            ->paginate(4);

        $products = DB::table('products')->where('vendor_id', $vendor->vendor_id)->where('is_active', true)->orderBy('product_name')->get(['product_id', 'product_name']);

        return view('vendor.rebate-applications.index', compact('applications', 'products'));
    }

    /** All versions of one program, oldest first — reached via "View History". */
    public function history(Request $request, string $applicationNumber)
    {
        $vendor = Auth::guard('vendor')->user();

        // CHANGED 13 Aug 2026 per Chris: "incorporate prev and next... if
        // search result exceed to the screen display incorporate prev
        // and next." This used to ->get() every version at once into a
        // fixed-height overflow:hidden box — a program revised many times
        // would have its oldest versions silently invisible, no scroll
        // and no way to reach them. Paginated (6/page) with the same
        // bottom Prev/Next bar as every other list screen instead.
        $versions = DB::table('vendor_rebate_applications as a')
            ->leftJoin('products as p', 'a.product_id', '=', 'p.product_id')
            ->where('a.vendor_id', $vendor->vendor_id)
            ->where('a.application_number', $applicationNumber)
            ->select('a.*', 'p.product_name')
            ->orderBy('a.version_number')
            ->paginate(6);

        abort_if($versions->isEmpty(), 404);

        return view('vendor.rebate-applications.history', compact('versions', 'applicationNumber'));
    }

    public function store(Request $request)
    {
        $vendor = Auth::guard('vendor')->user();

        $request->validate([
            'product_id' => ['nullable', 'uuid', 'exists:products,product_id'],
            'rebate_details' => ['required', 'string', 'max:2000'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        $applicationId = (string) Str::uuid();
        $applicationNumber = RebateNumberingService::nextApplicationNumber();

        DB::table('vendor_rebate_applications')->insert([
            'application_id' => $applicationId,
            'application_number' => $applicationNumber,
            'vendor_id' => $vendor->vendor_id,
            'product_id' => $request->product_id ?: null,
            'version_number' => 1,
            'rebate_details' => $request->rebate_details,
            'valid_from' => $request->valid_from,
            'valid_until' => $request->valid_until,
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Services\AuditService::logChange('vendor_rebate_applications', $applicationId, 'VENDOR_REBATE_APPLICATION_SUBMITTED', null, ['application_number' => $applicationNumber, 'vendor_id' => $vendor->vendor_id]);

        return redirect()->route('vendor.rebate-applications.index')->with('success', 'Application ' . $applicationNumber . ' submitted for review.');
    }

    /** Resubmission of an existing program — same application_number, version + 1. Allowed after a REJECTED decision, or to propose updated terms on an already-APPROVED program. */
    public function revise(Request $request, string $applicationNumber)
    {
        $vendor = Auth::guard('vendor')->user();

        $current = DB::table('vendor_rebate_applications')
            ->where('vendor_id', $vendor->vendor_id)
            ->where('application_number', $applicationNumber)
            ->whereNull('superseded_at')
            ->first();
        abort_if(!$current, 404);

        $request->validate([
            'product_id' => ['nullable', 'uuid', 'exists:products,product_id'],
            'rebate_details' => ['required', 'string', 'max:2000'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        DB::table('vendor_rebate_applications')->where('application_id', $current->application_id)->update(['superseded_at' => now(), 'updated_at' => now()]);

        $newId = (string) Str::uuid();
        DB::table('vendor_rebate_applications')->insert([
            'application_id' => $newId,
            'application_number' => $applicationNumber,
            'vendor_id' => $vendor->vendor_id,
            'product_id' => $request->product_id ?: null,
            'version_number' => $current->version_number + 1,
            'rebate_details' => $request->rebate_details,
            'valid_from' => $request->valid_from,
            'valid_until' => $request->valid_until,
            'status' => 'PENDING',
            'rebate_program_number' => $current->rebate_program_number, // carried forward — same program, new terms
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Services\AuditService::logChange('vendor_rebate_applications', $newId, 'VENDOR_REBATE_APPLICATION_REVISED', null, ['application_number' => $applicationNumber, 'version_number' => $current->version_number + 1]);

        return redirect()->route('vendor.rebate-applications.index')->with('success', 'Revised version (v' . ($current->version_number + 1) . ') of ' . $applicationNumber . ' submitted for review.');
    }
}
