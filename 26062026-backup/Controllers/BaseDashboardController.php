<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

abstract class BaseDashboardController extends Controller
{
    /**
     * Build the shared dashboard data for any role.
     * Each role's dashboard controller calls this and passes data to the view.
     */
    protected function dashboardData(): array
    {
        $agent = Auth::guard('agent')->user();

        return [
            'agent'        => $agent,
            'metrics'      => $this->getMetrics($agent),
            'orgTree'      => $this->getOrgTree($agent),
            'incomeMonthly'=> $this->getIncomeByMonth($agent),
            'recentPolicies'=> $this->getRecentPolicies($agent),
            'renewalAlerts' => $this->getRenewalAlerts($agent),
        ];
    }

    // -------------------------------------------------------
    // Metric cards — scoped by role
    // -------------------------------------------------------
    private function getMetrics(Agent $agent): array
    {
        $scope = $agent->visibleAgentsQuery();

        $totalAgents  = $scope->count();
        $activeAgents = (clone $scope)->where('status', 'ACTIVE')->count();

        // Commission this month
        $commThisMonth = DB::table('commission_transactions')
            ->whereIn('agent_id', $scope->pluck('agent_id'))
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at',  now()->year)
            ->where('status', 'CONFIRMED')
            ->sum('commission_amount');

        // Commission last month
        $commLastMonth = DB::table('commission_transactions')
            ->whereIn('agent_id', $scope->pluck('agent_id'))
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at',  now()->subMonth()->year)
            ->where('status', 'CONFIRMED')
            ->sum('commission_amount');

        $commGrowth = $commLastMonth > 0
            ? round((($commThisMonth - $commLastMonth) / $commLastMonth) * 100, 1)
            : 0;

        // Total policies this month
        $policiesThisMonth = DB::table('sales_transactions')
            ->whereIn('agent_id', $scope->pluck('agent_id'))
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at',  now()->year)
            ->where('status', 'ACTIVE')
            ->count();

        // Renewals due in 30 days
        $renewalsDue = DB::table('sales_transactions')
            ->whereIn('agent_id', $scope->pluck('agent_id'))
            ->whereBetween('renewal_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->whereIn('status', ['ACTIVE', 'PENDING_RENEWAL'])
            ->count();

        // Own reward points balance
        $pointsBalance = DB::table('reward_points_ledger')
            ->where('agent_id', $agent->agent_id)
            ->selectRaw('COALESCE(SUM(points_in) - SUM(points_out), 0) as balance')
            ->value('balance') ?? 0;

        return compact(
            'totalAgents', 'activeAgents',
            'commThisMonth', 'commLastMonth', 'commGrowth',
            'policiesThisMonth', 'renewalsDue', 'pointsBalance'
        );
    }

    // -------------------------------------------------------
    // Org tree — 3 levels deep max, scoped by role
    // -------------------------------------------------------
    private function getOrgTree(Agent $agent): array
    {
        if ($agent->isAdmin()) {
            // Admin sees all Group Leaders as roots
            $roots = Agent::where('role', 'GROUP_LEADER')
                          ->where('is_deleted', false)
                          ->get(['agent_id','full_name','role','status','member_code']);
        } elseif ($agent->isGroupLeader()) {
            // GL is the root
            $roots = collect([$agent]);
        } else {
            // TL or Introducer — start from self
            $roots = collect([$agent]);
        }

        return $roots->map(fn($r) => $this->buildNode($r, 0))->toArray();
    }

    private function buildNode(Agent $node, int $depth): array
    {
        $children = [];
        if ($depth < 3) {
            $children = Agent::where('parent_id', $node->agent_id)
                             ->where('is_deleted', false)
                             ->get(['agent_id','full_name','role','status','member_code','parent_id'])
                             ->map(fn($c) => $this->buildNode($c, $depth + 1))
                             ->toArray();
        }
        return [
            'id'         => $node->agent_id,
            'name'       => $node->full_name,
            'role'       => $node->role,
            'status'     => $node->status,
            'code'       => $node->member_code ?? $node->agent_code ?? '',
            'children'   => $children,
        ];
    }

    // -------------------------------------------------------
    // Income by month — last 6 months, split by role
    // -------------------------------------------------------
    private function getIncomeByMonth(Agent $agent): array
    {
        $agentIds = $agent->visibleAgentsQuery()->pluck('agent_id');

        $rows = DB::table('commission_transactions')
            ->whereIn('agent_id', $agentIds)
            ->where('status', 'CONFIRMED')
            ->whereDate('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->selectRaw("
                DATE_FORMAT(created_at, '%Y-%m') as period,
                role_at_transaction as role,
                SUM(commission_amount) as total
            ")
            ->groupBy('period', 'role_at_transaction')
            ->orderBy('period')
            ->get();

        // Build chart-ready structure
        $periods = collect();
        for ($i = 5; $i >= 0; $i--) {
            $periods->push(now()->subMonths($i)->format('M Y'));
        }

        $data = ['labels' => $periods->values()->toArray(), 'introducer' => [], 'team_leader' => [], 'group_leader' => []];

        foreach ($periods as $label) {
            $key = now()->subMonths(5 - $periods->search($label))->format('Y-m');
            $data['introducer'][]   = (float) $rows->where('period', $key)->where('role', 'INTRODUCER')->sum('total');
            $data['team_leader'][]  = (float) $rows->where('period', $key)->where('role', 'TEAM_LEADER')->sum('total');
            $data['group_leader'][] = (float) $rows->where('period', $key)->where('role', 'GROUP_LEADER')->sum('total');
        }

        return $data;
    }

    // -------------------------------------------------------
    // Recent 5 policies
    // -------------------------------------------------------
    private function getRecentPolicies(Agent $agent): array
    {
        $agentIds = $agent->visibleAgentsQuery()->pluck('agent_id');

        return DB::table('sales_transactions as st')
            ->join('agents as a', 'a.agent_id', '=', 'st.agent_id')
            ->join('vendors as v', 'v.vendor_id', '=', 'st.vendor_id')
            ->join('products as p', 'p.product_id', '=', 'st.product_id')
            ->join('customers as c', 'c.customer_id', '=', 'st.customer_id')
            ->whereIn('st.agent_id', $agentIds)
            ->select('st.policy_number','st.premium_amount','st.status','st.created_at',
                     'a.full_name as agent_name','v.vendor_name','p.product_name','c.full_name as customer_name')
            ->orderByDesc('st.created_at')
            ->limit(5)
            ->get()
            ->toArray();
    }

    // -------------------------------------------------------
    // Renewals due in 30 days
    // -------------------------------------------------------
    private function getRenewalAlerts(Agent $agent): array
    {
        $agentIds = $agent->visibleAgentsQuery()->pluck('agent_id');

        return DB::table('sales_transactions as st')
            ->join('customers as c', 'c.customer_id', '=', 'st.customer_id')
            ->join('agents as a', 'a.agent_id', '=', 'st.agent_id')
            ->whereIn('st.agent_id', $agentIds)
            ->whereBetween('st.renewal_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->whereIn('st.status', ['ACTIVE', 'PENDING_RENEWAL'])
            ->select('st.policy_number','st.renewal_date','st.premium_amount',
                     'c.full_name as customer_name','a.full_name as agent_name')
            ->orderBy('st.renewal_date')
            ->limit(10)
            ->get()
            ->toArray();
    }
}
