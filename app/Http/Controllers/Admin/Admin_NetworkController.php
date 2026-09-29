<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class NetworkController extends Controller
{
    // -------------------------------------------------------
    // AJAX Typeahead — for agent search filter
    // -------------------------------------------------------
    public function ajaxTypeahead(Request $request)
    {
        $q      = $request->get('q', '');
        $mode   = $request->get('mode', 'all');
        $scope  = $request->get('scope', ''); // parent agent_id to scope search

        if ($mode === 'gl') {
            // Only GROUP_LEADER agents
            $results = DB::table('agents')
                ->where('role', 'GROUP_LEADER')->where('is_deleted', false)
                ->where(function($q2) use ($q) {
                    $q2->where('full_name', 'like', "%{$q}%")->orWhere('agent_code', 'like', "%{$q}%");
                })
                ->orderBy('full_name')->limit(20)
                ->get(['agent_id as id', 'full_name as name', 'agent_code as code']);
            return response()->json($results);

        } elseif ($mode === 'tl') {
            // TEAM_LEADER agents — scoped to parent GL if provided
            $query = DB::table('agents')
                ->where('role', 'TEAM_LEADER')->where('is_deleted', false)
                ->where(function($q2) use ($q) {
                    $q2->where('full_name', 'like', "%{$q}%")->orWhere('agent_code', 'like', "%{$q}%");
                });
            if ($scope) $query->where('parent_id', $scope);
            $results = $query->orderBy('full_name')->limit(20)->get(['agent_id as id', 'full_name as name', 'agent_code as code']);
            return response()->json($results);

        } elseif ($mode === 'intro') {
            // INTRODUCER agents — scoped to parent TL if provided
            $query = DB::table('agents')
                ->where('role', 'INTRODUCER')->where('is_deleted', false)
                ->where(function($q2) use ($q) {
                    $q2->where('full_name', 'like', "%{$q}%")->orWhere('agent_code', 'like', "%{$q}%");
                });
            if ($scope) $query->where('parent_id', $scope);
            $results = $query->orderBy('full_name')->limit(20)->get(['agent_id as id', 'full_name as name', 'agent_code as code']);
            return response()->json($results);

        } else {
            // Legacy fallback — all agents
            $results = DB::table('agents')
                ->where('is_deleted', false)
                ->where(function($q2) use ($q) {
                    $q2->where('full_name', 'like', "%{$q}%")->orWhere('agent_code', 'like', "%{$q}%");
                })
                ->orderBy('full_name')->limit(20)
                ->get(['agent_id as id', 'full_name as name', 'agent_code as code']);
            return response()->json($results);
        }
    }

    // -------------------------------------------------------
    // AJAX Endpoints for cascading dropdowns
    // -------------------------------------------------------
    public function ajaxGLs()
    {
        $gls = DB::table('agents')
            ->where('role', 'GROUP_LEADER')
            ->where('is_deleted', false)
            ->orderBy('full_name')
            ->get(['agent_id', 'full_name', 'agent_code']);
        return response()->json($gls);
    }

    public function ajaxTLs(Request $request)
    {
        $tls = DB::table('agents')
            ->where('role', 'TEAM_LEADER')
            ->where('parent_id', $request->gl_id)
            ->where('is_deleted', false)
            ->orderBy('full_name')
            ->get(['agent_id', 'full_name', 'agent_code']);
        return response()->json($tls);
    }

    public function ajaxIntros(Request $request)
    {
        $intros = DB::table('agents')
            ->where('role', 'INTRODUCER')
            ->where('parent_id', $request->tl_id)
            ->where('is_deleted', false)
            ->orderBy('full_name')
            ->get(['agent_id', 'full_name', 'agent_code']);
        return response()->json($intros);
    }

    // -------------------------------------------------------
    // AJAX Children — recursive network tree
    // -------------------------------------------------------
    public function ajaxChildren(Request $request)
    {
        $parentId = $request->agent_id;
        $page     = max(1, (int)$request->get('page', 1));
        $perPage  = 10;

        $query = DB::table('agents')
            ->where('parent_id', $parentId)
            ->where('is_deleted', false)
            ->orderByRaw("FIELD(role,'GROUP_LEADER','TEAM_LEADER','INTRODUCER')")
            ->orderBy('full_name');

        $total    = $query->count();
        $agents   = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();
        $lastPage = max(1, ceil($total / $perPage));
        $from     = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to       = min($page * $perPage, $total);

        $result = $agents->map(function($a) {
            $hasChildren = DB::table('agents')->where('parent_id', $a->agent_id)->where('is_deleted', false)->exists();
            $totalTL     = DB::table('agents')->where('parent_id', $a->agent_id)->where('role', 'TEAM_LEADER')->where('is_deleted', false)->count();
            $totalIntro  = DB::table('agents')->where('parent_id', $a->agent_id)->where('role', 'INTRODUCER')->where('is_deleted', false)->count();

            return [
                'agent_id'    => $a->agent_id,
                'full_name'   => $a->full_name,
                'member_code' => $a->member_code ?? $a->agent_code,
                'agent_code'  => $a->agent_code,
                'role'        => $a->role,
                'email'       => $a->email,
                'phone'       => $a->phone,
                'status'      => $a->status,
                'joined'      => \Carbon\Carbon::parse($a->created_at)->format('d M Y'),
                'has_children'=> $hasChildren,
                'total_tl'    => $totalTL,
                'total_intro' => $totalIntro,
            ];
        });

        return response()->json([
            'agents' => $result,
            'pagination' => $total > $perPage ? [
                'current_page' => $page,
                'last_page'    => $lastPage,
                'from'         => $from,
                'to'           => $to,
                'total'        => $total,
            ] : null,
        ]);
    }

    // -------------------------------------------------------
    // Agent Edit
    // -------------------------------------------------------
    public function editAgent(Request $request, string $id)
    {
        $agent  = Agent::where('agent_id', $id)->where('is_deleted', false)->firstOrFail();
        $back   = $request->get('back', route('admin.network'));
        $group  = $agent->group_id ? DB::table('groups')->where('group_id', $agent->group_id)->first() : null;
        $states = [
            'Johor','Kedah','Kelantan','Melaka','Negeri Sembilan',
            'Pahang','Perak','Perlis','Pulau Pinang','Sabah',
            'Sarawak','Selangor','Terengganu','Kuala Lumpur',
            'Labuan','Putrajaya'
        ];
        return view('admin.network.edit-agent', compact('agent', 'back', 'group', 'states'));
    }

    public function updateAgent(Request $request, string $id)
    {
        $agent = Agent::where('agent_id', $id)->where('is_deleted', false)->firstOrFail();

        $rules = [
            'full_name' => ['required', 'string', 'max:200'],
            'email'     => ['required', 'email', 'max:200', 'unique:agents,email,'.$id.',agent_id'],
            'phone'     => ['required', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'status'    => ['required', 'in:ACTIVE,INACTIVE,TERMINATED,RISK_DEBT,RESIGNED,DECEASED'],
        ];

        if ($agent->role === 'GROUP_LEADER') {
            $rules['group_email'] = ['nullable', 'email', 'max:200'];
        }

        $request->validate($rules);

        $before = $agent->toArray();

        $agent->full_name  = $request->full_name;
        $agent->email      = $request->email;
        $agent->phone      = $request->phone;
        $agent->bank_name  = $request->bank_name;
        $agent->status     = $request->status;
        $agent->updated_by = auth('agent')->id();
        $agent->save();

        if ($agent->role === 'GROUP_LEADER' && $agent->group_id && $request->filled('group_email')) {
            DB::table('groups')->where('group_id', $agent->group_id)->update([
                'group_email' => strtolower($request->group_email),
                'updated_at'  => now(),
            ]);
        }

        AuditService::logChange('agents', $id, 'UPDATE', $before, $request->all());

        $back = $request->get('back', route('admin.network'));
        return redirect($back)->with('success', $agent->full_name . ' updated successfully.');
    }

    // -------------------------------------------------------
    // Level 1 — All GLs (network index — no month filter, shows all time)
    // -------------------------------------------------------
    public function index(Request $request)
    {
        $search  = $request->input('search', '');
        $sortBy  = $request->input('sort', 'full_name');
        $sortDir = $request->input('dir', 'asc');

        $allowedSorts = ['full_name', 'agent_code', 'created_at', 'total_tl', 'total_intro', 'total_premium', 'commission_balance'];
        if (!in_array($sortBy, $allowedSorts)) $sortBy = 'full_name';

        $query = DB::table('agents as gl')
            ->where('gl.role', 'GROUP_LEADER')
            ->where('gl.is_deleted', false)
            ->leftJoin('agents as tl', function($join) {
                $join->on('tl.parent_id', '=', 'gl.agent_id')
                     ->where('tl.role', 'TEAM_LEADER')
                     ->where('tl.is_deleted', false);
            })
            ->leftJoin('agents as intro', function($join) {
                $join->on('intro.parent_id', '=', 'tl.agent_id')
                     ->where('intro.role', 'INTRODUCER')
                     ->where('intro.is_deleted', false);
            })
            ->leftJoin('sales_transactions as st', function($join) {
                $join->on('st.agent_id', '=', 'gl.agent_id')
                     ->where('st.is_deleted', false)
                     ->whereMonth('st.created_at', now()->month)
                     ->whereYear('st.created_at', now()->year);
            })
            ->select(
                'gl.agent_id', 'gl.full_name', 'gl.agent_code', 'gl.status',
                'gl.commission_balance', 'gl.created_at',
                DB::raw('COUNT(DISTINCT tl.agent_id) as total_tl'),
                DB::raw('COUNT(DISTINCT intro.agent_id) as total_intro'),
                DB::raw('COALESCE(SUM(st.premium_amount), 0) as total_premium'),
                DB::raw('COUNT(DISTINCT st.policy_id) as total_transactions')
            )
            ->groupBy('gl.agent_id', 'gl.full_name', 'gl.agent_code', 'gl.status', 'gl.commission_balance', 'gl.created_at');

        // Filter-first: no records until filter applied
        $hasFilter = $search || $request->input('status') || $request->input('joined_from') || $request->input('joined_to') || $request->input('show_all');
        $status     = $request->input('status', '');
        $joinedFrom = $request->input('joined_from', '');
        $joinedTo   = $request->input('joined_to', '');

        $summary = Cache::remember('network_summary', 300, function() {
            return DB::table('agents')
                ->where('is_deleted', false)
                ->selectRaw("
                    SUM(CASE WHEN role='GROUP_LEADER' AND status='ACTIVE' THEN 1 ELSE 0 END) as total_gl,
                    SUM(CASE WHEN role='TEAM_LEADER' AND status='ACTIVE' THEN 1 ELSE 0 END) as total_tl,
                    SUM(CASE WHEN role='INTRODUCER' AND status='ACTIVE' THEN 1 ELSE 0 END) as total_intro
                ")->first();
        });

        if (!$hasFilter) {
            $gls = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15, 1);
            return view('admin.network.index', compact('gls', 'summary', 'search', 'sortBy', 'sortDir', 'status', 'joinedFrom', 'joinedTo'));
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('gl.full_name', 'like', "%{$search}%")
                  ->orWhere('gl.agent_code', 'like', "%{$search}%");
            });
        }
        if ($status) $query->where('gl.status', $status);
        if ($joinedFrom) $query->whereDate('gl.created_at', '>=', $joinedFrom);
        if ($joinedTo)   $query->whereDate('gl.created_at', '<=', $joinedTo);

        if ($sortBy === 'sales' || in_array($sortBy, ['total_tl', 'total_intro', 'total_premium'])) {
            $query->orderByDesc('total_premium');
        } else {
            $query->orderBy('gl.full_name', 'asc');
        }

        $gls = $query->paginate(15)->withQueryString();

        return view('admin.network.index', compact('gls', 'summary', 'search', 'sortBy', 'sortDir', 'status', 'joinedFrom', 'joinedTo'));
    }

    // -------------------------------------------------------
    // Level 2 — GL → All TLs (respects month/year from request)
    // -------------------------------------------------------
    public function byGL(Request $request, string $glId)
    {
        $gl         = Agent::where('agent_id', $glId)->where('is_deleted', false)->firstOrFail();
        $search     = $request->input('search', '');
        $sortBy     = $request->input('sort', 'full_name');
        $sortDir    = $request->input('dir', 'asc');
        $selMonth   = (int)$request->get('month', now()->month);
        $selYear    = (int)$request->get('year',  now()->year);

        $query = DB::table('agents as tl')
            ->where('tl.parent_id', $glId)
            ->where('tl.role', 'TEAM_LEADER')
            ->where('tl.is_deleted', false)
            ->leftJoin('agents as intro', function($join) {
                $join->on('intro.parent_id', '=', 'tl.agent_id')
                     ->where('intro.role', 'INTRODUCER')
                     ->where('intro.is_deleted', false);
            })
            ->leftJoin('sales_transactions as st', function($join) use ($selMonth, $selYear) {
                $join->on('st.agent_id', '=', 'tl.agent_id')
                     ->where('st.is_deleted', false)
                     ->whereMonth('st.created_at', $selMonth)
                     ->whereYear('st.created_at', $selYear);
            })
            ->select(
                'tl.agent_id', 'tl.full_name', 'tl.agent_code', 'tl.status',
                'tl.commission_balance', 'tl.created_at',
                DB::raw('COUNT(DISTINCT intro.agent_id) as total_intro'),
                DB::raw('SUM(CASE WHEN intro.status="ACTIVE" THEN 1 ELSE 0 END) as active_intro'),
                DB::raw('COALESCE(SUM(st.premium_amount), 0) as total_premium'),
                DB::raw('COUNT(DISTINCT st.policy_id) as total_transactions')
            )
            ->groupBy('tl.agent_id', 'tl.full_name', 'tl.agent_code', 'tl.status', 'tl.commission_balance', 'tl.created_at');

        $status     = $request->input('status', '');
        $joinedFrom = $request->input('joined_from', '');
        $joinedTo   = $request->input('joined_to', '');
        $hasFilter  = $search || $status || $joinedFrom || $joinedTo || $request->input('show_all');

        $glSummary = DB::table('sales_transactions')
            ->where('agent_id', $glId)->where('is_deleted', false)
            ->whereMonth('created_at', $selMonth)->whereYear('created_at', $selYear)
            ->selectRaw('COALESCE(SUM(premium_amount),0) as total_premium, COUNT(policy_id) as total_transactions')
            ->first();

        if (!$hasFilter) {
            $tls = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15, 1);
            return view('admin.network.by-gl', compact('gl', 'tls', 'glSummary', 'search', 'sortBy', 'sortDir', 'status', 'joinedFrom', 'joinedTo', 'selMonth', 'selYear'));
        }

        if ($search) $query->where(function($q) use ($search) {
            $q->where('tl.full_name', 'like', "%{$search}%")
              ->orWhere('tl.agent_code', 'like', "%{$search}%");
        });
        if ($status) $query->where('tl.status', $status);
        if ($joinedFrom) $query->whereDate('tl.created_at', '>=', $joinedFrom);
        if ($joinedTo)   $query->whereDate('tl.created_at', '<=', $joinedTo);

        $query->orderBy("tl.full_name", 'asc');
        $tls = $query->paginate(15)->withQueryString();

        return view('admin.network.by-gl', compact('gl', 'tls', 'glSummary', 'search', 'sortBy', 'sortDir', 'status', 'joinedFrom', 'joinedTo', 'selMonth', 'selYear'));
    }

    // -------------------------------------------------------
    // Level 3 — TL → All Introducers (respects month/year from request)
    // -------------------------------------------------------
    public function byTL(Request $request, string $glId, string $tlId)
    {
        $gl         = Agent::where('agent_id', $glId)->where('is_deleted', false)->firstOrFail();
        $tl         = Agent::where('agent_id', $tlId)->where('is_deleted', false)->firstOrFail();
        $search     = $request->input('search', '');
        $sortBy     = $request->input('sort', 'full_name');
        $sortDir    = $request->input('dir', 'asc');
        $selMonth   = (int)$request->get('month', now()->month);
        $selYear    = (int)$request->get('year',  now()->year);

        $query = DB::table('agents as i')
            ->where('i.parent_id', $tlId)
            ->where('i.role', 'INTRODUCER')
            ->where('i.is_deleted', false)
            ->leftJoin('sales_transactions as st', function($join) use ($selMonth, $selYear) {
                $join->on('st.agent_id', '=', 'i.agent_id')
                     ->where('st.is_deleted', false)
                     ->whereMonth('st.created_at', $selMonth)
                     ->whereYear('st.created_at', $selYear);
            })
            ->leftJoin('commission_transactions as ct', function($join) use ($selMonth, $selYear) {
                $join->on('ct.agent_id', '=', 'i.agent_id')
                     ->whereMonth('ct.created_at', $selMonth)
                     ->whereYear('ct.created_at', $selYear);
            })
            ->select(
                'i.agent_id', 'i.full_name', 'i.agent_code', 'i.status', 'i.created_at',
                DB::raw('COALESCE(SUM(DISTINCT st.premium_amount), 0) as total_premium'),
                DB::raw('COUNT(DISTINCT st.policy_id) as total_transactions'),
                DB::raw('COALESCE(SUM(ct.commission_amount), 0) as total_commission')
            )
            ->groupBy('i.agent_id', 'i.full_name', 'i.agent_code', 'i.status', 'i.created_at');

        $status     = $request->input('status', '');
        $joinedFrom = $request->input('joined_from', '');
        $joinedTo   = $request->input('joined_to', '');
        $hasFilter  = $search || $status || $joinedFrom || $joinedTo || $request->input('show_all');

        if (!$hasFilter) {
            $introducers = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15, 1);
            return view('admin.network.by-tl', compact('gl', 'tl', 'introducers', 'search', 'sortBy', 'sortDir', 'status', 'joinedFrom', 'joinedTo', 'selMonth', 'selYear'));
        }

        if ($search) $query->where(function($q) use ($search) {
            $q->where('i.full_name', 'like', "%{$search}%")
              ->orWhere('i.agent_code', 'like', "%{$search}%");
        });
        if ($status) $query->where('i.status', $status);
        if ($joinedFrom) $query->whereDate('i.created_at', '>=', $joinedFrom);
        if ($joinedTo)   $query->whereDate('i.created_at', '<=', $joinedTo);

        $query->orderByRaw('LENGTH(i.agent_code), i.agent_code');
        $introducers = $query->paginate(15)->withQueryString();

        return view('admin.network.by-tl', compact('gl', 'tl', 'introducers', 'search', 'sortBy', 'sortDir', 'status', 'joinedFrom', 'joinedTo', 'selMonth', 'selYear'));
    }

    // -------------------------------------------------------
    // Level 4 — Introducer → All Transactions (respects month/year from request)
    // -------------------------------------------------------
    public function byIntroducer(Request $request, string $glId, string $tlId, string $introducerId)
    {
        $gl         = Agent::where('agent_id', $glId)->where('is_deleted', false)->firstOrFail();
        $tl         = Agent::where('agent_id', $tlId)->where('is_deleted', false)->firstOrFail();
        $introducer = Agent::where('agent_id', $introducerId)->where('is_deleted', false)->firstOrFail();
        $search     = $request->input('search', '');
        $selMonth   = (int)$request->get('month', now()->month);
        $selYear    = (int)$request->get('year',  now()->year);

        $query = DB::table('sales_transactions as st')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->where('st.agent_id', $introducerId)
            ->where('st.is_deleted', false)
            ->whereMonth('st.created_at', $selMonth)
            ->whereYear('st.created_at', $selYear)
            ->select(
                'st.policy_id', 'st.policy_number', 'st.premium_amount', 'st.status',
                'st.coverage_start', 'st.coverage_end', 'st.renewal_date',
                'st.created_at', 'st.renewal_date',
                'v.vendor_name', 'p.product_name',
                'c.full_name as customer_name'
            );

        if ($search && strlen($search) >= 3) {
            $query->where(function($q) use ($search) {
                $q->where('st.policy_number', 'like', "{$search}%")
                  ->orWhere('c.full_name', 'like', "%{$search}%");
            });
        }

        $transactions = $query->orderByDesc('st.created_at')->paginate(10)->withQueryString();

        $summary = DB::table('sales_transactions')
            ->where('agent_id', $introducerId)
            ->where('is_deleted', false)
            ->selectRaw('
                COALESCE(SUM(premium_amount), 0) as total_premium,
                COUNT(policy_id) as total_transactions,
                COALESCE(SUM(CASE WHEN MONTH(created_at)=? AND YEAR(created_at)=? THEN premium_amount ELSE 0 END), 0) as premium_mtd
            ', [$selMonth, $selYear])
            ->first();

        $totalCommission = DB::table('commission_transactions')
            ->where('agent_id', $introducerId)
            ->whereMonth('created_at', $selMonth)
            ->whereYear('created_at', $selYear)
            ->sum('commission_amount') ?? 0;

        return view('admin.network.by-introducer', compact(
            'gl', 'tl', 'introducer', 'transactions',
            'summary', 'totalCommission', 'search'
        ));
    }

    // -------------------------------------------------------
    // Excel Export — Full Group Data (3 sheets)
    // -------------------------------------------------------
    public function exportGL(Request $request, string $glId)
    {
        $gl       = Agent::where('agent_id', $glId)->where('is_deleted', false)->firstOrFail();
        $month    = (int)$request->get('month', now()->month);
        $year     = (int)$request->get('year',  now()->year);
        $monthName = \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y');
        $today    = now()->format('d M Y');
        $filename = 'GeneralLink_' . str_replace(' ', '', $gl->full_name) . '_' . $gl->agent_code . '_' . str_replace(' ', '', $monthName) . '_' . now()->format('dMY') . '.xlsx';

        // ── Get all agent IDs in this group (used by all sheets) ──
        $groupAgentIds = DB::table('agents')
            ->where('group_id', $gl->group_id)
            ->where('is_deleted', false)
            ->pluck('agent_id')
            ->toArray();

        // ── Sheet 1: TL Summary ──
        $tlAgents = DB::table('agents as tl')
            ->where('tl.parent_id', $glId)
            ->where('tl.role', 'TEAM_LEADER')
            ->where('tl.is_deleted', false)
            ->leftJoin('agents as intro', function($join) {
                $join->on('intro.parent_id', '=', 'tl.agent_id')
                     ->where('intro.role', 'INTRODUCER')
                     ->where('intro.is_deleted', false);
            })
            ->select(
                'tl.agent_id', 'tl.full_name', 'tl.agent_code', 'tl.status', 'tl.created_at',
                DB::raw('COUNT(DISTINCT intro.agent_id) as total_intro')
            )
            ->groupBy('tl.agent_id', 'tl.full_name', 'tl.agent_code', 'tl.status', 'tl.created_at')
            ->orderBy('tl.full_name')
            ->get();

        // For each TL, get ALL agents under them recursively (TL + Intro + Sub-Intro)
        $tls = $tlAgents->map(function($tl) use ($month, $year, $groupAgentIds) {
            // Get direct introducers under this TL
            $introIds = DB::table('agents')
                ->where('parent_id', $tl->agent_id)
                ->where('is_deleted', false)
                ->pluck('agent_id')
                ->toArray();

            // Get sub-introducers (under the introducers)
            $subIntroIds = count($introIds) > 0
                ? DB::table('agents')
                    ->whereIn('parent_id', $introIds)
                    ->where('is_deleted', false)
                    ->pluck('agent_id')
                    ->toArray()
                : [];

            // All agent IDs under this TL
            $agentIds = array_merge([$tl->agent_id], $introIds, $subIntroIds);

            $total = DB::table('sales_transactions')
                ->whereIn('agent_id', $agentIds)
                ->where('is_deleted', false)
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->sum('premium_amount');

            $tl->total_sales = (float)$total;
            return $tl;
        });

        $sheet1 = [];
        $sheet1[] = ['GeneralLink Digital Ecosystem'];
        $sheet1[] = ['Group Leader: ' . $gl->full_name . ' (' . $gl->agent_code . ')'];
        $sheet1[] = ['Period: ' . $monthName];
        $sheet1[] = ['Downloaded: ' . $today];
        $sheet1[] = [];
        $sheet1[] = ['Name', 'Code', 'Introducers', 'Sales Amount (RM)', 'Status', 'Joined'];
        // Add GL own sales as first row
        $glOwnSales = (float)DB::table('sales_transactions')
            ->where('agent_id', $glId)
            ->where('is_deleted', false)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->sum('premium_amount');
        $sheet1[] = [
            $gl->full_name . ' (GL)',
            $gl->agent_code,
            0,
            $glOwnSales,
            $gl->status,
            \Carbon\Carbon::parse($gl->created_at)->format('d M Y'),
        ];
        foreach ($tls as $tl) {
            $sheet1[] = [
                $tl->full_name,
                $tl->agent_code,
                (int)$tl->total_intro,
                (float)$tl->total_sales,
                $tl->status,
                \Carbon\Carbon::parse($tl->created_at)->format('d M Y'),
            ];
        }

        // ── Sheet 2: All Agents under this GL (all levels) ───
        // Build TL lookup for all agents in group
        $tlIdsList = DB::table('agents')->where('parent_id', $glId)->where('role','TEAM_LEADER')->where('is_deleted',false)->pluck('agent_id')->toArray();
        $tlLookup = [];
        foreach ($tlIdsList as $tlId) {
            $tlInfo = DB::table('agents')->where('agent_id', $tlId)->first(['full_name','agent_code']);
            $tlLookup[$tlId] = ['name' => $tlInfo->full_name, 'code' => $tlInfo->agent_code];
            $l1Ids = DB::table('agents')->where('parent_id', $tlId)->where('is_deleted',false)->pluck('agent_id')->toArray();
            foreach ($l1Ids as $l1Id) {
                $tlLookup[$l1Id] = ['name' => $tlInfo->full_name, 'code' => $tlInfo->agent_code];
                $l2Ids = DB::table('agents')->where('parent_id', $l1Id)->where('is_deleted',false)->pluck('agent_id')->toArray();
                foreach ($l2Ids as $l2Id) {
                    $tlLookup[$l2Id] = ['name' => $tlInfo->full_name, 'code' => $tlInfo->agent_code];
                }
            }
        }

        $allAgents = DB::table('agents as a')
            ->whereIn('a.agent_id', $groupAgentIds)
            ->where('a.role', '!=', 'GROUP_LEADER')
            ->leftJoin('sales_transactions as st', function($join) use ($month, $year) {
                $join->on('st.agent_id', '=', 'a.agent_id')
                     ->where('st.is_deleted', false)
                     ->whereMonth('st.created_at', $month)
                     ->whereYear('st.created_at', $year);
            })
            ->select('a.agent_id','a.full_name','a.agent_code','a.role','a.status','a.created_at',
                DB::raw('COALESCE(SUM(st.premium_amount), 0) as total_sales'))
            ->groupBy('a.agent_id','a.full_name','a.agent_code','a.role','a.status','a.created_at')
            ->orderBy('a.full_name')
            ->get()
            ->map(function($a) use ($tlLookup) {
                $a->tl_name = $tlLookup[$a->agent_id]['name'] ?? '-';
                $a->tl_code = $tlLookup[$a->agent_id]['code'] ?? '-';
                return $a;
            })
            ->sortBy('tl_name');
        $intros = $allAgents;

        $sheet2 = [];
        $sheet2[] = ['GeneralLink Digital Ecosystem'];
        $sheet2[] = ['Group Leader: ' . $gl->full_name . ' (' . $gl->agent_code . ')'];
        $sheet2[] = ['Period: ' . $monthName];
        $sheet2[] = ['Downloaded: ' . $today];
        $sheet2[] = [];
        $sheet2[] = ['Team Leader', 'TL Code', 'Introducer', 'Code', 'Sales Amount (RM)', 'Status', 'Joined'];
        // Add GL own sales as first row
        $sheet2[] = [
            '-',
            '-',
            $gl->full_name . ' (GL)',
            $gl->agent_code,
            $glOwnSales,
            $gl->status,
            \Carbon\Carbon::parse($gl->created_at)->format('d M Y'),
        ];
        foreach ($intros as $i) {
            $sheet2[] = [
                $i->tl_name,
                $i->tl_code,
                $i->full_name . ' (' . $i->role . ')',
                $i->agent_code,
                (float)$i->total_sales,
                $i->status,
                \Carbon\Carbon::parse($i->created_at)->format('d M Y'),
            ];
        }

        // ── Sheet 3: All Transactions ────────────────────────

        $txns = DB::table('sales_transactions as st')
            ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->whereIn('st.agent_id', $groupAgentIds)
            ->where('st.is_deleted', false)
            ->whereMonth('st.created_at', $month)
            ->whereYear('st.created_at', $year)
            ->select(
                'st.policy_number', 'c.full_name as customer_name',
                'a.full_name as agent_name', 'a.agent_code', 'a.role',
                'v.vendor_name', 'p.product_name',
                'st.premium_amount', 'st.status', 'st.created_at', 'st.renewal_date'
            )
            ->orderBy('a.full_name')
            ->orderByDesc('st.created_at')
            ->get();

        $sheet3 = [];
        $sheet3[] = ['GeneralLink Digital Ecosystem'];
        $sheet3[] = ['Group Leader: ' . $gl->full_name . ' (' . $gl->agent_code . ')'];
        $sheet3[] = ['Period: ' . $monthName];
        $sheet3[] = ['Downloaded: ' . $today];
        $sheet3[] = [];
        $sheet3[] = ['Transaction No.', 'Customer', 'Agent', 'Code', 'Role', 'Vendor', 'Product', 'Sales Amount (RM)', 'Date', 'Renewal Date', 'Status'];
        foreach ($txns as $tx) {
            $sheet3[] = [
                $tx->policy_number,
                $tx->customer_name ?? '—',
                $tx->agent_name,
                $tx->agent_code,
                $tx->role,
                $tx->vendor_name,
                $tx->product_name,
                (float)$tx->premium_amount,
                \Carbon\Carbon::parse($tx->created_at)->format('d M Y'),
                $tx->renewal_date ? \Carbon\Carbon::parse($tx->renewal_date)->format('d M Y') : '—',
                $tx->status,
            ];
        }

        // ── Build Excel using PhpSpreadsheet ─────────────────
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        // Sheet 1 — TL Summary
        $ws1 = $spreadsheet->getActiveSheet();
        $ws1->setTitle('TL Summary');
        foreach ($sheet1 as $rowIdx => $row) {
            foreach ($row as $colIdx => $val) {
                $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1) . ($rowIdx + 1);
                if (is_float($val) || is_int($val)) {
                    $ws1->getCell($coord)->setValueExplicit($val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                } else {
                    $ws1->getCell($coord)->setValue($val);
                }
            }
        }
        // Bold headers
        $ws1->getStyle('A1:A4')->getFont()->setBold(true);
        $ws1->getStyle('A6:G6')->getFont()->setBold(true);
        // Sales Amount (column D) - number format and right align
        $lastRow1 = $ws1->getHighestRow();
        $ws1->getStyle('D7:D'.$lastRow1)->getNumberFormat()->setFormatCode('#,##0.00');
        $ws1->getStyle('D7:D'.$lastRow1)->getAlignment()->setHorizontal('right');
        $ws1->getStyle('C7:C'.$lastRow1)->getAlignment()->setHorizontal('center');
        // Add total row
        $totalRow1 = $lastRow1 + 2;
        $ws1->getCell('C'.$totalRow1)->setValue('Total');
        $ws1->getStyle('C'.$totalRow1)->getFont()->setBold(true)->setSize(12);
        $ws1->getCell('D'.$totalRow1)->setValue('=SUM(D7:D'.$lastRow1.')');
        $ws1->getStyle('D'.$totalRow1)->getNumberFormat()->setFormatCode('#,##0.00');
        $ws1->getStyle('D'.$totalRow1)->getFont()->setBold(true)->setSize(12);
        $ws1->getStyle('D'.$totalRow1)->getAlignment()->setHorizontal('right');
        foreach (range('A','F') as $col) {
            $ws1->getColumnDimension($col)->setAutoSize(true);
        }

        // Sheet 2 — Introducers
        $ws2 = $spreadsheet->createSheet();
        $ws2->setTitle('Introducers');
        foreach ($sheet2 as $rowIdx => $row) {
            foreach ($row as $colIdx => $val) {
                $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1) . ($rowIdx + 1);
                if (is_float($val) || is_int($val)) {
                    $ws2->getCell($coord)->setValueExplicit($val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                } else {
                    $ws2->getCell($coord)->setValue($val);
                }
            }
        }
        $ws2->getStyle('A1:A4')->getFont()->setBold(true);
        $ws2->getStyle('A6:G6')->getFont()->setBold(true);
        $lastRow2 = $ws2->getHighestRow();
        $ws2->getStyle('E7:E'.$lastRow2)->getNumberFormat()->setFormatCode('#,##0.00');
        $ws2->getStyle('E7:E'.$lastRow2)->getAlignment()->setHorizontal('right');
        // Add total row
        $totalRow2 = $lastRow2 + 2;
        $ws2->getCell('D'.$totalRow2)->setValue('Total');
        $ws2->getStyle('D'.$totalRow2)->getFont()->setBold(true)->setSize(12);
        $ws2->getCell('E'.$totalRow2)->setValue('=SUM(E7:E'.$lastRow2.')');
        $ws2->getStyle('E'.$totalRow2)->getNumberFormat()->setFormatCode('#,##0.00');
        $ws2->getStyle('E'.$totalRow2)->getFont()->setBold(true)->setSize(12);
        $ws2->getStyle('E'.$totalRow2)->getAlignment()->setHorizontal('right');
        foreach (range('A','G') as $col) {
            $ws2->getColumnDimension($col)->setAutoSize(true);
        }

        // Sheet 3 — Transactions
        $ws3 = $spreadsheet->createSheet();
        $ws3->setTitle('Transactions');
        foreach ($sheet3 as $rowIdx => $row) {
            foreach ($row as $colIdx => $val) {
                $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1) . ($rowIdx + 1);
                if (is_float($val) || is_int($val)) {
                    $ws3->getCell($coord)->setValueExplicit($val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                } else {
                    $ws3->getCell($coord)->setValue($val);
                }
            }
        }
        $ws3->getStyle('A1:A4')->getFont()->setBold(true);
        $ws3->getStyle('A6:K6')->getFont()->setBold(true);
        $lastRow3 = $ws3->getHighestRow();
        $ws3->getStyle('H7:H'.$lastRow3)->getNumberFormat()->setFormatCode('#,##0.00');
        $ws3->getStyle('H7:H'.$lastRow3)->getAlignment()->setHorizontal('right');
        // Add total row
        $totalRow3 = $lastRow3 + 2;
        $ws3->getCell('G'.$totalRow3)->setValue('Total');
        $ws3->getStyle('G'.$totalRow3)->getFont()->setBold(true)->setSize(12);
        $ws3->getCell('H'.$totalRow3)->setValue('=SUM(H7:H'.$lastRow3.')');
        $ws3->getStyle('H'.$totalRow3)->getNumberFormat()->setFormatCode('#,##0.00');
        $ws3->getStyle('H'.$totalRow3)->getFont()->setBold(true)->setSize(12);
        $ws3->getStyle('H'.$totalRow3)->getAlignment()->setHorizontal('right');
        foreach (range('A','K') as $col) {
            $ws3->getColumnDimension($col)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // -------------------------------------------------------
    // All Team Leaders (Box 2 drill down)
    // -------------------------------------------------------
    public function allTLs(Request $request)
    {
        $sortBy  = $request->input('sort', 'sales');
        $month   = (int)$request->get('month', now()->month);
        $year    = (int)$request->get('year', now()->year);
        $glId    = $request->input('gl_id', '');
        $search  = $request->input('search', '');

        $query = DB::table('agents as tl')
            ->where('tl.role', 'TEAM_LEADER')
            ->where('tl.is_deleted', false)
            ->leftJoin('agents as grp_gl', function($join) {
                $join->on('grp_gl.group_id', '=', 'tl.group_id')
                     ->where('grp_gl.role', 'GROUP_LEADER')
                     ->where('grp_gl.is_deleted', false);
            })
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
                'grp_gl.agent_id as gl_id', 'grp_gl.full_name as gl_name', 'grp_gl.agent_code as gl_code',
                DB::raw('COUNT(DISTINCT intro.agent_id) as total_intro'),
                DB::raw('COALESCE(SUM(DISTINCT st.premium_amount), 0) as total_sales'),
                DB::raw('COALESCE(SUM(DISTINCT ct.commission_amount), 0) as total_earn')
            )
            ->groupBy('tl.agent_id', 'tl.full_name', 'tl.agent_code', 'tl.status', 'tl.created_at',
                      'grp_gl.agent_id', 'grp_gl.full_name', 'grp_gl.agent_code');

        $glList = DB::table('agents')
            ->where('role', 'GROUP_LEADER')->where('is_deleted', false)
            ->orderBy('agent_code')->get(['agent_id', 'full_name', 'agent_code']);

        $hasFilter = $search || ($glId && $glId !== 'all') || $status || $joinedFrom || $joinedTo || $request->input('show_all');
        if (!$hasFilter) {
            $tls = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15, 1);
            return view('admin.network.all-tls', compact('tls', 'sortBy', 'month', 'year', 'glList', 'status', 'joinedFrom', 'joinedTo'));
        }

        $status     = $request->input('status', '');
        $joinedFrom = $request->input('joined_from', '');
        $joinedTo   = $request->input('joined_to', '');

        if ($glId && $glId !== 'all') $query->where('grp_gl.agent_id', $glId);
        if ($search) $query->where(function($q) use ($search) {
            $q->where('tl.full_name', 'like', "%{$search}%")
              ->orWhere('tl.agent_code', 'like', "%{$search}%");
        });
        if ($status) $query->where('tl.status', $status);
        if ($joinedFrom) $query->whereDate('tl.created_at', '>=', $joinedFrom);
        if ($joinedTo)   $query->whereDate('tl.created_at', '<=', $joinedTo);

        if ($sortBy === 'earn') $query->orderByDesc('total_earn');
        elseif ($sortBy === 'name') $query->orderBy('tl.full_name');
        else $query->orderByDesc('total_sales');

        $tls = $query->paginate(15)->withQueryString();

        return view('admin.network.all-tls', compact('tls', 'sortBy', 'month', 'year', 'glList'));
    }

    // -------------------------------------------------------
    // All Introducers (Box 2 drill down)
    // -------------------------------------------------------
    public function allIntros(Request $request)
    {
        $month      = (int)$request->get('month', now()->month);
        $year       = (int)$request->get('year', now()->year);
        $glId       = $request->input('gl_id', '');
        $tlId       = $request->input('tl_id', '');
        $search     = $request->input('search', '');
        $status     = $request->input('status', '');
        $joinedFrom = $request->input('joined_from', '');
        $joinedTo   = $request->input('joined_to', '');
        $scope      = $request->input('scope', 'direct'); // direct or all
        $hasFilter  = $search || $glId || $tlId || $status || $joinedFrom || $joinedTo || $request->input('show_all');

        $glList = DB::table('agents')->where('role','GROUP_LEADER')->where('is_deleted',false)->orderBy('agent_code')->get(['agent_id','full_name','agent_code']);

        if (!$hasFilter) {
            $intros = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15, 1);
            $tlList = collect();
            $scope  = 'direct';
            return view('admin.network.all-intros', compact('intros','month','year','glList','tlList','scope'));
        }

        // Step 1: determine which parent_ids to filter by
        $parentIds = [];
        if ($tlId) {
            // Specific TL selected - show direct intros under that TL
            $parentIds = [$tlId];
        } elseif ($glId && $scope === 'direct') {
            // GL selected, Direct only - show direct intros under GL
            $parentIds = [$glId];
        } elseif ($glId && $scope === 'all') {
            // GL selected, All - show intros under GL + all TLs under GL
            $tlIds = DB::table('agents')->where('parent_id',$glId)->where('is_deleted',false)->pluck('agent_id')->toArray();
            $parentIds = array_merge([$glId], $tlIds);
        }

        // Step 2: build simple query
        $query = DB::table('agents as i')
            ->where('i.role','INTRODUCER')
            ->where('i.is_deleted',false)
            ->leftJoin('agents as p','p.agent_id','=','i.parent_id')
            ->select('i.agent_id','i.full_name','i.agent_code','i.status','i.created_at','p.full_name as parent_name','p.role as parent_role');

        if (!empty($parentIds)) $query->whereIn('i.parent_id', $parentIds);
        if ($search)     $query->where('i.full_name','like',"%{$search}%");
        if ($status)     $query->where('i.status',$status);
        if ($joinedFrom) $query->whereDate('i.created_at','>=',$joinedFrom);
        if ($joinedTo)   $query->whereDate('i.created_at','<=',$joinedTo);

        $query->orderByRaw('LENGTH(i.agent_code), i.agent_code');
        $intros = $query->paginate(15)->withQueryString();

        // Get TL list for selected GL
        $tlList = $glId ? DB::table('agents')->where('parent_id',$glId)->where('role','TEAM_LEADER')->where('is_deleted',false)->orderBy('agent_code')->get(['agent_id','full_name','agent_code']) : collect();

        return view('admin.network.all-intros', compact('intros','month','year','glList','tlList','scope'));
    }

}
