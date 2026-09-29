<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Support\Facades\DB;

class AgentProfileController extends Controller
{
    public function show(string $agentId)
    {
        // Get full agent details
        $agent = Agent::where('agent_id', $agentId)
            ->where('is_deleted', false)
            ->firstOrFail();

        // Get upline agent
        $upline = $agent->parent_id
            ? Agent::where('agent_id', $agent->parent_id)
                ->select('agent_id', 'full_name', 'agent_code', 'role', 'status')
                ->first()
            : null;

        // Get direct downlines
        $downlines = Agent::where('parent_id', $agentId)
            ->where('is_deleted', false)
            ->select('agent_id', 'full_name', 'agent_code', 'role', 'status', 'created_at')
            ->orderBy('full_name')
            ->paginate(10);

        // Performance metrics
        $metrics = DB::table('sales_transactions')
            ->where('agent_id', $agentId)
            ->where('is_deleted', false)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN MONTH(created_at)=".now()->month." AND YEAR(created_at)=".now()->year." THEN premium_amount ELSE 0 END), 0) as premium_mtd,
                COALESCE(SUM(CASE WHEN YEAR(created_at)=".now()->year." THEN premium_amount ELSE 0 END), 0) as premium_ytd,
                COALESCE(SUM(premium_amount), 0) as premium_total,
                COUNT(CASE WHEN MONTH(created_at)=".now()->month." AND YEAR(created_at)=".now()->year." THEN 1 END) as transactions_mtd,
                COUNT(*) as transactions_total
            ")
            ->first();

        // Commission metrics
        $commission = DB::table('commission_transactions')
            ->where('agent_id', $agentId)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN MONTH(created_at)=".now()->month." AND YEAR(created_at)=".now()->year." THEN commission_amount ELSE 0 END), 0) as commission_mtd,
                COALESCE(SUM(commission_amount), 0) as commission_total
            ")
            ->first();

        // Recent transactions
        $recentTransactions = DB::table('sales_transactions as st')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->where('st.agent_id', $agentId)
            ->where('st.is_deleted', false)
            ->select(
                'st.policy_number',
                'st.premium_amount',
                'st.status',
                'st.created_at',
                'v.vendor_name',
                'p.product_name',
                'c.full_name as customer_name'
            )
            ->orderByDesc('st.created_at')
            ->limit(5)
            ->get();

        // Beneficiaries
        $beneficiaries = DB::table('beneficiaries')
            ->where('agent_id', $agentId)
            ->get();

        return view('admin.agents.profile', compact(
            'agent', 'upline', 'downlines', 'metrics',
            'commission', 'recentTransactions', 'beneficiaries'
        ));
    }
}
