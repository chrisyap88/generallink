<?php

namespace App\Http\Controllers\Introducer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecruitController extends Controller
{
    public function index(Request $request)
    {
        $agent      = auth('agent')->user();
        $month      = (int)$request->input('month', now()->month);
        $year       = (int)$request->input('year', now()->year);
        $filter     = $request->input('filter');
        $searchName = $request->input('search_name', '');
        $searchCode = $request->input('search_code', '');
        $showAll    = $request->input('show_all', '');
        $hasFilter  = $showAll || $searchName || $searchCode || $filter;

        $filterLabels = ['active' => 'Active Recruits', 'inactive' => 'Inactive Recruits', 'new' => 'New Recruits This Month'];
        $heading = $filterLabels[$filter] ?? 'My Recruits';

        // Datalist for autocomplete
        $recruitList = DB::table('agents')
            ->where('parent_id', $agent->agent_id)
            ->where('is_deleted', false)
            ->orderByRaw('LENGTH(agent_code), agent_code')
            ->get(['full_name', 'agent_code']);

        if (!$hasFilter) {
            return view('introducer.recruits.index', compact('agent', 'filter', 'heading', 'recruitList', 'month', 'year'))
                ->with('recruits', new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10, 1))
                ->with('totalSalesAll', 0);
        }

        $query = DB::table('agents as i')
            ->where('i.parent_id', $agent->agent_id)
            ->where('i.is_deleted', false)
            ->select('i.agent_id', 'i.full_name', 'i.agent_code', 'i.status', 'i.created_at');

        switch ($filter) {
            case 'active':   $query->where('i.status', 'ACTIVE'); break;
            case 'inactive': $query->where('i.status', '!=', 'ACTIVE'); break;
            case 'new':      $query->whereMonth('i.created_at', $month)->whereYear('i.created_at', $year); break;
        }
        if ($searchName) $query->where('i.full_name', 'like', "%{$searchName}%");
        if ($searchCode) $query->where('i.agent_code', 'like', "%{$searchCode}%");

        $recruits = $query->orderByRaw('LENGTH(i.agent_code), i.agent_code')->paginate(10, ['*'], 'page')->withQueryString();

        // Enrich
        $ids = $recruits->pluck('agent_id')->toArray();
        $sD = DB::table('sales_transactions')->whereIn('agent_id', $ids)->where('is_deleted', false)
            ->whereMonth('created_at', $month)->whereYear('created_at', $year)
            ->select('agent_id', DB::raw('SUM(premium_amount) as s'), DB::raw('COUNT(policy_id) as t'))
            ->groupBy('agent_id')->get()->keyBy('agent_id');
        $eD = DB::table('commission_transactions')->whereIn('agent_id', $ids)->where('status', '!=', 'CANCELLED')
            ->whereMonth('created_at', $month)->whereYear('created_at', $year)
            ->select('agent_id', DB::raw('SUM(commission_amount) as e'))
            ->groupBy('agent_id')->get()->keyBy('agent_id');
        $recruits->getCollection()->transform(function($i) use ($sD, $eD) {
            $i->sales_mtd = (float)($sD[$i->agent_id]->s ?? 0);
            $i->txn_count = (int)($sD[$i->agent_id]->t ?? 0);
            $i->earn_mtd  = (float)($eD[$i->agent_id]->e ?? 0);
            return $i;
        });

        $totalQuery = DB::table('agents as i')
            ->where('i.parent_id', $agent->agent_id)->where('i.is_deleted', false)
            ->leftJoin('sales_transactions as st', function($join) use ($month, $year) {
                $join->on('st.agent_id', '=', 'i.agent_id')->where('st.is_deleted', false)
                     ->whereMonth('st.created_at', $month)->whereYear('st.created_at', $year);
            });
        if ($searchName) $totalQuery->where('i.full_name', 'like', "%{$searchName}%");
        if ($searchCode) $totalQuery->where('i.agent_code', 'like', "%{$searchCode}%");
        $totalSalesAll = (float)$totalQuery->sum('st.premium_amount');

        return view('introducer.recruits.index', compact('agent', 'recruits', 'filter', 'heading', 'totalSalesAll', 'recruitList', 'month', 'year'));
    }

    public function transactions(Request $request, $recruitId)
    {
        $agent  = auth('agent')->user();
        $month  = (int)$request->get('month', now()->month);
        $year   = (int)$request->get('year', now()->year);

        $recruit = DB::table('agents')->where('agent_id', $recruitId)->where('parent_id', $agent->agent_id)->where('is_deleted', false)->first();
        abort_if(!$recruit, 404);

        $transactions = DB::table('sales_transactions as st')
            ->join('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
            ->leftJoin('commission_transactions as ct', function($join) use ($recruitId) {
                $join->on('ct.policy_id', '=', 'st.policy_id')->where('ct.agent_id', '=', $recruitId);
            })
            ->where('st.agent_id', $recruitId)->where('st.is_deleted', 0)
            ->whereMonth('st.created_at', $month)->whereYear('st.created_at', $year)
            ->select('st.policy_id', 'st.policy_number', 'st.premium_amount', 'st.status', 'st.created_at',
                     'st.coverage_start', 'st.coverage_end', 'c.full_name as customer_name',
                     'p.product_name', 'p.product_type', 'v.vendor_name',
                     'a.full_name as agent_name', 'a.agent_code',
                     DB::raw('COALESCE(SUM(ct.commission_amount), 0) as my_commission'),
                     DB::raw('COALESCE(MAX(ct.entitlement_pct), 0) as my_pct'))
            ->groupBy('st.policy_id', 'st.policy_number', 'st.premium_amount', 'st.status', 'st.created_at',
                      'st.coverage_start', 'st.coverage_end', 'c.full_name', 'p.product_name',
                      'p.product_type', 'v.vendor_name', 'a.full_name', 'a.agent_code')
            ->orderBy('st.created_at', 'desc')
            ->paginate(10, ['*'], 'page')->withQueryString();

        $grandTotal = DB::table('sales_transactions')->where('agent_id', $recruitId)->where('is_deleted', 0)
            ->whereMonth('created_at', $month)->whereYear('created_at', $year)->sum('premium_amount');
        $commissionGrandTotal = DB::table('commission_transactions')->where('agent_id', $recruitId)
            ->where('status', '!=', 'CANCELLED')->whereMonth('created_at', $month)->whereYear('created_at', $year)->sum('commission_amount');

        $backUrl = route('introducer.recruits') . '?month=' . $month . '&year=' . $year
            . ($request->get('search_name') ? '&search_name=' . $request->get('search_name') : '')
            . ($request->get('search_code') ? '&search_code=' . $request->get('search_code') : '')
            . ($request->get('filter') ? '&filter=' . $request->get('filter') : '')
            . ($request->get('show_all') ? '&show_all=1' : '');
        $monthName = \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y');

        // Reuse TL intro_transactions blade with $intro variable
        $intro = $recruit;
        return view('introducer.recruits.transactions', compact('agent', 'intro', 'transactions', 'grandTotal', 'commissionGrandTotal', 'backUrl', 'month', 'year', 'monthName'));
    }
}
