<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard.admin');
    }

    // Live search behind the "which group name" field. Category narrows the
    // pool: 'public' = groups NOT tied to an Organization Rewards Group label,
    // 'special' = groups that ARE. Matches on group name, GL name, or GL code
    // — same live-typeahead pattern already used elsewhere in the app
    // (postcode/city lookup etc.), for consistency.
    //
    // NEW 22 Aug 2026 — per Chris (spotted CBE missing from this dropdown,
    // then: "why say search for GL? I should able to select drop down
    // menu HQ, State, Branch, etc"). 'cbe' is a separate branch entirely,
    // not a variant of the public/special query above, and it isn't a
    // search-as-you-type field either — it's the first of a chain of
    // plain dropdowns (Community -> State -> Temple -> ...). This
    // endpoint's job for 'cbe' is just "list every CBE community", used
    // once to populate that first dropdown (see cbeChildren() below for
    // walking down the tree from there). Returns each community's HQ
    // node_id (not group_label_id) as 'group_id', since everything
    // downstream — the level dropdowns, and scope resolution in
    // resolveFilterAgentIds() — works in terms of cbe_hierarchy_nodes,
    // and a community's HQ node IS "everyone in this community".
    public function groupTypeahead(Request $request)
    {
        $q        = trim($request->get('q', ''));
        $category = $request->get('category', 'all');

        if ($category === 'cbe') {
            $results = DB::table('group_labels as gl')
                ->join('cbe_hierarchy_nodes as hq', function ($j) {
                    $j->on('hq.group_label_id', '=', 'gl.group_label_id')->whereNull('hq.parent_node_id');
                })
                ->where('gl.group_type', 'CBE')
                ->when(strlen($q) >= 1, fn($qq) => $qq->where('gl.group_name', 'like', '%'.$q.'%'))
                ->select('hq.node_id', 'gl.group_name')
                ->orderBy('gl.group_name')
                ->limit(20)
                ->get()
                ->map(fn($r) => [
                    'group_id' => $r->node_id,
                    'label'    => $r->group_name,
                ]);

            return response()->json($results);
        }

        // FIXED (confirmed against real data 15 Jul 2026): every group —
        // public or special — gets a group_labels row via Group Name
        // Maintenance, so agents.group_label_id being set/unset never
        // distinguishes public vs special. The ONLY real signal is
        // group_labels.promotion_demotion_enabled (1 = public/normal
        // rules, 0 = Organization Rewards Group). Search must also match
        // group_labels.group_name (the actual "group name label", e.g.
        // "prihatin2u"), not the operational groups table's name.
        $query = DB::table('agents as gl')
            ->leftJoin('group_labels as lbl', 'lbl.group_label_id', '=', 'gl.group_label_id')
            ->where('gl.role', 'GROUP_LEADER')
            ->where('gl.is_deleted', false);

        if ($category === 'public') {
            $query->where(function ($qq) {
                $qq->whereNull('gl.group_label_id')
                   ->orWhere('lbl.promotion_demotion_enabled', true);
            });
        } elseif ($category === 'special') {
            $query->whereNotNull('gl.group_label_id')
                  ->where('lbl.promotion_demotion_enabled', false);
        }

        if (strlen($q) >= 1) {
            $query->where(function ($qq) use ($q) {
                $qq->where('lbl.group_name', 'like', '%'.$q.'%')
                   ->orWhere('gl.full_name', 'like', '%'.$q.'%')
                   ->orWhere('gl.agent_code', 'like', '%'.$q.'%');
            });
        }

        $results = $query->select('gl.group_id', 'lbl.group_name', 'gl.full_name', 'gl.agent_code')
            ->orderBy('gl.full_name')
            ->limit(20)
            ->get()
            ->map(fn($r) => [
                'group_id' => $r->group_id,
                'label'    => ($r->group_name ? $r->group_name.' — ' : '').$r->full_name.(str_contains($r->agent_code, '-') ? ' (Promoted)' : ''),
            ]);

        return response()->json($results);
    }

    // Resolves the "All / Direct Selling Group / Organization Rewards Group" + "which group name"
    // selection into either:
    // - null (no filter — "All", the default, original behaviour)
    // - an array of agent_ids for the chosen scope
    // A specific group_id (a chosen group name) always wins over category —
    // category alone gives the combined view for that whole category.
    private function resolveFilterAgentIds(Request $request): ?array
    {
        $groupId  = $request->get('group_id');
        $category = $request->get('category', 'all');

        if ($groupId && $category === 'cbe') {
            // groupId here is a cbe_hierarchy_nodes.node_id — could be a
            // whole community's HQ node, a State, a Temple, or any
            // deeper level — never an agents.group_id dynasty, since CBE
            // has no GROUP_LEADER/downline structure to walk. cbe_city is
            // set only when Admin picked a Branch (a city grouping) —
            // narrows to that city within the chosen node's descendants.
            return $this->getCbeNodeDescendantAgentIds($groupId, $request->get('cbe_city') ?: null);
        }

        if ($groupId) {
            return $this->getDynastyAgentIds($groupId);
        }

        // Category selected but no specific group picked yet ("Direct
        // Selling Group" / "Organization Rewards Group" / "CBE Group" on
        // their own, with no group name). Blocked on purpose — at
        // real-world scale (millions of agents) pulling every matching
        // agent_id into memory before filtering would be a serious
        // performance/memory risk. The dashboard UI always requires
        // picking a specific group name before it calls this endpoint,
        // so this should never legitimately be hit; this is a
        // server-side guard in case it ever is (e.g. a direct URL hit
        // bypassing the UI).
        if ($category === 'public' || $category === 'special' || $category === 'cbe') {
            abort(422, 'Please select a specific group name — viewing an entire category in bulk is disabled for performance reasons.');
        }

        return null;
    }

    // NEW 22 Aug 2026 — every agent whose own cbe_node_id sits at or
    // below the given node in the tree, using the same hierarchy_path
    // prefix-match pattern already used for DSG/ORG's agents.hierarchy_
    // path. Picking a community's HQ node this way naturally returns
    // everyone in the whole community (every node's path starts with
    // the HQ's own path), so this one method covers every depth — HQ,
    // State, Temple, or however many levels deep a community goes.
    private function getCbeNodeDescendantAgentIds(string $nodeId, ?string $city = null): array
    {
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        if (! $node || ! $node->hierarchy_path) {
            return [];
        }

        return DB::table('agents as a')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'a.cbe_node_id')
            ->where('n.hierarchy_path', 'like', $node->hierarchy_path.'%')
            ->when($city, fn ($q) => $q->where('n.city', $city))
            ->where('a.is_deleted', false)
            ->pluck('a.agent_id')
            ->toArray();
    }

    // NEW 22 Aug 2026 — per Chris: powers the CBE drill-down dropdowns.
    // Given a parent node_id, returns the next step down the tree.
    //
    // Two response shapes:
    // - mode "city_group": this level's real nodes carry more than one
    //   distinct `city` value (e.g. Selangor's 281 Temples span dozens
    //   of towns) — group them by city first ("Branch", e.g. "Klang"),
    //   per Chris's own Klang/postcode discussion earlier. Picking a
    //   Branch re-calls this same endpoint with &city= to get the real
    //   nodes inside just that city.
    // - mode "nodes": the real child nodes themselves. Chris's rule
    //   (22 Aug): never label the bottom-most rung with whatever
    //   community-specific word is stored in cbe_hierarchy_levels (e.g.
    //   "Temple") since a different CBE community wouldn't call its own
    //   bottom rung that — so when this is the deepest configured level
    //   for the community, the label is always the generic "Affiliate"
    //   (matches "Affiliate Code" terminology already used elsewhere).
    //   Any level that ISN'T the deepest (a real admin-defined "Branch"
    //   level for a community that has one) keeps its own configured name.
    //
    // CHANGED 23 Aug 2026 — per Chris: "all select filter must be in one
    // row... you should have typeahead features". Plain <select> dropdowns
    // were unwieldy for e.g. Selangor's 281 Temples, so the front-end now
    // types into a search box instead of opening a giant list. This
    // endpoint gained an optional `q` — it still returns the *whole*
    // picture needed to decide city_group vs nodes mode (grouping must be
    // decided from the full child set, never a filtered slice, or a
    // search that happens to match only one city's temples would wrongly
    // skip straight past the Branch step) but the actual list handed back
    // (groups or children) is narrowed to whatever matches `q`, capped at
    // 30 rows so a giant match list never has to be scrolled through.
    public function cbeChildren(Request $request)
    {
        $parentNodeId = $request->get('parent_node_id');
        $city         = $request->get('city');
        $q            = trim($request->get('q', ''));

        if (! $parentNodeId) {
            return response()->json(['mode' => 'nodes', 'level_name' => null, 'children' => []]);
        }

        $children = DB::table('cbe_hierarchy_nodes as n')
            ->leftJoin('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
            ->where('n.parent_node_id', $parentNodeId)
            ->when($city, fn ($qq) => $qq->where('n.city', $city))
            ->orderBy('n.node_name')
            ->select('n.node_id', 'n.node_name', 'n.node_name_zh', 'n.city', 'l.level_name', 'l.level_order', 'l.group_label_id')
            ->get();

        if ($children->isEmpty()) {
            return response()->json(['mode' => 'nodes', 'level_name' => null, 'children' => []]);
        }

        $distinctCities = $children->pluck('city')->filter()->unique();
        if (! $city && $distinctCities->count() > 1) {
            $groups = $children->groupBy('city')->map(fn ($rows, $cityName) => [
                'city'  => $cityName,
                'count' => $rows->count(),
            ])->filter(fn ($g) => $g['city']);

            if (strlen($q) >= 1) {
                $groups = $groups->filter(fn ($g) => stripos($g['city'], $q) !== false);
            }

            $groups = $groups->sortBy('city')->values()->take(30);

            return response()->json([
                'mode'       => 'city_group',
                'level_name' => __('dashboard.admin_cbe_branch_word'),
                'groups'     => $groups,
            ]);
        }

        $levelOrder    = $children->first()->level_order;
        $maxLevelOrder = DB::table('cbe_hierarchy_levels')->where('group_label_id', $children->first()->group_label_id)->max('level_order');
        $isDeepest     = $levelOrder !== null && $levelOrder == $maxLevelOrder;

        $filtered = $children;
        if (strlen($q) >= 1) {
            $filtered = $children->filter(fn ($c) => stripos($c->node_name, $q) !== false
                || ($c->node_name_zh && stripos($c->node_name_zh, $q) !== false));
        }
        $filtered = $filtered->values()->take(30);

        return response()->json([
            'mode'       => 'nodes',
            'level_name' => $isDeepest ? __('dashboard.admin_cbe_affiliate_word') : ($children->first()->level_name ?? null),
            'children'   => $filtered->map(fn ($c) => [
                'node_id' => $c->node_id,
                'label'   => $c->node_name_zh ? "{$c->node_name} ({$c->node_name_zh})" : $c->node_name,
            ])->values(),
        ]);
    }

    // CHANGED 2 Aug 2026 — per Chris: this used to also pull in agents under
    // any GL that had been "promoted" out of this GL's original downline
    // (e.g. Amy Tan, promoted out of Chris Yap's team). That meant picking
    // "prihatin2u — Chris Yap" on the Admin dashboard showed Amy Tan's own
    // Team Leaders (like Tan Ah Kow) mixed into Chris Yap's Top Performers.
    // Chris's call: each promoted GL already gets their own full dashboard
    // when they log in themselves, so folding their branch into the
    // original GL's scope here double-counts the same people under two
    // different "Top Performer" views. Now returns ONLY that GL's own
    // current direct group — no promoted-branch expansion.
    private function getDynastyAgentIds(string $groupId): array
    {
        return DB::table('agents')->where('group_id', $groupId)->where('is_deleted', false)->pluck('agent_id')->toArray();
    }

    public function metrics(Request $request)
    {
        $now       = now();
        $thisMonth = (int)$request->get('month', $now->month);
        $thisYear  = (int)$request->get('year',  $now->year);
        $filterIds = $this->resolveFilterAgentIds($request);

        $selectedDate = \Carbon\Carbon::createFromDate($thisYear, $thisMonth, 1);
        $lastMonth    = $selectedDate->copy()->subMonth()->month;
        $lastYear     = $selectedDate->copy()->subMonth()->year;

        // ── Network counts ──────────────────────────────────────────
        $counts = DB::table('agents')
            ->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))
            ->selectRaw("
                SUM(CASE WHEN role='GROUP_LEADER' THEN 1 ELSE 0 END) as gl,
                SUM(CASE WHEN role='TEAM_LEADER'  THEN 1 ELSE 0 END) as tl,
                SUM(CASE WHEN role='INTRODUCER'   THEN 1 ELSE 0 END) as intro
            ")
            ->where('status', 'ACTIVE')
            ->first();

        $pending = DB::table('agents')
            ->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))
            ->whereNull('parent_id')
            ->where('role', 'INTRODUCER')
            ->where('status', 'ACTIVE')
            ->count();

        // ── Sales MTD / YTD / Last MTD ──────────────────────────────
        $salesMTD = DB::table('sales_transactions')
            ->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))
            ->whereMonth('created_at', $thisMonth)
            ->whereYear('created_at', $thisYear)
            ->sum('premium_amount');

        $salesYTD = DB::table('sales_transactions')
            ->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))
            ->whereYear('created_at', $thisYear)
            ->sum('premium_amount');

        $salesLastMTD = DB::table('sales_transactions')
            ->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))
            ->whereMonth('created_at', $lastMonth)
            ->whereYear('created_at', $lastYear)
            ->sum('premium_amount');

        // ── Earnings MTD / YTD / Last MTD ───────────────────────────
        // FIXED 1 Aug 2026 — per Chris: none of these excluded REVERSED
        // rows, so a policy that got reversed-and-recalculated (e.g. via
        // commission:recalculate-pvatm) double-counted — the old
        // REVERSED row's commission_amount AND the new replacement row's
        // amount were both summed. Excluding REVERSED everywhere below
        // matches how the wallet balance itself is computed (reversed
        // rows never contributed to commission_balance in the first
        // place — see CommissionEngine::reverse()).
        $earnMTD = DB::table('commission_transactions')
            ->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))
            ->where('status', '!=', 'REVERSED')
            ->whereMonth('created_at', $thisMonth)
            ->whereYear('created_at', $thisYear)
            ->sum('commission_amount');

        $earnYTD = DB::table('commission_transactions')
            ->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))
            ->where('status', '!=', 'REVERSED')
            ->whereYear('created_at', $thisYear)
            ->sum('commission_amount');

        $earnLastMTD = DB::table('commission_transactions')
            ->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))
            ->where('status', '!=', 'REVERSED')
            ->whereMonth('created_at', $lastMonth)
            ->whereYear('created_at', $lastYear)
            ->sum('commission_amount');

        // ── Unclaimed Earnings ───────────────────────────────────────
        $unclaimed = DB::table('commission_transactions')
            ->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))
            ->where('status', 'PENDING')
            ->sum('commission_amount');

        // ── Points Purchase Pending ──────────────────────────────────
        $pointsPending = DB::table('point_purchases')
            ->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))
            ->where('status', 'PENDING')
            ->count();

        // ── Renewal counts ───────────────────────────────────────────
        $renewal_lte30 = DB::table('sales_transactions')
            ->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))
            ->where('status', 'PENDING_RENEWAL')
            ->whereNotNull('renewal_date')
            ->whereRaw('renewal_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)')
            ->whereRaw('renewal_date >= CURDATE()')
            ->count();

        $renewal_gt30 = DB::table('sales_transactions')
            ->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))
            ->where('status', 'PENDING_RENEWAL')
            ->whereNotNull('renewal_date')
            ->whereRaw('renewal_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)')
            ->count();

        // ── Redemption pending ───────────────────────────────────────
        $redemption = DB::table('reward_points_ledger')
            ->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))
            ->where('txn_type', 'REDEEMED')
            ->count();

        // ── Trend: last 6 months Sales & Earnings ───────────────────
        $trend = DB::table('sales_transactions')
            ->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))
            ->selectRaw("DATE_FORMAT(created_at,'%b %Y') as label,
                         MONTH(created_at) as m, YEAR(created_at) as y,
                         SUM(premium_amount) as sales")
            ->whereRaw('created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)')
            ->groupByRaw('YEAR(created_at), MONTH(created_at), DATE_FORMAT(created_at,\'%b %Y\')')
            ->orderByRaw('YEAR(created_at), MONTH(created_at)')
            ->get();

        $earnLookup = DB::table('commission_transactions')
            ->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))
            ->where('status', '!=', 'REVERSED')
            ->selectRaw("CONCAT(YEAR(created_at),'-',LPAD(MONTH(created_at),2,'0')) as ym,
                         SUM(commission_amount) as earn")
            ->whereRaw('created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)')
            ->groupByRaw('YEAR(created_at), MONTH(created_at), CONCAT(YEAR(created_at),\'-\',LPAD(MONTH(created_at),2,\'0\'))')
            ->pluck('earn', 'ym')
            ->toArray();

        $trend_labels = $trend->map(fn($r) => $r->label)->values()->toArray();
        $trend_sales  = $trend->map(fn($r) => (float)$r->sales)->values()->toArray();
        $trend_earn   = $trend->map(fn($r) => (float)($earnLookup[$r->y.'-'.str_pad($r->m,2,'0',STR_PAD_LEFT)] ?? 0))->values()->toArray();

        // ── Sales by Vendor ──────────────────────────────────────────
        $vendorData = DB::table('sales_transactions as st')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->when($filterIds !== null, fn($q) => $q->whereIn('st.agent_id', $filterIds))
            ->selectRaw("v.vendor_name as name, SUM(st.premium_amount) as sales")
            ->whereMonth('st.created_at', $thisMonth)
            ->whereYear('st.created_at', $thisYear)
            ->groupBy('v.vendor_id', 'v.vendor_name')
            ->orderByDesc('sales')
            ->limit(3)
            ->get();

        $vendorEarnMap = DB::table('commission_transactions as ct')
            ->join('sales_transactions as st', 'ct.policy_id', '=', 'st.policy_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->when($filterIds !== null, fn($q) => $q->whereIn('ct.agent_id', $filterIds))
            ->where('ct.status', '!=', 'REVERSED')
            ->selectRaw("v.vendor_name as name, SUM(ct.commission_amount) as earn")
            ->whereMonth('ct.created_at', $thisMonth)
            ->whereYear('ct.created_at', $thisYear)
            ->groupBy('v.vendor_id', 'v.vendor_name')
            ->pluck('earn', 'name')
            ->toArray();

        $vendors = $vendorData->map(fn($r) => [
            'name'  => $r->name,
            'sales' => (float)$r->sales,
            'earn'  => (float)($vendorEarnMap[$r->name] ?? 0),
        ])->values()->toArray();

        // ── Sales by Product ─────────────────────────────────────────
        $productData = DB::table('sales_transactions as st')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->when($filterIds !== null, fn($q) => $q->whereIn('st.agent_id', $filterIds))
            ->selectRaw("p.product_name as name, SUM(st.premium_amount) as sales")
            ->whereMonth('st.created_at', $thisMonth)
            ->whereYear('st.created_at', $thisYear)
            ->groupBy('p.product_id', 'p.product_name')
            ->orderByDesc('sales')
            ->limit(3)
            ->get();

        $productEarnMap = DB::table('commission_transactions as ct')
            ->join('sales_transactions as st', 'ct.policy_id', '=', 'st.policy_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->when($filterIds !== null, fn($q) => $q->whereIn('ct.agent_id', $filterIds))
            ->where('ct.status', '!=', 'REVERSED')
            ->selectRaw("p.product_name as name, SUM(ct.commission_amount) as earn")
            ->whereMonth('ct.created_at', $thisMonth)
            ->whereYear('ct.created_at', $thisYear)
            ->groupBy('p.product_id', 'p.product_name')
            ->pluck('earn', 'name')
            ->toArray();

        $products = $productData->map(fn($r) => [
            'name'  => $r->name,
            'sales' => (float)$r->sales,
            'earn'  => (float)($productEarnMap[$r->name] ?? 0),
        ])->values()->toArray();

        // ── Top Performers ───────────────────────────────────────────
        $topPerformers = $this->getTopPerformers($thisMonth, $thisYear, $filterIds);

        // NEW 1 Aug 2026 — per Chris: the "GL / TL / INT" radio labels on
        // Box 1 (Top 3 Performers) are rendered once at page load, before
        // any group is picked, so they can never reflect the selected
        // group's own short labels via a normal server-rendered variable
        // (the group filter here is pure client-side JS, no page
        // reload). Sending the right short labels back with every
        // metrics refresh lets the JS update the radio text live instead.
        $groupId      = $request->get('group_id');
        $groupLabelId = $groupId
            ? DB::table('agents')->where('group_id', $groupId)->where('role', 'GROUP_LEADER')->where('is_deleted', false)->value('group_label_id')
            : null;
        $shortLabels = [
            'gl'    => \App\Services\RoleLabelService::shortLabel('GROUP_LEADER', groupLabelId: $groupLabelId),
            'tl'    => \App\Services\RoleLabelService::shortLabel('TEAM_LEADER', groupLabelId: $groupLabelId),
            'intro' => \App\Services\RoleLabelService::shortLabel('INTRODUCER', groupLabelId: $groupLabelId),
        ];

        return response()->json([
            'shortLabels'          => $shortLabels,
            'totalGL'              => (int)($counts->gl ?? 0),
            'totalTL'              => (int)($counts->tl ?? 0),
            'totalIntroducers'     => (int)($counts->intro ?? 0),
            'pendingAssignment'    => $pending,
            'totalPremiumMonth'    => (float)$salesMTD,
            'totalPremiumYTD'      => (float)$salesYTD,
            'salesLastMTD'         => (float)$salesLastMTD,
            'commDistributedMonth' => (float)$earnMTD,
            'commDistributedYTD'   => (float)$earnYTD,
            'earnLastMTD'          => (float)$earnLastMTD,
            'unclaimedCommission'  => (float)$unclaimed,
            'unclaimedLastMTD'     => 0,
            'pointsPurchasePending'=> $pointsPending,
            'renewal_lte30'        => $renewal_lte30,
            'renewal_gt30'         => $renewal_gt30,
            'redemption_pending'   => $redemption,
            'trend_labels'         => $trend_labels,
            'trend_sales'          => $trend_sales,
            'trend_earn'           => $trend_earn,
            'vendors'              => $vendors,
            'products'             => $products,
            'topPerformers'        => $topPerformers,
        ]);
    }

    public function chartDrilldown(Request $request)
    {
        $type      = $request->get('type'); // vendors, products, trend
        $month     = (int)$request->get('month', now()->month);
        $year      = (int)$request->get('year',  now()->year);
        $page      = (int)$request->get('page', 1);
        $perPage   = $type === 'trend' ? 12 : 10;
        $filterIds = $this->resolveFilterAgentIds($request);
        $category  = $request->get('category', 'all');
        $groupId   = $request->get('group_id', '');

        $selectedDate = \Carbon\Carbon::createFromDate($year, $month, 1);
        $monthName    = $selectedDate->format('F Y');

        $carryParams = '&category='.$category.'&group_id='.$groupId;

        if ($type === 'vendors') {
            $all = DB::table('sales_transactions as st')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->where('st.is_deleted', false)
                ->when($filterIds !== null, fn($q) => $q->whereIn('st.agent_id', $filterIds))
                ->whereMonth('st.created_at', $month)
                ->whereYear('st.created_at', $year)
                ->select('v.vendor_id', 'v.vendor_name', DB::raw('SUM(st.premium_amount) as total_sales'))
                ->groupBy('v.vendor_id', 'v.vendor_name')
                ->orderByDesc('total_sales')
                ->get();

            $total       = $all->sum('total_sales');
            $periodLabel = 'All Vendors — '.$monthName;
            $slice       = $all->slice(($page-1)*$perPage, $perPage)->values();
            $startRank   = ($page-1)*$perPage + 1;
            $rawLabels   = $slice->map(fn($r) => $r->vendor_name)->toArray();
            $labels      = $slice->map(fn($r, $i) => '#'.($startRank+$i).' '.$r->vendor_name)->toArray();
            $sales       = $slice->map(fn($r) => (float)$r->total_sales)->toArray();
            $links       = $slice->map(fn($r) => route('admin.dashboard.drilldown').'?type=vendor_gl&vendor_id='.$r->vendor_id.'&month='.$month.'&year='.$year.$carryParams)->toArray();

        } elseif ($type === 'products') {
            $all = DB::table('sales_transactions as st')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->where('st.is_deleted', false)
                ->when($filterIds !== null, fn($q) => $q->whereIn('st.agent_id', $filterIds))
                ->whereMonth('st.created_at', $month)
                ->whereYear('st.created_at', $year)
                ->select('p.product_id', 'p.product_name', DB::raw('SUM(st.premium_amount) as total_sales'))
                ->groupBy('p.product_id', 'p.product_name')
                ->orderByDesc('total_sales')
                ->get();

            $total       = $all->sum('total_sales');
            $periodLabel = 'All Products — '.$monthName;
            $slice       = $all->slice(($page-1)*$perPage, $perPage)->values();
            $startRank   = ($page-1)*$perPage + 1;
            $rawLabels   = $slice->map(fn($r) => $r->product_name)->toArray();
            $labels      = $slice->map(fn($r, $i) => '#'.($startRank+$i).' '.$r->product_name)->toArray();
            $sales       = $slice->map(fn($r) => (float)$r->total_sales)->toArray();
            $links       = $slice->map(fn($r) => route('admin.dashboard.drilldown').'?type=product_gl&product_id='.$r->product_id.'&month='.$month.'&year='.$year.$carryParams)->toArray();

        } elseif ($type === 'trend') {
            $all = DB::table('sales_transactions as st')
                ->where('st.is_deleted', false)
                ->when($filterIds !== null, fn($q) => $q->whereIn('st.agent_id', $filterIds))
                ->whereYear('st.created_at', $year)
                ->selectRaw("DATE_FORMAT(st.created_at,'%M %Y') as month_name, MONTH(st.created_at) as month_num, SUM(st.premium_amount) as total_sales")
                ->groupByRaw("YEAR(st.created_at), MONTH(st.created_at), DATE_FORMAT(st.created_at,'%M %Y')")
                ->orderByDesc('total_sales')
                ->get();

            $total       = $all->sum('total_sales');
            $periodLabel = 'Monthly Sales Ranking — '.$year;
            $slice       = $all->slice(($page-1)*$perPage, $perPage)->values();
            $startRank   = ($page-1)*$perPage + 1;
            $rawLabels   = $slice->map(fn($r) => $r->month_name)->toArray();
            $labels      = $slice->map(fn($r, $i) => '#'.($startRank+$i).' '.$r->month_name)->toArray();
            $sales       = $slice->map(fn($r) => (float)$r->total_sales)->toArray();
            $links       = $slice->map(fn($r) => route('admin.dashboard.drilldown').'?type=sales&period=mtd&month='.$r->month_num.'&year='.$year.$carryParams)->toArray();
        }

        $lastPage = ceil($all->count() / $perPage);
        $hasMore  = $page < $lastPage;

        $ctype = $request->get('ctype', 'bar');
        return view('dashboard.chart-drilldown', compact(
            'rawLabels', 'labels', 'sales', 'links',
            'total', 'periodLabel', 'monthName', 'startRank',
            'month', 'year', 'type', 'page', 'lastPage', 'hasMore', 'perPage', 'ctype'
        ));
    }

    public function drilldown(Request $request)
    {
        $type      = $request->get('type', 'sales'); // sales, earnings, unclaimed
        $period    = $request->get('period', 'mtd'); // mtd, last_mtd, ytd
        $month     = (int)$request->get('month', now()->month);
        $year      = (int)$request->get('year',  now()->year);
        $filterIds = $this->resolveFilterAgentIds($request);
        $groupId   = $request->get('group_id');

        $selectedDate = \Carbon\Carbon::createFromDate($year, $month, 1);
        $lastMonth    = $selectedDate->copy()->subMonth()->month;
        $lastYear     = $selectedDate->copy()->subMonth()->year;
        $monthName    = $selectedDate->format('F Y');
        $lastMonthName = $selectedDate->copy()->subMonth()->format('F Y');

        // For the "GL breakdown" views, restrict which GL rows can show up
        // to just the dynasty currently in scope (empty = no restriction,
        // i.e. "All" or a whole category with no specific group chosen).
        $glScopeIds = $groupId ? array_unique(
            DB::table('agents')->whereIn('agent_id', $filterIds ?? [])->where('role', 'GROUP_LEADER')->pluck('agent_id')->toArray()
        ) : null;

        if ($type === 'performers') {
            $records = DB::table('agents as a')
                ->where('a.is_deleted', false)
                ->where('a.status', 'ACTIVE')
                ->when($filterIds !== null, fn($q) => $q->whereIn('a.agent_id', $filterIds))
                ->leftJoin('sales_transactions as st', function($join) use ($month, $year) {
                    $join->on('st.agent_id', '=', 'a.agent_id')
                         ->where('st.is_deleted', false)
                         ->whereMonth('st.created_at', $month)
                         ->whereYear('st.created_at', $year);
                })
                ->select(
                    'a.agent_id', 'a.full_name', 'a.agent_code', 'a.role',
                    DB::raw('COALESCE(SUM(st.premium_amount), 0) as total_sales'),
                    DB::raw('COUNT(DISTINCT st.policy_id) as total_transactions')
                )
                ->groupBy('a.agent_id', 'a.full_name', 'a.agent_code', 'a.role')
                ->orderByDesc('total_sales')
                ->paginate(15)->withQueryString();

            $total       = DB::table('sales_transactions')->where('is_deleted', false)->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))->whereMonth('created_at', $month)->whereYear('created_at', $year)->sum('premium_amount');
            $periodLabel = 'All Performers — '.$monthName;
            $columns     = [__('gl.col_agent'), __('network.col_code'), __('gl.col_role'), __('drilldown.col_sales_amount_rm'), __('gl.col_transactions')];
            $viewType    = 'ranking_performers';

        } elseif ($type === 'vendors') {
            $records = DB::table('sales_transactions as st')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->where('st.is_deleted', false)
                ->when($filterIds !== null, fn($q) => $q->whereIn('st.agent_id', $filterIds))
                ->whereMonth('st.created_at', $month)
                ->whereYear('st.created_at', $year)
                ->select(
                    'v.vendor_id', 'v.vendor_name',
                    DB::raw('COALESCE(SUM(st.premium_amount), 0) as total_sales'),
                    DB::raw('COUNT(DISTINCT st.policy_id) as total_transactions')
                )
                ->groupBy('v.vendor_id', 'v.vendor_name')
                ->orderByDesc('total_sales')
                ->paginate(15)->withQueryString();

            $total       = DB::table('sales_transactions')->where('is_deleted', false)->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))->whereMonth('created_at', $month)->whereYear('created_at', $year)->sum('premium_amount');
            $periodLabel = 'All Vendors — '.$monthName;
            $columns     = [__('network.col_vendor'), __('drilldown.col_sales_amount_rm'), __('gl.col_transactions')];
            $viewType    = 'ranking_vendors';

        } elseif ($type === 'vendor_gl') {
            $vendorId   = $request->get('vendor_id');
            $vendorName = DB::table('vendors')->where('vendor_id', $vendorId)->value('vendor_name');
            $records = DB::table('agents as gl')
                ->where('gl.role', 'GROUP_LEADER')
                ->where('gl.is_deleted', false)
                ->when($glScopeIds !== null, fn($q) => $q->whereIn('gl.agent_id', $glScopeIds))
                ->leftJoin('agents as members', 'members.group_id', '=', 'gl.group_id')
                ->leftJoin('sales_transactions as st', function($join) use ($vendorId, $month, $year) {
                    $join->on('st.agent_id', '=', 'members.agent_id')
                         ->where('st.vendor_id', $vendorId)
                         ->where('st.is_deleted', false)
                         ->whereMonth('st.created_at', $month)
                         ->whereYear('st.created_at', $year);
                })
                ->select(
                    'gl.agent_id', 'gl.full_name', 'gl.agent_code', 'gl.role',
                    DB::raw('COALESCE(SUM(st.premium_amount), 0) as total_sales'),
                    DB::raw('COUNT(DISTINCT st.policy_id) as total_transactions')
                )
                ->groupBy('gl.agent_id', 'gl.full_name', 'gl.agent_code', 'gl.role')
                ->orderByDesc('total_sales')
                ->paginate(15)->withQueryString();

            $total       = DB::table('sales_transactions')->where('vendor_id', $vendorId)->where('is_deleted', false)->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))->whereMonth('created_at', $month)->whereYear('created_at', $year)->sum('premium_amount');
            $periodLabel = $vendorName.' — GL Breakdown — '.$monthName;
            $columns     = [__('gl.role_group_leader'), __('gl.col_role'), __('network.col_code'), __('drilldown.col_sales_amount_rm'), __('gl.col_transactions')];
            $viewType    = 'ranking_vendor_gl';

        } elseif ($type === 'products') {
            $records = DB::table('sales_transactions as st')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->where('st.is_deleted', false)
                ->when($filterIds !== null, fn($q) => $q->whereIn('st.agent_id', $filterIds))
                ->whereMonth('st.created_at', $month)
                ->whereYear('st.created_at', $year)
                ->select(
                    'p.product_id', 'p.product_name',
                    DB::raw('COALESCE(SUM(st.premium_amount), 0) as total_sales'),
                    DB::raw('COUNT(DISTINCT st.policy_id) as total_transactions')
                )
                ->groupBy('p.product_id', 'p.product_name')
                ->orderByDesc('total_sales')
                ->paginate(15)->withQueryString();

            $total       = DB::table('sales_transactions')->where('is_deleted', false)->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))->whereMonth('created_at', $month)->whereYear('created_at', $year)->sum('premium_amount');
            $periodLabel = 'All Products — '.$monthName;
            $columns     = [__('network.col_product'), __('drilldown.col_sales_amount_rm'), __('gl.col_transactions')];
            $viewType    = 'ranking_products';

        } elseif ($type === 'product_gl') {
            $productId   = $request->get('product_id');
            $productName = DB::table('products')->where('product_id', $productId)->value('product_name');
            $records = DB::table('agents as gl')
                ->where('gl.role', 'GROUP_LEADER')
                ->where('gl.is_deleted', false)
                ->when($glScopeIds !== null, fn($q) => $q->whereIn('gl.agent_id', $glScopeIds))
                ->leftJoin('agents as members', 'members.group_id', '=', 'gl.group_id')
                ->leftJoin('sales_transactions as st', function($join) use ($productId, $month, $year) {
                    $join->on('st.agent_id', '=', 'members.agent_id')
                         ->where('st.product_id', $productId)
                         ->where('st.is_deleted', false)
                         ->whereMonth('st.created_at', $month)
                         ->whereYear('st.created_at', $year);
                })
                ->select(
                    'gl.agent_id', 'gl.full_name', 'gl.agent_code', 'gl.role',
                    DB::raw('COALESCE(SUM(st.premium_amount), 0) as total_sales'),
                    DB::raw('COUNT(DISTINCT st.policy_id) as total_transactions')
                )
                ->groupBy('gl.agent_id', 'gl.full_name', 'gl.agent_code', 'gl.role')
                ->orderByDesc('total_sales')
                ->paginate(15)->withQueryString();

            $total       = DB::table('sales_transactions')->where('product_id', $productId)->where('is_deleted', false)->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))->whereMonth('created_at', $month)->whereYear('created_at', $year)->sum('premium_amount');
            $periodLabel = $productName.' — GL Breakdown — '.$monthName;
            $columns     = [__('gl.role_group_leader'), __('gl.col_role'), __('network.col_code'), __('drilldown.col_sales_amount_rm'), __('gl.col_transactions')];
            $viewType    = 'ranking_product_gl';

        } elseif ($type === 'trend') {
            $records = DB::table('sales_transactions as st')
                ->where('st.is_deleted', false)
                ->when($filterIds !== null, fn($q) => $q->whereIn('st.agent_id', $filterIds))
                ->whereYear('st.created_at', $year)
                ->selectRaw("DATE_FORMAT(st.created_at,'%M %Y') as month_name, MONTH(st.created_at) as month_num, YEAR(st.created_at) as year_num, COALESCE(SUM(st.premium_amount),0) as total_sales, COUNT(DISTINCT st.policy_id) as total_transactions")
                ->groupByRaw("YEAR(st.created_at), MONTH(st.created_at), DATE_FORMAT(st.created_at,'%M %Y')")
                ->orderByDesc('total_sales')
                ->paginate(15)->withQueryString();

            $total       = DB::table('sales_transactions')->where('is_deleted', false)->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))->whereYear('created_at', $year)->sum('premium_amount');
            $periodLabel = 'Monthly Sales Ranking — '.$year;
            $columns     = [__('renewal_forecast.col_month'), __('drilldown.col_sales_amount_rm'), __('gl.col_transactions')];
            $viewType    = 'ranking_trend';

        } elseif ($type === 'renewal_lte30') {
            $query = DB::table('sales_transactions as st')
                ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
                ->where('st.status', 'PENDING_RENEWAL')
                ->whereNotNull('st.renewal_date')
                ->whereRaw('st.renewal_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)')
                ->whereRaw('st.renewal_date >= CURDATE()')
                ->where('st.is_deleted', false)
                ->when($filterIds !== null, fn($q) => $q->whereIn('st.agent_id', $filterIds))
                ->select('st.created_at', 'v.vendor_name', 'p.product_name', 'a.agent_code',
                    'a.full_name as agent_name', 'c.full_name as customer_name',
                    'st.premium_amount', 'st.renewal_date', 'st.status');
            $periodLabel = 'Renewal Due ≤ 30 Days';
            $total = (clone $query)->sum('st.premium_amount');
            $records = $query->orderBy('st.renewal_date')->paginate(15)->withQueryString();
            $columns = [__('gl.col_date'), __('network.col_vendor'), __('network.col_product'), __('network.col_code'), __('gl.col_agent'), __('gl.col_customer'), __('drilldown.col_sales_amount_rm'), __('gl.renewal_date_label'), __('network.col_status')];

        } elseif ($type === 'renewal_gt30') {
            $query = DB::table('sales_transactions as st')
                ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
                ->where('st.status', 'PENDING_RENEWAL')
                ->whereNotNull('st.renewal_date')
                ->whereRaw('st.renewal_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)')
                ->where('st.is_deleted', false)
                ->when($filterIds !== null, fn($q) => $q->whereIn('st.agent_id', $filterIds))
                ->select('st.created_at', 'v.vendor_name', 'p.product_name', 'a.agent_code',
                    'a.full_name as agent_name', 'c.full_name as customer_name',
                    'st.premium_amount', 'st.renewal_date', 'st.status');
            $periodLabel = 'Renewal Due > 30 Days';
            $total = (clone $query)->sum('st.premium_amount');
            $records = $query->orderBy('st.renewal_date')->paginate(15)->withQueryString();
            $columns = [__('gl.col_date'), __('network.col_vendor'), __('network.col_product'), __('network.col_code'), __('gl.col_agent'), __('gl.col_customer'), __('drilldown.col_sales_amount_rm'), __('gl.renewal_date_label'), __('network.col_status')];

        } elseif ($type === 'sales') {
            $query = DB::table('sales_transactions as st')
                ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
                ->where('st.is_deleted', false)
                ->when($filterIds !== null, fn($q) => $q->whereIn('st.agent_id', $filterIds))
                ->select(
                    'st.policy_number', 'st.premium_amount', 'st.status', 'st.created_at',
                    'a.full_name as agent_name', 'a.agent_code', 'a.role',
                    'v.vendor_name', 'p.product_name',
                    'c.full_name as customer_name'
                );

            if ($period === 'mtd') {
                $query->whereMonth('st.created_at', $month)->whereYear('st.created_at', $year);
                $periodLabel = 'Sales Amount — MTD '.$monthName;
            } elseif ($period === 'last_mtd') {
                $query->whereMonth('st.created_at', $lastMonth)->whereYear('st.created_at', $lastYear);
                $periodLabel = 'Sales Amount — Last MTD '.$lastMonthName;
            } else {
                $query->whereYear('st.created_at', $year);
                $periodLabel = 'Sales Amount — YTD '.$year;
            }

            $total   = (clone $query)->sum('st.premium_amount');
            $records = $query->orderBy('st.created_at')->paginate(15)->withQueryString();
            $columns = [__('gl.col_date'), __('network.col_vendor'), __('network.col_product'), __('network.col_code'), __('gl.col_agent'), __('gl.col_customer'), __('drilldown.col_sales_amount_rm'), __('network.col_status')];

        } elseif ($type === 'earnings') {
            $query = DB::table('commission_transactions as ct')
                ->join('agents as a', 'ct.agent_id', '=', 'a.agent_id')
                ->join('sales_transactions as st', 'ct.policy_id', '=', 'st.policy_id')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
                // FIXED 1 Aug 2026 — was checking status != 'CANCELLED',
                // but commission_transactions.status is only ever PENDING/
                // CONFIRMED/REVERSED — 'CANCELLED' never matches anything,
                // so this was a silent no-op letting REVERSED rows through.
                ->where('ct.status', '!=', 'REVERSED')
                ->when($filterIds !== null, fn($q) => $q->whereIn('ct.agent_id', $filterIds))
                ->select(
                    'ct.created_at',
                    'v.vendor_name',
                    'p.product_name',
                    'a.agent_code',
                    'a.full_name as agent_name',
                    'c.full_name as customer_name',
                    'ct.commission_amount',
                    'ct.status'
                );

            if ($period === 'mtd') {
                $query->whereMonth('ct.created_at', $month)->whereYear('ct.created_at', $year);
                $periodLabel = 'Earning Income — MTD '.$monthName;
            } elseif ($period === 'last_mtd') {
                $query->whereMonth('ct.created_at', $lastMonth)->whereYear('ct.created_at', $lastYear);
                $periodLabel = 'Earning Income — Last MTD '.$lastMonthName;
            } else {
                $query->whereYear('ct.created_at', $year);
                $periodLabel = 'Earning Income — YTD '.$year;
            }

            $total   = (clone $query)->sum('ct.commission_amount');
            $records = $query->orderBy('ct.created_at')->paginate(15)->withQueryString();
            $columns = [__('gl.col_date'), __('network.col_vendor'), __('network.col_product'), __('network.col_code'), __('gl.col_agent'), __('gl.col_customer'), __('drilldown.col_earning_rm'), __('network.col_status')];

        } else {
            // unclaimed
            $query = DB::table('commission_transactions as ct')
                ->join('agents as a', 'ct.agent_id', '=', 'a.agent_id')
                ->join('sales_transactions as st', 'ct.policy_id', '=', 'st.policy_id')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->leftJoin('customers as c', 'st.customer_id', '=', 'c.customer_id')
                ->where('ct.status', 'PENDING')
                ->when($filterIds !== null, fn($q) => $q->whereIn('ct.agent_id', $filterIds))
                ->select(
                    'ct.created_at',
                    'v.vendor_name',
                    'p.product_name',
                    'a.agent_code',
                    'a.full_name as agent_name',
                    'c.full_name as customer_name',
                    'ct.commission_amount',
                    'ct.status'
                );

            $periodLabel = 'Unclaimed Earning';
            $total   = (clone $query)->sum('ct.commission_amount');
            $records = $query->orderBy('ct.created_at')->paginate(15)->withQueryString();
            $columns = [__('gl.col_date'), __('network.col_vendor'), __('network.col_product'), __('network.col_code'), __('gl.col_agent'), __('gl.col_customer'), __('drilldown.col_earning_rm'), __('network.col_status')];
        }

        if (!isset($viewType)) $viewType = 'transactions';
        return view('dashboard.drilldown', compact(
            'records', 'total', 'columns', 'type', 'period',
            'periodLabel', 'month', 'year', 'monthName', 'viewType'
        ));
    }

    private function getTopPerformers(int $month, int $year, ?array $filterIds = null): array
    {
        // ── GL: ranked by total group sales for selected month ──
        $glAgents = DB::table('agents')
            ->where('role', 'GROUP_LEADER')
            ->where('status', 'ACTIVE')
            ->where('is_deleted', false)
            ->when($filterIds !== null, fn($q) => $q->whereIn('agent_id', $filterIds))
            ->get(['agent_id', 'full_name', 'agent_code', 'group_id']);

        $glSales = $glAgents->map(function($gl) use ($month, $year) {
            $total = DB::table('sales_transactions as st')
                ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
                ->where('a.group_id', $gl->group_id)
                ->whereMonth('st.created_at', $month)
                ->whereYear('st.created_at', $year)
                ->sum('st.premium_amount');
            return (object)[
                'agent_id'   => $gl->agent_id,
                'full_name'  => $gl->full_name,
                'agent_code' => $gl->agent_code,
                'total'      => (float)$total,
            ];
        })->sortByDesc('total')->values()->take(3)->toArray();

        $glEarn = DB::table('commission_transactions as ct')
            ->join('agents as a', 'ct.agent_id', '=', 'a.agent_id')
            ->selectRaw("a.agent_id, a.full_name, a.agent_code, SUM(ct.commission_amount) as total")
            ->where('a.role', 'GROUP_LEADER')
            ->where('a.status', 'ACTIVE')
            ->where('ct.status', '!=', 'REVERSED')
            ->when($filterIds !== null, fn($q) => $q->whereIn('a.agent_id', $filterIds))
            ->whereMonth('ct.created_at', $month)
            ->whereYear('ct.created_at', $year)
            ->groupBy('a.agent_id', 'a.full_name', 'a.agent_code')
            ->orderByDesc('total')
            ->limit(3)
            ->get()->toArray();

        // ── TL: ranked by own + introducers sales for selected month ──
        $tlAgents = DB::table('agents as tl')
            ->where('tl.role', 'TEAM_LEADER')
            ->where('tl.status', 'ACTIVE')
            ->where('tl.is_deleted', false)
            ->when($filterIds !== null, fn($q) => $q->whereIn('tl.agent_id', $filterIds))
            ->leftJoin('agents as gl', 'gl.agent_id', '=', 'tl.parent_id')
            ->select('tl.agent_id', 'tl.full_name', 'tl.agent_code',
                     'gl.agent_id as gl_id', 'gl.full_name as gl_name', 'gl.agent_code as gl_code')
            ->get();

        $tlSales = $tlAgents->map(function($tl) use ($month, $year) {
            $total = DB::table('sales_transactions as st')
                ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
                ->where(function($q) use ($tl) {
                    $q->where('a.parent_id', $tl->agent_id)
                      ->orWhere('st.agent_id', $tl->agent_id);
                })
                ->whereMonth('st.created_at', $month)
                ->whereYear('st.created_at', $year)
                ->sum('st.premium_amount');
            return (object)[
                'agent_id'   => $tl->agent_id,
                'full_name'  => $tl->full_name,
                'agent_code' => $tl->agent_code,
                'gl_id'      => $tl->gl_id,
                'gl_name'    => $tl->gl_name,
                'gl_code'    => $tl->gl_code,
                'total'      => (float)$total,
            ];
        })->sortByDesc('total')->values()->take(3)->toArray();

        $tlEarn = DB::table('commission_transactions as ct')
            ->join('agents as a', 'ct.agent_id', '=', 'a.agent_id')
            ->leftJoin('agents as gl', 'gl.agent_id', '=', 'a.parent_id')
            ->selectRaw("a.agent_id, a.full_name, a.agent_code,
                         gl.agent_id as gl_id, gl.full_name as gl_name, gl.agent_code as gl_code,
                         SUM(ct.commission_amount) as total")
            ->where('a.role', 'TEAM_LEADER')
            ->where('a.status', 'ACTIVE')
            ->where('ct.status', '!=', 'REVERSED')
            ->when($filterIds !== null, fn($q) => $q->whereIn('a.agent_id', $filterIds))
            ->whereMonth('ct.created_at', $month)
            ->whereYear('ct.created_at', $year)
            ->groupBy('a.agent_id','a.full_name','a.agent_code','gl.agent_id','gl.full_name','gl.agent_code')
            ->orderByDesc('total')
            ->limit(3)
            ->get()->toArray();

        // ── Intro: ranked by own + downline sales for selected month ──
        $introAgents = DB::table('agents as i')
            ->where('i.role', 'INTRODUCER')
            ->where('i.status', 'ACTIVE')
            ->where('i.is_deleted', false)
            ->when($filterIds !== null, fn($q) => $q->whereIn('i.agent_id', $filterIds))
            ->leftJoin('agents as tl', 'tl.agent_id', '=', 'i.parent_id')
            ->leftJoin('agents as gl', 'gl.agent_id', '=', 'tl.parent_id')
            ->select('i.agent_id', 'i.full_name', 'i.agent_code',
                     'tl.agent_id as tl_id', 'tl.full_name as tl_name', 'tl.agent_code as tl_code',
                     'gl.agent_id as gl_id', 'gl.full_name as gl_name', 'gl.agent_code as gl_code')
            ->get();

        $introSales = $introAgents->map(function($i) use ($month, $year) {
            $total = DB::table('sales_transactions as st')
                ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
                ->where(function($q) use ($i) {
                    $q->where('st.agent_id', $i->agent_id)
                      ->orWhere('a.parent_id', $i->agent_id);
                })
                ->whereMonth('st.created_at', $month)
                ->whereYear('st.created_at', $year)
                ->sum('st.premium_amount');
            return (object)[
                'agent_id'   => $i->agent_id,
                'full_name'  => $i->full_name,
                'agent_code' => $i->agent_code,
                'tl_id'      => $i->tl_id,
                'tl_name'    => $i->tl_name,
                'tl_code'    => $i->tl_code,
                'gl_id'      => $i->gl_id,
                'gl_name'    => $i->gl_name,
                'gl_code'    => $i->gl_code,
                'total'      => (float)$total,
            ];
        })->sortByDesc('total')->values()->take(3)->toArray();

        $introEarn = DB::table('commission_transactions as ct')
            ->join('agents as a', 'ct.agent_id', '=', 'a.agent_id')
            ->leftJoin('agents as tl', 'tl.agent_id', '=', 'a.parent_id')
            ->leftJoin('agents as gl', 'gl.agent_id', '=', 'tl.parent_id')
            ->selectRaw("a.agent_id, a.full_name, a.agent_code,
                         tl.agent_id as tl_id, tl.full_name as tl_name, tl.agent_code as tl_code,
                         gl.agent_id as gl_id, gl.full_name as gl_name, gl.agent_code as gl_code,
                         SUM(ct.commission_amount) as total")
            ->where('a.role', 'INTRODUCER')
            ->where('a.status', 'ACTIVE')
            ->where('ct.status', '!=', 'REVERSED')
            ->when($filterIds !== null, fn($q) => $q->whereIn('a.agent_id', $filterIds))
            ->whereMonth('ct.created_at', $month)
            ->whereYear('ct.created_at', $year)
            ->groupBy('a.agent_id','a.full_name','a.agent_code',
                      'tl.agent_id','tl.full_name','tl.agent_code',
                      'gl.agent_id','gl.full_name','gl.agent_code')
            ->orderByDesc('total')
            ->limit(3)
            ->get()->toArray();

        return [
            'gl'    => ['sales' => $glSales,    'earnings' => $glEarn],
            'tl'    => ['sales' => $tlSales,    'earnings' => $tlEarn],
            'intro' => ['sales' => $introSales, 'earnings' => $introEarn],
        ];
    }
}