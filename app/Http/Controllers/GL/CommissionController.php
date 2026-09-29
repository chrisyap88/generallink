<?php

namespace App\Http\Controllers\GL;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $agent = auth('agent')->user();

        // Get all agent IDs in this GL's group
        $agentIds = DB::table('agents')
            ->where('group_id', $agent->group_id)
            ->pluck('agent_id')
            ->toArray();

        // ── Summary Cards ──────────────────────────────────────────
        $totalEarned = DB::table('commission_transactions')
            ->where('agent_id', $agent->agent_id)
            ->whereIn('status', ['CONFIRMED', 'PAID'])
            ->sum('commission_amount');

        $totalPending = DB::table('commission_transactions')
            ->where('agent_id', $agent->agent_id)
            ->where('status', 'PENDING')
            ->sum('commission_amount');

        $totalHeld = DB::table('commission_hold_log')
            ->where('agent_id', $agent->agent_id)
            ->where('status', 'HELD')
            ->sum('held_amount');

        $walletBalance = DB::table('commission_transactions')
            ->where('agent_id', $agent->agent_id)
            ->where('status', 'CONFIRMED')
            ->sum('commission_amount');

        // ── Group Summary ──────────────────────────────────────────
        $groupTotalEarned = DB::table('commission_transactions')
            ->whereIn('agent_id', $agentIds)
            ->whereIn('status', ['CONFIRMED', 'PAID'])
            ->sum('commission_amount');

        // ── Breakdown by Product Type ──────────────────────────────
        $byProductType = DB::table('commission_transactions as ct')
            ->join('sales_transactions as st', 'ct.policy_id', '=', 'st.policy_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->where('ct.agent_id', $agent->agent_id)
            ->whereIn('ct.status', ['CONFIRMED', 'PAID', 'PENDING'])
            ->groupBy('p.product_type')
            ->select(
                'p.product_type',
                DB::raw('COUNT(*) as txn_count'),
                DB::raw('SUM(ct.commission_amount) as total_commission'),
                DB::raw('SUM(CASE WHEN ct.status IN ("CONFIRMED","PAID") THEN ct.commission_amount ELSE 0 END) as earned'),
                DB::raw('SUM(CASE WHEN ct.status = "PENDING" THEN ct.commission_amount ELSE 0 END) as pending')
            )
            ->get();

        // ── Breakdown by Role ──────────────────────────────────────
        $byRole = DB::table('commission_transactions as ct')
            ->whereIn('ct.agent_id', $agentIds)
            ->whereIn('ct.status', ['CONFIRMED', 'PAID', 'PENDING'])
            ->groupBy('ct.role_at_transaction')
            ->select(
                'ct.role_at_transaction',
                DB::raw('COUNT(*) as txn_count'),
                DB::raw('SUM(ct.commission_amount) as total_commission'),
                DB::raw('SUM(CASE WHEN ct.status IN ("CONFIRMED","PAID") THEN ct.commission_amount ELSE 0 END) as earned'),
                DB::raw('SUM(CASE WHEN ct.status = "PENDING" THEN ct.commission_amount ELSE 0 END) as pending')
            )
            ->get();

        // ── Held Items (simplified — no ref_no join) ───────────────
        $heldItems = DB::table('commission_hold_log as chl')
            ->join('commission_transactions as ct', 'chl.commission_txn_id', '=', 'ct.txn_id')
            ->join('sales_transactions as st', 'ct.policy_id', '=', 'st.policy_id')
            ->join('customers as cu', 'st.customer_id', '=', 'cu.customer_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->where('chl.agent_id', $agent->agent_id)
            ->where('chl.status', 'HELD')
            ->select(
                'chl.hold_id',
                'chl.held_amount',
                'chl.hold_reason',
                'chl.held_at',
                'st.policy_id',
                'st.policy_number',
                'cu.full_name as customer_name',
                'p.product_name',
                'p.product_type',
                DB::raw('DATEDIFF(NOW(), chl.held_at) as days_held')
            )
            ->orderByDesc('chl.held_at')
            ->get();

        // ── Commission Transactions (paginated) ────────────────────
        $filter = $request->get('filter', 'all');
        $search = $request->get('search', '');

        $query = DB::table('commission_transactions as ct')
            ->join('sales_transactions as st', 'ct.policy_id', '=', 'st.policy_id')
            ->join('customers as cu', 'st.customer_id', '=', 'cu.customer_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->where('ct.agent_id', $agent->agent_id)
            ->select(
                'ct.txn_id',
                'ct.policy_id',
                'ct.commission_amount',
                'ct.entitlement_pct',
                'ct.role_at_transaction',
                'ct.status',
                'ct.created_at',
                'st.policy_number',
                'st.premium_amount',
                'st.coverage_start',
                'cu.full_name as customer_name',
                'p.product_name',
                'p.product_type',
                'p.product_code',
                'v.vendor_name'
            );

        if ($filter === 'earned') {
            $query->whereIn('ct.status', ['CONFIRMED', 'PAID']);
        } elseif ($filter === 'pending') {
            $query->where('ct.status', 'PENDING');
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('cu.full_name', 'like', "%{$search}%")
                  ->orWhere('st.policy_number', 'like', "%{$search}%")
                  ->orWhere('p.product_name', 'like', "%{$search}%");
            });
        }

        $commissions = $query->orderByDesc('ct.created_at')->paginate(15)->withQueryString();

        // ── Monthly Trend ──────────────────────────────────────────
        $monthlyTrend = DB::table('commission_transactions')
            ->where('agent_id', $agent->agent_id)
            ->whereIn('status', ['CONFIRMED', 'PAID'])
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('DATE_FORMAT(MIN(created_at), "%b %Y") as month_label'),
                DB::raw('SUM(commission_amount) as total')
            )
            ->orderBy('month')
            ->get();

        return view('gl.commissions.index', compact(
            'agent',
            'totalEarned',
            'totalPending',
            'totalHeld',
            'walletBalance',
            'groupTotalEarned',
            'byProductType',
            'byRole',
            'heldItems',
            'commissions',
            'monthlyTrend',
            'filter',
            'search'
        ));
    }
}
