<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard.admin');
    }

    public function metrics(Request $request)
    {
        $now       = now();
        $thisMonth = (int)$request->get('month', $now->month);
        $thisYear  = (int)$request->get('year',  $now->year);

        $selectedDate = \Carbon\Carbon::createFromDate($thisYear, $thisMonth, 1);
        $lastMonth    = $selectedDate->copy()->subMonth()->month;
        $lastYear     = $selectedDate->copy()->subMonth()->year;

        // ── Network counts ──────────────────────────────────────────
        $counts = DB::table('agents')
            ->selectRaw("
                SUM(CASE WHEN role='GROUP_LEADER' THEN 1 ELSE 0 END) as gl,
                SUM(CASE WHEN role='TEAM_LEADER'  THEN 1 ELSE 0 END) as tl,
                SUM(CASE WHEN role='INTRODUCER'   THEN 1 ELSE 0 END) as intro
            ")
            ->where('status', 'ACTIVE')
            ->first();

        $pending = DB::table('agents')
            ->whereNull('parent_id')
            ->where('role', 'INTRODUCER')
            ->where('status', 'ACTIVE')
            ->count();

        // ── Sales MTD / YTD / Last MTD ──────────────────────────────
        $salesMTD = DB::table('sales_transactions')
            ->whereMonth('created_at', $thisMonth)
            ->whereYear('created_at', $thisYear)
            ->sum('premium_amount');

        $salesYTD = DB::table('sales_transactions')
            ->whereYear('created_at', $thisYear)
            ->sum('premium_amount');

        $salesLastMTD = DB::table('sales_transactions')
            ->whereMonth('created_at', $lastMonth)
            ->whereYear('created_at', $lastYear)
            ->sum('premium_amount');

        // ── Earnings MTD / YTD / Last MTD ───────────────────────────
        $earnMTD = DB::table('commission_transactions')
            ->whereMonth('created_at', $thisMonth)
            ->whereYear('created_at', $thisYear)
            ->sum('commission_amount');

        $earnYTD = DB::table('commission_transactions')
            ->whereYear('created_at', $thisYear)
            ->sum('commission_amount');

        $earnLastMTD = DB::table('commission_transactions')
            ->whereMonth('created_at', $lastMonth)
            ->whereYear('created_at', $lastYear)
            ->sum('commission_amount');

        // ── Unclaimed Earnings ───────────────────────────────────────
        $unclaimed = DB::table('commission_transactions')
            ->where('status', 'PENDING')
            ->sum('commission_amount');

        // ── Points Purchase Pending ──────────────────────────────────
        $pointsPending = DB::table('point_purchases')
            ->where('status', 'PENDING')
            ->count();

        // ── Renewal counts ───────────────────────────────────────────
        $renewal_lte30 = DB::table('sales_transactions')
            ->where('status', 'PENDING_RENEWAL')
            ->whereNotNull('renewal_date')
            ->whereRaw('renewal_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)')
            ->whereRaw('renewal_date >= CURDATE()')
            ->count();

        $renewal_gt30 = DB::table('sales_transactions')
            ->where('status', 'PENDING_RENEWAL')
            ->whereNotNull('renewal_date')
            ->whereRaw('renewal_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)')
            ->count();

        // ── Redemption pending ───────────────────────────────────────
        $redemption = DB::table('reward_points_ledger')
            ->where('txn_type', 'REDEEMED')
            ->count();

        // ── Trend: last 6 months Sales & Earnings ───────────────────
        $trend = DB::table('sales_transactions')
            ->selectRaw("DATE_FORMAT(created_at,'%b %Y') as label,
                         MONTH(created_at) as m, YEAR(created_at) as y,
                         SUM(premium_amount) as sales")
            ->whereRaw('created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)')
            ->groupByRaw('YEAR(created_at), MONTH(created_at), DATE_FORMAT(created_at,\'%b %Y\')')
            ->orderByRaw('YEAR(created_at), MONTH(created_at)')
            ->get();

        $earnLookup = DB::table('commission_transactions')
            ->selectRaw("CONCAT(YEAR(created_at),'-',LPAD(MONTH(created_at),2,'0')) as ym,
                         SUM(commission_amount) as earn")
            ->whereRaw('created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)')
            ->groupByRaw('YEAR(created_at), MONTH(created_at), CONCAT(YEAR(created_at),\'-\',LPAD(MONTH(created_at),2,\'0\'))')
            ->pluck('earn', 'ym')
            ->toArray();

        $trend_labels = $trend->map(fn($r) => $r->label)->values()->toArray();
        $trend_sales  = $trend->map(fn($r) => (float)$r->sales)->values()->toArray();
        $trend_earn   = $trend->map(fn($r) => (float)($earnLookup[$r->y.'-'.str_pad($r->m,2,'0',STR_PAD_LEFT)] ?? 0))->values()->toArray();

        // ── Sales by Vendor ──────────────────────────────────────────
        $vendorData = DB::table('sales_transactions as st')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->selectRaw("v.vendor_name as name, SUM(st.premium_amount) as sales")
            ->whereMonth('st.created_at', $thisMonth)
            ->whereYear('st.created_at', $thisYear)
            ->groupBy('v.vendor_id', 'v.vendor_name')
            ->orderByDesc('sales')
            ->limit(3)
            ->get();

        $vendorEarnMap = DB::table('commission_transactions as ct')
            ->join('sales_transactions as st', 'ct.policy_id', '=', 'st.policy_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->selectRaw("v.vendor_name as name, SUM(ct.commission_amount) as earn")
            ->whereMonth('ct.created_at', $thisMonth)
            ->whereYear('ct.created_at', $thisYear)
            ->groupBy('v.vendor_id', 'v.vendor_name')
            ->pluck('earn', 'name')
            ->toArray();

        $vendors = $vendorData->map(fn($r) => [
            'name'  => $r->name,
            'sales' => (float)$r->sales,
            'earn'  => (float)($vendorEarnMap[$r->name] ?? 0),
        ])->values()->toArray();

        // ── Sales by Product ─────────────────────────────────────────
        $productData = DB::table('sales_transactions as st')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->selectRaw("p.product_name as name, SUM(st.premium_amount) as sales")
            ->whereMonth('st.created_at', $thisMonth)
            ->whereYear('st.created_at', $thisYear)
            ->groupBy('p.product_id', 'p.product_name')
            ->orderByDesc('sales')
            ->limit(3)
            ->get();

        $productEarnMap = DB::table('commission_transactions as ct')
            ->join('sales_transactions as st', 'ct.policy_id', '=', 'st.policy_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->selectRaw("p.product_name as name, SUM(ct.commission_amount) as earn")
            ->whereMonth('ct.created_at', $thisMonth)
            ->whereYear('ct.created_at', $thisYear)
            ->groupBy('p.product_id', 'p.product_name')
            ->pluck('earn', 'name')
            ->toArray();

        $products = $productData->map(fn($r) => [
            'name'  => $r->name,
            'sales' => (float)$r->sales,
            'earn'  => (float)($productEarnMap[$r->name] ?? 0),
        ])->values()->toArray();

        // ── Top Performers ───────────────────────────────────────────
        $topPerformers = $this->getTopPerformers($thisMonth, $thisYear);

        return response()->json([
            'totalGL'              => (int)($counts->gl ?? 0),
            'totalTL'              => (int)($counts->tl ?? 0),
            'totalIntroducers'     => (int)($counts->intro ?? 0),
            'pendingAssignment'    => $pending,
            'totalPremiumMonth'    => (float)$salesMTD,
            'totalPremiumYTD'      => (float)$salesYTD,
            'salesLastMTD'         => (float)$salesLastMTD,
            'commDistributedMonth' => (float)$earnMTD,
            'commDistributedYTD'   => (float)$earnYTD,
            'earnLastMTD'          => (float)$earnLastMTD,
            'unclaimedCommission'  => (float)$unclaimed,
            'unclaimedLastMTD'     => 0,
            'pointsPurchasePending'=> $pointsPending,
            'renewal_lte30'        => $renewal_lte30,
            'renewal_gt30'         => $renewal_gt30,
            'redemption_pending'   => $redemption,
            'trend_labels'         => $trend_labels,
            'trend_sales'          => $trend_sales,
            'trend_earn'           => $trend_earn,
            'vendors'              => $vendors,
            'products'             => $products,
            'topPerformers'        => $topPerformers,
        ]);
    }

    public function chartDrilldown(Request $request)
    {
        $type    = $request->get('type'); // vendors, products, trend
        $month   = (int)$request->get('month', now()->month);
        $year    = (int)$request->get('year',  now()->year);
        $page    = (int)$request->get('page', 1);
        $perPage = $type === 'trend' ? 12 : 10;

        $selectedDate = \Carbon\Carbon::createFromDate($year, $month, 1);
        $monthName    = $selectedDate->format('F Y');

        if ($type === 'vendors') {
            $all = DB::table('sales_transactions as st')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->where('st.is_deleted', false)
                ->whereMonth('st.created_at', $month)
                ->whereYear('st.created_at', $year)
                ->select('v.vendor_id', 'v.vendor_name', DB::raw('SUM(st.premium_amount) as total_sales'))
                ->groupBy('v.vendor_id', 'v.vendor_name')
                ->orderByDesc('total_sales')
                ->get();

            $total       = $all->sum('total_sales');
            $periodLabel = 'All Vendors — '.$monthName;
            $slice       = $all->slice(($page-1)*$perPage, $perPage)->values();
            $startRank   = ($page-1)*$perPage + 1;
            $rawLabels   = $slice->map(fn($r) => $r->vendor_name)->toArray();
            $labels      = $slice->map(fn($r, $i) => '#'.($startRank+$i).' '.$r->vendor_name)->toArray();
            $sales       = $slice->map(fn($r) => (float)$r->total_sales)->toArray();
            $links       = $slice->map(fn($r) => route('admin.dashboard.drilldown').'?type=vendor_gl&vendor_id='.$r->vendor_id.'&month='.$month.'&year='.$year)->toArray();

        } elseif ($type === 'products') {
            $all = DB::table('sales_transactions as st')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->where('st.is_deleted', false)
                ->whereMonth('st.created_at', $month)
                ->whereYear('st.created_at', $year)
                ->select('p.product_id', 'p.product_name', DB::raw('SUM(st.premium_amount) as total_sales'))
                ->groupBy('p.product_id', 'p.product_name')
                ->orderByDesc('total_sales')
                ->get();

            $total       = $all->sum('total_sales');
            $periodLabel = 'All Products — '.$monthName;
            $slice       = $all->slice(($page-1)*$perPage, $perPage)->values();
            $startRank   = ($page-1)*$perPage + 1;
            $rawLabels   = $slice->map(fn($r) => $r->product_name)->toArray();
            $labels      = $slice->map(fn($r, $i) => '#'.($startRank+$i).' '.$r->product_name)->toArray();
            $sales       = $slice->map(fn($r) => (float)$r->total_sales)->toArray();
            $links       = $slice->map(fn($r) => route('admin.dashboard.drilldown').'?type=product_gl&product_id='.$r->product_id.'&month='.$month.'&year='.$year)->toArray();

        } elseif ($type === 'trend') {
            $all = DB::table('sales_transactions as st')
                ->where('st.is_deleted', false)
                ->whereYear('st.created_at', $year)
                ->selectRaw("DATE_FORMAT(st.created_at,'%M %Y') as month_name, MONTH(st.created_at) as month_num, SUM(st.premium_amount) as total_sales")
                ->groupByRaw("YEAR(st.created_at), MONTH(st.created_at), DATE_FORMAT(st.created_at,'%M %Y')")
                ->orderByDesc('total_sales')
                ->get();

            $total       = $all->sum('total_sales');
            $periodLabel = 'Monthly Sales Ranking — '.$year;
            $slice       = $all->slice(($page-1)*$perPage, $perPage)->values();
            $startRank   = ($page-1)*$perPage + 1;
            $rawLabels   = $slice->map(fn($r) => $r->month_name)->toArray();
            $labels      = $slice->map(fn($r, $i) => '#'.($startRank+$i).' '.$r->month_name)->toArray();
            $sales       = $slice->map(fn($r) => (float)$r->total_sales)->toArray();
            $links       = $slice->map(fn($r) => route('admin.dashboard.drilldown').'?type=sales&period=mtd&month='.$r->month_num.'&year='.$year)->toArray();
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
        $type     = $request->get('type', 'sales'); // sales, earnings, unclaimed
        $period   = $request->get('period', 'mtd'); // mtd, last_mtd, ytd
        $month    = (int)$request->get('month', now()->month);
        $year     = (int)$request->get('year',  now()->year);

        $selectedDate = \Carbon\Carbon::createFromDate($year, $month, 1);
        $lastMonth    = $selectedDate->copy()->subMonth()->month;
        $lastYear     = $selectedDate->copy()->subMonth()->year;
        $monthName    = $selectedDate->format('F Y');
        $lastMonthName = $selectedDate->copy()->subMonth()->format('F Y');

        if ($type === 'performers') {
            // Ranked list of all agents by sales amount for selected month
            $records = DB::table('agents as a')
                ->where('a.is_deleted', false)
                ->where('a.status', 'ACTIVE')
                ->leftJoin('sales_transactions as st', function($join) use ($month, $year) {
                    $join->on('st.agent_id', '=', 'a.agent_id')
                         ->where('st.is_deleted', false)
                         ->whereMonth('st.created_at', $month)
                         ->whereYear('st.created_at', $year);
                })
                ->select(
                    'a.agent_id', 'a.full_name', 'a.agent_code', 'a.role',
                    DB::raw('COALESCE(SUM(st.premium_amount), 0) as total_sales'),
                    DB::raw('COUNT(DISTINCT st.policy_id) as total_transactions')
                )
                ->groupBy('a.agent_id', 'a.full_name', 'a.agent_code', 'a.role')
                ->orderByDesc('total_sales')
                ->paginate(15)->withQueryString();

            $total       = DB::table('sales_transactions')->where('is_deleted', false)->whereMonth('created_at', $month)->whereYear('created_at', $year)->sum('premium_amount');
            $periodLabel = 'All Performers — '.$monthName;
            $columns     = ['Agent', 'Code', 'Role', 'Sales Amount (RM)', 'Transactions'];
            $viewType    = 'ranking_performers';

        } elseif ($type === 'vendors') {
            // Ranked list of all vendors by sales amount
            $records = DB::table('sales_transactions as st')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->where('st.is_deleted', false)
                ->whereMonth('st.created_at', $month)
                ->whereYear('st.created_at', $year)
                ->select(
                    'v.vendor_id', 'v.vendor_name',
                    DB::raw('COALESCE(SUM(st.premium_amount), 0) as total_sales'),
                    DB::raw('COUNT(DISTINCT st.policy_id) as total_transactions')
                )
                ->groupBy('v.vendor_id', 'v.vendor_name')
                ->orderByDesc('total_sales')
                ->paginate(15)->withQueryString();

            $total       = DB::table('sales_transactions')->where('is_deleted', false)->whereMonth('created_at', $month)->whereYear('created_at', $year)->sum('premium_amount');
            $periodLabel = 'All Vendors — '.$monthName;
            $columns     = ['Vendor', 'Sales Amount (RM)', 'Transactions'];
            $viewType    = 'ranking_vendors';

        } elseif ($type === 'vendor_gl') {
            // GL breakdown for a specific vendor
            $vendorId   = $request->get('vendor_id');
            $vendorName = DB::table('vendors')->where('vendor_id', $vendorId)->value('vendor_name');
            $records = DB::table('agents as gl')
                ->where('gl.role', 'GROUP_LEADER')
                ->where('gl.is_deleted', false)
                ->leftJoin('agents as members', 'members.group_id', '=', 'gl.group_id')
                ->leftJoin('sales_transactions as st', function($join) use ($vendorId, $month, $year) {
                    $join->on('st.agent_id', '=', 'members.agent_id')
                         ->where('st.vendor_id', $vendorId)
                         ->where('st.is_deleted', false)
                         ->whereMonth('st.created_at', $month)
                         ->whereYear('st.created_at', $year);
                })
                ->select(
                    'gl.agent_id', 'gl.full_name', 'gl.agent_code',
                    DB::raw('COALESCE(SUM(st.premium_amount), 0) as total_sales'),
                    DB::raw('COUNT(DISTINCT st.policy_id) as total_transactions')
                )
                ->groupBy('gl.agent_id', 'gl.full_name', 'gl.agent_code')
                ->orderByDesc('total_sales')
                ->paginate(15)->withQueryString();

            $total       = DB::table('sales_transactions')->where('vendor_id', $vendorId)->where('is_deleted', false)->whereMonth('created_at', $month)->whereYear('created_at', $year)->sum('premium_amount');
            $periodLabel = $vendorName.' — GL Breakdown — '.$monthName;
            $columns     = ['Group Leader', 'Code', 'Sales Amount (RM)', 'Transactions'];
            $viewType    = 'ranking_vendor_gl';

        } elseif ($type === 'products') {
            // Ranked list of all products by sales amount
            $records = DB::table('sales_transactions as st')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->where('st.is_deleted', false)
                ->whereMonth('st.created_at', $month)
                ->whereYear('st.created_at', $year)
                ->select(
                    'p.product_id', 'p.product_name',
                    DB::raw('COALESCE(SUM(st.premium_amount), 0) as total_sales'),
                    DB::raw('COUNT(DISTINCT st.policy_id) as total_transactions')
                )
                ->groupBy('p.product_id', 'p.product_name')
                ->orderByDesc('total_sales')
                ->paginate(15)->withQueryString();

            $total       = DB::table('sales_transactions')->where('is_deleted', false)->whereMonth('created_at', $month)->whereYear('created_at', $year)->sum('premium_amount');
            $periodLabel = 'All Products — '.$monthName;
            $columns     = ['Product', 'Sales Amount (RM)', 'Transactions'];
            $viewType    = 'ranking_products';

        } elseif ($type === 'product_gl') {
            // GL breakdown for a specific product
            $productId   = $request->get('product_id');
            $productName = DB::table('products')->where('product_id', $productId)->value('product_name');
            $records = DB::table('agents as gl')
                ->where('gl.role', 'GROUP_LEADER')
                ->where('gl.is_deleted', false)
                ->leftJoin('agents as members', 'members.group_id', '=', 'gl.group_id')
                ->leftJoin('sales_transactions as st', function($join) use ($productId, $month, $year) {
                    $join->on('st.agent_id', '=', 'members.agent_id')
                         ->where('st.product_id', $productId)
                         ->where('st.is_deleted', false)
                         ->whereMonth('st.created_at', $month)
                         ->whereYear('st.created_at', $year);
                })
                ->select(
                    'gl.agent_id', 'gl.full_name', 'gl.agent_code',
                    DB::raw('COALESCE(SUM(st.premium_amount), 0) as total_sales'),
                    DB::raw('COUNT(DISTINCT st.policy_id) as total_transactions')
                )
                ->groupBy('gl.agent_id', 'gl.full_name', 'gl.agent_code')
                ->orderByDesc('total_sales')
                ->paginate(15)->withQueryString();

            $total       = DB::table('sales_transactions')->where('product_id', $productId)->where('is_deleted', false)->whereMonth('created_at', $month)->whereYear('created_at', $year)->sum('premium_amount');
            $periodLabel = $productName.' — GL Breakdown — '.$monthName;
            $columns     = ['Group Leader', 'Code', 'Sales Amount (RM)', 'Transactions'];
            $viewType    = 'ranking_product_gl';

        } elseif ($type === 'trend') {
            // Monthly ranking by sales amount for the year
            $records = DB::table('sales_transactions as st')
                ->where('st.is_deleted', false)
                ->whereYear('st.created_at', $year)
                ->selectRaw("DATE_FORMAT(st.created_at,'%M %Y') as month_name, MONTH(st.created_at) as month_num, YEAR(st.created_at) as year_num, COALESCE(SUM(st.premium_amount),0) as total_sales, COUNT(DISTINCT st.policy_id) as total_transactions")
                ->groupByRaw("YEAR(st.created_at), MONTH(st.created_at), DATE_FORMAT(st.created_at,'%M %Y')")
                ->orderByDesc('total_sales')
                ->paginate(15)->withQueryString();

            $total       = DB::table('sales_transactions')->where('is_deleted', false)->whereYear('created_at', $year)->sum('premium_amount');
            $periodLabel = 'Monthly Sales Ranking — '.$year;
            $columns     = ['Month', 'Sales Amount (RM)', 'Transactions'];
            $viewType    = 'ranking_trend';

        } elseif ($type === 'renewal_lte30') {
            $query = DB::table('sales_transactions as st')
                ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
                ->where('st.status', 'PENDING_RENEWAL')
                ->whereNotNull('st.renewal_date')
                ->whereRaw('st.renewal_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)')
                ->whereRaw('st.renewal_date >= CURDATE()')
                ->where('st.is_deleted', false)
                ->select('st.created_at', 'v.vendor_name', 'p.product_name', 'a.agent_code',
                    'a.full_name as agent_name', 'c.full_name as customer_name',
                    'st.premium_amount', 'st.renewal_date', 'st.status');
            $periodLabel = 'Renewal Due ≤ 30 Days';
            $total = (clone $query)->sum('st.premium_amount');
            $records = $query->orderBy('st.renewal_date')->paginate(15)->withQueryString();
            $columns = ['Date', 'Vendor', 'Product', 'Code', 'Agent', 'Customer', 'Sales Amount (RM)', 'Renewal Date', 'Status'];

        } elseif ($type === 'renewal_gt30') {
            $query = DB::table('sales_transactions as st')
                ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
                ->where('st.status', 'PENDING_RENEWAL')
                ->whereNotNull('st.renewal_date')
                ->whereRaw('st.renewal_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)')
                ->where('st.is_deleted', false)
                ->select('st.created_at', 'v.vendor_name', 'p.product_name', 'a.agent_code',
                    'a.full_name as agent_name', 'c.full_name as customer_name',
                    'st.premium_amount', 'st.renewal_date', 'st.status');
            $periodLabel = 'Renewal Due > 30 Days';
            $total = (clone $query)->sum('st.premium_amount');
            $records = $query->orderBy('st.renewal_date')->paginate(15)->withQueryString();
            $columns = ['Date', 'Vendor', 'Product', 'Code', 'Agent', 'Customer', 'Sales Amount (RM)', 'Renewal Date', 'Status'];

        } elseif ($type === 'sales') {
            $query = DB::table('sales_transactions as st')
                ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
                ->where('st.is_deleted', false)
                ->select(
                    'st.policy_number', 'st.premium_amount', 'st.status', 'st.created_at',
                    'a.full_name as agent_name', 'a.agent_code', 'a.role',
                    'v.vendor_name', 'p.product_name',
                    'c.full_name as customer_name'
                );

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
            $columns = ['Date', 'Vendor', 'Product', 'Code', 'Agent', 'Customer', 'Sales Amount (RM)', 'Status'];

        } elseif ($type === 'earnings') {
            $query = DB::table('commission_transactions as ct')
                ->join('agents as a', 'ct.agent_id', '=', 'a.agent_id')
                ->join('sales_transactions as st', 'ct.policy_id', '=', 'st.policy_id')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
                ->where('ct.status', '!=', 'CANCELLED')
                ->select(
                    'ct.created_at',
                    'v.vendor_name',
                    'p.product_name',
                    'a.agent_code',
                    'a.full_name as agent_name',
                    'c.full_name as customer_name',
                    'ct.commission_amount',
                    'ct.status'
                );

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
            $columns = ['Date', 'Vendor', 'Product', 'Code', 'Agent', 'Customer', 'Earning (RM)', 'Status'];

        } else {
            // unclaimed
            $query = DB::table('commission_transactions as ct')
                ->join('agents as a', 'ct.agent_id', '=', 'a.agent_id')
                ->join('sales_transactions as st', 'ct.policy_id', '=', 'st.policy_id')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
                ->where('ct.status', 'PENDING')
                ->select(
                    'ct.created_at',
                    'v.vendor_name',
                    'p.product_name',
                    'a.agent_code',
                    'a.full_name as agent_name',
                    'c.full_name as customer_name',
                    'ct.commission_amount',
                    'ct.status'
                );

            $periodLabel = 'Unclaimed Earning';
            $total   = (clone $query)->sum('ct.commission_amount');
            $records = $query->orderBy('ct.created_at')->paginate(15)->withQueryString();
            $columns = ['Date', 'Vendor', 'Product', 'Code', 'Agent', 'Customer', 'Earning (RM)', 'Status'];
        }

        if (!isset($viewType)) $viewType = 'transactions';
        return view('dashboard.drilldown', compact(
            'records', 'total', 'columns', 'type', 'period',
            'periodLabel', 'month', 'year', 'monthName', 'viewType'
        ));
    }

    private function getTopPerformers(int $month, int $year): array
    {
        // ── GL: ranked by total group sales for selected month ──
        $glAgents = DB::table('agents')
            ->where('role', 'GROUP_LEADER')
            ->where('status', 'ACTIVE')
            ->where('is_deleted', false)
            ->get(['agent_id', 'full_name', 'agent_code', 'group_id']);

        $glSales = $glAgents->map(function($gl) use ($month, $year) {
            $total = DB::table('sales_transactions as st')
                ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
                ->where('a.group_id', $gl->group_id)
                ->whereMonth('st.created_at', $month)
                ->whereYear('st.created_at', $year)
                ->sum('st.premium_amount');
            return (object)[
                'agent_id'   => $gl->agent_id,
                'full_name'  => $gl->full_name,
                'agent_code' => $gl->agent_code,
                'total'      => (float)$total,
            ];
        })->sortByDesc('total')->values()->take(3)->toArray();

        $glEarn = DB::table('commission_transactions as ct')
            ->join('agents as a', 'ct.agent_id', '=', 'a.agent_id')
            ->selectRaw("a.agent_id, a.full_name, a.agent_code, SUM(ct.commission_amount) as total")
            ->where('a.role', 'GROUP_LEADER')
            ->where('a.status', 'ACTIVE')
            ->whereMonth('ct.created_at', $month)
            ->whereYear('ct.created_at', $year)
            ->groupBy('a.agent_id', 'a.full_name', 'a.agent_code')
            ->orderByDesc('total')
            ->limit(3)
            ->get()->toArray();

        // ── TL: ranked by own + introducers sales for selected month ──
        $tlAgents = DB::table('agents as tl')
            ->where('tl.role', 'TEAM_LEADER')
            ->where('tl.status', 'ACTIVE')
            ->where('tl.is_deleted', false)
            ->leftJoin('agents as gl', 'gl.agent_id', '=', 'tl.parent_id')
            ->select('tl.agent_id', 'tl.full_name', 'tl.agent_code',
                     'gl.agent_id as gl_id', 'gl.full_name as gl_name', 'gl.agent_code as gl_code')
            ->get();

        $tlSales = $tlAgents->map(function($tl) use ($month, $year) {
            $total = DB::table('sales_transactions as st')
                ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
                ->where(function($q) use ($tl) {
                    $q->where('a.parent_id', $tl->agent_id)
                      ->orWhere('st.agent_id', $tl->agent_id);
                })
                ->whereMonth('st.created_at', $month)
                ->whereYear('st.created_at', $year)
                ->sum('st.premium_amount');
            return (object)[
                'agent_id'   => $tl->agent_id,
                'full_name'  => $tl->full_name,
                'agent_code' => $tl->agent_code,
                'gl_id'      => $tl->gl_id,
                'gl_name'    => $tl->gl_name,
                'gl_code'    => $tl->gl_code,
                'total'      => (float)$total,
            ];
        })->sortByDesc('total')->values()->take(3)->toArray();

        $tlEarn = DB::table('commission_transactions as ct')
            ->join('agents as a', 'ct.agent_id', '=', 'a.agent_id')
            ->leftJoin('agents as gl', 'gl.agent_id', '=', 'a.parent_id')
            ->selectRaw("a.agent_id, a.full_name, a.agent_code,
                         gl.agent_id as gl_id, gl.full_name as gl_name, gl.agent_code as gl_code,
                         SUM(ct.commission_amount) as total")
            ->where('a.role', 'TEAM_LEADER')
            ->where('a.status', 'ACTIVE')
            ->whereMonth('ct.created_at', $month)
            ->whereYear('ct.created_at', $year)
            ->groupBy('a.agent_id','a.full_name','a.agent_code','gl.agent_id','gl.full_name','gl.agent_code')
            ->orderByDesc('total')
            ->limit(3)
            ->get()->toArray();

        // ── Intro: ranked by own + downline sales for selected month ──
        $introAgents = DB::table('agents as i')
            ->where('i.role', 'INTRODUCER')
            ->where('i.status', 'ACTIVE')
            ->where('i.is_deleted', false)
            ->leftJoin('agents as tl', 'tl.agent_id', '=', 'i.parent_id')
            ->leftJoin('agents as gl', 'gl.agent_id', '=', 'tl.parent_id')
            ->select('i.agent_id', 'i.full_name', 'i.agent_code',
                     'tl.agent_id as tl_id', 'tl.full_name as tl_name', 'tl.agent_code as tl_code',
                     'gl.agent_id as gl_id', 'gl.full_name as gl_name', 'gl.agent_code as gl_code')
            ->get();

        $introSales = $introAgents->map(function($i) use ($month, $year) {
            $total = DB::table('sales_transactions as st')
                ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
                ->where(function($q) use ($i) {
                    $q->where('st.agent_id', $i->agent_id)
                      ->orWhere('a.parent_id', $i->agent_id);
                })
                ->whereMonth('st.created_at', $month)
                ->whereYear('st.created_at', $year)
                ->sum('st.premium_amount');
            return (object)[
                'agent_id'   => $i->agent_id,
                'full_name'  => $i->full_name,
                'agent_code' => $i->agent_code,
                'tl_id'      => $i->tl_id,
                'tl_name'    => $i->tl_name,
                'tl_code'    => $i->tl_code,
                'gl_id'      => $i->gl_id,
                'gl_name'    => $i->gl_name,
                'gl_code'    => $i->gl_code,
                'total'      => (float)$total,
            ];
        })->sortByDesc('total')->values()->take(3)->toArray();

        $introEarn = DB::table('commission_transactions as ct')
            ->join('agents as a', 'ct.agent_id', '=', 'a.agent_id')
            ->leftJoin('agents as tl', 'tl.agent_id', '=', 'a.parent_id')
            ->leftJoin('agents as gl', 'gl.agent_id', '=', 'tl.parent_id')
            ->selectRaw("a.agent_id, a.full_name, a.agent_code,
                         tl.agent_id as tl_id, tl.full_name as tl_name, tl.agent_code as tl_code,
                         gl.agent_id as gl_id, gl.full_name as gl_name, gl.agent_code as gl_code,
                         SUM(ct.commission_amount) as total")
            ->where('a.role', 'INTRODUCER')
            ->where('a.status', 'ACTIVE')
            ->whereMonth('ct.created_at', $month)
            ->whereYear('ct.created_at', $year)
            ->groupBy('a.agent_id','a.full_name','a.agent_code',
                      'tl.agent_id','tl.full_name','tl.agent_code',
                      'gl.agent_id','gl.full_name','gl.agent_code')
            ->orderByDesc('total')
            ->limit(3)
            ->get()->toArray();

        return [
            'gl'    => ['sales' => $glSales,    'earnings' => $glEarn],
            'tl'    => ['sales' => $tlSales,    'earnings' => $tlEarn],
            'intro' => ['sales' => $introSales, 'earnings' => $introEarn],
        ];
    }
}
