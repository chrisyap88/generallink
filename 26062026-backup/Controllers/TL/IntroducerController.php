<?php

namespace App\Http\Controllers\TL;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IntroducerController extends Controller
{
    /**
     * List of Introducers under this TL — filterable by status / new-this-month
     */
    public function index(Request $request)
    {
        $agent = auth('agent')->user();

        $filter = $request->input('filter'); // active | inactive | new

        $query = DB::table('agents as i')
            ->where('i.parent_id', $agent->agent_id)
            ->where('i.role', 'INTRODUCER')
            ->where('i.is_deleted', false)
            ->leftJoin('sales_transactions as st', function($join) {
                $join->on('st.agent_id', '=', 'i.agent_id')
                     ->where('st.is_deleted', false)
                     ->whereMonth('st.created_at', now()->month)
                     ->whereYear('st.created_at', now()->year);
            })
            ->leftJoin('commission_transactions as ct', function($join) {
                $join->on('ct.agent_id', '=', 'i.agent_id')
                     ->whereMonth('ct.created_at', now()->month)
                     ->whereYear('ct.created_at', now()->year);
            })
            ->select(
                'i.agent_id',
                'i.full_name',
                'i.agent_code',
                'i.status',
                'i.created_at',
                DB::raw('COALESCE(SUM(DISTINCT st.premium_amount), 0) as sales_mtd'),
                DB::raw('COUNT(DISTINCT st.policy_id) as transactions_mtd'),
                DB::raw('COALESCE(SUM(DISTINCT ct.commission_amount), 0) as commission_mtd')
            )
            ->groupBy('i.agent_id', 'i.full_name', 'i.agent_code', 'i.status', 'i.created_at');

        switch ($filter) {
            case 'active':
                $query->where('i.status', 'ACTIVE');
                break;
            case 'inactive':
                $query->where('i.status', '!=', 'ACTIVE');
                break;
            case 'new':
                $query->whereMonth('i.created_at', now()->month)
                      ->whereYear('i.created_at', now()->year);
                break;
        }

        $introducers = $query->orderBy('i.full_name')->paginate(10, ['*'], 'page')->withQueryString();

        $filterLabels = [
            'active'   => 'Active Introducers',
            'inactive' => 'Inactive Introducers',
            'new'      => 'New Introducers This Month',
        ];
        $heading = $filterLabels[$filter] ?? 'My Introducers';

        return view('tl.introducers.index', compact('agent', 'introducers', 'filter', 'heading'));
    }

    /**
     * Transaction history for a single Introducer under this TL
     */
    public function transactions(Request $request, $introId)
    {
        $agent = auth('agent')->user();

        $intro = DB::table('agents')
            ->where('agent_id', $introId)
            ->where('parent_id', $agent->agent_id)
            ->where('role', 'INTRODUCER')
            ->first();

        abort_if(!$intro, 404);

        $summary = DB::table('sales_transactions as st')
            ->where('st.agent_id', $introId)
            ->where('st.is_deleted', 0)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN st.status = "ACTIVE" THEN 1 ELSE 0 END) as active,
                SUM(st.premium_amount) as total_premium
            ')
            ->first();

        $commissionEarned = DB::table('commission_transactions as ct')
            ->join('sales_transactions as st', 'ct.policy_id', '=', 'st.policy_id')
            ->where('ct.agent_id', $agent->agent_id)
            ->where('st.agent_id', $introId)
            ->where('st.is_deleted', 0)
            ->sum('ct.commission_amount');

        $grandTotal = DB::table('sales_transactions')
            ->where('agent_id', $introId)
            ->where('is_deleted', 0)
            ->sum('premium_amount');

        $transactions = DB::table('sales_transactions as st')
            ->join('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->leftJoin('commission_transactions as ct', function($join) use ($agent) {
                $join->on('ct.policy_id', '=', 'st.policy_id')
                     ->where('ct.agent_id', '=', $agent->agent_id);
            })
            ->where('st.agent_id', $introId)
            ->where('st.is_deleted', 0)
            ->select(
                'st.policy_id', 'st.policy_number', 'st.premium_amount', 'st.status', 'st.created_at',
                'st.coverage_start', 'st.coverage_end',
                'c.full_name as customer_name',
                'p.product_name', 'p.product_type', 'v.vendor_name',
                DB::raw('COALESCE(SUM(ct.commission_amount), 0) as my_commission'),
                DB::raw('COALESCE(MAX(ct.entitlement_pct), 0) as my_pct')
            )
            ->groupBy(
                'st.policy_id','st.policy_number','st.premium_amount','st.status','st.created_at',
                'st.coverage_start','st.coverage_end','c.full_name','p.product_name','p.product_type','v.vendor_name'
            )
            ->orderBy('st.created_at', 'desc')
            ->paginate(10, ['*'], 'page')
            ->withQueryString();

        $commissionGrandTotal = $commissionEarned;

        return view('tl.introducers.transactions', compact('agent', 'intro', 'summary', 'commissionEarned', 'transactions', 'grandTotal', 'commissionGrandTotal'));
    }
}
