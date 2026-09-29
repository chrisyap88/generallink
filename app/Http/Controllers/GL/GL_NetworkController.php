<?php

namespace App\Http\Controllers\GL;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NetworkController extends Controller
{
    private function getGL()
    {
        return auth('agent')->user();
    }

    public function index(Request $request)
    {
        $gl       = $this->getGL();
        $month    = (int)$request->get('month', now()->month);
        $year     = (int)$request->get('year',  now()->year);
        $from     = $request->get('from', '');

        $tls = DB::table('agents as a')
            ->where('a.group_id', $gl->group_id)
            ->where('a.role', 'TEAM_LEADER')
            ->where('a.is_deleted', false)
            ->leftJoin('sales_transactions as st', function($j) use ($month, $year) {
                $j->on('st.agent_id', '=', 'a.agent_id')
                  ->where('st.is_deleted', false)
                  ->whereMonth('st.created_at', $month)
                  ->whereYear('st.created_at', $year);
            })
            ->select(
                'a.agent_id', 'a.full_name', 'a.agent_code', 'a.status', 'a.created_at',
                DB::raw('COUNT(DISTINCT st.policy_id) as total_transactions'),
                DB::raw('COALESCE(SUM(st.premium_amount), 0) as total_sales')
            )
            ->groupBy('a.agent_id', 'a.full_name', 'a.agent_code', 'a.status', 'a.created_at')
            ->orderBy('a.agent_code')
            ->paginate(15)->withQueryString();

        // Intro count per TL
        $introCount = DB::table('agents')
            ->where('group_id', $gl->group_id)
            ->where('role', 'INTRODUCER')
            ->where('is_deleted', false)
            ->selectRaw('parent_id, COUNT(*) as cnt')
            ->groupBy('parent_id')
            ->pluck('cnt', 'parent_id');

        // Own sales
        $ownSales = DB::table('sales_transactions')
            ->where('agent_id', $gl->agent_id)
            ->where('is_deleted', false)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->sum('premium_amount');

        $monthName = Carbon::createFromDate($year, $month, 1)->format('F Y');

        // Get top 3 TL agent_ids by sales for medal display
        $top3 = DB::table('agents as a')
            ->where('a.group_id', $gl->group_id)
            ->where('a.role', 'TEAM_LEADER')
            ->where('a.is_deleted', false)
            ->leftJoin('sales_transactions as st', function($j) use ($month, $year) {
                $j->on('st.agent_id', '=', 'a.agent_id')
                  ->where('st.is_deleted', false)
                  ->whereMonth('st.created_at', $month)
                  ->whereYear('st.created_at', $year);
            })
            ->select('a.agent_id', DB::raw('COALESCE(SUM(st.premium_amount),0) as total_sales'))
            ->groupBy('a.agent_id')
            ->orderByDesc('total_sales')
            ->limit(3)->pluck('agent_id')->toArray();

        return view('gl.network.index', compact(
            'gl', 'tls', 'introCount', 'ownSales', 'top3',
            'month', 'year', 'monthName', 'from'
        ));
    }

    public function byTL(Request $request, $tlId)
    {
        $gl       = $this->getGL();
        $month    = (int)$request->get('month', now()->month);
        $year     = (int)$request->get('year',  now()->year);
        $from     = $request->get('from', '');

        $tl = DB::table('agents')->where('agent_id', $tlId)->first();

        $intros = DB::table('agents as a')
            ->where('a.parent_id', $tlId)
            ->where('a.role', 'INTRODUCER')
            ->where('a.is_deleted', false)
            ->leftJoin('sales_transactions as st', function($j) use ($month, $year) {
                $j->on('st.agent_id', '=', 'a.agent_id')
                  ->where('st.is_deleted', false)
                  ->whereMonth('st.created_at', $month)
                  ->whereYear('st.created_at', $year);
            })
            ->select(
                'a.agent_id', 'a.full_name', 'a.agent_code', 'a.status', 'a.created_at',
                DB::raw('COUNT(DISTINCT st.policy_id) as total_transactions'),
                DB::raw('COALESCE(SUM(st.premium_amount), 0) as total_sales')
            )
            ->groupBy('a.agent_id', 'a.full_name', 'a.agent_code', 'a.status', 'a.created_at')
            ->orderBy('a.agent_code')
            ->paginate(15)->withQueryString();

        $monthName = Carbon::createFromDate($year, $month, 1)->format('F Y');

        return view('gl.network.by-tl', compact(
            'gl', 'tl', 'intros',
            'month', 'year', 'monthName', 'from'
        ));
    }
}
