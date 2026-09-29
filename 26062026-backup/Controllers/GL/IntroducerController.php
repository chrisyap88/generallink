<?php

namespace App\Http\Controllers\GL;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class IntroducerController extends Controller
{
    private function getGL()
    {
        return auth('agent')->user();
    }

    public function index(Request $request)
    {
        $gl     = $this->getGL();
        $month  = (int)$request->get('month', now()->month);
        $year   = (int)$request->get('year',  now()->year);
        $from   = $request->get('from', '');
        $sortBy = $request->get('sort', 'sales');

        // Get all TLs in this GL group ordered by code
        $tls = DB::table('agents')
            ->where('group_id', $gl->group_id)
            ->where('role', 'TEAM_LEADER')
            ->where('is_deleted', false)
            ->orderBy('agent_code')
            ->get(['agent_id', 'full_name', 'agent_code']);

        // Get all Introducers with their TL info
        $allIntros = DB::table('agents as a')
            ->where('a.group_id', $gl->group_id)
            ->where('a.role', 'INTRODUCER')
            ->where('a.is_deleted', false)
            ->leftJoin('agents as tl', 'tl.agent_id', '=', 'a.parent_id')
            ->leftJoin('sales_transactions as st', function($j) use ($month, $year) {
                $j->on('st.agent_id', '=', 'a.agent_id')
                  ->where('st.is_deleted', false)
                  ->whereMonth('st.created_at', $month)
                  ->whereYear('st.created_at', $year);
            })
            ->leftJoin('commission_transactions as ct', function($j) use ($month, $year) {
                $j->on('ct.agent_id', '=', 'a.agent_id')
                  ->where('ct.status', '!=', 'CANCELLED')
                  ->whereMonth('ct.created_at', $month)
                  ->whereYear('ct.created_at', $year);
            })
            ->select(
                'a.agent_id', 'a.full_name', 'a.agent_code', 'a.status', 'a.created_at',
                'a.parent_id',
                'tl.full_name as tl_name', 'tl.agent_code as tl_code',
                'tl.role as tl_role',
                DB::raw('COUNT(DISTINCT st.policy_id) as total_transactions'),
                DB::raw('COALESCE(SUM(DISTINCT st.premium_amount), 0) as total_sales'),
                DB::raw('COALESCE(SUM(DISTINCT ct.commission_amount), 0) as total_earn')
            )
            ->groupBy('a.agent_id', 'a.full_name', 'a.agent_code', 'a.status', 'a.created_at',
                      'a.parent_id', 'tl.full_name', 'tl.agent_code', 'tl.role')
            ->get();

        // Sort: TL Introducers first (by TL code then Intro code), GL direct Introducers last
        $tlIntros = $allIntros->filter(fn($i) => $i->tl_role === 'TEAM_LEADER')
            ->sortBy([
                fn($a, $b) => strcmp($a->tl_code, $b->tl_code),
                fn($a, $b) => strcmp($a->agent_code, $b->agent_code),
            ])->values();

        $glDirectIntros = $allIntros->filter(fn($i) => $i->tl_role !== 'TEAM_LEADER')
            ->sortBy('agent_code')->values();

        $sorted = $tlIntros->concat($glDirectIntros)->values();

        // Top 3 by sales
        $top3Sales = $sorted->filter(fn($i) => (float)$i->total_sales > 0)
            ->sortByDesc(fn($i) => (float)$i->total_sales)
            ->take(3)->pluck('agent_id')->toArray();

        // Top 3 by earn
        $top3Earn = $sorted->filter(fn($i) => (float)$i->total_earn > 0)
            ->sortByDesc(fn($i) => (float)$i->total_earn)
            ->take(3)->pluck('agent_id')->toArray();

        // Manual pagination
        $page    = (int)$request->get('page', 1);
        $perPage = 15;
        $total   = $sorted->count();
        $slice   = $sorted->slice(($page-1)*$perPage, $perPage)->values();
        $intros  = new \Illuminate\Pagination\LengthAwarePaginator($slice, $total, $perPage, $page, [
            'path'  => $request->url(),
            'query' => $request->query(),
        ]);

        $monthName = Carbon::createFromDate($year, $month, 1)->format('F Y');
        $totalSales = $sorted->sum(fn($i) => (float)$i->total_sales);

        return view('gl.network.intros', compact(
            'gl', 'intros', 'top3Sales', 'top3Earn', 'sortBy',
            'month', 'year', 'monthName', 'from', 'totalSales'
        ));
    }
}
