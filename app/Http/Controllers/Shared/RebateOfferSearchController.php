<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// NEW 9 Aug 2026 — per Chris: every logged-in affiliate (GL/TL/Introducer,
// and Admin too) can search active vendor rebate offers. Deliberately
// shows ONLY vendor name, product, and the rebate details — no address,
// no vendor contact info, per Chris's explicit instruction. An agent who
// wants more goes through Admin (Help Desk) or asks Carolyn, never
// straight to the vendor's own contact details.
class RebateOfferSearchController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $query = DB::table('vendor_rebate_offers as o')
            ->join('vendors as v', 'o.vendor_id', '=', 'v.vendor_id')
            ->leftJoin('products as p', 'o.product_id', '=', 'p.product_id')
            ->where('o.is_active', true)
            ->where('v.is_active', true)
            ->where(function ($w) {
                $w->whereNull('o.valid_until')->orWhere('o.valid_until', '>=', now()->toDateString());
            })
            ->select('o.rebate_offer_id', 'o.vendor_id', 'o.rebate_program_number', 'o.rebate_details', 'o.valid_until', 'v.vendor_name', 'v.industry', 'p.product_name');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('v.vendor_name', 'like', "%{$q}%")
                  ->orWhere('p.product_name', 'like', "%{$q}%")
                  ->orWhere('o.rebate_details', 'like', "%{$q}%");
            });
        }

        $offers = $query->orderBy('v.vendor_name')->paginate(6);

        return view('rebate-offers.search', compact('offers', 'q'));
    }

    // NEW — type-ahead suggestions for the search box above, matching
    // vendor name and product name (rebate_details is free text, too
    // long to show as a suggestion).
    public function typeahead(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 1) {
            return response()->json([]);
        }
        $needle = '%'.$q.'%';

        $results = DB::table('vendor_rebate_offers as o')
            ->join('vendors as v', 'o.vendor_id', '=', 'v.vendor_id')
            ->leftJoin('products as p', 'o.product_id', '=', 'p.product_id')
            ->where('o.is_active', true)
            ->where('v.is_active', true)
            ->where(function ($w) {
                $w->whereNull('o.valid_until')->orWhere('o.valid_until', '>=', now()->toDateString());
            })
            ->where(function ($w) use ($needle) {
                $w->where('v.vendor_name', 'like', $needle)->orWhere('p.product_name', 'like', $needle);
            })
            ->orderBy('v.vendor_name')->limit(15)
            ->get(['v.vendor_name', 'p.product_name']);

        return response()->json($results);
    }
}
