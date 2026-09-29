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

        // NEW 5 Aug 2026 — Integration Hub vault indicator, admin-assist
        // path. Admin can never decrypt this agent's connected keys (by
        // design), but CAN clear a stuck/forgotten vault for them if the
        // agent can't do the self-service reset themselves (e.g. they're
        // also locked out of their account) — see clearHubVault() below.
        $hasHubVault = !empty($agent->hub_vault_key_encrypted);

        return view('admin.agents.profile', compact(
            'agent', 'upline', 'downlines', 'metrics',
            'commission', 'recentTransactions', 'beneficiaries', 'hasHubVault'
        ));
    }

    /**
     * Admin-assisted wipe of an agent's Hub vault — used when the agent
     * can't do the self-service reset themselves. Admin cannot read what
     * was in there (never could); this just clears the lock so the agent
     * can set a fresh Hub password next time they log in. Every one of
     * their connected integrations is erased along with it, same as the
     * self-service path.
     */
    public function clearHubVault(string $agentId)
    {
        DB::table('agents')->where('agent_id', $agentId)->update([
            'hub_password_hash' => null,
            'hub_vault_salt' => null,
            'hub_vault_key_encrypted' => null,
            'hub_vault_created_at' => null,
        ]);
        DB::table('agent_integrations')->where('agent_id', $agentId)->delete();

        return back()->with('success', "This agent's Integration Hub vault was cleared. They'll be asked to set up a new Hub password next time they visit it.");
    }
}
