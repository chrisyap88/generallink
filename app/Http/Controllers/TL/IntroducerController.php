<?php

namespace App\Http\Controllers\TL;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IntroducerController extends Controller
{
    /**
     * List of Introducers under this TL — filterable by status / new-this-month / name / code
     */
    public function index(Request $request)
    {
        $agent = auth('agent')->user();

        $filter     = $request->input('filter');
        $searchName = $request->input('search_name', '');
        $searchCode = $request->input('search_code', '');
        $showAll    = $request->input('show_all', '');
        $month      = (int)$request->input('month', now()->month);
        $year       = (int)$request->input('year', now()->year);

        $hasFilter = $showAll || $searchName || $searchCode || $filter;

        // Get promoted TL IDs — agents with sub-agents OR already TEAM_LEADER
        $directIds = DB::table('agents')->where('parent_id', $agent->agent_id)->where('is_deleted', false)->pluck('agent_id')->toArray();
        $hasSubIds = empty($directIds) ? [] : DB::table('agents')->whereIn('parent_id', $directIds)->where('is_deleted', false)->distinct()->pluck('parent_id')->toArray();
        $tlIds = DB::table('agents')->where('parent_id', $agent->agent_id)->where('role', 'TEAM_LEADER')->where('is_deleted', false)->pluck('agent_id')->toArray();
        $promotedTLIds = array_unique(array_merge($hasSubIds, $tlIds));

        $introList = DB::table('agents')
            ->where('parent_id', $agent->agent_id)
            ->where('role', 'INTRODUCER')
            ->where('is_deleted', false)
            ->whereNotIn('agent_id', $promotedTLIds)
            ->orderByRaw('LENGTH(agent_code), agent_code')
            ->get(['full_name', 'agent_code']);

        $filterLabels = [
            'active'   => 'Active Introducers',
            'inactive' => 'Inactive Introducers',
            'new'      => 'New Introducers This Month',
        ];
        $heading = $filterLabels[$filter] ?? 'My Introducers';

        // Return empty state if no filter applied
        if (!$hasFilter) {
            return view('tl.introducers.index', compact('agent', 'filter', 'heading', 'introList'))
                ->with('introducers', new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10, 1))
                ->with('totalSalesAll', 0);
        }

        $query = DB::table('agents as i')
            ->where('i.parent_id', $agent->agent_id)
            ->where('i.role', 'INTRODUCER')
            ->where('i.is_deleted', false)
            ->whereNotIn('i.agent_id', $promotedTLIds)
            ->select('i.agent_id', 'i.full_name', 'i.agent_code', 'i.status', 'i.created_at');

        switch ($filter) {
            case 'active':
                $query->where('i.status', 'ACTIVE');
                break;
            case 'inactive':
                $query->where('i.status', '!=', 'ACTIVE');
                break;
            case 'new':
                $query->whereMonth('i.created_at', $month)
                      ->whereYear('i.created_at', $year);
                break;
        }

        if ($searchName) $query->where('i.full_name', 'like', "%{$searchName}%");
        if ($searchCode) $query->where('i.agent_code', 'like', "%{$searchCode}%");

        $introducers = $query->orderByRaw('LENGTH(i.agent_code), i.agent_code')->paginate(10, ['*'], 'page')->withQueryString();

        // Enrich page with sales/earn
        $ids = $introducers->pluck('agent_id')->toArray();
        $sD = DB::table('sales_transactions')->whereIn('agent_id',$ids)->where('is_deleted',0)
            ->whereMonth('created_at',$month)->whereYear('created_at',$year)
            ->select('agent_id',DB::raw('SUM(premium_amount) as s'),DB::raw('COUNT(policy_id) as t'))
            ->groupBy('agent_id')->get()->keyBy('agent_id');
        $eD = DB::table('commission_transactions')->whereIn('agent_id',$ids)->where('status','!=','CANCELLED')
            ->whereMonth('created_at',$month)->whereYear('created_at',$year)
            ->select('agent_id',DB::raw('SUM(commission_amount) as e'))
            ->groupBy('agent_id')->get()->keyBy('agent_id');
        $introducers->getCollection()->transform(function($i) use ($sD,$eD) {
            $i->sales_mtd = (float)($sD[$i->agent_id]->s ?? 0);
            $i->transactions_mtd = (int)($sD[$i->agent_id]->t ?? 0);
            $i->commission_mtd = (float)($eD[$i->agent_id]->e ?? 0);
            return $i;
        });

        // Grand total across ALL matching records
        $totalQuery = DB::table('agents as i')
            ->where('i.parent_id', $agent->agent_id)
            ->where('i.role', 'INTRODUCER')
            ->where('i.is_deleted', false)
            ->whereNotIn('i.agent_id', $promotedTLIds)
            ->leftJoin('sales_transactions as st', function($join) use ($month, $year) {
                $join->on('st.agent_id', '=', 'i.agent_id')
                     ->where('st.is_deleted', false)
                     ->whereMonth('st.created_at', $month)
                     ->whereYear('st.created_at', $year);
            });
        switch ($filter) {
            case 'active': $totalQuery->where('i.status', 'ACTIVE'); break;
            case 'inactive': $totalQuery->where('i.status', '!=', 'ACTIVE'); break;
            case 'new': $totalQuery->whereMonth('i.created_at', now()->month)->whereYear('i.created_at', now()->year); break;
        }
        if ($searchName) $totalQuery->where('i.full_name', 'like', "%{$searchName}%");
        if ($searchCode) $totalQuery->where('i.agent_code', 'like', "%{$searchCode}%");
        $totalSalesAll = (float)$totalQuery->sum('st.premium_amount');

        return view('tl.introducers.index', compact('agent', 'introducers', 'filter', 'heading', 'totalSalesAll', 'introList'));
    }

    /**
     * Transaction history for a single Introducer under this TL
     */
    public function transactions(Request $request, $introId)
    {
        $agent  = auth('agent')->user();
        $month  = (int)$request->get('month', now()->month);
        $year   = (int)$request->get('year', now()->year);

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
                SUM(CASE WHEN st.status = "PENDING_RENEWAL" THEN 1 ELSE 0 END) as pending_renewal,
                SUM(st.premium_amount) as total_premium
            ')
            ->first();

        $commissionEarned = DB::table('commission_transactions as ct')
            ->join('sales_transactions as st', 'ct.policy_id', '=', 'st.policy_id')
            ->where('ct.agent_id', $introId)
            ->where('st.agent_id', $introId)
            ->where('st.is_deleted', 0)
            ->whereMonth('st.created_at', $month)
            ->whereYear('st.created_at', $year)
            ->sum('ct.commission_amount');

        $grandTotal = DB::table('sales_transactions')
            ->where('agent_id', $introId)
            ->where('is_deleted', 0)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->sum('premium_amount');

        $transactions = DB::table('sales_transactions as st')
            ->join('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
            ->leftJoin('commission_transactions as ct', function($join) use ($introId) {
                $join->on('ct.policy_id', '=', 'st.policy_id')
                     ->where('ct.agent_id', '=', $introId);
            })
            ->where('st.agent_id', $introId)
            ->where('st.is_deleted', 0)
            ->whereMonth('st.created_at', $month)
            ->whereYear('st.created_at', $year)
            ->select(
                'st.policy_id', 'st.policy_number', 'st.premium_amount', 'st.status', 'st.created_at',
                'st.coverage_start', 'st.coverage_end',
                'c.full_name as customer_name',
                'p.product_name', 'p.product_type', 'v.vendor_name',
                'a.full_name as agent_name', 'a.agent_code',
                DB::raw('COALESCE(SUM(ct.commission_amount), 0) as my_commission'),
                DB::raw('COALESCE(MAX(ct.entitlement_pct), 0) as my_pct')
            )
            ->groupBy(
                'st.policy_id','st.policy_number','st.premium_amount','st.status','st.created_at',
                'st.coverage_start','st.coverage_end','c.full_name','p.product_name','p.product_type',
                'v.vendor_name','a.full_name','a.agent_code'
            )
            ->orderBy('st.created_at', 'desc')
            ->paginate(10, ['*'], 'page')
            ->withQueryString();

        $byProductType = DB::table('sales_transactions as st')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->where('st.agent_id', $introId)
            ->where('st.is_deleted', 0)
            ->whereMonth('st.created_at', $month)
            ->whereYear('st.created_at', $year)
            ->select('p.product_type', DB::raw('COUNT(*) as total'), DB::raw('SUM(st.premium_amount) as total_premium'))
            ->groupBy('p.product_type')
            ->orderByDesc('total_premium')
            ->get();

        $vendors = DB::table('sales_transactions as st')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->where('st.agent_id', $introId)
            ->where('st.is_deleted', 0)
            ->whereMonth('st.created_at', $month)
            ->whereYear('st.created_at', $year)
            ->distinct()
            ->pluck('v.vendor_name');

        $commissionGrandTotal = $commissionEarned;

        $backUrl = route('tl.introducers') . '?month=' . $month . '&year=' . $year
            . ($request->get('search_name') ? '&search_name=' . $request->get('search_name') : '')
            . ($request->get('search_code') ? '&search_code=' . $request->get('search_code') : '')
            . ($request->get('filter') ? '&filter=' . $request->get('filter') : '')
            . ($request->get('show_all') ? '&show_all=1' : '');
        return view('tl.introducers.intro_transactions', compact('agent', 'intro', 'summary', 'commissionEarned', 'transactions', 'grandTotal', 'commissionGrandTotal', 'byProductType', 'vendors', 'month', 'year', 'backUrl'));
    }

    public function promotedTLs(Request $request)
    {
        $agent  = auth('agent')->user();
        $month  = (int)$request->get('month', now()->month);
        $year   = (int)$request->get('year', now()->year);

        // Get promoted TLs — any direct agent who has sub-agents OR is already TEAM_LEADER
        $directIds = DB::table('agents')
            ->where('parent_id', $agent->agent_id)
            ->where('is_deleted', false)
            ->pluck('agent_id')->toArray();

        // Those with sub-agents
        $hasSubIds = DB::table('agents')
            ->whereIn('parent_id', $directIds)
            ->where('is_deleted', false)
            ->distinct()->pluck('parent_id')->toArray();

        // Those who are already TEAM_LEADER
        $tlIds = DB::table('agents')
            ->where('parent_id', $agent->agent_id)
            ->where('role', 'TEAM_LEADER')
            ->where('is_deleted', false)
            ->pluck('agent_id')->toArray();

        $promotedTLIds = array_unique(array_merge($hasSubIds, $tlIds));

        $promotedTLs = DB::table('agents')
            ->whereIn('agent_id', $promotedTLIds)
            ->where('is_deleted', false)
            ->orderByRaw('LENGTH(agent_code), agent_code')
            ->get(['agent_id', 'full_name', 'agent_code', 'status', 'created_at']);

        // Enrich with sub-team sales
        $promotedTLs = $promotedTLs->map(function($ptl) use ($month, $year) {
            $subIds = DB::table('agents')->where(function($q) use ($ptl) {
                $q->where('agent_id', $ptl->agent_id)->orWhere('parent_id', $ptl->agent_id);
            })->where('is_deleted', false)->pluck('agent_id')->toArray();

            $ptl->sales_mtd = (float)DB::table('sales_transactions')
                ->whereIn('agent_id', $subIds)->where('is_deleted', false)
                ->whereMonth('created_at', $month)->whereYear('created_at', $year)
                ->sum('premium_amount');

            $ptl->earn_mtd = (float)DB::table('commission_transactions')
                ->whereIn('agent_id', $subIds)->where('status', '!=', 'CANCELLED')
                ->whereMonth('created_at', $month)->whereYear('created_at', $year)
                ->sum('commission_amount');

            $ptl->sub_count = DB::table('agents')
                ->where('parent_id', $ptl->agent_id)->where('is_deleted', false)->count();

            return $ptl;
        });

        $totalSales = $promotedTLs->sum('sales_mtd');
        $monthName  = \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y');

        return view('tl.promoted-tls.index', compact('agent', 'promotedTLs', 'totalSales', 'month', 'year', 'monthName'));
    }

    public function promotedTLTransactions(Request $request, $promotedTLId)
    {
        $agent  = auth('agent')->user();
        $month  = (int)$request->get('month', now()->month);
        $year   = (int)$request->get('year', now()->year);

        $promotedTL = DB::table('agents')->where('agent_id', $promotedTLId)->where('is_deleted', false)->first();
        abort_if(!$promotedTL, 404);

        // Get all sub-team members (Promoted TL + their direct sub-introducers)
        $subMembers = DB::table('agents')
            ->where(function($q) use ($promotedTLId) {
                $q->where('agent_id', $promotedTLId)->orWhere('parent_id', $promotedTLId);
            })
            ->where('is_deleted', false)
            ->orderByRaw('LENGTH(agent_code), agent_code')
            ->get(['agent_id', 'full_name', 'agent_code', 'role', 'status', 'created_at']);

        $subIds = $subMembers->pluck('agent_id')->toArray();

        // Enrich with sales/earn data
        $salesData = DB::table('sales_transactions')
            ->whereIn('agent_id', $subIds)->where('is_deleted', false)
            ->whereMonth('created_at', $month)->whereYear('created_at', $year)
            ->select('agent_id', DB::raw('SUM(premium_amount) as sales_mtd'), DB::raw('COUNT(policy_id) as txn_count'))
            ->groupBy('agent_id')->get()->keyBy('agent_id');

        $earnData = DB::table('commission_transactions')
            ->whereIn('agent_id', $subIds)->where('status', '!=', 'CANCELLED')
            ->whereMonth('created_at', $month)->whereYear('created_at', $year)
            ->select('agent_id', DB::raw('SUM(commission_amount) as earn_mtd'))
            ->groupBy('agent_id')->get()->keyBy('agent_id');

        $subMembers = $subMembers->map(function($m) use ($salesData, $earnData) {
            $m->sales_mtd = (float)($salesData[$m->agent_id]->sales_mtd ?? 0);
            $m->txn_count = (int)($salesData[$m->agent_id]->txn_count ?? 0);
            $m->earn_mtd  = (float)($earnData[$m->agent_id]->earn_mtd ?? 0);
            return $m;
        });

        $grandTotal = $subMembers->sum('sales_mtd');
        $backUrl = route('tl.promoted-tls') . '?month=' . $month . '&year=' . $year;
        $monthName = \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y');

        return view('tl.promoted-tls.transactions', compact('agent', 'promotedTL', 'subMembers', 'grandTotal', 'backUrl', 'month', 'year', 'monthName'));
    }


    public function promotedTLMemberTransactions(Request $request, $ptlId, $memberId)
    {
        $agent     = auth('agent')->user();
        $month     = (int)$request->get('month', now()->month);
        $year      = (int)$request->get('year', now()->year);

        $intro     = DB::table('agents')->where('agent_id', $memberId)->where('is_deleted', false)->first();
        $promotedTL= DB::table('agents')->where('agent_id', $ptlId)->where('is_deleted', false)->first();
        abort_if(!$intro || !$promotedTL, 404);

        $transactions = DB::table('sales_transactions as st')
            ->join('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
            ->leftJoin('commission_transactions as ct', function($join) use ($memberId) {
                $join->on('ct.policy_id', '=', 'st.policy_id')
                     ->where('ct.agent_id', '=', $memberId);
            })
            ->where('st.agent_id', $memberId)
            ->where('st.is_deleted', 0)
            ->whereMonth('st.created_at', $month)
            ->whereYear('st.created_at', $year)
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

        $grandTotal = DB::table('sales_transactions')
            ->where('agent_id', $memberId)->where('is_deleted', 0)
            ->whereMonth('created_at', $month)->whereYear('created_at', $year)
            ->sum('premium_amount');

        $commissionGrandTotal = DB::table('commission_transactions')
            ->where('agent_id', $memberId)->where('status', '!=', 'CANCELLED')
            ->whereMonth('created_at', $month)->whereYear('created_at', $year)
            ->sum('commission_amount');

        $backUrl = route('tl.promoted-tls.transactions', $ptlId) . '?month=' . $month . '&year=' . $year;
        $monthName = \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y');
        $intro->full_name; // use intro as the subject

        return view('tl.introducers.intro_transactions', [
            'agent' => $agent, 'intro' => $intro, 'transactions' => $transactions,
            'grandTotal' => $grandTotal, 'commissionGrandTotal' => $commissionGrandTotal,
            'backUrl' => $backUrl, 'month' => $month, 'year' => $year, 'monthName' => $monthName,
        ]);
    }

}
