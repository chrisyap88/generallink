<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\DataScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// NEW 25 Jul 2026 — Customer KPI Dashboard. REPLACES "My Badges" (task
// #212/#223/#224) per Chris: "remove my badge confusing replace with
// customer kpi." Chris's request verbatim: "show ALL CUSTOMER kpi BY
// SALES AND EARNING INCOME TOP 3, LIKE KPI DASHBOARD 1 WITH RADIO, WITH
// VIEW ALL, CATEGORY, TYPE, OCCUPATION, CUSTOMER SOURCE, STATUS FILTER
// BY COMPANY LEVEL DRILL DOWN FROM GROUP FROM TL AND INTRODUCER, DRILL
// DOWN MUST SHOW TRANSACTION BY VENDOR, TYPE DATE, SALES AND EARNING.
// FOLLOW THE KPI DASHBOARD DESIGN."
//
// Reuses the SAME proven cascade mechanics as the Yearly Renewal
// Forecast (RenewalForecastController) — same query param names
// (gl_id/tl_id/introducer_id), same typeahead endpoint
// (renewal-forecast.agent-typeahead, generic and already scoped per
// viewer role), same node-and-descendants technique — rather than
// re-inventing a second cascade. Every non-Admin viewer's cascade
// selection is still intersected with their own DataScopeService base
// scope, never trusted at face value (same guarantee used everywhere
// else in this app).
//
// Scoping decision: ranks customers by SALES TRANSACTIONS/COMMISSIONS
// attributed to the agent scope in view (sales_transactions.agent_id /
// commission_transactions.agent_id) — the same "whole team" rule
// DataScopeService::applyToTransactions() and Renewal Forecast already
// use — NOT customers.owned_by_agent_id (that's a different, deliberately
// stricter single-owner-only rule used for the Customer Maintenance CRUD
// screens per task #129). A GL/TL needs to see customers their WHOLE
// team sold to, exactly like every other KPI screen in this app.
class CustomerKpiController extends Controller
{
    // Same role -> route-prefix mapping used by RenewalForecastController
    // — needed because customers.show is registered separately inside
    // each role-prefixed route group (gl.customers.show, tl.customers.show,
    // etc.), not once as a single shared name. Per Chris: "when i point
    // to the customer name you allow me drill down to see the entire
    // customer profile" — every customer name in Customer KPI should
    // link to that full tabbed profile (Profile/Sales History/
    // Reminders/Activity Log/KPI Dashboard — task #125), not just the
    // transaction list this screen builds itself.
    private function rolePrefix($agent): string
    {
        return match ($agent->role) {
            'ADMIN' => 'admin',
            'GROUP_LEADER' => 'gl',
            'TEAM_LEADER' => 'tl',
            default => 'introducer',
        };
    }

    private function nodeAndDescendantIds(string $agentId): array
    {
        return DB::table('agents')
            ->where(function ($q) use ($agentId) {
                $q->where('agent_id', $agentId)->orWhere('hierarchy_path', 'like', '%/' . $agentId . '/%');
            })
            ->where('is_deleted', false)
            ->pluck('agent_id')
            ->toArray();
    }

    // The Company -> Group -> TL -> Introducer AGENT HIERARCHY cascade
    // only (never the Customer Group / group_label_id dimension — kept
    // separate below so the Performance Overview breakdown table can
    // always compare every Customer Group side by side, even while the
    // rest of the dashboard is drilled into one specific Customer Group).
    private function cascadeAgentIds(Request $request, $agent, DataScopeService $scope): ?array
    {
        $glId = $agent->role === 'ADMIN' ? trim((string) $request->get('gl_id', '')) : '';
        $tlId = in_array($agent->role, ['ADMIN', 'GROUP_LEADER'], true) ? trim((string) $request->get('tl_id', '')) : '';
        $introId = trim((string) $request->get('introducer_id', ''));

        $narrowed = null;
        if ($introId !== '') {
            $narrowed = $this->nodeAndDescendantIds($introId);
        } elseif ($tlId !== '') {
            $narrowed = $this->nodeAndDescendantIds($tlId);
        } elseif ($glId !== '') {
            $gl = DB::table('agents')->where('agent_id', $glId)->first();
            $narrowed = $gl ? DB::table('agents')->where('group_id', $gl->group_id)->where('is_deleted', false)->pluck('agent_id')->toArray() : [];
        }

        if ($narrowed !== null) {
            if (!$scope->isAdmin()) {
                $narrowed = array_values(array_intersect($narrowed, $scope->getAgentIds()));
            }
            return $narrowed;
        }

        return $scope->isAdmin() ? null : $scope->getAgentIds();
    }

    // Full scope: the agent-hierarchy cascade above, further narrowed by
    // Customer Group (group_label_id) if one is selected — per Chris:
    // "follow the sequence of kpi dash[board]... ask me public? the
    // group." Used for every box EXCEPT the Performance Overview table
    // (which always shows every group, for comparison — see index()).
    // Returns null = no restriction at all (Admin, nothing picked).
    private function scopedAgentIds(Request $request, $agent, DataScopeService $scope): ?array
    {
        $narrowed = $this->cascadeAgentIds($request, $agent, $scope);
        $groupLabelId = $agent->role === 'ADMIN' ? trim((string) $request->get('group_label_id', '')) : '';
        // ADDED 19 Sep 2026 — per Chris: "i want the display like
        // generallink" for the CBE Executive KPI Dashboard's own
        // Customer KPI — this screen previously only ever filtered to
        // ONE specific Customer Group at a time (group_label_id), with
        // group_type only narrowing the dropdown's own option list, not
        // actually filtering data. That left no way to see "every CBE
        // community combined" the way the CBE portal's own screens
        // already do (see CbeExecDashboardController's "All CBE Groups
        // (Combined)" scope). When group_type is set and no single
        // group_label_id is picked, narrow to every agent whose group
        // belongs to that type instead.
        $groupType = $agent->role === 'ADMIN' ? trim((string) $request->get('group_type', '')) : '';

        if ($groupLabelId === '') {
            if ($groupType === '') {
                return $narrowed;
            }
            $typeAgentIds = DB::table('agents as a')
                ->join('group_labels as gl', 'gl.group_label_id', '=', 'a.group_label_id')
                ->where('a.is_deleted', false)
                ->where('gl.group_type', $groupType)
                ->pluck('a.agent_id')
                ->toArray();
            return $narrowed !== null ? array_values(array_intersect($narrowed, $typeAgentIds)) : $typeAgentIds;
        }

        $groupQuery = DB::table('agents')->where('is_deleted', false);
        $groupQuery = $groupLabelId === 'SYSTEM_DEFAULT'
            ? $groupQuery->whereNull('group_label_id')
            : $groupQuery->where('group_label_id', $groupLabelId);
        $groupAgentIds = $groupQuery->pluck('agent_id')->toArray();

        return $narrowed !== null ? array_values(array_intersect($narrowed, $groupAgentIds)) : $groupAgentIds;
    }

    private function filters(Request $request): array
    {
        return [
            'customer_category_id' => trim((string) $request->get('customer_category_id', '')),
            'customer_type_id'     => trim((string) $request->get('customer_type_id', '')),
            'occupation_group_id'  => trim((string) $request->get('occupation_group_id', '')),
            'source_id'            => trim((string) $request->get('source_id', '')),
            'status_id'            => trim((string) $request->get('status_id', '')),
        ];
    }

    // NEW 18 Aug 2026 — per Chris: "under KPI dashboard also choose
    // selection by DSG, ORG or CBE" — the Customer Group dropdown used
    // to mix every group_labels row together in one flat alphabetical
    // list regardless of type. Now a Group Type picker narrows this
    // list first (DSG/ORG/CBE), same "always filter by type" rule
    // applied across Rank Hierarchy/Rank Allocation/Organization
    // Category Maintenance. Blank = every type (unchanged behaviour).
    private function filterOptions(?string $groupType = null): array
    {
        return [
            'categories'  => DB::table('customer_categories')->where('is_active', true)->orderBy('description')->get(),
            'types'       => DB::table('customer_types')->where('is_active', true)->orderBy('description')->get(),
            'occupations' => DB::table('occupation_groups')->where('is_active', true)->orderBy('description')->get(),
            'sources'     => DB::table('customer_sources')->where('is_active', true)->orderBy('description')->get(),
            'statuses'    => DB::table('customer_statuses')->where('is_active', true)->orderBy('description')->get(),
            'groupLabels' => DB::table('group_labels')
                ->when($groupType, fn ($q) => $q->where('group_type', $groupType))
                ->orderBy('group_name')->get(),
        ];
    }

    // Shared by every query below that applies the Category/Type/
    // Occupation/Source/Status filter set. 'NONE' is a sentinel (never
    // a real UUID) meaning "the Uncategorized/Unspecified bucket" —
    // needed so a chart click on that bucket (id is genuinely NULL in
    // the DB) can still filter down to it, instead of the sentinel
    // being indistinguishable from "no filter applied."
    private function applyFilters($query, array $filters): void
    {
        foreach ($filters as $col => $val) {
            if ($val === '') {
                continue;
            }
            if ($val === 'NONE') {
                $query->whereNull("c.{$col}");
            } else {
                $query->where("c.{$col}", $val);
            }
        }
    }

    private function baseSalesQuery(?array $agentIds, array $filters)
    {
        $q = DB::table('sales_transactions as st')
            ->join('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->where('st.is_deleted', false)
            ->when($agentIds !== null, fn ($qq) => $qq->whereIn('st.agent_id', $agentIds));
        $this->applyFilters($q, $filters);
        return $q;
    }

    private function baseEarnQuery(?array $agentIds, array $filters)
    {
        $q = DB::table('commission_transactions as ct')
            ->join('sales_transactions as st', 'ct.policy_id', '=', 'st.policy_id')
            ->join('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->where('st.is_deleted', false)
            ->where('ct.status', '!=', 'REVERSED')
            ->when($agentIds !== null, fn ($qq) => $qq->whereIn('ct.agent_id', $agentIds));
        $this->applyFilters($q, $filters);
        return $q;
    }

    // Human-readable scope label + selected GL/TL/Introducer rows, for
    // the header and for carrying the cascade through every link —
    // mirrors Renewal Forecast's own scopeLabel logic exactly.
    private function scopeContext(Request $request, $agent): array
    {
        $glId = $agent->role === 'ADMIN' ? trim((string) $request->get('gl_id', '')) : '';
        $tlId = in_array($agent->role, ['ADMIN', 'GROUP_LEADER'], true) ? trim((string) $request->get('tl_id', '')) : '';
        $introId = trim((string) $request->get('introducer_id', ''));
        $groupLabelId = $agent->role === 'ADMIN' ? trim((string) $request->get('group_label_id', '')) : '';
        $groupType = $agent->role === 'ADMIN' ? trim((string) $request->get('group_type', '')) : '';

        $selectedGL = $glId !== '' ? DB::table('agents')->where('agent_id', $glId)->first() : null;
        $selectedTL = $tlId !== '' ? DB::table('agents')->where('agent_id', $tlId)->first() : null;
        $selectedIntro = $introId !== '' ? DB::table('agents')->where('agent_id', $introId)->first() : null;
        $groupLabelName = null;
        if ($groupLabelId === 'SYSTEM_DEFAULT') {
            $groupLabelName = 'Public / System Default';
        } elseif ($groupLabelId !== '') {
            $groupLabelName = DB::table('group_labels')->where('group_label_id', $groupLabelId)->value('group_name');
        } elseif ($groupType !== '') {
            // ADDED 19 Sep 2026 — per Chris: combined-by-type view (see
            // scopedAgentIds above) needs its own label, same wording
            // convention as "All CBE Groups (Combined)" elsewhere.
            $groupLabelName = 'All ' . $groupType . ' Groups (Combined)';
        }

        if ($selectedIntro) {
            $scopeLabel = 'Introducer — ' . $selectedIntro->full_name;
        } elseif ($selectedTL) {
            $scopeLabel = 'Team Leader — ' . $selectedTL->full_name;
        } elseif ($selectedGL) {
            $scopeLabel = 'Group — ' . $selectedGL->full_name;
        } else {
            $scopeLabel = match ($agent->role) {
                'ADMIN'        => 'Overall Company',
                'GROUP_LEADER' => 'My Whole Group',
                'TEAM_LEADER'  => 'My Whole Team',
                default        => 'My Own Book',
            };
        }
        if ($groupLabelName) {
            $scopeLabel .= ' · Customer Group: ' . $groupLabelName;
        }

        return compact('glId', 'tlId', 'introId', 'groupLabelId', 'groupLabelName', 'selectedGL', 'selectedTL', 'selectedIntro', 'scopeLabel');
    }

    // Every customer with at least one transaction inside the current
    // agent-scope, with the same Category/Type/Occupation/Source/Status
    // filters applied — the base population for the Overview counts and
    // the Category/Occupation/Source breakdown boxes below.
    private function customersInScopeQuery(?array $agentIds, array $filters)
    {
        $q = DB::table('customers as c')
            ->where('c.is_deleted', false)
            ->whereExists(function ($sub) use ($agentIds) {
                $sub->selectRaw('1')->from('sales_transactions as st2')
                    ->whereColumn('st2.customer_id', 'c.customer_id')
                    ->where('st2.is_deleted', false);
                if ($agentIds !== null) {
                    $sub->whereIn('st2.agent_id', $agentIds);
                }
            });
        $this->applyFilters($q, $filters);
        return $q;
    }

    public function index(Request $request, DataScopeService $scope)
    {
        $agent = Auth::guard('agent')->user();
        $agentIds = $this->scopedAgentIds($request, $agent, $scope);
        $filters = $this->filters($request);
        $ctx = $this->scopeContext($request, $agent);
        $rolePrefix = $this->rolePrefix($agent);
        $groupType = $agent->role === 'ADMIN' ? trim((string) $request->get('group_type', '')) : '';

        // NEW 25 Jul 2026 — per Chris: "why i have not select you
        // immediate display the results?" Nothing below this point
        // queries the database until the viewer has explicitly clicked
        // Apply (or changed a filter dropdown, which auto-submits) at
        // least once — same "don't load until told to" rule the main
        // Admin Dashboard already follows, for the same reason (never
        // hammer the database by default at real-world scale).
        $loaded = $request->has('loaded');

        if (!$loaded) {
            return view('customer-kpi.index', array_merge(compact(
                'agent', 'loaded', 'filters', 'rolePrefix', 'groupType'
            ), $this->filterOptions($groupType), $ctx, [
                'topSales' => collect(), 'topEarning' => collect(),
                'totalCustomers' => 0, 'activeCustomers' => 0, 'prospectCustomers' => 0,
                'byCategory' => collect(), 'byOccupation' => collect(),
                'bySource' => collect(), 'groupBreakdown' => collect(),
                'activeStatusId' => null, 'prospectStatusId' => null,
                'topStatesSales' => collect(), 'topStatesEarning' => collect(),
            ]));
        }

        // Looked up once, reused to build clickable Overview-row links
        // (Total/Active/Prospect) straight into the "View All" list
        // filtered by that exact status — the customer_statuses.code
        // constants ('ACTIVE'/'PROSPECT') are stable, the status_id
        // (UUID) is not, so it must be resolved per-request.
        $activeStatusId = DB::table('customer_statuses')->where('code', 'ACTIVE')->value('status_id');
        $prospectStatusId = DB::table('customer_statuses')->where('code', 'PROSPECT')->value('status_id');

        $topSales = (clone $this->baseSalesQuery($agentIds, $filters))
            ->select('c.customer_id', 'c.full_name', DB::raw('SUM(st.premium_amount) as total'))
            ->groupBy('c.customer_id', 'c.full_name')
            ->orderByDesc('total')->limit(3)->get();

        $topEarning = (clone $this->baseEarnQuery($agentIds, $filters))
            ->select('c.customer_id', 'c.full_name', DB::raw('SUM(ct.commission_amount) as total'))
            ->groupBy('c.customer_id', 'c.full_name')
            ->orderByDesc('total')->limit(3)->get();

        // ── Customer Overview (mirrors the main Dashboard's Network
        // Overview box) — plain COUNT()s, safe at any scale. ──
        $totalCustomers = (clone $this->customersInScopeQuery($agentIds, $filters))->count();
        $activeCustomers = (clone $this->customersInScopeQuery($agentIds, $filters))
            ->join('customer_statuses as cs', 'c.status_id', '=', 'cs.status_id')->where('cs.code', 'ACTIVE')->count();
        $prospectCustomers = (clone $this->customersInScopeQuery($agentIds, $filters))
            ->join('customer_statuses as cs', 'c.status_id', '=', 'cs.status_id')->where('cs.code', 'PROSPECT')->count();

        // ── By Location — Top 3 States by contribution (Sales/Earning
        // radio, same medal-ranked pattern as Box 1). Per Chris: "include
        // by location top 3 states contributor radio sales earning
        // drill down all stage sequence by contribution rank state." A
        // state row click opens the ranked customer list for that one
        // state (customer-kpi.list?state=...), which itself shows each
        // customer's Owner — per Chris: "drill down who is the customer
        // and ownership (created by)." ──
        $topStatesSales = (clone $this->baseSalesQuery($agentIds, $filters))
            ->select(DB::raw("COALESCE(c.state,'Unspecified') as state"), DB::raw('SUM(st.premium_amount) as total'))
            ->groupBy('state')->orderByDesc('total')->limit(3)->get();

        $topStatesEarning = (clone $this->baseEarnQuery($agentIds, $filters))
            ->select(DB::raw("COALESCE(c.state,'Unspecified') as state"), DB::raw('SUM(ct.commission_amount) as total'))
            ->groupBy('state')->orderByDesc('total')->limit(3)->get();

        // ── By Category / Occupation / Source — the 3 chart boxes,
        // customer COUNT per bucket (bar/pie/line switchable client-side).
        // The id column travels alongside the label so a chart click can
        // drill the WHOLE dashboard down into that one bucket. ──
        $byCategory = (clone $this->customersInScopeQuery($agentIds, $filters))
            ->leftJoin('customer_categories as cc', 'c.customer_category_id', '=', 'cc.category_id')
            ->select('c.customer_category_id as id', DB::raw("COALESCE(cc.description,'Uncategorized') as label"), DB::raw('COUNT(*) as total'))
            ->groupBy('c.customer_category_id', 'label')->orderByDesc('total')->limit(8)->get();

        $byOccupation = (clone $this->customersInScopeQuery($agentIds, $filters))
            ->leftJoin('occupation_groups as og', 'c.occupation_group_id', '=', 'og.occupation_group_id')
            ->select('c.occupation_group_id as id', DB::raw("COALESCE(og.description,'Unspecified') as label"), DB::raw('COUNT(*) as total'))
            ->groupBy('c.occupation_group_id', 'label')->orderByDesc('total')->limit(8)->get();

        $bySource = (clone $this->customersInScopeQuery($agentIds, $filters))
            ->leftJoin('customer_sources as cs2', 'c.source_id', '=', 'cs2.source_id')
            ->select('c.source_id as id', DB::raw("COALESCE(cs2.description,'Unspecified') as label"), DB::raw('COUNT(*) as total'))
            ->groupBy('c.source_id', 'label')->orderByDesc('total')->limit(8)->get();

        // ── Performance Overview — per Chris: "bottom performance
        // overview is all the customer group according to public and
        // prihatin2u." A "group" here is the GROUP LABEL of the AGENT
        // who sold to that customer (group_labels — the same Public /
        // Organization Rewards Group concept used everywhere else in this
        // app, e.g. PVATM/PRIHATIN2U), not a customer-level field —
        // customers don't carry their own group_label_id, agents do.
        //
        // Deliberately uses $cascadeAgentIds (the GL/TL/Introducer
        // hierarchy cascade only), NOT $agentIds — if the viewer has
        // already drilled into one specific Customer Group via the
        // filter bar, this table must still show every group side by
        // side for comparison, otherwise it collapses to one row and
        // stops being an "overview." Each row is clickable straight
        // into that Customer Group (group_label_id carried through). ──
        $cascadeAgentIds = $this->cascadeAgentIds($request, $agent, $scope);

        $salesByGroup = DB::table('sales_transactions as st')
            ->join('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
            ->leftJoin('group_labels as glbl', 'a.group_label_id', '=', 'glbl.group_label_id')
            ->where('st.is_deleted', false)
            ->when($cascadeAgentIds !== null, fn ($q) => $q->whereIn('st.agent_id', $cascadeAgentIds));
        foreach ($filters as $col => $val) {
            if ($val !== '') {
                $salesByGroup->where("c.{$col}", $val);
            }
        }
        $salesByGroup = $salesByGroup
            ->select(
                'a.group_label_id',
                DB::raw("COALESCE(glbl.group_name,'Public / System Default') as group_name"),
                DB::raw('COUNT(DISTINCT c.customer_id) as customers'),
                DB::raw('SUM(st.premium_amount) as sales')
            )
            ->groupBy('a.group_label_id', 'group_name')
            ->orderByDesc('sales')
            ->get();

        $earnByGroup = DB::table('commission_transactions as ct')
            ->join('sales_transactions as st', 'ct.policy_id', '=', 'st.policy_id')
            ->join('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->join('agents as a', 'ct.agent_id', '=', 'a.agent_id')
            ->leftJoin('group_labels as glbl', 'a.group_label_id', '=', 'glbl.group_label_id')
            ->where('st.is_deleted', false)
            ->where('ct.status', '!=', 'REVERSED')
            ->when($cascadeAgentIds !== null, fn ($q) => $q->whereIn('ct.agent_id', $cascadeAgentIds));
        foreach ($filters as $col => $val) {
            if ($val !== '') {
                $earnByGroup->where("c.{$col}", $val);
            }
        }
        $earnByGroup = $earnByGroup
            ->select(DB::raw("COALESCE(glbl.group_name,'Public / System Default') as group_name"), DB::raw('SUM(ct.commission_amount) as earn'))
            ->groupBy('group_name')
            ->pluck('earn', 'group_name');

        $groupBreakdown = $salesByGroup->map(function ($row) use ($earnByGroup) {
            $row->earning = (float) ($earnByGroup[$row->group_name] ?? 0);
            $row->sales = (float) $row->sales;
            $row->link_group_label_id = $row->group_label_id ?: 'SYSTEM_DEFAULT';
            return $row;
        });

        return view('customer-kpi.index', array_merge(compact(
            'agent', 'loaded', 'topSales', 'topEarning', 'filters', 'rolePrefix', 'groupType',
            'totalCustomers', 'activeCustomers', 'prospectCustomers',
            'byCategory', 'byOccupation', 'bySource', 'groupBreakdown',
            'activeStatusId', 'prospectStatusId', 'topStatesSales', 'topStatesEarning'
        ), $this->filterOptions($groupType), $ctx));
    }

    // ── By Location — FULL State breakdown (Box 3's "View All States"
    // drill-down). Per Chris: "the next screen you must show state in
    // every row and the customer count and total amount then view at
    // the right column ... click drill down to see all customer and the
    // owner and amount." Each row's View goes to the existing
    // customer-kpi.list?state=... (already shows Customer/Owner/Amount).
    // Small, already-aggregated result set, so pagination is done in
    // memory rather than adding a second SQL round trip. ──
    public function states(Request $request, DataScopeService $scope)
    {
        $agent = Auth::guard('agent')->user();
        $agentIds = $this->scopedAgentIds($request, $agent, $scope);
        $filters = $this->filters($request);
        $ctx = $this->scopeContext($request, $agent);
        $rolePrefix = $this->rolePrefix($agent);
        $sort = $request->get('sort') === 'earning' ? 'earning' : 'sales';

        $countByState = (clone $this->customersInScopeQuery($agentIds, $filters))
            ->select(DB::raw("COALESCE(c.state,'Unspecified') as state"), DB::raw('COUNT(*) as customers'))
            ->groupBy('state')->pluck('customers', 'state');

        if ($sort === 'sales') {
            $amountByState = (clone $this->baseSalesQuery($agentIds, $filters))
                ->select(DB::raw("COALESCE(c.state,'Unspecified') as state"), DB::raw('SUM(st.premium_amount) as total'))
                ->groupBy('state')->pluck('total', 'state');
        } else {
            $amountByState = (clone $this->baseEarnQuery($agentIds, $filters))
                ->select(DB::raw("COALESCE(c.state,'Unspecified') as state"), DB::raw('SUM(ct.commission_amount) as total'))
                ->groupBy('state')->pluck('total', 'state');
        }

        $allStates = $countByState->keys()->merge($amountByState->keys())->unique();
        $rowsColl = $allStates->map(fn ($state) => (object) [
            'state'     => $state,
            'customers' => (int) ($countByState[$state] ?? 0),
            'total'     => (float) ($amountByState[$state] ?? 0),
        ])->sortByDesc('total')->values();

        $rows = $this->paginateCollection($rowsColl, $request);

        return view('customer-kpi.states', array_merge(compact('agent', 'rows', 'sort', 'rolePrefix'), $ctx));
    }

    // ── By Status — FULL breakdown (reached from Box 3's "Not Active"
    // row, since "not active" spans many individual statuses and isn't
    // one status_id that customer-kpi.list can filter on directly).
    // Same shape as states() above; View goes to the existing
    // customer-kpi.list?status_id=... per row. ──
    public function statuses(Request $request, DataScopeService $scope)
    {
        $agent = Auth::guard('agent')->user();
        $agentIds = $this->scopedAgentIds($request, $agent, $scope);
        $filters = $this->filters($request);
        $ctx = $this->scopeContext($request, $agent);
        $rolePrefix = $this->rolePrefix($agent);
        $sort = $request->get('sort') === 'earning' ? 'earning' : 'sales';

        $countByStatus = (clone $this->customersInScopeQuery($agentIds, $filters))
            ->join('customer_statuses as cs', 'c.status_id', '=', 'cs.status_id')
            ->select('cs.status_id as status_id', 'cs.description as label', DB::raw('COUNT(*) as customers'))
            ->groupBy('cs.status_id', 'cs.description')->get();

        if ($sort === 'sales') {
            $amountByStatus = (clone $this->baseSalesQuery($agentIds, $filters))
                ->join('customer_statuses as cs', 'c.status_id', '=', 'cs.status_id')
                ->select('cs.status_id as status_id', DB::raw('SUM(st.premium_amount) as total'))
                ->groupBy('cs.status_id')->pluck('total', 'status_id');
        } else {
            $amountByStatus = (clone $this->baseEarnQuery($agentIds, $filters))
                ->join('customer_statuses as cs', 'c.status_id', '=', 'cs.status_id')
                ->select('cs.status_id as status_id', DB::raw('SUM(ct.commission_amount) as total'))
                ->groupBy('cs.status_id')->pluck('total', 'status_id');
        }

        $rowsColl = $countByStatus->map(fn ($row) => (object) [
            'status_id' => $row->status_id,
            'label'     => $row->label,
            'customers' => (int) $row->customers,
            'total'     => (float) ($amountByStatus[$row->status_id] ?? 0),
        ])->sortByDesc('total')->values();

        $rows = $this->paginateCollection($rowsColl, $request);

        return view('customer-kpi.statuses', array_merge(compact('agent', 'rows', 'sort', 'rolePrefix'), $ctx));
    }

    // Small, already-in-memory aggregated collections (one row per state
    // or per status — never more than a few dozen) still get the same
    // Prev/Next pagination UI as every other list in this screen, rather
    // than dumping an unbounded table — paginated manually since there's
    // no SQL query left to paginate against.
    private function paginateCollection($collection, Request $request, int $perPage = 8)
    {
        $page = max(1, (int) $request->get('p', 1));
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $collection->forPage($page, $perPage)->values(),
            $collection->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'pageName' => 'p']
        );
        return $paginator->appends($request->except('p'));
    }

    // "View All" — full ranked list, paginated, Prev/Next, one screen.
    // Optional ?state= narrows to one state (the By Location drill-down
    // from the index screen) — 'UNSPECIFIED' is the sentinel for
    // customers with no state on file, same NONE-style convention used
    // for the Category/Occupation/Source chart drill-downs. Every row
    // shows the customer's Owner — per Chris: "drill down who is the
    // customer and ownership (created by)."
    public function list(Request $request, DataScopeService $scope)
    {
        $agent = Auth::guard('agent')->user();
        $agentIds = $this->scopedAgentIds($request, $agent, $scope);
        $filters = $this->filters($request);
        $ctx = $this->scopeContext($request, $agent);
        $rolePrefix = $this->rolePrefix($agent);
        $sort = $request->get('sort') === 'earning' ? 'earning' : 'sales';
        $state = trim((string) $request->get('state', ''));

        if ($sort === 'sales') {
            $q = (clone $this->baseSalesQuery($agentIds, $filters));
            if ($state !== '') {
                $state === 'UNSPECIFIED' ? $q->whereNull('c.state') : $q->where('c.state', $state);
            }
            $rows = $q->join('agents as owner', 'c.owned_by_agent_id', '=', 'owner.agent_id')
                ->select('c.customer_id', 'c.full_name', 'owner.full_name as owner_name', 'owner.agent_code as owner_code', DB::raw('SUM(st.premium_amount) as total'))
                ->groupBy('c.customer_id', 'c.full_name', 'owner.full_name', 'owner.agent_code')
                ->orderByDesc('total')
                ->paginate(8, ['*'], 'p')->appends($request->except('p'));
        } else {
            $q = (clone $this->baseEarnQuery($agentIds, $filters));
            if ($state !== '') {
                $state === 'UNSPECIFIED' ? $q->whereNull('c.state') : $q->where('c.state', $state);
            }
            $rows = $q->join('agents as owner', 'c.owned_by_agent_id', '=', 'owner.agent_id')
                ->select('c.customer_id', 'c.full_name', 'owner.full_name as owner_name', 'owner.agent_code as owner_code', DB::raw('SUM(ct.commission_amount) as total'))
                ->groupBy('c.customer_id', 'c.full_name', 'owner.full_name', 'owner.agent_code')
                ->orderByDesc('total')
                ->paginate(8, ['*'], 'p')->appends($request->except('p'));
        }

        $stateLabel = $state === 'UNSPECIFIED' ? 'Unspecified' : ($state !== '' ? $state : null);

        return view('customer-kpi.list', array_merge(compact('agent', 'rows', 'sort', 'stateLabel', 'rolePrefix'), $ctx));
    }

    // Deepest drill-down — one customer's actual transactions: Vendor,
    // Type (Product), Date, Sales, Earning. Per Chris: "drill down must
    // show transaction by vendor, type date, sales and earning."
    public function detail(Request $request, string $customerId, DataScopeService $scope)
    {
        $agent = Auth::guard('agent')->user();
        $agentIds = $this->scopedAgentIds($request, $agent, $scope);
        $ctx = $this->scopeContext($request, $agent);
        $rolePrefix = $this->rolePrefix($agent);

        $customer = DB::table('customers')->where('customer_id', $customerId)->where('is_deleted', false)->first();
        abort_unless($customer, 404);

        // Ownership — per Chris: "drill down who is the customer and
        // ownership (created by)." owned_by_agent_id is this app's one
        // canonical "who owns this customer" field (task #129), so that
        // is what's shown as Owner, alongside their role for context.
        $owner = DB::table('agents')->where('agent_id', $customer->owned_by_agent_id)->first();

        // Security — the customer must have at least one transaction
        // inside the CURRENT viewer's scope; a spoofed customer_id from
        // outside the scope gets nothing, never someone else's book.
        $existsQuery = DB::table('sales_transactions')->where('customer_id', $customerId)->where('is_deleted', false);
        if ($agentIds !== null) {
            $existsQuery->whereIn('agent_id', $agentIds);
        }
        abort_unless($existsQuery->exists(), 403);

        $rows = DB::table('sales_transactions as st')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->where('st.customer_id', $customerId)
            ->where('st.is_deleted', false)
            ->when($agentIds !== null, fn ($q) => $q->whereIn('st.agent_id', $agentIds))
            ->select('st.policy_id', 'v.vendor_name', 'p.product_name', 'st.created_at', 'st.premium_amount')
            ->orderByDesc('st.created_at')
            ->paginate(8, ['*'], 'p')->appends($request->except('p'));

        $policyIds = collect($rows->items())->pluck('policy_id')->all();
        $earnByPolicy = collect();
        if (!empty($policyIds)) {
            $earnQuery = DB::table('commission_transactions')->whereIn('policy_id', $policyIds)->where('status', '!=', 'REVERSED');
            if ($agentIds !== null) {
                $earnQuery->whereIn('agent_id', $agentIds);
            }
            $earnByPolicy = $earnQuery->select('policy_id', DB::raw('SUM(commission_amount) as total'))->groupBy('policy_id')->pluck('total', 'policy_id');
        }
        foreach ($rows as $r) {
            $r->earning = (float) ($earnByPolicy[$r->policy_id] ?? 0);
        }

        return view('customer-kpi.detail', array_merge(compact('agent', 'rows', 'customer', 'owner', 'rolePrefix'), $ctx));
    }
}
