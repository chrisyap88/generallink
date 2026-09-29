<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 9 Aug 2026 — per Chris: Admin-managed vendor rebate offers, so
// agents have a real (not fake/placeholder) directory to search for deals
// to offer customers. Deliberately Admin-created, not vendor-submitted —
// that flow (the old Offer Request feature) is exactly what Chris scrapped
// 8 Aug 2026 for being confusing; this is the simpler replacement.
class VendorRebateOfferController extends Controller
{
    public function index(Request $request)
    {
        $offers = DB::table('vendor_rebate_offers as o')
            ->join('vendors as v', 'o.vendor_id', '=', 'v.vendor_id')
            ->leftJoin('products as p', 'o.product_id', '=', 'p.product_id')
            ->orderByDesc('o.created_at')
            ->select('o.*', 'v.vendor_name', 'p.product_name')
            ->paginate(6);

        return view('masterfile.vendor-rebate-offers', compact('offers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'vendor_id'     => ['required', 'exists:vendors,vendor_id'],
            'product_id'    => ['nullable', 'exists:products,product_id'],
            'rebate_details' => ['required', 'string', 'max:2000'],
            'valid_from'    => ['nullable', 'date'],
            'valid_until'   => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        $offerId = (string) Str::uuid();
        DB::table('vendor_rebate_offers')->insert([
            'rebate_offer_id' => $offerId,
            'vendor_id'       => $request->vendor_id,
            'product_id'      => $request->product_id ?: null,
            'rebate_details'  => $request->rebate_details,
            'valid_from'      => $request->valid_from,
            'valid_until'     => $request->valid_until,
            'is_active'       => true,
            'created_by'      => auth('agent')->user()->agent_id,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        \App\Services\AuditService::logChange('vendor_rebate_offers', $offerId, 'REBATE_OFFER_CREATED', null, $request->only(['vendor_id', 'product_id', 'rebate_details']), auth('agent')->user()->agent_id);

        return redirect()->route('admin.masterfile.rebate-offers')->with('success', 'Rebate offer added.');
    }

    public function toggle(string $rebateOfferId)
    {
        $offer = DB::table('vendor_rebate_offers')->where('rebate_offer_id', $rebateOfferId)->first();
        if (!$offer) {
            abort(404);
        }
        DB::table('vendor_rebate_offers')->where('rebate_offer_id', $rebateOfferId)->update(['is_active' => !$offer->is_active, 'updated_at' => now()]);

        \App\Services\AuditService::logChange('vendor_rebate_offers', $rebateOfferId, 'REBATE_OFFER_TOGGLED', ['is_active' => $offer->is_active], ['is_active' => !$offer->is_active], auth('agent')->user()->agent_id);

        return back()->with('success', 'Rebate offer ' . (!$offer->is_active ? 'activated' : 'deactivated') . '.');
    }
}
