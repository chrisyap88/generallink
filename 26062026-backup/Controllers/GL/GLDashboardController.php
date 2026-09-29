<?php

namespace App\Http\Controllers\GL;

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

        // Group info
        $group = DB::table('groups')
            ->where('agent_id', $agent->agent_id)
            ->first();

        // Metrics — cached 10 minutes per GL
        $metrics = Cache::remember($scope->getCacheKey('dashboard_metrics'), 600, function () use ($agent, $scope) {

            $agentIds = $scope->getAgentIds();

            // Network counts — only his group
            $counts = DB::table('agents')
                ->where('group_id', $agent->group_id)
                ->where('is_deleted', false)
                ->selectRaw("
                    SUM(CASE WHEN role='TEAM_LEADER' AND status='ACTIVE' THEN 1 ELSE 0 END) as total_tl,
                    SUM(CASE WHEN role='INTRODUCER' AND status='ACTIVE' THEN 1 ELSE 0 END) as total_intro,
                    SUM(CASE WHEN status='ACTIVE' THEN 1 ELSE 0 END) as total_active,
                    SUM(CASE WHEN status!='ACTIVE' THEN 1 ELSE 0 END) as total_inactive,
                    SUM(CASE WHEN MONTH(created_at)=".now()->month." AND YEAR(created_at)=".now()->year." THEN 1 ELSE 0 END) as new_this_month
                ")
                ->first();

            // Premium — his group only
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

            // Top 5 TLs — his group only
            $topTLs = DB::table('agents as tl')
                ->where('tl.parent_id', $agent->agent_id)
                ->where('tl.role', 'TEAM_LEADER')
                ->where('tl.is_deleted', false)
                ->leftJoin('sales_transactions as st', function($join) {
                    $join->on('st.agent_id', '=', 'tl.agent_id')
                         ->where('st.is_deleted', false)
                         ->whereMonth('st.created_at', now()->month)
                         ->whereYear('st.created_at', now()->year);
                })
                ->select('tl.agent_id', 'tl.full_name', 'tl.agent_code', 'tl.status',
                    DB::raw('COALESCE(SUM(st.premium_amount),0) as total_premium'),
                    DB::raw('COUNT(st.policy_id) as total_transactions'))
                ->groupBy('tl.agent_id', 'tl.full_name', 'tl.agent_code', 'tl.status')
                ->orderByDesc('total_premium')
                ->limit(5)
                ->get();

            // Chart — premium by TL last 6 months
            $months = collect(range(5,0))->map(fn($i) => now()->subMonths($i));
            $tlIds = DB::table('agents')->where('parent_id', $agent->agent_id)->where('role','TEAM_LEADER')->pluck('agent_id')->toArray();

            $chartData = [
                'labels' => $months->map(fn($m) => $m->format('M Y'))->toArray(),
                'data'   => $months->map(fn($m) => (float) DB::table('sales_transactions')
                    ->whereIn('agent_id', $agentIds)
                    ->whereMonth('created_at', $m->month)
                    ->whereYear('created_at', $m->year)
                    ->where('is_deleted', false)
                    ->sum('premium_amount'))->toArray(),
            ];

            return compact('counts', 'premium', 'commission', 'topTLs', 'chartData');
        });

        // Recent transactions — his group, last 10
        $recentTransactions = DB::table('sales_transactions as st')
            ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->whereIn('st.agent_id', $scope->getAgentIds())
            ->where('st.is_deleted', false)
            ->select('st.policy_number', 'st.premium_amount', 'st.status', 'st.created_at', 'a.full_name as agent_name', 'v.vendor_name')
            ->orderByDesc('st.created_at')
            ->limit(10)
            ->get();

        return view('dashboard.gl', compact('agent', 'group', 'metrics', 'recentTransactions'));
    }
}
