<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RebateNumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 12 Aug 2026 — per Chris: Admin reviews vendor-submitted rebate
// program applications (see App\Http\Controllers\Vendor\
// VendorRebateApplicationController for the vendor-facing side).
// Approving one issues a rebate_program_number and creates/updates the
// matching live row in vendor_rebate_offers so agents see it in Rebate
// Offer Search immediately — every later revision of the same program
// keeps the SAME program number, just updates that one offer row.
class VendorRebateApplicationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'PENDING');

        $applications = DB::table('vendor_rebate_applications as a')
            ->join('vendors as v', 'a.vendor_id', '=', 'v.vendor_id')
            ->leftJoin('products as p', 'a.product_id', '=', 'p.product_id')
            ->whereNull('a.superseded_at')
            ->when($status !== 'ALL', fn ($q) => $q->where('a.status', $status))
            ->select('a.*', 'v.vendor_name', 'p.product_name')
            ->orderByDesc('a.created_at')
            ->paginate(5)
            ->withQueryString();

        return view('masterfile.vendor-rebate-applications', compact('applications', 'status'));
    }

    public function approve(string $applicationId)
    {
        $adminId = auth('agent')->user()->agent_id;

        $app = DB::table('vendor_rebate_applications')->where('application_id', $applicationId)->first();
        abort_if(!$app, 404);

        $programNumber = $app->rebate_program_number ?: RebateNumberingService::nextProgramNumber();

        DB::table('vendor_rebate_applications')->where('application_id', $applicationId)->update([
            'status' => 'APPROVED',
            'rebate_program_number' => $programNumber,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'rejection_reason' => null,
            'updated_at' => now(),
        ]);

        // Upsert the live agent-facing offer — same program number stays
        // attached to the same vendor_rebate_offers row across every
        // future revision of this application_number.
        $existingOffer = DB::table('vendor_rebate_offers')->where('rebate_program_number', $programNumber)->first();
        if ($existingOffer) {
            DB::table('vendor_rebate_offers')->where('rebate_offer_id', $existingOffer->rebate_offer_id)->update([
                'vendor_id' => $app->vendor_id,
                'product_id' => $app->product_id,
                'rebate_details' => $app->rebate_details,
                'valid_from' => $app->valid_from,
                'valid_until' => $app->valid_until,
                'is_active' => true,
                'source_application_id' => $applicationId,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('vendor_rebate_offers')->insert([
                'rebate_offer_id' => (string) Str::uuid(),
                'rebate_program_number' => $programNumber,
                'source_application_id' => $applicationId,
                'vendor_id' => $app->vendor_id,
                'product_id' => $app->product_id,
                'rebate_details' => $app->rebate_details,
                'valid_from' => $app->valid_from,
                'valid_until' => $app->valid_until,
                'is_active' => true,
                'created_by' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        \App\Services\AuditService::logChange('vendor_rebate_applications', $applicationId, 'VENDOR_REBATE_APPLICATION_APPROVED', null, ['rebate_program_number' => $programNumber], $adminId);

        return back()->with('success', 'Application ' . $app->application_number . ' approved — rebate program ' . $programNumber . ' is now live.');
    }

    public function reject(Request $request, string $applicationId)
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $adminId = auth('agent')->user()->agent_id;

        $app = DB::table('vendor_rebate_applications')->where('application_id', $applicationId)->first();
        abort_if(!$app, 404);

        DB::table('vendor_rebate_applications')->where('application_id', $applicationId)->update([
            'status' => 'REJECTED',
            'rejection_reason' => $request->reason,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Services\AuditService::logChange('vendor_rebate_applications', $applicationId, 'VENDOR_REBATE_APPLICATION_REJECTED', null, ['reason' => $request->reason], $adminId);

        return back()->with('success', 'Application ' . $app->application_number . ' rejected.');
    }
}
