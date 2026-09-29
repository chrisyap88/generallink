<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\DataScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 19 Jul 2026 — per Chris: every agent (Introducer, TL, GL — Admin
// too) needs to see their yearly Projected Sales and Earning Income
// Forecast, broken down by month, with drill-down to individual
// transactions.
//
// REBUILT 22 Jul 2026 (v2) — contributor ranking drill-down (sortable,
// highest-to-lowest), Vendor/Product as their own filter dimension,
// Prev/Next moved to the bottom, renamed off "insurance renewal"
// wording.
//
// REBUILT AGAIN 22 Jul 2026 (v3) — tabbed layout (Overview / Detail)
// so the page fits one screen with no scrolling.
//
// REBUILT AGAIN 22 Jul 2026 (v4) — per Chris: "before execute my
// search why you show figure? what if million of potential forecast."
// Nothing — not even the summary cards/chart — gets queried until the
// viewer has explicitly clicked Search at least once. Only the filter
// row's own dropdown option lists (small, bounded — GL/TL/Introducer/
// Vendor/Product counts are never huge) are computed up front so
// there's something to pick from. This is the same "never
// default-load everything" principle already applied to the Agent
// Balances screen, just extended to cover the whole page here instead
// of only the detail table.
//
// Scoped via the same DataScopeService used everywhere else as the
// outer boundary, with the cascade selection applied ON TOP of it —
// so even a tampered-with URL can never see outside the viewer's own
// base scope: the two whereIn()s intersect, never union.
// -------------------------------------------------------
class RenewalForecastController extends Controller
{
    private function rolePrefix($agent): string
    {
        return match ($agent->role) {
            'ADMIN' => 'admin',
            'GROUP_LEADER' => 'gl',
            'TEAM_LEADER' => 'tl',
            default => 'introducer',
        };
    }

    // NEW 23 Jul 2026 (v6) — direct children of one specific parent, for
    // the Contributor Ranking table. Always bounded by a real parent_id
    // (a single team's roster, never the whole company), plus a sane
    // limit as a safety net regardless.
    //
    // FIXED 23 Jul 2026 (v13) — per Chris's real data: a Team Leader can
    // have another Team Leader reporting directly to her (e.g. Wendy
    // Koh reports straight to Tan Boon Hwa, not through an Introducer
    // level), not just Introducers. This used to hardcode role =
    // 'INTRODUCER' whenever drilling under a TL, which silently hid any
    // TEAM_LEADER whose parent_id pointed at another TEAM_LEADER — her
    // whole subtree's transactions were being counted in the summary
    // totals but had no row to appear under and no way to drill into.
    // Now this fetches BOTH roles for any parent — whatever the real
    // parent_id says, not an assumption about which role a given level
    // "should" be.
    private function childAgents(string $parentId, int $limit = 300)
    {
        return DB::table('agents')
            ->whereIn('role', ['TEAM_LEADER', 'INTRODUCER'])
            ->where('parent_id', $parentId)
            ->where('is_deleted', false)
            ->orderBy('full_name')
            ->limit($limit)
            ->get(['agent_id', 'full_name', 'agent_code', 'role']);
    }

    // Top-of-hierarchy Groups, for the one case with no real parent to
    // scope by (Admin, nothing selected yet). Capped — per Chris's own
    // "10,000 Group Leaders" example, ranking every single one flat and
    // unpaginated would eventually need proper pagination; this limit
    // is the interim safety net so a huge company can never make this
    // query unbounded.
    private function topLevelGroups(int $limit = 300)
    {
        return DB::table('agents')
            ->where('role', 'GROUP_LEADER')
            ->where('is_deleted', false)
            ->orderBy('full_name')
            ->limit($limit)
            ->selectRaw("agent_id, full_name, agent_code, 'GROUP_LEADER' as role")
            ->get();
    }

    // A node plus every descendant under it, however deep — same
    // hierarchy_path technique DataScopeService::getAgentIds() and the
    // Document Credit / Team Document Credit screens already use.
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

    // NEW 23 Jul 2026 (v8) — per Chris: the Contributor Ranking table's
    // new "Pipeline" column jumps straight to a customer-level list for
    // one child's WHOLE subtree (every Introducer under a Team Leader,
    // for example) without drilling through each intermediate level
    // first. Shared with the ordinary leaf-agent detail list, which is
    // the same query for a subtree of exactly one person.
    private function buildDetailPage($baseQuery, array $agentIds, ?int $month, int $year, string $glId, string $tlId, string $introId, string $vendorId, string $productId, array $extraAppends = [])
    {
        $detailQuery = $baseQuery()->whereIn('st.agent_id', $agentIds);
        if ($month !== null) {
            $detailQuery->whereMonth('r.coverage_end', $month);
        }
        $detailPage = $detailQuery->select(
            'r.renewal_id', 'r.coverage_end', 'r.status',
            'st.policy_id', 'st.document_reference_number', 'st.premium_amount', 'st.created_at as transaction_date',
            'c.customer_id', 'c.full_name as customer_name',
            'p.product_name', 'v.vendor_name'
        )->orderBy('r.coverage_end')->paginate(10, ['*'], 'rfPage')->appends(array_merge([
            'search' => 1, 'year' => $year, 'month' => $month, 'gl_id' => $glId, 'tl_id' => $tlId,
            'introducer_id' => $introId, 'vendor_id' => $vendorId, 'product_id' => $productId,
        ], $extraAppends));

        $detailPolicyIds = collect($detailPage->items())->pluck('policy_id')->all();
        $detailCommByPolicy = collect();
        if (!empty($detailPolicyIds)) {
            $detailCommByPolicy = DB::table('commission_transactions')
                ->whereIn('policy_id', $detailPolicyIds)->whereIn('agent_id', $agentIds)
                ->select('policy_id', DB::raw('SUM(commission_amount) as total'))
                ->groupBy('policy_id')->pluck('total', 'policy_id');
        }

        $todayTs = now()->startOfDay()->getTimestamp();
        foreach ($detailPage as $row) {
            $row->earning_income = (float) ($detailCommByPolicy[$row->policy_id] ?? 0);
            $dueTs = \Illuminate\Support\Carbon::parse($row->coverage_end)->startOfDay()->getTimestamp();
            $row->days_until_due = (int) round(($dueTs - $todayTs) / 86400);
        }

        return $detailPage;
    }

    // NEW 23 Jul 2026 (v6) — per Chris: "if i have 10000 Group Leader
    // 600000 Team Leader and 1 million introducer... you going to
    // display 10000 group records one by one to pick?" — plain
    // <select> dropdowns populated with every candidate do not scale.
    // Typeahead search-as-you-type instead: never loads more than 20
    // rows at a time, and every mode is scoped so a GL/TL/Introducer
    // can only ever search within their own team, mirroring the exact
    // narrowing already used for the drill-down cascade above (never
    // trust a client-supplied parent_id for a non-Admin viewer — it's
    // re-validated against their own scope, not taken at face value).
    public function agentTypeahead(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        $mode = $request->get('mode');
        $q = trim((string) $request->get('q', ''));
        $parentId = trim((string) $request->get('parent_id', ''));

        if (!in_array($mode, ['gl', 'tl', 'introducer'], true)) {
            return response()->json([]);
        }

        $roleForMode = ['gl' => 'GROUP_LEADER', 'tl' => 'TEAM_LEADER', 'introducer' => 'INTRODUCER'][$mode];

        $query = DB::table('agents')->where('is_deleted', false)->where('role', $roleForMode);

        if ($mode === 'gl') {
            // Only Admin has a Group selector at all.
            if ($agent->role !== 'ADMIN') {
                return response()->json([]);
            }
        } elseif ($mode === 'tl') {
            if ($agent->role === 'ADMIN') {
                // Admin may optionally narrow to one Group first, or
                // search every Team Leader in the company directly.
                if ($parentId !== '') {
                    $query->where('parent_id', $parentId);
                }
            } elseif ($agent->role === 'GROUP_LEADER') {
                // Own group's Team Leaders only — parent_id is forced
                // to the logged-in GL's own id, never the client value.
                $query->where('parent_id', $agent->agent_id);
            } else {
                return response()->json([]); // TL/Introducer have no TL selector.
            }
        } else { // introducer
            if ($agent->role === 'ADMIN') {
                if ($parentId !== '') {
                    $query->where('parent_id', $parentId);
                }
            } elseif ($agent->role === 'GROUP_LEADER') {
                // Narrow to one of the GL's own Team Leaders if given,
                // but always intersected with "somewhere in my group" —
                // a spoofed parent_id belonging to another group still
                // returns nothing, same intersect-never-union guarantee
                // DataScopeService uses everywhere else.
                $query->where('group_id', $agent->group_id);
                if ($parentId !== '') {
                    $query->where('parent_id', $parentId);
                }
            } elseif ($agent->role === 'TEAM_LEADER') {
                // Direct Introducers only — deeper nesting is reached by
                // drilling row-by-row, not by a flat search here.
                $query->where('parent_id', $agent->agent_id);
            } else {
                // Introducer — own directly-recruited sub-Introducers.
                $query->where('parent_id', $agent->agent_id);
            }
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('full_name', 'like', "%{$q}%")->orWhere('agent_code', 'like', "%{$q}%");
            });
        }

        $results = $query->orderBy('full_name')->limit(20)->get(['agent_id', 'full_name', 'agent_code']);

        return response()->json($results);
    }

    public function index(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        $rolePrefix = $this->rolePrefix($agent);
        $scope = new DataScopeService();
        $year = (int) $request->get('year', now()->year);

        $monthRaw = $request->get('month', '');
        $month = ($monthRaw !== '' && ctype_digit((string) $monthRaw) && (int) $monthRaw >= 1 && (int) $monthRaw <= 12)
            ? (int) $monthRaw : null;

        $sort = in_array($request->get('sort'), ['premium', 'earning'], true) ? $request->get('sort') : 'premium';
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';

        // Only the levels BELOW the viewer's own role are ever read —
        // Admin gets all 3 params, GL gets 2, TL/Introducer get 1.
        $glId = $agent->role === 'ADMIN' ? trim((string) $request->get('gl_id', '')) : '';
        $tlId = in_array($agent->role, ['ADMIN', 'GROUP_LEADER'], true) ? trim((string) $request->get('tl_id', '')) : '';
        $introId = trim((string) $request->get('introducer_id', ''));

        // Vendor + Product are their own filter dimension, per Chris:
        // "it is NOT only insurance renewal" — every role gets these.
        $vendorId = trim((string) $request->get('vendor_id', ''));
        $productId = trim((string) $request->get('product_id', ''));

        // NEW 23 Jul 2026 (v8) — per Chris: a direct "View" action on
        // the Contributor Ranking table's new Pipeline column, jumping
        // straight to that one child's whole-subtree customer list
        // without drilling through every intermediate level first.
        // Independent of the gl_id/tl_id/introducer_id cascade above —
        // this only decides what the Detail tab shows.
        $pipelineId = trim((string) $request->get('pipeline_id', ''));

        // NEW 22 Jul 2026 (v4) — per Chris: nothing gets queried at all
        // — not even the summary cards/chart — until the viewer has
        // actually clicked Search. See class comment above.
        $searched = $request->has('search');

        $baseQuery = function () use ($scope, $year, $vendorId, $productId) {
            $q = DB::table('insurance_renewal_schedules as r')
                ->join('sales_transactions as st', 'r.policy_id', '=', 'st.policy_id')
                ->join('customers as c', 'st.customer_id', '=', 'c.customer_id')
                ->join('products as p', 'st.product_id', '=', 'p.product_id')
                ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
                ->where('st.is_deleted', false)
                ->whereIn('r.status', ['UPCOMING', 'DUE', 'OVERDUE'])
                ->whereYear('r.coverage_end', $year);
            if ($vendorId !== '') {
                $q->where('st.vendor_id', $vendorId);
            }
            if ($productId !== '') {
                $q->where('st.product_id', $productId);
            }
            $scope->applyToTransactions($q, 'st');
            return $q;
        };

        // ---- Filter row data ----
        // NEW 23 Jul 2026 (v6) — per Chris: "10000 Group Leader 600000
        // Team Leader and 1 million introducer... you going to display
        // 10000 group records one by one to pick?" — the old code here
        // unconditionally loaded EVERY Group Leader in the company on
        // every single page load just to fill a <select>, before the
        // viewer had even clicked Search. That's gone: the GL/TL/
        // Introducer pickers are now typeahead search boxes (see
        // agentTypeahead() above and the view), so the only thing
        // needed here is a cheap single-row lookup for whichever one is
        // already selected, to show its name in the box and build the
        // scope label below.
        $vendorOptions = DB::table('vendors')->where('is_active', true)->orderBy('vendor_name')->get(['vendor_id', 'vendor_name', 'vendor_code']);
        $productOptions = $vendorId !== ''
            ? DB::table('products')->where('vendor_id', $vendorId)->where('is_active', true)->orderBy('product_name')->get(['product_id', 'product_name'])
            : DB::table('products')->where('is_active', true)->orderBy('product_name')->get(['product_id', 'product_name']);
        $selectedVendor = $vendorId !== '' ? DB::table('vendors')->where('vendor_id', $vendorId)->first() : null;
        $selectedProduct = $productId !== '' ? DB::table('products')->where('product_id', $productId)->first() : null;

        $selectedGL = $glId !== '' ? DB::table('agents')->where('agent_id', $glId)->first() : null;
        $selectedTL = $tlId !== '' ? DB::table('agents')->where('agent_id', $tlId)->first() : null;
        $selectedIntro = $introId !== '' ? DB::table('agents')->where('agent_id', $introId)->first() : null;

        // ---- Everything below this line touches actual transaction
        // data and is skipped entirely unless $searched. ----

        $monthly = [];
        $totalPremium = 0.0;
        $totalEarning = 0.0;
        $totalPolicies = 0;
        $isLeaf = true;
        $rankedChildren = collect();
        $detailPage = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10, 1, ['path' => $request->url()]);
        $pipelineAgent = null;
        $viewMode = 'ranking';

        if ($searched) {
            // Narrow further to whichever specific node was picked via
            // the dropdowns — most-specific level wins.
            $narrowedAgentIds = null;
            if ($introId !== '') {
                $narrowedAgentIds = $this->nodeAndDescendantIds($introId);
            } elseif ($tlId !== '') {
                $narrowedAgentIds = $this->nodeAndDescendantIds($tlId);
            } elseif ($glId !== '') {
                $gl = DB::table('agents')->where('agent_id', $glId)->first();
                $narrowedAgentIds = $gl
                    ? DB::table('agents')->where('group_id', $gl->group_id)->where('is_deleted', false)->pluck('agent_id')->toArray()
                    : [];
            }
            $cascadeApplied = $narrowedAgentIds !== null;

            // ---- Summary (cards + 12-month chart) — computed once
            // Search has been clicked. Reflects the cascade narrowing
            // above, but NOT the month filter (the whole point of this
            // section is the full year shape).
            $summaryQuery = $baseQuery();
            if ($cascadeApplied) {
                $summaryQuery->whereIn('st.agent_id', $narrowedAgentIds);
            }
            $summaryRows = $summaryQuery->select('r.coverage_end', 'st.policy_id', 'st.premium_amount')->get();

            $policyIds = $summaryRows->pluck('policy_id')->all();
            $commissionByPolicy = collect();
            if (!empty($policyIds)) {
                $commQuery = DB::table('commission_transactions')->whereIn('policy_id', $policyIds);
                if ($cascadeApplied) {
                    $commQuery->whereIn('agent_id', $narrowedAgentIds);
                } elseif (!$scope->isAdmin()) {
                    $commQuery->whereIn('agent_id', $scope->getAgentIds());
                }
                $commissionByPolicy = $commQuery->select('policy_id', DB::raw('SUM(commission_amount) as total'))
                    ->groupBy('policy_id')->pluck('total', 'policy_id');
            }

            $summaryRows = $summaryRows->map(function ($row) use ($commissionByPolicy) {
                $row->earning_income = (float) ($commissionByPolicy[$row->policy_id] ?? 0);
                $row->month = (int) \Illuminate\Support\Carbon::parse($row->coverage_end)->format('n');
                return $row;
            });

            for ($m = 1; $m <= 12; $m++) {
                $monthRows = $summaryRows->where('month', $m);
                $monthly[$m] = [
                    'label'          => \Illuminate\Support\Carbon::create($year, $m, 1)->format('M'),
                    'count'          => $monthRows->count(),
                    'premium'        => $monthRows->sum('premium_amount'),
                    'earning_income' => $monthRows->sum('earning_income'),
                ];
            }

            $totalPremium  = $summaryRows->sum('premium_amount');
            $totalEarning  = $summaryRows->sum('earning_income');
            $totalPolicies = $summaryRows->count();

            // ---- Determine the next drill-down level's children (for
            // the ranking table) or confirm we've reached a single
            // individual (leaf) whose own transactions should be
            // listed instead.
            // NEW 23 Jul 2026 (v6) — fetched fresh here, scoped to one
            // specific parent (never the whole company), via the same
            // childAgents()/topLevelGroups() helpers used nowhere else
            // — replaces the old reliance on the pre-loaded $glOptions/
            // $tlOptions/$introOptions collections, which no longer
            // exist (see index() filter-row comment above).
            //
            // FIXED 23 Jul 2026 (v13) — per Chris's real data (Wendy Koh,
            // a Team Leader, reports directly to Tan Boon Hwa, another
            // Team Leader): which query param a drilled-into child sets
            // can no longer be assumed from the CURRENT level alone —
            // it depends on THAT CHILD's own role, which is now decided
            // per-row below ($childParam is gone; see $child->role).
            $children = collect();

            if ($introId !== '') {
                $children = $this->childAgents($introId);
            } elseif ($tlId !== '') {
                $children = $this->childAgents($tlId);
            } elseif ($agent->role === 'ADMIN' && $glId !== '') {
                $children = $this->childAgents($glId);
            } elseif ($agent->role === 'ADMIN') {
                $children = $this->topLevelGroups();
            } else {
                $children = $this->childAgents($agent->agent_id);
            }

            $isLeaf = $children->isEmpty();

            if (!$isLeaf) {
                // ---- CONTRIBUTOR RANKING — one row per child, each
                // with their whole subtree's premium/earning income and
                // pipeline count, sortable by either amount column.
                // Small, bounded list (a team's TL or Introducer count
                // is never huge).
                $rankedChildren = $children->map(function ($child) use ($baseQuery, $month) {
                    $childIds = $this->nodeAndDescendantIds($child->agent_id);
                    $q = $baseQuery()->whereIn('st.agent_id', $childIds);
                    if ($month !== null) {
                        $q->whereMonth('r.coverage_end', $month);
                    }
                    $rows = $q->select('st.policy_id', 'st.premium_amount')->get();
                    $policyIds = $rows->pluck('policy_id')->all();
                    $earning = 0;
                    if (!empty($policyIds)) {
                        $earning = DB::table('commission_transactions')
                            ->whereIn('policy_id', $policyIds)
                            ->whereIn('agent_id', $childIds)
                            ->sum('commission_amount');
                    }
                    $child->policies_due = $rows->count();
                    $child->premium = (float) $rows->sum('premium_amount');
                    $child->earning_income = (float) $earning;
                    $child->is_self = false;
                    // FIXED 23 Jul 2026 (v13) — which query param
                    // clicking this row's name should set is now
                    // decided per-row from the child's OWN role, not
                    // assumed from the level being viewed — a Team
                    // Leader can report directly to another Team
                    // Leader, so a "Team Leader level" can still
                    // contain Introducer rows and vice versa.
                    $child->drill_param = $child->role === 'TEAM_LEADER' ? 'tl_id' : ($child->role === 'GROUP_LEADER' ? 'gl_id' : 'introducer_id');
                    return $child;
                });

                // NEW 23 Jul 2026 (v13) — per Chris: "click July is 4 i
                // drill down is 3" — the ranking table only ever listed
                // this node's CHILDREN, never the node's own directly-
                // attributed transactions (e.g. a Team Leader can have
                // her own personal sales, not placed under any of her
                // Introducers). Those were counted in the month/summary
                // totals above but had no row to appear in here, so the
                // children's totals never added up to the card/month
                // total — reading as a bug even though nothing was
                // actually miscounted. Adding an explicit "(Personal)"
                // row for whoever owns this ranking level closes that
                // gap: totals now always reconcile.
                $rankingOwnerId = null;
                $rankingOwnerName = null;
                if ($introId !== '') {
                    $o = DB::table('agents')->where('agent_id', $introId)->first();
                } elseif ($tlId !== '') {
                    $o = DB::table('agents')->where('agent_id', $tlId)->first();
                } elseif ($glId !== '' && $agent->role === 'ADMIN') {
                    $o = DB::table('agents')->where('agent_id', $glId)->first();
                } elseif ($agent->role !== 'ADMIN') {
                    $o = $agent;
                } else {
                    $o = null; // Admin, nothing picked yet — company-wide, no single owner
                }
                if ($o) {
                    $rankingOwnerId = $o->agent_id;
                    $rankingOwnerName = $o->full_name;
                }

                if ($rankingOwnerId) {
                    $ownQuery = $baseQuery()->where('st.agent_id', $rankingOwnerId);
                    if ($month !== null) {
                        $ownQuery->whereMonth('r.coverage_end', $month);
                    }
                    $ownRows = $ownQuery->select('st.policy_id', 'st.premium_amount')->get();
                    if ($ownRows->isNotEmpty()) {
                        $ownPolicyIds = $ownRows->pluck('policy_id')->all();
                        $ownEarning = DB::table('commission_transactions')
                            ->whereIn('policy_id', $ownPolicyIds)
                            ->where('agent_id', $rankingOwnerId)
                            ->sum('commission_amount');
                        $rankedChildren->push((object) [
                            'agent_id'       => $rankingOwnerId,
                            'full_name'      => $rankingOwnerName,
                            'policies_due'   => $ownRows->count(),
                            'premium'        => (float) $ownRows->sum('premium_amount'),
                            'earning_income' => (float) $ownEarning,
                            'is_self'        => true,
                        ]);
                    }
                }

                $rankedChildren = $sort === 'earning'
                    ? $rankedChildren->sortBy('earning_income', SORT_REGULAR, $dir === 'desc')
                    : $rankedChildren->sortBy('premium', SORT_REGULAR, $dir === 'desc');
                $rankedChildren = $rankedChildren->values();

                // NEW 23 Jul 2026 (v11) — per Chris: "no scroll show in
                // one screen" — this table had no Prev/Next at all, so
                // any team with more contributors than fit on screen
                // just silently scrolled (the exact pattern already
                // fixed elsewhere — Organization Rewards Group Introducer list,
                // Team Leaders list — by paginating instead). The full
                // ranked+sorted collection is already built above (it
                // has to be, since sorting by a computed amount needs
                // every row first); this just slices out one page of
                // it for display, same 8/page as the rest of the site.
                $rfPage = max(1, (int) $request->get('rfPage', 1));
                $rfPerPage = 10;
                $rankedChildren = new \Illuminate\Pagination\LengthAwarePaginator(
                    $rankedChildren->slice(($rfPage - 1) * $rfPerPage, $rfPerPage)->values(),
                    $rankedChildren->count(),
                    $rfPerPage,
                    $rfPage,
                    ['path' => $request->url(), 'query' => $request->except('rfPage'), 'pageName' => 'rfPage']
                );
            } else {
                // ---- LEAF — a single individual with nobody further
                // under them. Show their own transactions, paginated.
                // Always a small, naturally-bounded personal book.
                $leafIds = $cascadeApplied ? $narrowedAgentIds : $scope->getAgentIds();
                $detailPage = $this->buildDetailPage($baseQuery, $leafIds, $month, $year, $glId, $tlId, $introId, $vendorId, $productId);
            }

            // NEW 23 Jul 2026 (v8) — per Chris: "add new column at the
            // right show no of pipeline, view to see who which
            // customer/opportunities type" — the Pipeline column's
            // View action overrides whatever the ranking table would
            // otherwise show, jumping straight to the picked child's
            // WHOLE subtree customer list (works whether that child is
            // a Team Leader with many Introducers below, or a single
            // Introducer) — no need to drill through every intermediate
            // level first.
            if (!$isLeaf && $pipelineId !== '') {
                $pipelineAgent = DB::table('agents')->where('agent_id', $pipelineId)->first();
                if ($pipelineAgent) {
                    // NEW 23 Jul 2026 (v13) — the "(Personal)" row's own
                    // View link sets self_pipeline=1: it must show ONLY
                    // that agent's own transactions, not their whole
                    // subtree (their subtree is already broken out as
                    // separate rows above — including it again here
                    // would double-count).
                    $selfPipeline = $request->boolean('self_pipeline');
                    $pipelineIds = $selfPipeline ? [$pipelineId] : $this->nodeAndDescendantIds($pipelineId);
                    $extraAppends = ['pipeline_id' => $pipelineId];
                    if ($selfPipeline) {
                        $extraAppends['self_pipeline'] = 1;
                    }
                    $detailPage = $this->buildDetailPage($baseQuery, $pipelineIds, $month, $year, $glId, $tlId, $introId, $vendorId, $productId, $extraAppends);
                    $viewMode = 'pipeline';
                }
            } elseif (!$isLeaf) {
                $viewMode = 'ranking';
            } else {
                $viewMode = 'leaf';
            }
        }

        // Human-readable label for whatever scope is currently in
        // effect — most-specific selection wins, else a role-default.
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
        if ($selectedVendor) {
            $scopeLabel .= ' · Vendor: ' . $selectedVendor->vendor_name;
        }
        if ($selectedProduct) {
            $scopeLabel .= ' · Product: ' . $selectedProduct->product_name;
        }

        return view('renewal-forecast.index', compact(
            'searched', 'monthly', 'year', 'month', 'sort', 'dir', 'totalPremium', 'totalEarning', 'totalPolicies', 'rolePrefix',
            'glId', 'tlId', 'introId', 'vendorId', 'productId',
            'vendorOptions', 'productOptions', 'selectedVendor', 'selectedProduct',
            'selectedGL', 'selectedTL', 'selectedIntro', 'scopeLabel', 'isLeaf', 'rankedChildren',
            'detailPage', 'agent', 'pipelineAgent', 'viewMode'
        ));
    }
}
