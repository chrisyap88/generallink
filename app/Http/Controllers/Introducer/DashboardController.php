<?php

namespace App\Http\Controllers\Introducer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        return view('introducer.dashboard');
    }

    private function getTeamIds($intro)
    {
        // Introducer's own agent_id + all direct sub-introducers under them
        return DB::table('agents')
            ->where(function($q) use ($intro) {
                $q->where('agent_id', $intro->agent_id)
                  ->orWhere('parent_id', $intro->agent_id);
            })
            ->where('is_deleted', false)
            ->pluck('agent_id')->toArray();
    }

    public function metrics(Request $request)
    {
        $intro = auth('agent')->user();
        $month = (int)$request->get('month', now()->month);
        $year  = (int)$request->get('year',  now()->year);

        $selDate   = Carbon::createFromDate($year, $month, 1);
        $lastMonth = $selDate->copy()->subMonth()->month;
        $lastYear  = $selDate->copy()->subMonth()->year;

        // ── Network Overview — Introducers only (TL has no GL/TL downline) ──
        $introCount    = DB::table('agents')->where('parent_id', $intro->agent_id)->where('role', 'INTRODUCER')->where('status', 'ACTIVE')->where('is_deleted', false)->count();
        $inactiveIntro = DB::table('agents')->where('parent_id', $intro->agent_id)->where('role', 'INTRODUCER')->where('status', '!=', 'ACTIVE')->where('is_deleted', false)->count();

        $agentIds = $this->getTeamIds($intro);

        // ── Sales ───────────────────────────────────────────────
        $baseQ = DB::table('sales_transactions')->whereIn('agent_id', $agentIds)->where('is_deleted', false);

        $salesMTD     = (clone $baseQ)->whereMonth('created_at', $month)->whereYear('created_at', $year)->sum('premium_amount');
        $salesLastMTD = (clone $baseQ)->whereMonth('created_at', $lastMonth)->whereYear('created_at', $lastYear)->sum('premium_amount');
        $salesYTD     = (clone $baseQ)->whereYear('created_at', $year)->sum('premium_amount');

        // ── Earnings ────────────────────────────────────────────
        $earnQ = DB::table('commission_transactions')->whereIn('agent_id', $agentIds)->where('status', '!=', 'CANCELLED');

        $earnMTD     = (clone $earnQ)->whereMonth('created_at', $month)->whereYear('created_at', $year)->sum('commission_amount');
        $earnLastMTD = (clone $earnQ)->whereMonth('created_at', $lastMonth)->whereYear('created_at', $lastYear)->sum('commission_amount');
        $earnYTD     = (clone $earnQ)->whereYear('created_at', $year)->sum('commission_amount');
        $unclaimed   = DB::table('commission_transactions')->whereIn('agent_id', $agentIds)->where('status', 'PENDING')->sum('commission_amount');

        // ── Renewals ────────────────────────────────────────────
        $renewLte30 = (clone $baseQ)->where('status', 'PENDING_RENEWAL')->whereNotNull('renewal_date')->whereRaw('renewal_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)')->whereRaw('renewal_date >= CURDATE()')->count();
        $renewGt30  = (clone $baseQ)->where('status', 'PENDING_RENEWAL')->whereNotNull('renewal_date')->whereRaw('renewal_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)')->count();

        // ── Top 3 Performers — Introducers only ──────────────────
        $introAgents = DB::table('agents')
            ->where('parent_id', $intro->agent_id)
            ->where('role', 'INTRODUCER')
            ->where('is_deleted', false)
            ->get(['agent_id', 'full_name', 'agent_code', 'role']);

        $buildIntroPerformers = function() use ($introAgents, $month, $year) {
            return $introAgents->map(function($a) use ($month, $year) {
                $sales = (float)DB::table('sales_transactions')
                    ->where('agent_id', $a->agent_id)->where('is_deleted', false)
                    ->whereMonth('created_at', $month)->whereYear('created_at', $year)
                    ->sum('premium_amount');

                $earn = (float)DB::table('commission_transactions')
                    ->where('agent_id', $a->agent_id)->where('status', '!=', 'CANCELLED')
                    ->whereMonth('created_at', $month)->whereYear('created_at', $year)
                    ->sum('commission_amount');

                return [
                    'agent_id'   => $a->agent_id,
                    'full_name'  => $a->full_name,
                    'agent_code' => $a->agent_code,
                    'role'       => $a->role,
                    'sales'      => $sales,
                    'earn'       => $earn,
                ];
            });
        };

        $introBySales = $buildIntroPerformers()->sortByDesc('sales')->take(3)->values()->toArray();
        $introByEarn  = $buildIntroPerformers()->sortByDesc('earn')->take(3)->values()->toArray();

        $performers = [
            'intro' => ['sales' => $introBySales, 'earnings' => $introByEarn],
        ];

        // ── Top 3 Vendors ────────────────────────────────────────
        $vendors = DB::table('sales_transactions as st')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->leftJoin('commission_transactions as ct', function($j) use ($month, $year) {
                $j->on('ct.policy_id', '=', 'st.policy_id')
                  ->where('ct.status', '!=', 'CANCELLED');
            })
            ->whereIn('st.agent_id', $agentIds)->where('st.is_deleted', false)
            ->whereMonth('st.created_at', $month)->whereYear('st.created_at', $year)
            ->select('v.vendor_name as name', DB::raw('SUM(st.premium_amount) as sales'), DB::raw('COALESCE(SUM(ct.commission_amount),0) as earn'))
            ->groupBy('v.vendor_id', 'v.vendor_name')
            ->orderByDesc('sales')->limit(3)->get();

        // ── Top 3 Products ───────────────────────────────────────
        $products = DB::table('sales_transactions as st')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->leftJoin('commission_transactions as ct', function($j) use ($month, $year) {
                $j->on('ct.policy_id', '=', 'st.policy_id')
                  ->where('ct.status', '!=', 'CANCELLED');
            })
            ->whereIn('st.agent_id', $agentIds)->where('st.is_deleted', false)
            ->whereMonth('st.created_at', $month)->whereYear('st.created_at', $year)
            ->select('p.product_name as name', DB::raw('SUM(st.premium_amount) as sales'), DB::raw('COALESCE(SUM(ct.commission_amount),0) as earn'))
            ->groupBy('p.product_id', 'p.product_name')
            ->orderByDesc('sales')->limit(3)->get();

        // ── Top 3 Months trend ───────────────────────────────────
        $trend = DB::table('sales_transactions as st')
            ->whereIn('st.agent_id', $agentIds)->where('st.is_deleted', false)
            ->whereYear('st.created_at', $year)
            ->leftJoin('commission_transactions as ct', 'ct.policy_id', '=', 'st.policy_id')
            ->selectRaw("DATE_FORMAT(st.created_at,'%b %Y') as name, SUM(st.premium_amount) as sales, COALESCE(SUM(ct.commission_amount),0) as earn")
            ->groupByRaw("YEAR(st.created_at), MONTH(st.created_at), DATE_FORMAT(st.created_at,'%b %Y')")
            ->orderByRaw("YEAR(st.created_at), MONTH(st.created_at)")
            ->limit(3)->get();

        $trendLabels = $trend->pluck('name')->toArray();
        $trendSales  = $trend->pluck('sales')->map(fn($v) => (float)$v)->toArray();
        $trendEarn   = $trend->pluck('earn')->map(fn($v) => (float)$v)->toArray();

        return response()->json([
            // Box 2 Network (TL scoped — Introducers only)
            'totalIntroducers'     => $introCount,
            'inactiveIntro'        => $inactiveIntro,
            // Performance Overview
            'totalPremiumMonth'    => $salesMTD,
            'salesLastMTD'         => $salesLastMTD,
            'totalPremiumYTD'      => $salesYTD,
            'commDistributedMonth' => $earnMTD,
            'earnLastMTD'          => $earnLastMTD,
            'commDistributedYTD'   => $earnYTD,
            'unclaimedCommission'  => $unclaimed,
            'unclaimedLastMTD'     => $unclaimed,
            // Box 3 Pending Actions
            'renewal_lte30'        => $renewLte30,
            'renewal_gt30'         => $renewGt30,
            'redemption_pending'   => 0,
            'pointsPurchasePending'=> 0,
            // Charts
            'trend_labels'         => $trendLabels,
            'trend_sales'          => $trendSales,
            'trend_earn'           => $trendEarn,
            'vendors'              => $vendors,
            'products'             => $products,
            // Box 1 Top Performers
            'topPerformers'        => $performers,
        ]);
    }

    public function recentTransactions(Request $request)
    {
        $intro    = auth('agent')->user();
        $agentIds = $this->getTeamIds($intro);

        $transactions = DB::table('sales_transactions as st')
            ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->whereIn('st.agent_id', $agentIds)
            ->where('st.is_deleted', false)
            ->select('st.policy_number', 'st.premium_amount', 'st.status', 'st.created_at', 'st.renewal_date',
                     'a.full_name as agent_name', 'v.vendor_name', 'p.product_name', 'c.full_name as customer_name')
            ->orderByDesc('st.created_at')
            ->paginate(5, ['*'], 'txpage');

        return view('introducer.partials.recent-transactions', compact('transactions'));
    }

    public function chartDrilldown(Request $request)
    {
        $intro    = auth('agent')->user();
        $agentIds = $this->getTeamIds($intro);
        $type     = $request->get('type');
        $month    = (int)$request->get('month', now()->month);
        $year     = (int)$request->get('year',  now()->year);
        $page     = (int)$request->get('page', 1);
        $perPage  = $type === 'trend' ? 12 : 10;

        $selectedDate = Carbon::createFromDate($year, $month, 1);
        $monthName    = $selectedDate->format('F Y');

        if ($type === 'vendors') {
            $all = DB::table('sales_transactions as st')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->whereIn('st.agent_id', $agentIds)->where('st.is_deleted', false)
                ->whereMonth('st.created_at', $month)->whereYear('st.created_at', $year)
                ->select('v.vendor_id', 'v.vendor_name', DB::raw('SUM(st.premium_amount) as total_sales'))
                ->groupBy('v.vendor_id', 'v.vendor_name')->orderByDesc('total_sales')->get();

            $total       = $all->sum('total_sales');
            $periodLabel = 'All Vendors — '.$monthName;
            $slice       = $all->slice(($page-1)*$perPage, $perPage)->values();
            $startRank   = ($page-1)*$perPage + 1;
            $rawLabels   = $slice->map(fn($r) => $r->vendor_name)->toArray();
            $labels      = $slice->map(fn($r, $i) => '#'.($startRank+$i).' '.$r->vendor_name)->toArray();
            $sales       = $slice->map(fn($r) => (float)$r->total_sales)->toArray();
            $links       = $slice->map(fn($r) => '#')->toArray();

        } elseif ($type === 'products') {
            $all = DB::table('sales_transactions as st')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->whereIn('st.agent_id', $agentIds)->where('st.is_deleted', false)
                ->whereMonth('st.created_at', $month)->whereYear('st.created_at', $year)
                ->select('p.product_id', 'p.product_name', DB::raw('SUM(st.premium_amount) as total_sales'))
                ->groupBy('p.product_id', 'p.product_name')->orderByDesc('total_sales')->get();

            $total       = $all->sum('total_sales');
            $periodLabel = 'All Products — '.$monthName;
            $slice       = $all->slice(($page-1)*$perPage, $perPage)->values();
            $startRank   = ($page-1)*$perPage + 1;
            $rawLabels   = $slice->map(fn($r) => $r->product_name)->toArray();
            $labels      = $slice->map(fn($r, $i) => '#'.($startRank+$i).' '.$r->product_name)->toArray();
            $sales       = $slice->map(fn($r) => (float)$r->total_sales)->toArray();
            $links       = $slice->map(fn($r) => '#')->toArray();

        } elseif ($type === 'trend') {
            $all = DB::table('sales_transactions as st')
                ->whereIn('st.agent_id', $agentIds)->where('st.is_deleted', false)
                ->whereYear('st.created_at', $year)
                ->selectRaw("DATE_FORMAT(st.created_at,'%M %Y') as month_name, MONTH(st.created_at) as month_num, SUM(st.premium_amount) as total_sales")
                ->groupByRaw("YEAR(st.created_at), MONTH(st.created_at), DATE_FORMAT(st.created_at,'%M %Y')")
                ->orderByDesc('total_sales')->get();

            $total       = $all->sum('total_sales');
            $periodLabel = 'Monthly Sales Ranking — '.$year;
            $slice       = $all->slice(($page-1)*$perPage, $perPage)->values();
            $startRank   = ($page-1)*$perPage + 1;
            $rawLabels   = $slice->map(fn($r) => $r->month_name)->toArray();
            $labels      = $slice->map(fn($r, $i) => '#'.($startRank+$i).' '.$r->month_name)->toArray();
            $sales       = $slice->map(fn($r) => (float)$r->total_sales)->toArray();
            $links       = $slice->map(fn($r) => '#')->toArray();
        }

        $lastPage = ceil($all->count() / $perPage);
        $hasMore  = $page < $lastPage;

        $ctype = $request->get('ctype', 'bar');
        return view('dashboard.chart-drilldown', compact(
            'rawLabels', 'labels', 'sales', 'links',
            'total', 'periodLabel', 'monthName', 'startRank',
            'month', 'year', 'type', 'page', 'lastPage', 'hasMore', 'perPage', 'ctype'
        ));
    }

    public function drilldown(Request $request)
    {
        $intro    = auth('agent')->user();
        $agentIds = $this->getTeamIds($intro);
        $type     = $request->get('type', 'sales');
        $period   = $request->get('period', 'mtd');
        $month    = (int)$request->get('month', now()->month);
        $year     = (int)$request->get('year',  now()->year);

        $selectedDate  = Carbon::createFromDate($year, $month, 1);
        $lastMonth     = $selectedDate->copy()->subMonth()->month;
        $lastYear      = $selectedDate->copy()->subMonth()->year;
        $monthName     = $selectedDate->format('F Y');
        $lastMonthName = $selectedDate->copy()->subMonth()->format('F Y');

        if ($type === 'renewal_lte30') {
            $query = DB::table('sales_transactions as st')
                ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
                ->where('st.status', 'PENDING_RENEWAL')
                ->whereNotNull('st.renewal_date')
                ->whereRaw('st.renewal_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)')
                ->whereRaw('st.renewal_date >= CURDATE()')
                ->whereIn('st.agent_id', $agentIds)->where('st.is_deleted', false)
                ->select('st.created_at', 'v.vendor_name', 'p.product_name', 'a.agent_code', 'a.role',
                    'a.full_name as agent_name', 'c.full_name as customer_name',
                    'st.premium_amount', 'st.renewal_date', 'st.status');
            $periodLabel = 'Renewal Due ≤ 30 Days';
            $total = (clone $query)->sum('st.premium_amount');
            $records = $query->orderBy('st.renewal_date')->paginate(15)->withQueryString();
            $columns = [__('gl.col_date'), __('network.col_vendor'), __('network.col_product'), __('gl.col_role'), __('network.col_code'), __('gl.col_agent'), __('gl.col_customer'), __('drilldown.col_sales_amount_rm'), __('gl.renewal_date_label'), __('network.col_status')];

        } elseif ($type === 'renewal_gt30') {
            $query = DB::table('sales_transactions as st')
                ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
                ->where('st.status', 'PENDING_RENEWAL')
                ->whereNotNull('st.renewal_date')
                ->whereRaw('st.renewal_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)')
                ->whereIn('st.agent_id', $agentIds)->where('st.is_deleted', false)
                ->select('st.created_at', 'v.vendor_name', 'p.product_name', 'a.agent_code', 'a.role',
                    'a.full_name as agent_name', 'c.full_name as customer_name',
                    'st.premium_amount', 'st.renewal_date', 'st.status');
            $periodLabel = 'Renewal Due > 30 Days';
            $total = (clone $query)->sum('st.premium_amount');
            $records = $query->orderBy('st.renewal_date')->paginate(15)->withQueryString();
            $columns = [__('gl.col_date'), __('network.col_vendor'), __('network.col_product'), __('gl.col_role'), __('network.col_code'), __('gl.col_agent'), __('gl.col_customer'), __('drilldown.col_sales_amount_rm'), __('gl.renewal_date_label'), __('network.col_status')];

        } elseif ($type === 'sales') {
            $query = DB::table('sales_transactions as st')
                ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
                ->whereIn('st.agent_id', $agentIds)->where('st.is_deleted', false)
                ->select('st.policy_number', 'st.premium_amount', 'st.status', 'st.created_at',
                    'a.full_name as agent_name', 'a.agent_code', 'a.role',
                    'v.vendor_name', 'p.product_name', 'c.full_name as customer_name');

            if ($period === 'mtd') {
                $query->whereMonth('st.created_at', $month)->whereYear('st.created_at', $year);
                $periodLabel = 'Sales Amount — MTD '.$monthName;
            } elseif ($period === 'last_mtd') {
                $query->whereMonth('st.created_at', $lastMonth)->whereYear('st.created_at', $lastYear);
                $periodLabel = 'Sales Amount — Last MTD '.$lastMonthName;
            } else {
                $query->whereYear('st.created_at', $year);
                $periodLabel = 'Sales Amount — YTD '.$year;
            }

            $total   = (clone $query)->sum('st.premium_amount');
            $records = $query->orderBy('st.created_at')->paginate(15)->withQueryString();
            $columns = [__('gl.col_date'), __('network.col_vendor'), __('network.col_product'), __('gl.col_role'), __('network.col_code'), __('gl.col_agent'), __('gl.col_customer'), __('drilldown.col_sales_amount_rm'), __('network.col_status')];

        } elseif ($type === 'earnings') {
            $query = DB::table('commission_transactions as ct')
                ->join('agents as a', 'ct.agent_id', '=', 'a.agent_id')
                ->join('sales_transactions as st', 'ct.policy_id', '=', 'st.policy_id')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
                ->whereIn('ct.agent_id', $agentIds)->where('ct.status', '!=', 'CANCELLED')
                ->select('ct.created_at', 'v.vendor_name', 'p.product_name', 'a.agent_code', 'a.role',
                    'a.full_name as agent_name', 'c.full_name as customer_name',
                    'ct.commission_amount', 'ct.status');

            if ($period === 'mtd') {
                $query->whereMonth('ct.created_at', $month)->whereYear('ct.created_at', $year);
                $periodLabel = 'Earning Income — MTD '.$monthName;
            } elseif ($period === 'last_mtd') {
                $query->whereMonth('ct.created_at', $lastMonth)->whereYear('ct.created_at', $lastYear);
                $periodLabel = 'Earning Income — Last MTD '.$lastMonthName;
            } else {
                $query->whereYear('ct.created_at', $year);
                $periodLabel = 'Earning Income — YTD '.$year;
            }

            $total   = (clone $query)->sum('ct.commission_amount');
            $records = $query->orderBy('ct.created_at')->paginate(15)->withQueryString();
            $columns = [__('gl.col_date'), __('network.col_vendor'), __('network.col_product'), __('gl.col_role'), __('network.col_code'), __('gl.col_agent'), __('gl.col_customer'), __('drilldown.col_earning_rm'), __('network.col_status')];

        } else {
            $query = DB::table('commission_transactions as ct')
                ->join('agents as a', 'ct.agent_id', '=', 'a.agent_id')
                ->join('sales_transactions as st', 'ct.policy_id', '=', 'st.policy_id')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
                ->whereIn('ct.agent_id', $agentIds)->where('ct.status', 'PENDING')
                ->select('ct.created_at', 'v.vendor_name', 'p.product_name', 'a.agent_code', 'a.role',
                    'a.full_name as agent_name', 'c.full_name as customer_name',
                    'ct.commission_amount', 'ct.status');

            $periodLabel = 'Unclaimed Earning';
            $total   = (clone $query)->sum('ct.commission_amount');
            $records = $query->orderBy('ct.created_at')->paginate(15)->withQueryString();
            $columns = [__('gl.col_date'), __('network.col_vendor'), __('network.col_product'), __('gl.col_role'), __('network.col_code'), __('gl.col_agent'), __('gl.col_customer'), __('drilldown.col_earning_rm'), __('network.col_status')];
        }

        $viewType = 'transactions';
        $backUrl = route('introducer.dashboard');
        return view('dashboard.drilldown', compact(
            'records', 'total', 'columns', 'type', 'period',
            'periodLabel', 'month', 'year', 'monthName', 'viewType', 'backUrl'
        ));
    }

    public function exportTeam(Request $request)
    {
        $intro     = auth('agent')->user();
        $tl        = $intro; // alias for export template
        $month     = (int)$request->get('month', now()->month);
        $year      = (int)$request->get('year',  now()->year);
        $monthName = Carbon::createFromDate($year, $month, 1)->format('F Y');
        $today     = now()->format('d M Y');
        $filename  = 'GeneralLink_' . str_replace(' ', '', $tl->full_name) . '_' . $tl->agent_code . '_' . str_replace(' ', '', $monthName) . '_' . now()->format('dMY') . '.xlsx';

        $agentIds = $this->getTeamIds($intro);

        // Sheet 1: My Introducers
        $introAgents = DB::table('agents')
            ->where('parent_id', $intro->agent_id)
            ->where('role', 'INTRODUCER')
            ->where('is_deleted', false)
            ->orderBy('agent_code')->get();

        $intros = $introAgents->map(function($i) use ($month, $year) {
            $i->total_sales = (float)DB::table('sales_transactions')
                ->where('agent_id', $i->agent_id)->where('is_deleted', false)
                ->whereMonth('created_at', $month)->whereYear('created_at', $year)
                ->sum('premium_amount');
            $i->total_earn = (float)DB::table('commission_transactions')
                ->where('agent_id', $i->agent_id)->where('status', '!=', 'CANCELLED')
                ->whereMonth('created_at', $month)->whereYear('created_at', $year)
                ->sum('commission_amount');
            return $i;
        });

        $tlOwnSales = (float)DB::table('sales_transactions')
            ->where('agent_id', $tl->agent_id)->where('is_deleted', false)
            ->whereMonth('created_at', $month)->whereYear('created_at', $year)
            ->sum('premium_amount');

        $sheet1 = [];
        $sheet1[] = ['GeneralLink Digital Ecosystem'];
        $sheet1[] = ['Team Leader: ' . $tl->full_name . ' (' . $tl->agent_code . ')'];
        $sheet1[] = ['Period: ' . $monthName];
        $sheet1[] = ['Downloaded: ' . $today];
        $sheet1[] = [];
        $sheet1[] = ['Name', 'Code', 'Sales Amount (RM)', 'Earning Income (RM)', 'Status', 'Joined'];
        $sheet1[] = [$tl->full_name . ' (TL)', $tl->agent_code, $tlOwnSales, 0, $tl->status, Carbon::parse($tl->created_at)->format('d M Y')];
        foreach ($intros as $i) {
            $sheet1[] = [$i->full_name, $i->agent_code, (float)$i->total_sales, (float)$i->total_earn, $i->status, Carbon::parse($i->created_at)->format('d M Y')];
        }

        // Sheet 2: All Transactions
        $txns = DB::table('sales_transactions as st')
            ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->whereIn('st.agent_id', $agentIds)->where('st.is_deleted', false)
            ->whereMonth('st.created_at', $month)->whereYear('st.created_at', $year)
            ->select('st.policy_number', 'c.full_name as customer_name', 'a.full_name as agent_name', 'a.agent_code', 'a.role',
                     'v.vendor_name', 'p.product_name', 'st.premium_amount', 'st.status', 'st.created_at', 'st.renewal_date')
            ->orderBy('a.agent_code')->orderByDesc('st.created_at')->get();

        $sheet2 = [];
        $sheet2[] = ['GeneralLink Digital Ecosystem'];
        $sheet2[] = ['Team Leader: ' . $tl->full_name . ' (' . $tl->agent_code . ')'];
        $sheet2[] = ['Period: ' . $monthName];
        $sheet2[] = ['Downloaded: ' . $today];
        $sheet2[] = [];
        $sheet2[] = ['Transaction No.', 'Customer', 'Agent', 'Code', 'Role', 'Vendor', 'Product', 'Sales Amount (RM)', 'Date', 'Renewal Date', 'Status'];
        foreach ($txns as $tx) {
            $sheet2[] = [$tx->policy_number, $tx->customer_name ?? '—', $tx->agent_name, $tx->agent_code, $tx->role, $tx->vendor_name, $tx->product_name, (float)$tx->premium_amount, Carbon::parse($tx->created_at)->format('d M Y'), $tx->renewal_date ? Carbon::parse($tx->renewal_date)->format('d M Y') : '—', $tx->status];
        }

        // Build Excel
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        $ws1 = $spreadsheet->getActiveSheet(); $ws1->setTitle('My Introducers');
        foreach ($sheet1 as $ri => $row) { foreach ($row as $ci => $val) { $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci+1).($ri+1); if (is_float($val)||is_int($val)) $ws1->getCell($coord)->setValueExplicit($val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC); else $ws1->getCell($coord)->setValue($val); } }
        $ws1->getStyle('A1:A4')->getFont()->setBold(true); $ws1->getStyle('A6:F6')->getFont()->setBold(true);
        $lr1=$ws1->getHighestRow(); $ws1->getStyle('C7:D'.$lr1)->getNumberFormat()->setFormatCode('#,##0.00'); $ws1->getStyle('C7:D'.$lr1)->getAlignment()->setHorizontal('right');
        $ws1->getCell('B'.($lr1+2))->setValue('Total'); $ws1->getStyle('B'.($lr1+2))->getFont()->setBold(true)->setSize(12);
        $ws1->getCell('C'.($lr1+2))->setValue('=SUM(C7:C'.$lr1.')'); $ws1->getStyle('C'.($lr1+2))->getNumberFormat()->setFormatCode('#,##0.00'); $ws1->getStyle('C'.($lr1+2))->getFont()->setBold(true)->setSize(12); $ws1->getStyle('C'.($lr1+2))->getAlignment()->setHorizontal('right');
        $ws1->getCell('D'.($lr1+2))->setValue('=SUM(D7:D'.$lr1.')'); $ws1->getStyle('D'.($lr1+2))->getNumberFormat()->setFormatCode('#,##0.00'); $ws1->getStyle('D'.($lr1+2))->getFont()->setBold(true)->setSize(12); $ws1->getStyle('D'.($lr1+2))->getAlignment()->setHorizontal('right');
        foreach (range('A','F') as $col) $ws1->getColumnDimension($col)->setAutoSize(true);

        $ws2=$spreadsheet->createSheet(); $ws2->setTitle('Transactions');
        foreach ($sheet2 as $ri => $row) { foreach ($row as $ci => $val) { $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci+1).($ri+1); if (is_float($val)||is_int($val)) $ws2->getCell($coord)->setValueExplicit($val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC); else $ws2->getCell($coord)->setValue($val); } }
        $ws2->getStyle('A1:A4')->getFont()->setBold(true); $ws2->getStyle('A6:K6')->getFont()->setBold(true);
        $lr2=$ws2->getHighestRow(); $ws2->getStyle('H7:H'.$lr2)->getNumberFormat()->setFormatCode('#,##0.00'); $ws2->getStyle('H7:H'.$lr2)->getAlignment()->setHorizontal('right');
        $ws2->getCell('G'.($lr2+2))->setValue('Total'); $ws2->getStyle('G'.($lr2+2))->getFont()->setBold(true)->setSize(12);
        $ws2->getCell('H'.($lr2+2))->setValue('=SUM(H7:H'.$lr2.')'); $ws2->getStyle('H'.($lr2+2))->getNumberFormat()->setFormatCode('#,##0.00'); $ws2->getStyle('H'.($lr2+2))->getFont()->setBold(true)->setSize(12); $ws2->getStyle('H'.($lr2+2))->getAlignment()->setHorizontal('right');
        foreach (range('A','K') as $col) $ws2->getColumnDimension($col)->setAutoSize(true);

        $spreadsheet->setActiveSheetIndex(0);
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

}