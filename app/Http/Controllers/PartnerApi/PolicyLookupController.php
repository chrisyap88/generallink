<?php

namespace App\Http\Controllers\PartnerApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// NEW 5 Aug 2026 — Outbound Partner API, Phase 1's one real endpoint.
// Deliberately read-only and deliberately narrow: an outside system
// (insurance vendor, hotel PMS, customer ERP/POS) can look up a policy
// by its policy number and get back status/coverage info — nothing
// that lets them create, change, or delete anything in GeneralLink, and
// no customer NRIC/phone (PII minimization for a first pass talking to
// systems Chris hasn't vetted yet). Write endpoints are intentionally
// not built until a specific partner and specific action are named.
class PolicyLookupController extends Controller
{
    public function show(Request $request, string $policyNumber)
    {
        $policy = DB::table('sales_transactions as st')
            ->join('vendors as v', 'v.vendor_id', '=', 'st.vendor_id')
            ->join('products as p', 'p.product_id', '=', 'st.product_id')
            ->leftJoin('customers as c', 'c.customer_id', '=', 'st.customer_id')
            ->where('st.policy_number', $policyNumber)
            ->where('st.is_deleted', false)
            ->select([
                'st.policy_number', 'st.status', 'st.premium_amount', 'st.sum_insured',
                'st.coverage_start', 'st.coverage_end', 'st.renewal_date',
                'v.vendor_name', 'p.product_name',
                'c.full_name as customer_name',
            ])
            ->first();

        if (!$policy) {
            return response()->json(['error' => 'No policy found with that policy number.'], 404);
        }

        return response()->json(['policy' => $policy]);
    }
}
