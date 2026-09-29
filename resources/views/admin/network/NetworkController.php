<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NetworkController extends Controller
{
    /**
     * Level 1 — All Group Leaders
     */
    public function index()
    {
        $gls = Agent::where('role', 'GROUP_LEADER')
            ->where('status', 'ACTIVE')
            ->where('is_deleted', false)
            ->select('agent_id', 'full_name', 'agent_code', 'status', 'created_at')
            ->get()
            ->map(function ($gl) {
                $gl->team_count = Agent::where('parent_id', $gl->agent_id)->where('is_deleted', false)->count();
                $gl->total_commission = DB::table('commission_transactions')->where('agent_id', $gl->agent_id)->sum('commission_amount') ?? 0;
                $gl->total_transactions = DB::table('sales_transactions')->where('agent_id', $gl->agent_id)->where('is_deleted', false)->count();
                return $gl;
            });

        return view('admin.network.index', compact('gls'));
    }

    /**
     * Level 2 — GL → All TLs
     */
    public function byGL(string $glId)
    {
        $gl = Agent::where('agent_id', $glId)->where('is_deleted', false)->firstOrFail();

        $tls = Agent::where('parent_id', $glId)
            ->where('role', 'TEAM_LEADER')
            ->where('is_deleted', false)
            ->select('agent_id', 'full_name', 'agent_code', 'status', 'created_at')
            ->get()
            ->map(function ($tl) {
                $tl->member_count = Agent::where('parent_id', $tl->agent_id)->where('is_deleted', false)->count();
                $tl->total_commission = DB::table('commission_transactions')->where('agent_id', $tl->agent_id)->sum('commission_amount') ?? 0;
                $tl->total_transactions = DB::table('sales_transactions')->where('agent_id', $tl->agent_id)->where('is_deleted', false)->count();
                return $tl;
            });

        return view('admin.network.by-gl', compact('gl', 'tls'));
    }

    /**
     * Level 3 — TL → All Introducers
     */
    public function byTL(string $glId, string $tlId)
    {
        $gl = Agent::where('agent_id', $glId)->where('is_deleted', false)->firstOrFail();
        $tl = Agent::where('agent_id', $tlId)->where('is_deleted', false)->firstOrFail();

        $introducers = Agent::where('parent_id', $tlId)
            ->where('role', 'INTRODUCER')
            ->where('is_deleted', false)
            ->select('agent_id', 'full_name', 'agent_code', 'status', 'created_at')
            ->get()
            ->map(function ($i) {
                $i->total_commission = DB::table('commission_transactions')->where('agent_id', $i->agent_id)->sum('commission_amount') ?? 0;
                $i->total_transactions = DB::table('sales_transactions')->where('agent_id', $i->agent_id)->where('is_deleted', false)->count();
                return $i;
            });

        return view('admin.network.by-tl', compact('gl', 'tl', 'introducers'));
    }

    /**
     * Level 4 — Introducer → All Transactions
     */
    public function byIntroducer(string $glId, string $tlId, string $introducerId)
    {
        $gl         = Agent::where('agent_id', $glId)->where('is_deleted', false)->firstOrFail();
        $tl         = Agent::where('agent_id', $tlId)->where('is_deleted', false)->firstOrFail();
        $introducer = Agent::where('agent_id', $introducerId)->where('is_deleted', false)->firstOrFail();

        $transactions = DB::table('sales_transactions')
            ->join('vendors', 'sales_transactions.vendor_id', '=', 'vendors.vendor_id')
            ->join('products', 'sales_transactions.product_id', '=', 'products.product_id')
            ->leftJoin('customers', 'sales_transactions.customer_id', '=', 'customers.customer_id')
            ->select(
                'sales_transactions.policy_id',
                'sales_transactions.policy_number',
                'sales_transactions.premium_amount',
                'sales_transactions.status',
                'sales_transactions.created_at',
                'vendors.vendor_name',
                'products.product_name',
                'customers.full_name as customer_name'
            )
            ->where('sales_transactions.agent_id', $introducerId)
            ->where('sales_transactions.is_deleted', false)
            ->orderByDesc('sales_transactions.created_at')
            ->paginate(20);

        $summary = [
            'total_transactions' => $transactions->total(),
            'total_amount'       => DB::table('sales_transactions')->where('agent_id', $introducerId)->where('is_deleted', false)->sum('premium_amount'),
            'total_commission'   => DB::table('commission_transactions')->where('agent_id', $introducerId)->sum('commission_amount') ?? 0,
        ];

        return view('admin.network.by-introducer', compact('gl', 'tl', 'introducer', 'transactions', 'summary'));
    }
}
