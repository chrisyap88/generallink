<?php

namespace App\Http\Controllers\GL;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        return view('gl.dashboard');
    }

    public function metrics(Request $request)
    {
        $gl      = auth('agent')->user();
        $groupId = $gl->group_id;
        $month   = (int)$request->get('month', now()->month);
        $year    = (int)$request->get('year',  now()->year);

        $selDate   = Carbon::createFromDate($year, $month, 1);
        $lastMonth = $selDate->copy()->subMonth()->month;
        $lastYear  = $selDate->copy()->subMonth()->year;

        // All agent IDs in this group
        $agentIds = DB::table('agents')
            ->where('group_id', $groupId)
            ->where('is_deleted', false)
            ->pluck('agent_id')->toArray();

        // ── Network Overview ────────────────────────────────────
        $tlCount    = DB::table('agents')->where('group_id', $groupId)->where('role', 'TEAM_LEADER')->where('is_deleted', false)->count();
        $introCount = DB::table('agents')->where('group_id', $groupId)->where('role', 'INTRODUCER')->where('is_deleted', false)->count();
        $activeCount   = DB::table('agents')->where('group_id', $groupId)->where('status', 'ACTIVE')->where('is_deleted', false)->count();
        $inactiveCount = DB::table('agents')->where('group_id', $groupId)->where('status', '!=', 'ACTIVE')->where('is_deleted', false)->count();

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

        // ── Top 3 Performers ────────────────────────────────────
        $performers = DB::table('agents as a')
            ->where('a.group_id', $groupId)
            ->where('a.role', '!=', 'GROUP_LEADER')
            ->where('a.is_deleted', false)
            ->leftJoin('sales_transactions as st', function($j) use ($month, $year) {
                $j->on('st.agent_id', '=', 'a.agent_id')
                  ->where('st.is_deleted', false)
                  ->whereMonth('st.created_at', $month)
                  ->whereYear('st.created_at', $year);
            })
            ->select('a.full_name', 'a.agent_code', 'a.role', DB::raw('COALESCE(SUM(st.premium_amount),0) as total_sales'))
            ->groupBy('a.agent_id', 'a.full_name', 'a.agent_code', 'a.role')
            ->orderByDesc('total_sales')
            ->limit(3)->get();

        // ── Top 3 Vendors ────────────────────────────────────────
        $vendors = DB::table('sales_transactions as st')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->whereIn('st.agent_id', $agentIds)->where('st.is_deleted', false)
            ->whereMonth('st.created_at', $month)->whereYear('st.created_at', $year)
            ->select('v.vendor_name as name', DB::raw('SUM(st.premium_amount) as sales'), DB::raw('0 as earn'))
            ->groupBy('v.vendor_id', 'v.vendor_name')
            ->orderByDesc('sales')->limit(3)->get();

        // ── Top 3 Products ───────────────────────────────────────
        $products = DB::table('sales_transactions as st')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->whereIn('st.agent_id', $agentIds)->where('st.is_deleted', false)
            ->whereMonth('st.created_at', $month)->whereYear('st.created_at', $year)
            ->select('p.product_name as name', DB::raw('SUM(st.premium_amount) as sales'), DB::raw('0 as earn'))
            ->groupBy('p.product_id', 'p.product_name')
            ->orderByDesc('sales')->limit(3)->get();

        // ── Top 3 Months trend ───────────────────────────────────
        $trend = DB::table('sales_transactions as st')
            ->whereIn('st.agent_id', $agentIds)->where('st.is_deleted', false)
            ->whereYear('st.created_at', $year)
            ->selectRaw("DATE_FORMAT(st.created_at,'%b %Y') as name, SUM(st.premium_amount) as sales, 0 as earn")
            ->groupByRaw("YEAR(st.created_at), MONTH(st.created_at), DATE_FORMAT(st.created_at,'%b %Y')")
            ->orderByRaw("YEAR(st.created_at), MONTH(st.created_at)")
            ->limit(3)->get();

        return response()->json([
            'tlCount'           => $tlCount,
            'introCount'        => $introCount,
            'activeCount'       => $activeCount,
            'inactiveCount'     => $inactiveCount,
            'totalPremiumMonth' => $salesMTD,
            'salesLastMTD'      => $salesLastMTD,
            'totalPremiumYTD'   => $salesYTD,
            'commDistributedMonth' => $earnMTD,
            'earnLastMTD'       => $earnLastMTD,
            'commDistributedYTD'=> $earnYTD,
            'unclaimedCommission' => $unclaimed,
            'unclaimedLastMTD'  => $unclaimed,
            'renewal_lte30'     => $renewLte30,
            'renewal_gt30'      => $renewGt30,
            'redemption_pending'=> 0,
            'pointsPurchasePending' => 0,
            'topPerformers'     => $performers,
            'vendors'           => $vendors,
            'products'          => $products,
            'trend'             => $trend,
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


    private function getAgentIds()
    {
        $gl = auth('agent')->user();
        return DB::table('agents')->where('group_id', $gl->group_id)->where('is_deleted', false)->pluck('agent_id')->toArray();
    }

    public function drilldown(Request $request)
    {
        $agentIds = $this->getAgentIds();
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
                         ->whereIn('st.agent_id', \$agentIds)->where('st.is_deleted', false)
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
            $columns     = [__('gl.col_agent'), __('network.col_code'), __('gl.col_role'), __('drilldown.col_sales_amount_rm'), __('gl.col_transactions')];
            $viewType    = 'ranking_performers';

        } elseif ($type === 'vendors') {
            // Ranked list of all vendors by sales amount
            $records = DB::table('sales_transactions as st')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->whereIn('st.agent_id', \$agentIds)->where('st.is_deleted', false)
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
            $columns     = [__('network.col_vendor'), __('drilldown.col_sales_amount_rm'), __('gl.col_transactions')];
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
                         ->whereIn('st.agent_id', \$agentIds)->where('st.is_deleted', false)
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
            $columns     = [__('gl.role_group_leader'), __('network.col_code'), __('drilldown.col_sales_amount_rm'), __('gl.col_transactions')];
            $viewType    = 'ranking_vendor_gl';

        } elseif ($type === 'products') {
            // Ranked list of all products by sales amount
            $records = DB::table('sales_transactions as st')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->whereIn('st.agent_id', \$agentIds)->where('st.is_deleted', false)
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
            $columns     = [__('network.col_product'), __('drilldown.col_sales_amount_rm'), __('gl.col_transactions')];
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
                         ->whereIn('st.agent_id', \$agentIds)->where('st.is_deleted', false)
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
            $columns     = [__('gl.role_group_leader'), __('network.col_code'), __('drilldown.col_sales_amount_rm'), __('gl.col_transactions')];
            $viewType    = 'ranking_product_gl';

        } elseif ($type === 'trend') {
            // Monthly ranking by sales amount for the year
            $records = DB::table('sales_transactions as st')
                ->whereIn('st.agent_id', \$agentIds)->where('st.is_deleted', false)
                ->whereYear('st.created_at', $year)
                ->selectRaw("DATE_FORMAT(st.created_at,'%M %Y') as month_name, MONTH(st.created_at) as month_num, YEAR(st.created_at) as year_num, COALESCE(SUM(st.premium_amount),0) as total_sales, COUNT(DISTINCT st.policy_id) as total_transactions")
                ->groupByRaw("YEAR(st.created_at), MONTH(st.created_at), DATE_FORMAT(st.created_at,'%M %Y')")
                ->orderByDesc('total_sales')
                ->paginate(15)->withQueryString();

            $total       = DB::table('sales_transactions')->where('is_deleted', false)->whereYear('created_at', $year)->sum('premium_amount');
            $periodLabel = 'Monthly Sales Ranking — '.$year;
            $columns     = [__('renewal_forecast.col_month'), __('drilldown.col_sales_amount_rm'), __('gl.col_transactions')];
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
                ->whereIn('st.agent_id', \$agentIds)->where('st.is_deleted', false)
                ->select('st.created_at', 'v.vendor_name', 'p.product_name', 'a.agent_code',
                    'a.full_name as agent_name', 'c.full_name as customer_name',
                    'st.premium_amount', 'st.renewal_date', 'st.status');
            $periodLabel = 'Renewal Due ≤ 30 Days';
            $total = (clone $query)->sum('st.premium_amount');
            $records = $query->orderBy('st.renewal_date')->paginate(15)->withQueryString();
            $columns = [__('gl.col_date'), __('network.col_vendor'), __('network.col_product'), __('network.col_code'), __('gl.col_agent'), __('gl.col_customer'), __('drilldown.col_sales_amount_rm'), __('gl.renewal_date_label'), __('network.col_status')];

        } elseif ($type === 'renewal_gt30') {
            $query = DB::table('sales_transactions as st')
                ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
                ->where('st.status', 'PENDING_RENEWAL')
                ->whereNotNull('st.renewal_date')
                ->whereRaw('st.renewal_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)')
                ->whereIn('st.agent_id', \$agentIds)->where('st.is_deleted', false)
                ->select('st.created_at', 'v.vendor_name', 'p.product_name', 'a.agent_code',
                    'a.full_name as agent_name', 'c.full_name as customer_name',
                    'st.premium_amount', 'st.renewal_date', 'st.status');
            $periodLabel = 'Renewal Due > 30 Days';
            $total = (clone $query)->sum('st.premium_amount');
            $records = $query->orderBy('st.renewal_date')->paginate(15)->withQueryString();
            $columns = [__('gl.col_date'), __('network.col_vendor'), __('network.col_product'), __('network.col_code'), __('gl.col_agent'), __('gl.col_customer'), __('drilldown.col_sales_amount_rm'), __('gl.renewal_date_label'), __('network.col_status')];

        } elseif ($type === 'sales') {
            $query = DB::table('sales_transactions as st')
                ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
                ->whereIn('st.agent_id', \$agentIds)->where('st.is_deleted', false)
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
            $columns = [__('gl.col_date'), __('network.col_vendor'), __('network.col_product'), __('network.col_code'), __('gl.col_agent'), __('gl.col_customer'), __('drilldown.col_sales_amount_rm'), __('network.col_status')];

        } elseif ($type === 'earnings') {
            $query = DB::table('commission_transactions as ct')
                ->join('agents as a', 'ct.agent_id', '=', 'a.agent_id')
                ->join('sales_transactions as st', 'ct.policy_id', '=', 'st.policy_id')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
                ->whereIn('ct.agent_id', \$agentIds)->where('ct.status', '!=', 'CANCELLED')
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
            $columns = [__('gl.col_date'), __('network.col_vendor'), __('network.col_product'), __('network.col_code'), __('gl.col_agent'), __('gl.col_customer'), __('drilldown.col_earning_rm'), __('network.col_status')];

        } else {
            // unclaimed
            $query = DB::table('commission_transactions as ct')
                ->join('agents as a', 'ct.agent_id', '=', 'a.agent_id')
                ->join('sales_transactions as st', 'ct.policy_id', '=', 'st.policy_id')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
                ->whereIn('ct.agent_id', \$agentIds)->where('ct.status', 'PENDING')
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
            $columns = [__('gl.col_date'), __('network.col_vendor'), __('network.col_product'), __('network.col_code'), __('gl.col_agent'), __('gl.col_customer'), __('drilldown.col_earning_rm'), __('network.col_status')];
        }

        if (!isset($viewType)) $viewType = 'transactions';
        return view('dashboard.drilldown', compact(
            'records', 'total', 'columns', 'type', 'period',
            'periodLabel', 'month', 'year', 'monthName', 'viewType'
        ));
    }

}
