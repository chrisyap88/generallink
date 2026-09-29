<?php

namespace App\Http\Controllers\GL;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $agent = auth('agent')->user();

        // Get all agent IDs under this GL's group
        $agentIds = DB::table('agents')
            ->where('group_id', $agent->group_id)
            ->where('is_deleted', 0)
            ->pluck('agent_id');

        $query = DB::table('customers as c')
            ->join('agents as a', 'c.owned_by_agent_id', '=', 'a.agent_id')
            ->whereIn('c.owned_by_agent_id', $agentIds)
            ->where('c.is_deleted', 0)
            ->select(
                'c.customer_id',
                'c.full_name',
                'c.email',
                'c.phone',
                'c.city',
                'c.state',
                'c.created_at',
                'a.full_name as agent_name',
                'a.agent_code',
                'a.role as agent_role'
            );

        $hasQuery = $request->filled('search') || $request->filled('state') || $request->filled('agent_role') || $request->filled('sort') || $request->filled('page');

        if (!$hasQuery) {
            $customers = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);
            $summary = (object)['total' => 0, 'states_covered' => 0];
            $states = DB::table('customers as c')
                ->whereIn('c.owned_by_agent_id', $agentIds)
                ->where('c.is_deleted', 0)
                ->whereNotNull('c.state')
                ->distinct()
                ->orderBy('c.state')
                ->pluck('c.state');
            $sort = 'created_desc';
            return view('gl.customers.index', compact('customers', 'summary', 'states', 'sort', 'hasQuery'));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('c.full_name', 'like', "%{$search}%")
                  ->orWhere('c.email', 'like', "%{$search}%")
                  ->orWhere('c.phone', 'like', "%{$search}%");
            });
        }

        // Filter by state
        if ($request->filled('state')) {
            $query->where('c.state', $request->state);
        }

        // Filter by agent role
        if ($request->filled('agent_role')) {
            $query->where('a.role', $request->agent_role);
        }

        // Sort
        $sort = $request->input('sort', 'created_desc');
        switch ($sort) {
            case 'name_asc':
                $query->orderBy('c.full_name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('c.full_name', 'desc');
                break;
            case 'state_asc':
                $query->orderBy('c.state', 'asc')->orderBy('c.full_name', 'asc');
                break;
            case 'owner_asc':
                $query->orderBy('a.full_name', 'asc')->orderBy('c.full_name', 'asc');
                break;
            case 'created_asc':
                $query->orderBy('c.created_at', 'asc');
                break;
            default: // created_desc
                $query->orderBy('c.created_at', 'desc');
                break;
        }

        $customers = $query->paginate(10)->appends($request->query());

        // Summary
        $summary = DB::table('customers as c')
            ->whereIn('c.owned_by_agent_id', $agentIds)
            ->where('c.is_deleted', 0)
            ->selectRaw('
                COUNT(*) as total,
                COUNT(DISTINCT c.state) as states_covered
            ')
            ->first();

        // States for filter
        $states = DB::table('customers as c')
            ->whereIn('c.owned_by_agent_id', $agentIds)
            ->where('c.is_deleted', 0)
            ->whereNotNull('c.state')
            ->distinct()
            ->orderBy('c.state')
            ->pluck('c.state');

        return view('gl.customers.index', compact('customers', 'summary', 'states', 'sort', 'hasQuery'));
    }

    public function show($customerId)
    {
        $agent = auth('agent')->user();

        $agentIds = DB::table('agents')
            ->where('group_id', $agent->group_id)
            ->where('is_deleted', 0)
            ->pluck('agent_id');

        $customer = DB::table('customers as c')
            ->join('agents as a', 'c.owned_by_agent_id', '=', 'a.agent_id')
            ->whereIn('c.owned_by_agent_id', $agentIds)
            ->where('c.customer_id', $customerId)
            ->where('c.is_deleted', 0)
            ->select(
                'c.*',
                'a.full_name as agent_name',
                'a.agent_code',
                'a.role as agent_role'
            )
            ->first();

        abort_if(!$customer, 404);

        // Customer's transactions
        $transactions = DB::table('sales_transactions as st')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->where('st.customer_id', $customerId)
            ->where('st.is_deleted', 0)
            ->select(
                'st.policy_id',
                'st.policy_number',
                'st.premium_amount',
                'st.sum_insured',
                'st.coverage_start',
                'st.coverage_end',
                'st.renewal_date',
                'st.status',
                'st.created_at',
                'p.product_name',
                'p.product_type',
                'p.product_code',
                'v.vendor_name'
            )
            ->orderBy('st.created_at', 'desc')
            ->get();

        // Customer's claims
        $claims = DB::table('claims as cl')
            ->join('products as p', 'cl.product_id', '=', 'p.product_id')
            ->join('vendors as v', 'cl.vendor_id', '=', 'v.vendor_id')
            ->where('cl.customer_id', $customerId)
            ->where('cl.is_deleted', 0)
            ->select(
                'cl.claim_id',
                'cl.claim_reference',
                'cl.claim_amount',
                'cl.approved_amount',
                'cl.status',
                'cl.claim_upload_date',
                'cl.commission_held',
                'p.product_name',
                'v.vendor_name'
            )
            ->orderBy('cl.claim_upload_date', 'desc')
            ->get();

        return view('gl.customers.show', compact('customer', 'transactions', 'claims'));
    }

    public function edit($customerId)
    {
        $agent = auth('agent')->user();

        $agentIds = DB::table('agents')
            ->where('group_id', $agent->group_id)
            ->where('is_deleted', 0)
            ->pluck('agent_id');

        $customer = DB::table('customers')
            ->whereIn('owned_by_agent_id', $agentIds)
            ->where('customer_id', $customerId)
            ->where('is_deleted', 0)
            ->first();

        abort_if(!$customer, 404);

        return view('gl.customers.edit', compact('customer'));
    }

    public function update(Request $request, $customerId)
    {
        $agent = auth('agent')->user();

        $agentIds = DB::table('agents')
            ->where('group_id', $agent->group_id)
            ->where('is_deleted', 0)
            ->pluck('agent_id');

        $customer = DB::table('customers')
            ->whereIn('owned_by_agent_id', $agentIds)
            ->where('customer_id', $customerId)
            ->where('is_deleted', 0)
            ->first();

        abort_if(!$customer, 404);

        $request->validate([
            'email'    => 'nullable|email|max:200',
            'phone'    => 'required|string|max:20',
            'address'  => 'nullable|string',
            'postcode' => 'nullable|string|max:10',
            'city'     => 'nullable|string|max:100',
            'state'    => 'nullable|string|max:100',
        ]);

        // Full name and NRIC are locked — only Admin can change
        DB::table('customers')
            ->where('customer_id', $customerId)
            ->update([
                'email'      => $request->email,
                'phone'      => $request->phone,
                'address'    => $request->address,
                'postcode'   => $request->postcode,
                'city'       => $request->city,
                'state'      => $request->state,
                'updated_by' => $agent->agent_id,
                'updated_at' => now(),
            ]);

        return redirect()->route('gl.customers.show', $customerId)
            ->with('success', 'Customer updated successfully.');
    }
}
