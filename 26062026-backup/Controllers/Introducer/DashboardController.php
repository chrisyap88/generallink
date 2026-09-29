<?php
namespace App\Http\Controllers\Introducer;
use App\Http\Controllers\Controller;
use App\Services\DataScopeService;
use App\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
class DashboardController extends Controller
{
    public function index()
    {
        $agent = auth('agent')->user();
        $scope = new DataScopeService();
        // Metrics — cached per Introducer
        $metrics = Cache::remember($scope->getCacheKey('dashboard_metrics'), 600, function () use ($agent) {
            $premium = DB::table('sales_transactions')
                ->where('agent_id', $agent->agent_id)
                ->where('is_deleted', false)
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN MONTH(created_at)=".now()->month." AND YEAR(created_at)=".now()->year." THEN premium_amount ELSE 0 END),0) as premium_mtd,
                    COALESCE(SUM(CASE WHEN YEAR(created_at)=".now()->year." THEN premium_amount ELSE 0 END),0) as premium_ytd,
                    COALESCE(SUM(premium_amount),0) as premium_total,
                    COUNT(*) as transactions_total,
                    COUNT(CASE WHEN MONTH(created_at)=".now()->month." AND YEAR(created_at)=".now()->year." THEN 1 END) as transactions_mtd
                ")
                ->first();
            $commission = DB::table('commission_transactions')
                ->where('agent_id', $agent->agent_id)
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN MONTH(created_at)=".now()->month." AND YEAR(created_at)=".now()->year." THEN commission_amount ELSE 0 END),0) as commission_mtd,
                    COALESCE(SUM(commission_amount),0) as commission_total
                ")
                ->first();
            // Chart — last 6 months own premium
            $months = collect(range(5,0))->map(fn($i) => now()->subMonths($i));
            $chartData = [
                'labels' => $months->map(fn($m) => $m->format('M Y'))->toArray(),
                'data'   => $months->map(fn($m) => (float) DB::table('sales_transactions')
                    ->where('agent_id', $agent->agent_id)
                    ->whereMonth('created_at', $m->month)
                    ->whereYear('created_at', $m->year)
                    ->where('is_deleted', false)
                    ->sum('premium_amount'))->toArray(),
            ];
            return compact('premium', 'commission', 'chartData');
        });
        // My transactions — own only, paginated 10 per page
        $transactions = DB::table('sales_transactions as st')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->leftJoin('commission_transactions as ct', function($join) use ($agent) {
                $join->on('ct.policy_id', '=', 'st.policy_id')
                     ->where('ct.agent_id', '=', $agent->agent_id);
            })
            ->where('st.agent_id', $agent->agent_id)
            ->where('st.is_deleted', false)
            ->select('st.policy_id', 'st.policy_number', 'st.premium_amount', 'st.status', 'st.created_at', 'v.vendor_name', 'p.product_name',
                DB::raw('COALESCE(ct.commission_amount,0) as my_commission'),
                DB::raw('COALESCE(ct.entitlement_pct,0) as my_pct'))
            ->orderByDesc('st.created_at')
            ->paginate(10, ['*'], 'txpage');

        // Grand totals (across all pages)
        $grandTotal = DB::table('sales_transactions')
            ->where('agent_id', $agent->agent_id)
            ->where('is_deleted', false)
            ->sum('premium_amount');
        $commissionGrandTotal = DB::table('commission_transactions')
            ->where('agent_id', $agent->agent_id)
            ->sum('commission_amount');
        // My upline chain
        $myTL = $agent->parent_id ? Agent::where('agent_id', $agent->parent_id)->first() : null;
        $myGL = $myTL && $myTL->parent_id ? Agent::where('agent_id', $myTL->parent_id)->first() : null;
        // Group info
        $group = $agent->group_id ? DB::table('groups')->where('group_id', $agent->group_id)->first() : null;
        // Active vendors (no offer/commission-rate columns exist on vendors table)
        $vendorOffers = DB::table('vendors')
            ->where('is_active', true)
            ->select('vendor_id', 'vendor_name', 'vendor_code', 'pic_name')
            ->orderBy('vendor_name')
            ->limit(10)
            ->get();
        // Reward points balance
        $rewardPoints = DB::table('reward_points_ledger')
            ->where('agent_id', $agent->agent_id)
            ->orderByDesc('created_at')
            ->value('running_balance') ?? 0;

        // My direct recruits count
        $recruitCount = DB::table('agents')
            ->where('parent_id', $agent->agent_id)
            ->where('role', 'INTRODUCER')
            ->where('is_deleted', false)
            ->count();

        return view('dashboard.introducer', compact('agent', 'group', 'myTL', 'myGL', 'metrics', 'transactions', 'vendorOffers', 'rewardPoints', 'grandTotal', 'commissionGrandTotal', 'recruitCount'));
    }
}
