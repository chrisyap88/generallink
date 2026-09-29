<?php

namespace App\Http\Controllers\TL;

use App\Http\Controllers\Controller;
use App\Services\DataScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $agent = auth('agent')->user();
        $scope = new DataScopeService();
        $agentIds = $scope->getAgentIds();

        $summary = DB::table('sales_transactions as st')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->whereIn('st.agent_id', $agentIds)
            ->where('st.is_deleted', 0)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN st.status = "ACTIVE" THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN st.status = "PENDING_RENEWAL" THEN 1 ELSE 0 END) as pending_renewal,
                SUM(CASE WHEN st.status = "LAPSED" THEN 1 ELSE 0 END) as lapsed,
                SUM(st.premium_amount) as total_premium
            ')
            ->first();

        $byProductType = DB::table('sales_transactions as st')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->whereIn('st.agent_id', $agentIds)
            ->where('st.is_deleted', 0)
            ->selectRaw('p.product_type, COUNT(*) as total, SUM(st.premium_amount) as total_premium')
            ->groupBy('p.product_type')
            ->orderByDesc('total_premium')
            ->get();

        $vendors = DB::table('vendors')
            ->where('is_active', 1)
            ->orderBy('vendor_name')
            ->pluck('vendor_name');

        $query = DB::table('sales_transactions as st')
            ->join('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->leftJoin('commission_transactions as ct', function($join) use ($agent) {
                $join->on('ct.policy_id', '=', 'st.policy_id')
                     ->where('ct.agent_id', '=', $agent->agent_id);
            })
            ->whereIn('st.agent_id', $agentIds)
            ->where('st.is_deleted', 0)
            ->select(
                'st.policy_id',
                'st.policy_number',
                'st.premium_amount',
                'st.sum_insured',
                'st.coverage_start',
                'st.coverage_end',
                'st.status',
                'st.created_at',
                'c.full_name as customer_name',
                'p.product_name',
                'p.product_type',
                'v.vendor_name',
                'a.full_name as agent_name',
                'a.agent_code',
                DB::raw('COALESCE(SUM(ct.commission_amount), 0) as my_commission'),
                DB::raw('COALESCE(MAX(ct.entitlement_pct), 0) as my_pct')
            )
            ->groupBy(
                'st.policy_id','st.policy_number','st.premium_amount','st.sum_insured',
                'st.coverage_start','st.coverage_end','st.status','st.created_at',
                'c.full_name','p.product_name','p.product_type','v.vendor_name',
                'a.full_name','a.agent_code'
            );

        if ($request->filled('status')) {
            $query->where('st.status', $request->status);
        }
        if ($request->filled('vendor')) {
            $query->where('v.vendor_name', $request->vendor);
        }
        if ($request->filled('product_type')) {
            $query->where('p.product_type', $request->product_type);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('st.policy_number', 'like', "%{$search}%")
                  ->orWhere('c.full_name', 'like', "%{$search}%");
            });
        }

        $grandTotal = DB::table('sales_transactions as st')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->join('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->whereIn('st.agent_id', $agentIds)
            ->where('st.is_deleted', 0)
            ->when($request->filled('status'), fn($q) => $q->where('st.status', $request->status))
            ->when($request->filled('vendor'), fn($q) => $q->where('v.vendor_name', $request->vendor))
            ->when($request->filled('product_type'), fn($q) => $q->where('p.product_type', $request->product_type))
            ->when($request->filled('search'), function($q) use ($request) {
                $search = $request->search;
                $q->where(function ($q2) use ($search) {
                    $q2->where('st.policy_number', 'like', "%{$search}%")
                       ->orWhere('c.full_name', 'like', "%{$search}%");
                });
            })
            ->sum('st.premium_amount');

        $commissionGrandTotal = DB::table('commission_transactions as ct')
            ->join('sales_transactions as st', 'ct.policy_id', '=', 'st.policy_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->join('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->where('ct.agent_id', $agent->agent_id)
            ->whereIn('st.agent_id', $agentIds)
            ->where('st.is_deleted', 0)
            ->when($request->filled('status'), fn($q) => $q->where('st.status', $request->status))
            ->when($request->filled('vendor'), fn($q) => $q->where('v.vendor_name', $request->vendor))
            ->when($request->filled('product_type'), fn($q) => $q->where('p.product_type', $request->product_type))
            ->when($request->filled('search'), function($q) use ($request) {
                $search = $request->search;
                $q->where(function ($q2) use ($search) {
                    $q2->where('st.policy_number', 'like', "%{$search}%")
                       ->orWhere('c.full_name', 'like', "%{$search}%");
                });
            })
            ->sum('ct.commission_amount');

        $transactions = $query->orderBy('st.created_at', 'desc')->paginate(10);

        return view('tl.transactions.index', compact(
            'agent', 'transactions', 'summary', 'byProductType', 'vendors', 'grandTotal', 'commissionGrandTotal'
        ));
    }

    public function show(Request $request, $policyId)
    {
        $agent = auth('agent')->user();
        $scope = new DataScopeService();
        $agentIds = $scope->getAgentIds();

        $txn = DB::table('sales_transactions as st')
            ->join('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->whereIn('st.agent_id', $agentIds)
            ->where('st.policy_id', $policyId)
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
                'st.customer_id',
                'c.full_name as customer_name',
                'p.product_name',
                'p.product_type',
                'v.vendor_name',
                'a.full_name as agent_name',
                'a.agent_code',
                'a.role as agent_role'
            )
            ->first();

        abort_if(!$txn, 404);

        $commissions = DB::table('commission_transactions as ct')
            ->join('agents as a', 'ct.agent_id', '=', 'a.agent_id')
            ->where('ct.policy_id', $policyId)
            ->where('ct.role_at_transaction', '!=', 'GROUP_LEADER')
            ->select(
                'ct.txn_id',
                'ct.agent_id',
                'ct.commission_amount',
                'ct.entitlement_pct',
                'ct.role_at_transaction',
                'ct.status',
                'a.full_name as agent_name',
                'a.agent_code'
            )
            ->get();

        $commissionSubtotal = $commissions->sum('commission_amount');

        return view('tl.transactions.show', compact('agent', 'txn', 'commissions', 'commissionSubtotal'));
    }
}
