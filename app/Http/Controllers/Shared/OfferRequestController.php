<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 8 Aug 2026 (Task #93/#94) — offer submission, reachable from BOTH
// the Vendor Portal (auth:vendor) and an agent's own dashboard
// (auth:agent) — same form, same table (offer_requests), same rules.
// One controller instead of two near-duplicates, since the only real
// difference is WHO is submitting (resolved once via submitter() below).
class OfferRequestController extends Controller
{
    /** @return array{type:string, id:string, vendor_id:?string, vendor_locked:bool} */
    private function submitter(): array
    {
        if (Auth::guard('vendor')->check()) {
            $vendor = Auth::guard('vendor')->user();
            return ['type' => 'VENDOR', 'id' => $vendor->vendor_id, 'vendor_id' => $vendor->vendor_id, 'vendor_locked' => true];
        }
        $agent = Auth::guard('agent')->user();
        return ['type' => 'AGENT', 'id' => $agent->agent_id, 'vendor_id' => null, 'vendor_locked' => false];
    }

    public function create()
    {
        $submitter = $this->submitter();

        $vendors = $submitter['vendor_locked']
            ? null
            : DB::table('vendors')->where('is_active', true)->orderBy('vendor_name')->get(['vendor_id', 'vendor_name', 'vendor_code']);

        return view('offer-requests.create', [
            'submitter' => $submitter,
            'vendors'   => $vendors,
            'isVendor'  => $submitter['type'] === 'VENDOR',
        ]);
    }

    public function store(Request $request)
    {
        $submitter = $this->submitter();

        $rules = [
            'title'       => ['required', 'string', 'max:150'],
            'body'        => ['required', 'string', 'max:3000'],
            'expiry_date' => ['required', 'date', 'after_or_equal:today'],
            'attachment'  => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ];
        if (!$submitter['vendor_locked']) {
            $rules['vendor_id'] = ['required', 'exists:vendors,vendor_id'];
        }
        $request->validate($rules);

        $vendorId = $submitter['vendor_locked'] ? $submitter['vendor_id'] : $request->input('vendor_id');

        // Same duplicate-guard the Admin approval screen also checks —
        // catching it here saves a submitter from waiting days for a
        // rejection they could've avoided immediately.
        $hasActiveOffer = DB::table('offer_requests as o')
            ->join('notices as n', 'n.notice_id', '=', 'o.notice_id')
            ->where('o.vendor_id', $vendorId)
            ->where('o.status', 'APPROVED')
            ->where('n.is_deleted', false)
            ->where(function ($q) { $q->whereNull('n.expires_at')->orWhere('n.expires_at', '>=', now()->toDateString()); })
            ->exists();
        if ($hasActiveOffer) {
            return back()->withErrors(['vendor_id' => 'This vendor already has a live offer running. Wait for it to expire, or ask Admin to remove it, before submitting a new one.'])->withInput();
        }

        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('offer-request-attachments', 'local');
            $attachmentName = $file->getClientOriginalName();
        }

        DB::table('offer_requests')->insert([
            'request_id'             => (string) Str::uuid(),
            'vendor_id'               => $vendorId,
            'submitted_by_type'       => $submitter['type'],
            'submitted_by_vendor_id'  => $submitter['type'] === 'VENDOR' ? $submitter['id'] : null,
            'submitted_by_agent_id'   => $submitter['type'] === 'AGENT' ? $submitter['id'] : null,
            'title'                   => $request->input('title'),
            'body'                    => $request->input('body'),
            'expiry_date'             => $request->input('expiry_date'),
            'attachment_file_path'    => $attachmentPath,
            'attachment_file_name'    => $attachmentName,
            'status'                  => 'PENDING',
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);

        $redirectRoute = $submitter['type'] === 'VENDOR' ? 'vendor.portal.my-offers' : 'offer-requests.my';
        return redirect()->route($redirectRoute)->with('success', 'Offer submitted — Admin will review it shortly.');
    }

    public function myRequests()
    {
        $submitter = $this->submitter();

        $query = DB::table('offer_requests as o')
            ->join('vendors as v', 'v.vendor_id', '=', 'o.vendor_id')
            ->select('o.*', 'v.vendor_name');

        if ($submitter['type'] === 'VENDOR') {
            $query->where('o.submitted_by_vendor_id', $submitter['id']);
        } else {
            $query->where('o.submitted_by_agent_id', $submitter['id']);
        }

        $requests = $query->orderByDesc('o.created_at')->paginate(8, ['*'], 'orPage');

        $view = $submitter['type'] === 'VENDOR' ? 'vendor.my-offers' : 'offer-requests.my';
        return view($view, compact('requests'));
    }
}
