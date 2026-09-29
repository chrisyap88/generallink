<?php

namespace App\Http\Controllers\TL;

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

        // Group + sub-team info
        $group = $agent->group_id ? DB::table('groups')->where('group_id', $agent->group_id)->first() : null;

        // Metrics — cached per TL
        $metrics = Cache::remember($scope->getCacheKey('dashboard_metrics'), 600, function () use ($agent, $scope) {

            $agentIds = $scope->getAgentIds();

            // Introducer counts — his direct team only
            $counts = DB::table('agents')
                ->where('parent_id', $agent->agent_id)
                ->where('role', 'INTRODUCER')
                ->where('is_deleted', false)
                ->selectRaw("
                    COUNT(*) as total_intro,
                    SUM(CASE WHEN status='ACTIVE' THEN 1 ELSE 0 END) as active_intro,
                    SUM(CASE WHEN status!='ACTIVE' THEN 1 ELSE 0 END) as inactive_intro,
                    SUM(CASE WHEN MONTH(created_at)=".now()->month." AND YEAR(created_at)=".now()->year." THEN 1 ELSE 0 END) as new_this_month
                ")
                ->first();

            // Premium — his team only
            $premium = DB::table('sales_transactions')
                ->whereIn('agent_id', $agentIds)
                ->where('is_deleted', false)
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN MONTH(created_at)=".now()->month." AND YEAR(created_at)=".now()->year." THEN premium_amount ELSE 0 END),0) as premium_mtd,
                    COALESCE(SUM(CASE WHEN YEAR(created_at)=".now()->year." THEN premium_amount ELSE 0 END),0) as premium_ytd,
                    COUNT(CASE WHEN MONTH(created_at)=".now()->month." AND YEAR(created_at)=".now()->year." THEN 1 END) as transactions_mtd
                ")
                ->first();

            // Commission — his own
            $commission = DB::table('commission_transactions')
                ->where('agent_id', $agent->agent_id)
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN MONTH(created_at)=".now()->month." AND YEAR(created_at)=".now()->year." THEN commission_amount ELSE 0 END),0) as commission_mtd,
                    COALESCE(SUM(commission_amount),0) as commission_total
                ")
                ->first();

            // Top 5 Introducers — his team only
            $topIntroducers = DB::table('agents as i')
                ->where('i.parent_id', $agent->agent_id)
                ->where('i.role', 'INTRODUCER')
                ->where('i.is_deleted', false)
                ->leftJoin('sales_transactions as st', function($join) {
                    $join->on('st.agent_id', '=', 'i.agent_id')
                         ->where('st.is_deleted', false)
                         ->whereMonth('st.created_at', now()->month)
                         ->whereYear('st.created_at', now()->year);
                })
                ->select('i.agent_id', 'i.full_name', 'i.agent_code', 'i.status',
                    DB::raw('COALESCE(SUM(st.premium_amount),0) as total_premium'),
                    DB::raw('COUNT(st.policy_id) as total_transactions'))
                ->groupBy('i.agent_id', 'i.full_name', 'i.agent_code', 'i.status')
                ->orderByDesc('total_premium')
                ->limit(5)
                ->get();

            // Chart — last 6 months premium
            $months = collect(range(5,0))->map(fn($i) => now()->subMonths($i));
            $chartData = [
                'labels' => $months->map(fn($m) => $m->format('M Y'))->toArray(),
                'data'   => $months->map(fn($m) => (float) DB::table('sales_transactions')
                    ->whereIn('agent_id', $agentIds)
                    ->whereMonth('created_at', $m->month)
                    ->whereYear('created_at', $m->year)
                    ->where('is_deleted', false)
                    ->sum('premium_amount'))->toArray(),
            ];

            return compact('counts', 'premium', 'commission', 'topIntroducers', 'chartData');
        });

        // Recent transactions — his team, paginated 5 per page
        $recentTransactions = DB::table('sales_transactions as st')
            ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->whereIn('st.agent_id', $scope->getAgentIds())
            ->where('st.is_deleted', false)
            ->select('st.policy_number', 'st.premium_amount', 'st.status', 'st.created_at', 'a.full_name as agent_name', 'v.vendor_name')
            ->orderByDesc('st.created_at')
            ->paginate(5, ['*'], 'txpage');

        // My upline GL
        $myGL = Agent::where('agent_id', $agent->parent_id)->first();

        // Reward points balance (latest running_balance from ledger)
        $rewardPoints = DB::table('reward_points_ledger')
            ->where('agent_id', $agent->agent_id)
            ->orderByDesc('created_at')
            ->value('running_balance') ?? 0;

        return view('dashboard.tl', compact('agent', 'group', 'myGL', 'metrics', 'recentTransactions', 'rewardPoints'));
    }
}
