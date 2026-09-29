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
        $gl     = $this->getGL();
        $month  = (int)$request->get('month', now()->month);
        $year   = (int)$request->get('year',  now()->year);
        $from   = $request->get('from', '');
        $sortBy = $request->get('sort', 'sales');
        $filter = $request->get('filter', 'tl');

        $tlList = DB::table('agents')
            ->where('group_id', $gl->group_id)
            ->where('role', 'TEAM_LEADER')
            ->where('is_deleted', false)
            ->orderBy('agent_code')
            ->get(['agent_id', 'full_name', 'agent_code', 'status', 'created_at']);

        $tlData = $tlList->map(function($tl) use ($month, $year) {
            $teamIds = DB::table('agents')
                ->where(function($q) use ($tl) {
                    $q->where('agent_id', $tl->agent_id)
                      ->orWhere('parent_id', $tl->agent_id);
                })
                ->where('is_deleted', false)
                ->pluck('agent_id')->toArray();

            $tl->total_sales = (float)DB::table('sales_transactions')
                ->whereIn('agent_id', $teamIds)->where('is_deleted', false)
                ->whereMonth('created_at', $month)->whereYear('created_at', $year)
                ->sum('premium_amount');

            $tl->total_earn = (float)DB::table('commission_transactions')
                ->whereIn('agent_id', $teamIds)->where('status', '!=', 'CANCELLED')
                ->whereMonth('created_at', $month)->whereYear('created_at', $year)
                ->sum('commission_amount');

            $tl->total_transactions = DB::table('sales_transactions')
                ->whereIn('agent_id', $teamIds)->where('is_deleted', false)
                ->whereMonth('created_at', $month)->whereYear('created_at', $year)
                ->count();

            return $tl;
        });

        $page    = (int)request()->get('page', 1);
        $perPage = 15;
        $total   = $tlData->count();
        $slice   = $tlData->slice(($page-1)*$perPage, $perPage)->values();
        $tls     = new \Illuminate\Pagination\LengthAwarePaginator($slice, $total, $perPage, $page, [
            'path' => request()->url(), 'query' => request()->query(),
        ]);

        $introCount = DB::table('agents')
            ->where('group_id', $gl->group_id)->where('role', 'INTRODUCER')->where('is_deleted', false)
            ->selectRaw('parent_id, COUNT(*) as cnt')->groupBy('parent_id')
            ->pluck('cnt', 'parent_id');

        $ownSales = (float)DB::table('sales_transactions')
            ->where('agent_id', $gl->agent_id)->where('is_deleted', false)
            ->whereMonth('created_at', $month)->whereYear('created_at', $year)
            ->sum('premium_amount');

        $monthName = Carbon::createFromDate($year, $month, 1)->format('F Y');

        $top3Sales = $tlData->filter(fn($t) => $t->total_sales > 0)
            ->sortByDesc(fn($t) => (float)$t->total_sales)->take(3)->pluck('agent_id')->toArray();
        $top3Earn  = $tlData->filter(fn($t) => $t->total_earn > 0)
            ->sortByDesc(fn($t) => (float)$t->total_earn)->take(3)->pluck('agent_id')->toArray();

        if ($filter === 'intro') {
            $intros = DB::table('agents as a')
                ->where('a.group_id', $gl->group_id)->where('a.role', 'INTRODUCER')->where('a.is_deleted', false)
                ->leftJoin('agents as tl', 'tl.agent_id', '=', 'a.parent_id')
                ->leftJoin('sales_transactions as st', function($j) use ($month, $year) {
                    $j->on('st.agent_id', '=', 'a.agent_id')->where('st.is_deleted', false)
                      ->whereMonth('st.created_at', $month)->whereYear('st.created_at', $year);
                })
                ->leftJoin('commission_transactions as ct', function($j) use ($month, $year) {
                    $j->on('ct.agent_id', '=', 'a.agent_id')->where('ct.status', '!=', 'CANCELLED')
                      ->whereMonth('ct.created_at', $month)->whereYear('ct.created_at', $year);
                })
                ->select(
                    'a.agent_id', 'a.full_name', 'a.agent_code', 'a.status', 'a.created_at',
                    'tl.full_name as tl_name', 'tl.agent_code as tl_code',
                    DB::raw('COUNT(DISTINCT st.policy_id) as total_transactions'),
                    DB::raw('COALESCE(SUM(DISTINCT st.premium_amount), 0) as total_sales'),
                    DB::raw('COALESCE(SUM(DISTINCT ct.commission_amount), 0) as total_earn')
                )
                ->groupBy('a.agent_id', 'a.full_name', 'a.agent_code', 'a.status', 'a.created_at', 'tl.full_name', 'tl.agent_code')
                ->orderBy('a.agent_code')
                ->paginate(15)->withQueryString();

            $top3IntroSales = DB::table('agents as a')
                ->where('a.group_id', $gl->group_id)->where('a.role', 'INTRODUCER')->where('a.is_deleted', false)
                ->leftJoin('sales_transactions as st', function($j) use ($month, $year) {
                    $j->on('st.agent_id', '=', 'a.agent_id')->where('st.is_deleted', false)
                      ->whereMonth('st.created_at', $month)->whereYear('st.created_at', $year);
                })
                ->select('a.agent_id', DB::raw('COALESCE(SUM(st.premium_amount),0) as total_sales'))
                ->groupBy('a.agent_id')->having('total_sales', '>', 0)
                ->orderByDesc('total_sales')->limit(3)->pluck('agent_id')->toArray();

            $top3IntroEarn = DB::table('agents as a')
                ->where('a.group_id', $gl->group_id)->where('a.role', 'INTRODUCER')->where('a.is_deleted', false)
                ->leftJoin('commission_transactions as ct', function($j) use ($month, $year) {
                    $j->on('ct.agent_id', '=', 'a.agent_id')->where('ct.status', '!=', 'CANCELLED')
                      ->whereMonth('ct.created_at', $month)->whereYear('ct.created_at', $year);
                })
                ->select('a.agent_id', DB::raw('COALESCE(SUM(ct.commission_amount),0) as total_earn'))
                ->groupBy('a.agent_id')->having('total_earn', '>', 0)
                ->orderByDesc('total_earn')->limit(3)->pluck('agent_id')->toArray();

            $totalSales = DB::table('sales_transactions as st')
                ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
                ->where('a.group_id', $gl->group_id)->where('a.role', 'INTRODUCER')->where('st.is_deleted', false)
                ->whereMonth('st.created_at', $month)->whereYear('st.created_at', $year)
                ->sum('st.premium_amount');

            return view('gl.network.intros', compact(
                'gl', 'intros', 'top3IntroSales', 'top3IntroEarn', 'sortBy',
                'month', 'year', 'monthName', 'from', 'totalSales'
            ));
        }

        return view('gl.network.index', compact(
            'gl', 'tls', 'introCount', 'ownSales',
            'top3Sales', 'top3Earn', 'sortBy',
            'month', 'year', 'monthName', 'from'
        ));
    }

    public function byTL(Request $request, $tlId)
    {
        $gl     = $this->getGL();
        $month  = (int)$request->get('month', now()->month);
        $year   = (int)$request->get('year',  now()->year);
        $from   = $request->get('from', '');
        $sortBy = $request->get('sort', 'sales');

        $tl = DB::table('agents')->where('agent_id', $tlId)->first();

        $intros = DB::table('agents as a')
            ->where('a.parent_id', $tlId)->where('a.role', 'INTRODUCER')->where('a.is_deleted', false)
            ->leftJoin('sales_transactions as st', function($j) use ($month, $year) {
                $j->on('st.agent_id', '=', 'a.agent_id')->where('st.is_deleted', false)
                  ->whereMonth('st.created_at', $month)->whereYear('st.created_at', $year);
            })
            ->leftJoin('commission_transactions as ct', function($j) use ($month, $year) {
                $j->on('ct.agent_id', '=', 'a.agent_id')->where('ct.status', '!=', 'CANCELLED')
                  ->whereMonth('ct.created_at', $month)->whereYear('ct.created_at', $year);
            })
            ->select(
                'a.agent_id', 'a.full_name', 'a.agent_code', 'a.status', 'a.created_at',
                DB::raw('COUNT(DISTINCT st.policy_id) as total_transactions'),
                DB::raw('COALESCE(SUM(DISTINCT st.premium_amount), 0) as total_sales'),
                DB::raw('COALESCE(SUM(DISTINCT ct.commission_amount), 0) as total_earn')
            )
            ->groupBy('a.agent_id', 'a.full_name', 'a.agent_code', 'a.status', 'a.created_at')
            ->orderBy('a.agent_code')
            ->paginate(15)->withQueryString();

        $top3Sales = DB::table('agents as a')->where('a.parent_id', $tlId)->where('a.role', 'INTRODUCER')->where('a.is_deleted', false)
            ->leftJoin('sales_transactions as st', function($j) use ($month, $year) {
                $j->on('st.agent_id', '=', 'a.agent_id')->where('st.is_deleted', false)
                  ->whereMonth('st.created_at', $month)->whereYear('st.created_at', $year);
            })
            ->select('a.agent_id', DB::raw('COALESCE(SUM(st.premium_amount),0) as total_sales'))
            ->groupBy('a.agent_id')->having('total_sales', '>', 0)->orderByDesc('total_sales')->limit(3)->pluck('agent_id')->toArray();

        $top3Earn = DB::table('agents as a')->where('a.parent_id', $tlId)->where('a.role', 'INTRODUCER')->where('a.is_deleted', false)
            ->leftJoin('commission_transactions as ct', function($j) use ($month, $year) {
                $j->on('ct.agent_id', '=', 'a.agent_id')->where('ct.status', '!=', 'CANCELLED')
                  ->whereMonth('ct.created_at', $month)->whereYear('ct.created_at', $year);
            })
            ->select('a.agent_id', DB::raw('COALESCE(SUM(ct.commission_amount),0) as total_earn'))
            ->groupBy('a.agent_id')->having('total_earn', '>', 0)->orderByDesc('total_earn')->limit(3)->pluck('agent_id')->toArray();

        $tlOwnSales = (float)DB::table('sales_transactions')
            ->where('agent_id', $tlId)->where('is_deleted', false)
            ->whereMonth('created_at', $month)->whereYear('created_at', $year)->sum('premium_amount');

        $monthName = Carbon::createFromDate($year, $month, 1)->format('F Y');

        return view('gl.network.by-tl', compact(
            'gl', 'tl', 'intros', 'tlOwnSales',
            'top3Sales', 'top3Earn', 'sortBy',
            'month', 'year', 'monthName', 'from'
        ));
    }

    public function introTransactions(Request $request, $introId)
    {
        $gl    = $this->getGL();
        $month = (int)$request->get('month', now()->month);
        $year  = (int)$request->get('year',  now()->year);
        $from  = $request->get('from', '');

        $intro = DB::table('agents')->where('agent_id', $introId)->first();

        $transactions = DB::table('sales_transactions as st')
            ->where('st.agent_id', $introId)->where('st.is_deleted', false)
            ->whereMonth('st.created_at', $month)->whereYear('st.created_at', $year)
            ->leftJoin('vendors as v', 'v.vendor_id', '=', 'st.vendor_id')
            ->leftJoin('products as p', 'p.product_id', '=', 'st.product_id')
            ->leftJoin('commission_transactions as ct', 'ct.policy_id', '=', 'st.policy_id')
            ->select(
                'st.policy_id', 'st.policy_number', 'st.premium_amount', 'st.status', 'st.created_at',
                'st.coverage_start', 'st.coverage_end',
                'v.vendor_name', 'p.product_name', 'p.product_code',
                DB::raw('COALESCE(ct.commission_amount, 0) as commission_amount'),
                DB::raw('COALESCE(ct.entitlement_pct, 0) as entitlement_pct')
            )
            ->orderByDesc('st.created_at')
            ->paginate(15)->withQueryString();

        $monthName = Carbon::createFromDate($year, $month, 1)->format('F Y');
        $from    = $request->get('from', '');
        $tlId    = $request->get('tl_id', '');
        if ($from === 'by-tl' && $tlId) {
            $backUrl = route('gl.network.tl', $tlId) . '?month=' . $month . '&year=' . $year . '&from=all-tls&search_name=' . $request->get('search_name','') . '&search_code=' . $request->get('search_code','') . '&status=' . $request->get('status','') . '&show_all=' . $request->get('show_all','');
        } elseif ($from === 'all-intros') {
            $backUrl = route('gl.network.all-intros') . '?search_name=' . $request->get('search_name','') . '&search_code=' . $request->get('search_code','') . '&tl_search=' . $request->get('tl_search','') . '&tl_search_by=' . $request->get('tl_search_by','all') . '&status=' . $request->get('status','') . '&show_all=' . $request->get('show_all','');
        } else {
            $backUrl = route('gl.network.intros') . '?month=' . $month . '&year=' . $year;
        }

        return view('gl.network.intro_transactions', compact(
            'gl', 'intro', 'transactions', 'monthName', 'month', 'year', 'backUrl'
        ));
    }

    // -------------------------------------------------------
    // Excel Export — GL's Own Group Data (3 sheets)
    // -------------------------------------------------------
    public function exportGroup(Request $request)
    {
        $gl        = $this->getGL();
        $month     = (int)$request->get('month', now()->month);
        $year      = (int)$request->get('year',  now()->year);
        $monthName = Carbon::createFromDate($year, $month, 1)->format('F Y');
        $today     = now()->format('d M Y');
        $filename  = 'GeneralLink_' . str_replace(' ', '', $gl->full_name) . '_' . $gl->agent_code . '_' . str_replace(' ', '', $monthName) . '_' . now()->format('dMY') . '.xlsx';

        $groupAgentIds = DB::table('agents')
            ->where('group_id', $gl->group_id)->where('is_deleted', false)
            ->pluck('agent_id')->toArray();

        // Sheet 1: TL Summary
        $tlAgents = DB::table('agents as tl')
            ->where('tl.parent_id', $gl->agent_id)->where('tl.role', 'TEAM_LEADER')->where('tl.is_deleted', false)
            ->leftJoin('agents as intro', function($join) {
                $join->on('intro.parent_id', '=', 'tl.agent_id')->where('intro.role', 'INTRODUCER')->where('intro.is_deleted', false);
            })
            ->select('tl.agent_id', 'tl.full_name', 'tl.agent_code', 'tl.status', 'tl.created_at', DB::raw('COUNT(DISTINCT intro.agent_id) as total_intro'))
            ->groupBy('tl.agent_id', 'tl.full_name', 'tl.agent_code', 'tl.status', 'tl.created_at')->orderBy('tl.agent_code')->get();

        $tls = $tlAgents->map(function($tl) use ($month, $year) {
            $introIds = DB::table('agents')->where('parent_id', $tl->agent_id)->where('is_deleted', false)->pluck('agent_id')->toArray();
            $agentIds = array_merge([$tl->agent_id], $introIds);
            $tl->total_sales = (float)DB::table('sales_transactions')->whereIn('agent_id', $agentIds)->where('is_deleted', false)->whereMonth('created_at', $month)->whereYear('created_at', $year)->sum('premium_amount');
            return $tl;
        });

        $glOwnSales = (float)DB::table('sales_transactions')->where('agent_id', $gl->agent_id)->where('is_deleted', false)->whereMonth('created_at', $month)->whereYear('created_at', $year)->sum('premium_amount');

        $sheet1 = [];
        $sheet1[] = ['GeneralLink Digital Ecosystem'];
        $sheet1[] = ['Group Leader: ' . $gl->full_name . ' (' . $gl->agent_code . ')'];
        $sheet1[] = ['Period: ' . $monthName];
        $sheet1[] = ['Downloaded: ' . $today];
        $sheet1[] = [];
        $sheet1[] = ['Name', 'Code', 'Introducers', 'Sales Amount (RM)', 'Status', 'Joined'];
        $sheet1[] = [$gl->full_name . ' (GL)', $gl->agent_code, 0, $glOwnSales, $gl->status, Carbon::parse($gl->created_at)->format('d M Y')];
        foreach ($tls as $tl) {
            $sheet1[] = [$tl->full_name, $tl->agent_code, (int)$tl->total_intro, (float)$tl->total_sales, $tl->status, Carbon::parse($tl->created_at)->format('d M Y')];
        }

        // Sheet 2: All Agents
        $tlIdsList = DB::table('agents')->where('parent_id', $gl->agent_id)->where('role','TEAM_LEADER')->where('is_deleted',false)->pluck('agent_id')->toArray();
        $tlLookup = [];
        foreach ($tlIdsList as $tlId) {
            $tlInfo = DB::table('agents')->where('agent_id', $tlId)->first(['full_name','agent_code']);
            $introIds = DB::table('agents')->where('parent_id', $tlId)->where('is_deleted',false)->pluck('agent_id')->toArray();
            $tlLookup[$tlId] = ['name' => $tlInfo->full_name, 'code' => $tlInfo->agent_code];
            foreach ($introIds as $id) { $tlLookup[$id] = ['name' => $tlInfo->full_name, 'code' => $tlInfo->agent_code]; }
        }

        $allAgents = DB::table('agents as a')
            ->whereIn('a.agent_id', $groupAgentIds)->where('a.role', '!=', 'GROUP_LEADER')
            ->leftJoin('sales_transactions as st', function($join) use ($month, $year) {
                $join->on('st.agent_id', '=', 'a.agent_id')->where('st.is_deleted', false)->whereMonth('st.created_at', $month)->whereYear('st.created_at', $year);
            })
            ->select('a.agent_id','a.full_name','a.agent_code','a.role','a.status','a.created_at', DB::raw('COALESCE(SUM(st.premium_amount), 0) as total_sales'))
            ->groupBy('a.agent_id','a.full_name','a.agent_code','a.role','a.status','a.created_at')->orderBy('a.agent_code')->get()
            ->map(function($a) use ($tlLookup) { $a->tl_name = $tlLookup[$a->agent_id]['name'] ?? '-'; $a->tl_code = $tlLookup[$a->agent_id]['code'] ?? '-'; return $a; })->sortBy('tl_code');

        $sheet2 = [];
        $sheet2[] = ['GeneralLink Digital Ecosystem'];
        $sheet2[] = ['Group Leader: ' . $gl->full_name . ' (' . $gl->agent_code . ')'];
        $sheet2[] = ['Period: ' . $monthName];
        $sheet2[] = ['Downloaded: ' . $today];
        $sheet2[] = [];
        $sheet2[] = ['Team Leader', 'TL Code', 'Agent', 'Code', 'Role', 'Sales Amount (RM)', 'Status', 'Joined'];
        $sheet2[] = ['-', '-', $gl->full_name . ' (GL)', $gl->agent_code, 'GL', $glOwnSales, $gl->status, Carbon::parse($gl->created_at)->format('d M Y')];
        foreach ($allAgents as $i) {
            $sheet2[] = [$i->tl_name, $i->tl_code, $i->full_name, $i->agent_code, $i->role, (float)$i->total_sales, $i->status, Carbon::parse($i->created_at)->format('d M Y')];
        }

        // Sheet 3: All Transactions
        $txns = DB::table('sales_transactions as st')
            ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->whereIn('st.agent_id', $groupAgentIds)->where('st.is_deleted', false)
            ->whereMonth('st.created_at', $month)->whereYear('st.created_at', $year)
            ->select('st.policy_number', 'c.full_name as customer_name', 'a.full_name as agent_name', 'a.agent_code', 'a.role', 'v.vendor_name', 'p.product_name', 'st.premium_amount', 'st.status', 'st.created_at', 'st.renewal_date')
            ->orderBy('a.agent_code')->orderByDesc('st.created_at')->get();

        $sheet3 = [];
        $sheet3[] = ['GeneralLink Digital Ecosystem'];
        $sheet3[] = ['Group Leader: ' . $gl->full_name . ' (' . $gl->agent_code . ')'];
        $sheet3[] = ['Period: ' . $monthName];
        $sheet3[] = ['Downloaded: ' . $today];
        $sheet3[] = [];
        $sheet3[] = ['Transaction No.', 'Customer', 'Agent', 'Code', 'Role', 'Vendor', 'Product', 'Sales Amount (RM)', 'Date', 'Renewal Date', 'Status'];
        foreach ($txns as $tx) {
            $sheet3[] = [$tx->policy_number, $tx->customer_name ?? '—', $tx->agent_name, $tx->agent_code, $tx->role, $tx->vendor_name, $tx->product_name, (float)$tx->premium_amount, Carbon::parse($tx->created_at)->format('d M Y'), $tx->renewal_date ? Carbon::parse($tx->renewal_date)->format('d M Y') : '—', $tx->status];
        }

        // Build Excel
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        $ws1 = $spreadsheet->getActiveSheet(); $ws1->setTitle('TL Summary');
        foreach ($sheet1 as $ri => $row) { foreach ($row as $ci => $val) { $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci+1).($ri+1); if (is_float($val)||is_int($val)) $ws1->getCell($coord)->setValueExplicit($val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC); else $ws1->getCell($coord)->setValue($val); } }
        $ws1->getStyle('A1:A4')->getFont()->setBold(true); $ws1->getStyle('A6:F6')->getFont()->setBold(true);
        $lr1=$ws1->getHighestRow(); $ws1->getStyle('D7:D'.$lr1)->getNumberFormat()->setFormatCode('#,##0.00'); $ws1->getStyle('D7:D'.$lr1)->getAlignment()->setHorizontal('right');
        $ws1->getCell('C'.($lr1+2))->setValue('Total'); $ws1->getStyle('C'.($lr1+2))->getFont()->setBold(true)->setSize(12);
        $ws1->getCell('D'.($lr1+2))->setValue('=SUM(D7:D'.$lr1.')'); $ws1->getStyle('D'.($lr1+2))->getNumberFormat()->setFormatCode('#,##0.00'); $ws1->getStyle('D'.($lr1+2))->getFont()->setBold(true)->setSize(12); $ws1->getStyle('D'.($lr1+2))->getAlignment()->setHorizontal('right');
        foreach (range('A','F') as $col) $ws1->getColumnDimension($col)->setAutoSize(true);

        $ws2=$spreadsheet->createSheet(); $ws2->setTitle('Introducers');
        foreach ($sheet2 as $ri => $row) { foreach ($row as $ci => $val) { $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci+1).($ri+1); if (is_float($val)||is_int($val)) $ws2->getCell($coord)->setValueExplicit($val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC); else $ws2->getCell($coord)->setValue($val); } }
        $ws2->getStyle('A1:A4')->getFont()->setBold(true); $ws2->getStyle('A6:H6')->getFont()->setBold(true);
        $lr2=$ws2->getHighestRow(); $ws2->getStyle('F7:F'.$lr2)->getNumberFormat()->setFormatCode('#,##0.00'); $ws2->getStyle('F7:F'.$lr2)->getAlignment()->setHorizontal('right');
        $ws2->getCell('E'.($lr2+2))->setValue('Total'); $ws2->getStyle('E'.($lr2+2))->getFont()->setBold(true)->setSize(12);
        $ws2->getCell('F'.($lr2+2))->setValue('=SUM(F7:F'.$lr2.')'); $ws2->getStyle('F'.($lr2+2))->getNumberFormat()->setFormatCode('#,##0.00'); $ws2->getStyle('F'.($lr2+2))->getFont()->setBold(true)->setSize(12); $ws2->getStyle('F'.($lr2+2))->getAlignment()->setHorizontal('right');
        foreach (range('A','H') as $col) $ws2->getColumnDimension($col)->setAutoSize(true);

        $ws3=$spreadsheet->createSheet(); $ws3->setTitle('Transactions');
        foreach ($sheet3 as $ri => $row) { foreach ($row as $ci => $val) { $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci+1).($ri+1); if (is_float($val)||is_int($val)) $ws3->getCell($coord)->setValueExplicit($val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC); else $ws3->getCell($coord)->setValue($val); } }
        $ws3->getStyle('A1:A4')->getFont()->setBold(true); $ws3->getStyle('A6:K6')->getFont()->setBold(true);
        $lr3=$ws3->getHighestRow(); $ws3->getStyle('H7:H'.$lr3)->getNumberFormat()->setFormatCode('#,##0.00'); $ws3->getStyle('H7:H'.$lr3)->getAlignment()->setHorizontal('right');
        $ws3->getCell('G'.($lr3+2))->setValue('Total'); $ws3->getStyle('G'.($lr3+2))->getFont()->setBold(true)->setSize(12);
        $ws3->getCell('H'.($lr3+2))->setValue('=SUM(H7:H'.$lr3.')'); $ws3->getStyle('H'.($lr3+2))->getNumberFormat()->setFormatCode('#,##0.00'); $ws3->getStyle('H'.($lr3+2))->getFont()->setBold(true)->setSize(12); $ws3->getStyle('H'.($lr3+2))->getAlignment()->setHorizontal('right');
        foreach (range('A','K') as $col) $ws3->getColumnDimension($col)->setAutoSize(true);

        $spreadsheet->setActiveSheetIndex(0);
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    // -------------------------------------------------------
    // All TLs — search first, scoped to GL group
    // -------------------------------------------------------
    public function allTLs(Request $request)
    {
        $gl     = $this->getGL();
        $month  = (int)$request->get('month', now()->month);
        $year   = (int)$request->get('year', now()->year);
        $sortBy     = $request->input('sort', 'sales');
        $searchName = $request->input('search_name', '');
        $searchCode = $request->input('search_code', '');
        $status     = $request->input('status', '');
        $showAll    = $request->input('show_all', '');

        $monthName = \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y');

        $hasFilter = $showAll || $searchName || $searchCode || $status;

        // Only query when filter applied
        if (!$hasFilter) {
            return view('gl.network.all-tls', [
                'gl' => $gl, 'tls' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15, 1),
                'month' => $month, 'year' => $year, 'monthName' => $monthName,
            ]);
        }

        $query = DB::table('agents as tl')
            ->where('tl.group_id', $gl->group_id)
            ->where('tl.role', 'TEAM_LEADER')
            ->where('tl.is_deleted', false);

        if ($searchName) $query->where('tl.full_name', 'like', "%{$searchName}%");
        if ($searchCode) $query->where('tl.agent_code', 'like', "%{$searchCode}%");
        if ($status) $query->where('tl.status', $status);

        $query
            ->leftJoin('agents as intro', function($join) {
                $join->on('intro.parent_id', '=', 'tl.agent_id')
                     ->where('intro.role', 'INTRODUCER')
                     ->where('intro.is_deleted', false);
            })
            ->leftJoin('sales_transactions as st', function($join) use ($month, $year) {
                $join->on('st.agent_id', '=', 'tl.agent_id')
                     ->where('st.is_deleted', false)
                     ->whereMonth('st.created_at', $month)
                     ->whereYear('st.created_at', $year);
            })
            ->leftJoin('commission_transactions as ct', function($join) use ($month, $year) {
                $join->on('ct.agent_id', '=', 'tl.agent_id')
                     ->where('ct.status', '!=', 'CANCELLED')
                     ->whereMonth('ct.created_at', $month)
                     ->whereYear('ct.created_at', $year);
            })
            ->select(
                'tl.agent_id', 'tl.full_name', 'tl.agent_code', 'tl.status', 'tl.created_at',
                DB::raw('COUNT(DISTINCT intro.agent_id) as total_intro'),
                DB::raw('COALESCE(SUM(DISTINCT st.premium_amount), 0) as total_sales'),
                DB::raw('COALESCE(SUM(DISTINCT ct.commission_amount), 0) as total_earn')
            )
            ->groupBy('tl.agent_id', 'tl.full_name', 'tl.agent_code', 'tl.status', 'tl.created_at');

        $query->orderBy('tl.agent_code');

        $tls = $query->paginate(15)->withQueryString();

        return view('gl.network.all-tls', compact('gl', 'tls', 'month', 'year', 'monthName'));
    }

    // -------------------------------------------------------
    // All Intros — TL filter first, scoped to GL group
    // -------------------------------------------------------
    public function allIntros(Request $request)
    {
        $gl     = $this->getGL();
        $month  = (int)$request->get('month', now()->month);
        $year   = (int)$request->get('year', now()->year);
        $searchName = $request->input('search_name', '');
        $searchCode = $request->input('search_code', '');
        $status     = $request->input('status', '');
        $showAll    = $request->input('show_all', '');
        $hasFilter  = $showAll || $searchName || $searchCode || $status;

        $monthName = \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y');

        $tlList = DB::table('agents')
            ->where('group_id', $gl->group_id)->where('role', 'TEAM_LEADER')->where('is_deleted', false)
            ->orderBy('agent_code')->get(['agent_id', 'full_name', 'agent_code']);

        $tlId       = '';  // removed direct tl_id, now using tl_search
        $tlSearch   = $request->input('tl_search', '');
        $tlSearchBy = $request->input('tl_search_by', '');
        if ($tlSearch) $hasFilter = true; // only trigger if actual TL search text

        if (!$hasFilter) {
            return view('gl.network.all-intros', [
                'gl' => $gl, 'intros' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15, 1),
                'tlList' => $tlList, 'month' => $month, 'year' => $year, 'monthName' => $monthName,
            ]);
        }

        // Step 1: Get all group_ids under this GL (own + promoted GLs)
        // Step 1: Get intro agent_ids only (clean pagination) - own group only
        $introQuery = DB::table('agents as intro')
            ->where('intro.group_id', $gl->group_id)
            ->where('intro.role', 'INTRODUCER')
            ->where('intro.is_deleted', false);

        if ($tlSearch) {
            $introQuery->join('agents as parent', 'parent.agent_id', '=', 'intro.parent_id');
            if ($tlSearchBy === 'code') $introQuery->where('parent.agent_code', 'like', "%{$tlSearch}%");
            else $introQuery->where('parent.full_name', 'like', "%{$tlSearch}%");
        }
        if ($searchName) $introQuery->where('intro.full_name', 'like', "%{$searchName}%");
        if ($searchCode) $introQuery->where('intro.agent_code', 'like', "%{$searchCode}%");
        if ($status) $introQuery->where('intro.status', $status);

        $introQuery->select('intro.agent_id', 'intro.full_name', 'intro.agent_code',
                            'intro.status', 'intro.parent_id', 'intro.group_id', 'intro.created_at')
                   ->orderBy('intro.agent_code');

        $intros = $introQuery->paginate(15)->withQueryString();

        // Step 2: Enrich page with parent/GL/sales data
        $introIds  = $intros->pluck('agent_id')->toArray();
        $parentIds = $intros->pluck('parent_id')->unique()->filter()->toArray();

        $parentData = DB::table('agents')->whereIn('agent_id', $parentIds)
            ->get(['agent_id','full_name','agent_code','role'])->keyBy('agent_id');

        $salesData = DB::table('sales_transactions')
            ->whereIn('agent_id', $introIds)->where('is_deleted', false)
            ->whereMonth('created_at', $month)->whereYear('created_at', $year)
            ->select('agent_id', DB::raw('SUM(premium_amount) as total_sales'), DB::raw('COUNT(policy_id) as total_transactions'))
            ->groupBy('agent_id')->get()->keyBy('agent_id');

        $earnData = DB::table('commission_transactions')
            ->whereIn('agent_id', $introIds)->where('status', '!=', 'CANCELLED')
            ->whereMonth('created_at', $month)->whereYear('created_at', $year)
            ->select('agent_id', DB::raw('SUM(commission_amount) as total_earn'))
            ->groupBy('agent_id')->get()->keyBy('agent_id');

        // Get GL info per group_id
        $groupIds = $intros->pluck('group_id')->unique()->toArray();
        $glByGroup = DB::table('agents')
            ->whereIn('group_id', $groupIds)
            ->where('role', 'GROUP_LEADER')
            ->where('is_deleted', false)
            ->get(['group_id', 'agent_id', 'full_name', 'agent_code'])
            ->keyBy('group_id');

        $intros->getCollection()->transform(function($intro) use ($parentData, $salesData, $earnData, $glByGroup) {
            $parent = $parentData[$intro->parent_id] ?? null;
            $intro->tl_id   = ($parent && $parent->role === 'TEAM_LEADER') ? $parent->agent_id : null;
            $intro->tl_name = ($parent && $parent->role === 'TEAM_LEADER') ? $parent->full_name : null;
            $intro->tl_code = ($parent && $parent->role === 'TEAM_LEADER') ? $parent->agent_code : null;
            $glInfo = $glByGroup[$intro->group_id] ?? null;
            $intro->gl_name = $glInfo ? $glInfo->full_name : '—';
            $intro->gl_code = $glInfo ? $glInfo->agent_code : '—';
            $intro->total_sales = (float)($salesData[$intro->agent_id]->total_sales ?? 0);
            $intro->total_earn  = (float)($earnData[$intro->agent_id]->total_earn ?? 0);
            $intro->total_transactions = (int)($salesData[$intro->agent_id]->total_transactions ?? 0);
            return $intro;
        });

        return view('gl.network.all-intros', compact('gl', 'intros', 'tlList', 'month', 'year', 'monthName'));
    }

    // -------------------------------------------------------
    // Inactive TLs
    // -------------------------------------------------------
    public function inactiveTLs(Request $request)
    {
        $gl     = $this->getGL();
        $search = $request->input('search', '');
        $monthName = \Carbon\Carbon::createFromDate(now()->year, now()->month, 1)->format('F Y');

        $query = DB::table('agents as tl')
            ->where('tl.group_id', $gl->group_id)
            ->where('tl.role', 'TEAM_LEADER')
            ->where('tl.is_deleted', false)
            ->where('tl.status', '!=', 'ACTIVE');

        if ($search) $query->where(function($q) use ($search) {
            $q->where('tl.full_name', 'like', "%{$search}%")
              ->orWhere('tl.agent_code', 'like', "%{$search}%");
        });

        $tls = $query->orderBy('tl.full_name')
            ->paginate(15)->withQueryString();

        return view('gl.network.inactive-tls', compact('gl', 'tls', 'search', 'monthName'));
    }

    // -------------------------------------------------------
    // Inactive Intros
    // -------------------------------------------------------
    public function inactiveIntros(Request $request)
    {
        $gl     = $this->getGL();
        $search = $request->input('search', '');
        $monthName = \Carbon\Carbon::createFromDate(now()->year, now()->month, 1)->format('F Y');

        $query = DB::table('agents as intro')
            ->where('intro.group_id', $gl->group_id)
            ->where('intro.role', 'INTRODUCER')
            ->where('intro.is_deleted', false)
            ->where('intro.status', '!=', 'ACTIVE')
            ->leftJoin('agents as tl', 'tl.agent_id', '=', 'intro.parent_id')
            ->select('intro.agent_id', 'intro.full_name', 'intro.agent_code', 'intro.status', 'intro.created_at',
                     'tl.full_name as tl_name', 'tl.agent_code as tl_code');

        if ($search) $query->where(function($q) use ($search) {
            $q->where('intro.full_name', 'like', "%{$search}%")
              ->orWhere('intro.agent_code', 'like', "%{$search}%");
        });

        $intros = $query->orderBy('intro.full_name')
            ->paginate(15)->withQueryString();

        return view('gl.network.inactive-intros', compact('gl', 'intros', 'search', 'monthName'));
    }

}
