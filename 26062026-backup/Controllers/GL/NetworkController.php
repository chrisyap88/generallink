<?php

namespace App\Http\Controllers\GL;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NetworkController extends Controller
{
    private function getGL()
    {
        return auth('agent')->user();
    }

    public function index(Request $request)
    {
        $gl     = $this->getGL();
        $month  = (int)$request->get('month', now()->month);
        $year   = (int)$request->get('year',  now()->year);
        $from   = $request->get('from', '');
        $sortBy = $request->get('sort', 'sales');
        $filter = $request->get('filter', 'tl'); // tl or intro

        // Get all TLs sorted by code
        $tlList = DB::table('agents')
            ->where('group_id', $gl->group_id)
            ->where('role', 'TEAM_LEADER')
            ->where('is_deleted', false)
            ->orderBy('agent_code')
            ->get(['agent_id', 'full_name', 'agent_code', 'status', 'created_at']);

        // For each TL, calculate team total (TL own + all Introducers under TL)
        $tlData = $tlList->map(function($tl) use ($month, $year) {
            // Get all agent_ids in this TL's team (TL + their Introducers)
            $teamIds = DB::table('agents')
                ->where(function($q) use ($tl) {
                    $q->where('agent_id', $tl->agent_id)
                      ->orWhere('parent_id', $tl->agent_id);
                })
                ->where('is_deleted', false)
                ->pluck('agent_id')->toArray();

            $teamSales = DB::table('sales_transactions')
                ->whereIn('agent_id', $teamIds)
                ->where('is_deleted', false)
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->sum('premium_amount');

            $teamEarn = DB::table('commission_transactions')
                ->whereIn('agent_id', $teamIds)
                ->where('status', '!=', 'CANCELLED')
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->sum('commission_amount');

            $txnCount = DB::table('sales_transactions')
                ->whereIn('agent_id', $teamIds)
                ->where('is_deleted', false)
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->count();

            $tl->total_sales = (float)$teamSales;
            $tl->total_earn  = (float)$teamEarn;
            $tl->total_transactions = $txnCount;
            return $tl;
        });

        // Manual pagination
        $page     = (int)request()->get('page', 1);
        $perPage  = 15;
        $total    = $tlData->count();
        $slice    = $tlData->slice(($page-1)*$perPage, $perPage)->values();
        $tls      = new \Illuminate\Pagination\LengthAwarePaginator($slice, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);

        $introCount = DB::table('agents')
            ->where('group_id', $gl->group_id)
            ->where('role', 'INTRODUCER')
            ->where('is_deleted', false)
            ->selectRaw('parent_id, COUNT(*) as cnt')
            ->groupBy('parent_id')
            ->pluck('cnt', 'parent_id');

        $ownSales = DB::table('sales_transactions')
            ->where('agent_id', $gl->agent_id)
            ->where('is_deleted', false)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->sum('premium_amount');

        $monthName = Carbon::createFromDate($year, $month, 1)->format('F Y');

        // Top 3 by team sales and earn from $tlData (already calculated)
        $top3Sales = $tlData->filter(fn($t) => $t->total_sales > 0)
            ->sortByDesc(fn($t) => (float)$t->total_sales)->take(3)->pluck('agent_id')->toArray();
        $top3Earn  = $tlData->filter(fn($t) => $t->total_earn > 0)
            ->sortByDesc(fn($t) => (float)$t->total_earn)->take(3)->pluck('agent_id')->toArray();

        // If filter=intro, show all introducers list
        if ($filter === 'intro') {
            $intros = DB::table('agents as a')
                ->where('a.group_id', $gl->group_id)
                ->where('a.role', 'INTRODUCER')
                ->where('a.is_deleted', false)
                ->leftJoin('agents as tl', 'tl.agent_id', '=', 'a.parent_id')
                ->leftJoin('sales_transactions as st', function($j) use ($month, $year) {
                    $j->on('st.agent_id', '=', 'a.agent_id')
                      ->where('st.is_deleted', false)
                      ->whereMonth('st.created_at', $month)
                      ->whereYear('st.created_at', $year);
                })
                ->leftJoin('commission_transactions as ct', function($j) use ($month, $year) {
                    $j->on('ct.agent_id', '=', 'a.agent_id')
                      ->where('ct.status', '!=', 'CANCELLED')
                      ->whereMonth('ct.created_at', $month)
                      ->whereYear('ct.created_at', $year);
                })
                ->select(
                    'a.agent_id', 'a.full_name', 'a.agent_code', 'a.status', 'a.created_at',
                    'tl.full_name as tl_name', 'tl.agent_code as tl_code',
                    DB::raw('COUNT(DISTINCT st.policy_id) as total_transactions'),
                    DB::raw('COALESCE(SUM(DISTINCT st.premium_amount), 0) as total_sales'),
                    DB::raw('COALESCE(SUM(DISTINCT ct.commission_amount), 0) as total_earn')
                )
                ->groupBy('a.agent_id', 'a.full_name', 'a.agent_code', 'a.status', 'a.created_at', 'tl.full_name', 'tl.agent_code')
                ->orderBy('a.agent_code')
                ->paginate(15)->withQueryString();

            $top3IntroSales = DB::table('agents as a')
                ->where('a.group_id', $gl->group_id)
                ->where('a.role', 'INTRODUCER')
                ->where('a.is_deleted', false)
                ->leftJoin('sales_transactions as st', function($j) use ($month, $year) {
                    $j->on('st.agent_id', '=', 'a.agent_id')
                      ->where('st.is_deleted', false)
                      ->whereMonth('st.created_at', $month)
                      ->whereYear('st.created_at', $year);
                })
                ->select('a.agent_id', DB::raw('COALESCE(SUM(st.premium_amount),0) as total_sales'))
                ->groupBy('a.agent_id')
                ->having('total_sales', '>', 0)
                ->orderByDesc('total_sales')
                ->limit(3)->pluck('agent_id')->toArray();

            $top3IntroEarn = DB::table('agents as a')
                ->where('a.group_id', $gl->group_id)
                ->where('a.role', 'INTRODUCER')
                ->where('a.is_deleted', false)
                ->leftJoin('commission_transactions as ct', function($j) use ($month, $year) {
                    $j->on('ct.agent_id', '=', 'a.agent_id')
                      ->where('ct.status', '!=', 'CANCELLED')
                      ->whereMonth('ct.created_at', $month)
                      ->whereYear('ct.created_at', $year);
                })
                ->select('a.agent_id', DB::raw('COALESCE(SUM(ct.commission_amount),0) as total_earn'))
                ->groupBy('a.agent_id')
                ->having('total_earn', '>', 0)
                ->orderByDesc('total_earn')
                ->limit(3)->pluck('agent_id')->toArray();

            return view('gl.network.intros', compact(
                'gl', 'intros', 'top3IntroSales', 'top3IntroEarn', 'sortBy',
                'month', 'year', 'monthName', 'from'
            ));
        }

        return view('gl.network.index', compact(
            'gl', 'tls', 'introCount', 'ownSales',
            'top3Sales', 'top3Earn', 'sortBy',
            'month', 'year', 'monthName', 'from'
        ));
    }

    public function byTL(Request $request, $tlId)
    {
        $gl     = $this->getGL();
        $month  = (int)$request->get('month', now()->month);
        $year   = (int)$request->get('year',  now()->year);
        $from   = $request->get('from', '');
        $sortBy = $request->get('sort', 'sales');

        $tl = DB::table('agents')->where('agent_id', $tlId)->first();

        $intros = DB::table('agents as a')
            ->where('a.parent_id', $tlId)
            ->where('a.role', 'INTRODUCER')
            ->where('a.is_deleted', false)
            ->leftJoin('sales_transactions as st', function($j) use ($month, $year) {
                $j->on('st.agent_id', '=', 'a.agent_id')
                  ->where('st.is_deleted', false)
                  ->whereMonth('st.created_at', $month)
                  ->whereYear('st.created_at', $year);
            })
            ->leftJoin('commission_transactions as ct', function($j) use ($month, $year) {
                $j->on('ct.agent_id', '=', 'a.agent_id')
                  ->where('ct.status', '!=', 'CANCELLED')
                  ->whereMonth('ct.created_at', $month)
                  ->whereYear('ct.created_at', $year);
            })
            ->select(
                'a.agent_id', 'a.full_name', 'a.agent_code', 'a.status', 'a.created_at',
                DB::raw('COUNT(DISTINCT st.policy_id) as total_transactions'),
                DB::raw('COALESCE(SUM(DISTINCT st.premium_amount), 0) as total_sales'),
                DB::raw('COALESCE(SUM(DISTINCT ct.commission_amount), 0) as total_earn')
            )
            ->groupBy('a.agent_id', 'a.full_name', 'a.agent_code', 'a.status', 'a.created_at')
            ->orderBy('a.agent_code')
            ->paginate(15)->withQueryString();

        // Top 3 by sales
        $top3Sales = DB::table('agents as a')
            ->where('a.parent_id', $tlId)
            ->where('a.role', 'INTRODUCER')
            ->where('a.is_deleted', false)
            ->leftJoin('sales_transactions as st', function($j) use ($month, $year) {
                $j->on('st.agent_id', '=', 'a.agent_id')
                  ->where('st.is_deleted', false)
                  ->whereMonth('st.created_at', $month)
                  ->whereYear('st.created_at', $year);
            })
            ->select('a.agent_id', DB::raw('COALESCE(SUM(st.premium_amount),0) as total_sales'))
            ->groupBy('a.agent_id')
            ->having('total_sales', '>', 0)
            ->orderByDesc('total_sales')
            ->limit(3)->pluck('agent_id')->toArray();

        // Top 3 by earnings
        $top3Earn = DB::table('agents as a')
            ->where('a.parent_id', $tlId)
            ->where('a.role', 'INTRODUCER')
            ->where('a.is_deleted', false)
            ->leftJoin('commission_transactions as ct', function($j) use ($month, $year) {
                $j->on('ct.agent_id', '=', 'a.agent_id')
                  ->where('ct.status', '!=', 'CANCELLED')
                  ->whereMonth('ct.created_at', $month)
                  ->whereYear('ct.created_at', $year);
            })
            ->select('a.agent_id', DB::raw('COALESCE(SUM(ct.commission_amount),0) as total_earn'))
            ->groupBy('a.agent_id')
            ->having('total_earn', '>', 0)
            ->orderByDesc('total_earn')
            ->limit(3)->pluck('agent_id')->toArray();

        $tlOwnSales = DB::table('sales_transactions')
            ->where('agent_id', $tlId)
            ->where('is_deleted', false)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->sum('premium_amount');

        $monthName = Carbon::createFromDate($year, $month, 1)->format('F Y');

        return view('gl.network.by-tl', compact(
            'gl', 'tl', 'intros', 'tlOwnSales',
            'top3Sales', 'top3Earn', 'sortBy',
            'month', 'year', 'monthName', 'from'
        ));
    }

    public function introTransactions(Request $request, $introId)
    {
        $gl     = $this->getGL();
        $month  = (int)$request->get('month', now()->month);
        $year   = (int)$request->get('year',  now()->year);
        $from   = $request->get('from', '');

        $intro = DB::table('agents')->where('agent_id', $introId)->first();

        $transactions = DB::table('sales_transactions as st')
            ->where('st.agent_id', $introId)
            ->where('st.is_deleted', false)
            ->whereMonth('st.created_at', $month)
            ->whereYear('st.created_at', $year)
            ->leftJoin('vendors as v', 'v.vendor_id', '=', 'st.vendor_id')
            ->leftJoin('products as p', 'p.product_id', '=', 'st.product_id')
            ->leftJoin('commission_transactions as ct', 'ct.policy_id', '=', 'st.policy_id')
            ->select(
                'st.policy_id', 'st.policy_number', 'st.premium_amount', 'st.status', 'st.created_at',
                'st.coverage_start', 'st.coverage_end',
                'v.vendor_name', 'p.product_name', 'p.product_code',
                DB::raw('COALESCE(ct.commission_amount, 0) as commission_amount'),
                DB::raw('COALESCE(ct.entitlement_pct, 0) as entitlement_pct')
            )
            ->orderByDesc('st.created_at')
            ->paginate(15)->withQueryString();

        $monthName = Carbon::createFromDate($year, $month, 1)->format('F Y');
        $backUrl   = route('gl.network.intros') . '?month=' . $month . '&year=' . $year;

        return view('gl.network.intro_transactions', compact(
            'gl', 'intro', 'transactions', 'monthName', 'month', 'year', 'backUrl'
        ));
    }
}
