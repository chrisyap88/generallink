<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CbeAccountingService;
use App\Services\CbeDistrictService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// NEW 25 Aug 2026 — per Chris: "the existing KPI dashboard for DSG and
// ORG remain unchanged... create another menu link just for CBE KPI,
// it work like president, finance and membership admin in for HQ,
// State, Branch and Temple, the different is in GeneralLink Admin
// Director, Finance and Sales admin, it allow to select drop down for
// ALL CBE or selected CBE group like Tao, Rotary Club etc."
//
// This is completely separate from CbeExecDashboardController — that
// one is for a CBE organization's OWN officers (Director/Finance/
// Membership at a specific node, via cbe_node_officers), scoped to
// just their own node + descendants, gated by that org's subscription
// tier. This one is for GeneralLink's 3 platform Admin accounts
// (agents.department), who oversee every CBE community on the whole
// platform — never gated by tier (Admin always sees everything), and
// can pick ANY community from a dropdown, or "ALL CBE" combined.
class AdminCbeKpiController extends Controller
{
    public function index()
    {
        $agent = auth('agent')->user();

        $nodeId = request('node');
        $districtName = request('district');
        $scopeParentId = request('parent');
        $scopeAll = request('scope') === 'all';

        // FIXED 26 Aug 2026 — per Chris: "dont default to search before
        // the selection criteria completed... koi dashboard should not
        // display any data." A bare, param-less visit (e.g. clicking the
        // sidebar link) now shows NO KPI numbers at all — just the
        // filter row and a prompt. Data only renders once a selection
        // has been deliberately completed via Go: a specific node, a
        // district, or an explicit "All CBE Groups" (?scope=all).
        $hasSelection = $nodeId || $districtName || $scopeAll;

        if (! $hasSelection) {
            $shared = $this->emptyShared();

            return match ($agent->department) {
                'FINANCE' => view('admin.cbe-kpi.finance', $shared),
                'SALES' => view('admin.cbe-kpi.membership', $shared),
                default => view('admin.cbe-kpi.director', $shared),
            };
        }

        // NEW 25 Aug 2026, 2nd pass — per Chris: "selection criteria with
        // typeahead search for HQ, State, Branch, Temple in ONE row...
        // only one GO." Replaced the earlier <select> + separate
        // drill-down row with a single cascading typeahead (same as
        // admin/dashboard's CBE filter bar) that picks ONE node at
        // whatever level — the Community itself (its HQ root), or any
        // State/Branch/Temple beneath it. Everything else is derived
        // from that one node_id, so there's exactly one "Go".
        // NEW 27 Aug 2026 — extracted into resolveScope() so the new
        // Bills and Approval Messages drill-down screens (Box 6) can
        // resolve the exact same nodeIds/scopeLabel/groupTier from the
        // same node/district/parent/scope query params, without
        // duplicating this whole block.
        $scope = $this->resolveScope($nodeId, $districtName, $scopeParentId);
        $node = $scope['node'];
        $profileParentNode = $scope['profileParentNode'];
        $drilledNodeId = $scope['drilledNodeId'];
        $nodeIds = $scope['nodeIds'];
        $selectedGroupId = $scope['selectedGroupId'];
        $scopeLabel = $scope['scopeLabel'];
        $groupTier = $scope['groupTier'];

        // NEW 12 Sep 2026 — per Chris: "there is a possibility to link to
        // parent id you have to a flag control linked to parent (show the
        // parent name) because sometime the Temple A management decision
        // does not want to link and stay by itself standalone." Powers
        // the Profile tab's parent-link line/toggle: the actual level
        // name of whatever this node's parent is (never a hardcoded
        // "State" — a parent can be any level in any CBE community), and
        // whether this node is its community's own top level (where a
        // parent/standalone choice doesn't apply at all).
        $profileParentLevelName = null;
        $profileIsTopLevel = false;
        if ($node) {
            $levelOrders = DB::table('cbe_hierarchy_levels')->where('group_label_id', $node->group_label_id)->pluck('level_order', 'level_id');
            $profileIsTopLevel = $levelOrders->isNotEmpty() && ($levelOrders[$node->level_id] ?? null) === $levelOrders->min();
            if ($profileParentNode) {
                $profileParentLevelName = DB::table('cbe_hierarchy_levels')->where('level_id', $profileParentNode->level_id)->value('level_name');
            }
        }

        $groupCountAll = DB::table('group_labels')->where('group_type', 'CBE')->count();

        // NEW 27 Aug 2026 — per Chris: "did you notice there is date at
        // the search bar? that determine the status on financial
        // standing period as MTD, YTD, LYTD selection period." A
        // Month/Year Prev/Next navigator next to the scope line lets the
        // Chairman look at ANY past month's closing figures, not just
        // today. Picking a past month closes it (uses that month's last
        // day as the "as of" date, a completed month); the current real
        // month always tracks today's actual date so MTD keeps moving
        // day by day. Every "this month/previous month/YTD/LYTD" figure
        // on all 7 boxes is driven off this single reference point.
        $periodParam = request('period');
        $periodMonth = ($periodParam && preg_match('/^\d{4}-\d{2}$/', $periodParam))
            ? Carbon::createFromFormat('Y-m-d', $periodParam.'-01')->startOfMonth()
            : now()->startOfMonth();
        $isCurrentPeriod = $periodMonth->isSameMonth(now());
        $asOfDate = $isCurrentPeriod ? now()->toDateString() : $periodMonth->copy()->endOfMonth()->toDateString();
        $periodLabel = $periodMonth->translatedFormat('F Y');
        $periodPrevParam = $periodMonth->copy()->subMonthNoOverflow()->format('Y-m');
        $periodNextParam = $isCurrentPeriod ? null : $periodMonth->copy()->addMonthNoOverflow()->format('Y-m');

        $from = $periodMonth->copy()->startOfYear()->toDateString();
        $to = $asOfDate;
        $monthStart = $periodMonth->copy()->startOfMonth()->toDateString();

        // NEW 25 Aug 2026 — per Chris: "CBE for Tao IS 591 la" — the
        // previous single "Branches/Chapters" figure silently summed
        // every non-root level together (State + Temple = 604), which
        // never matched the real Temple count he actually cares about.
        // This instead reads each ACTUAL level name + its own real count
        // straight from cbe_hierarchy_levels — never a hardcoded "Temple"
        // /"Branch" label, since level names differ per CBE community
        // (Tao's HQ/State/Temple vs. Rotary's single "Club" level, etc.).
        // Sorted by count so the deepest/most numerous level (usually
        // the real operating unit — a Temple, a Club) surfaces first.
        //
        // FIXED 26 Aug 2026 — per Chris: "why hardcode ON TAO" — under
        // "All CBE Groups (Combined)", each community's levels are
        // DIFFERENT rows here (Tao's own "HQ"/"State"/"Temple" vs.
        // Rotary's own single "Club"), so combining 2+ communities can
        // easily produce more than 3 distinct level names. The old
        // limit(3) silently dropped whichever row lost a tie (here,
        // Rotary's "Club": 1 lost to Tao's "HQ": 1) — making the
        // combined view look like it was scoped to Tao alone, even
        // though the underlying query genuinely included every group.
        // Raised the cap so every distinct level name actually in scope
        // gets shown, with level_name as a stable tiebreaker.
        $levelBreakdown = DB::table('cbe_hierarchy_nodes as n')
            ->join('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
            ->whereIn('n.node_id', $nodeIds)
            ->select('l.level_name', DB::raw('count(*) as cnt'))
            ->groupBy('l.level_name')
            ->orderByDesc('cnt')
            ->orderBy('l.level_name')
            ->limit(8)
            ->get();
        $topLevel = $levelBreakdown->first();
        $childBranchCount = $topLevel->cnt ?? 0;
        $childBranchLabel = $topLevel->level_name ?? __('cbe_exec.row_branches');

        // NEW 27 Sep 2026 — per Chris: Box 4 lists every tier — HQ, State,
        // Branch, City, Entity — then Total Affiliate Entity.
        $tierCounts = $this->tierCounts($node, $nodeIds, $scopeParentId);
        if ($tierCounts['entity_level']) {
            $childBranchLabel = $tierCounts['entity_level'];
        }

        // CHANGED 28 Sep 2026 — per Chris (§96.21): a member = an active link
        // with an ACTIVE Member / Follower / Believer line in the Affiliation
        // Register (a Donor- or Sponsor-only line is not a member).
        $totalMembers = DB::table('cbe_group_memberships as gm')->whereIn('gm.cbe_node_id', $nodeIds)->where('gm.status', 'ACTIVE')
            ->when(Schema::hasColumn('cbe_member_role_tags', 'status'), fn ($q) => $q->whereExists(fn ($x) => $x->select(DB::raw(1))->from('cbe_member_role_tags as rt')
                ->whereColumn('rt.membership_id', 'gm.membership_id')->where('rt.status', 'ACTIVE')->whereIn('rt.tag', ['MEMBER', 'FOLLOWER', 'BELIEVER'])))
            ->count();
        $newMembersThisMonth = DB::table('cbe_group_memberships')->whereIn('cbe_node_id', $nodeIds)
            ->where('status', 'ACTIVE')->where('joined_at', '>=', $monthStart)->count();
        $inactiveMembers = DB::table('cbe_group_memberships')->whereIn('cbe_node_id', $nodeIds)->where('status', 'INACTIVE')->count();
        $branchesReporting = DB::table('cbe_group_memberships')->whereIn('cbe_node_id', $nodeIds)
            ->where('status', 'ACTIVE')->distinct('cbe_node_id')->count('cbe_node_id');
        $upcomingEvents = DB::table('cbe_events')->whereIn('cbe_node_id', $nodeIds)
            ->where('status', '!=', 'CLOSED')->where('event_start_date', '>=', $to)->count();
        $completedEvents = DB::table('cbe_events')->whereIn('cbe_node_id', $nodeIds)->where('status', 'CLOSED')->count();
        $meetingsThisYear = DB::table('cbe_meeting_minutes')->whereIn('cbe_node_id', $nodeIds)
            ->whereYear('meeting_date', $periodMonth->year)->count();

        $financial = CbeAccountingService::financialSummaryForNodeIds($nodeIds, $from, $to);
        $financialMonth = CbeAccountingService::financialSummaryForNodeIds($nodeIds, $monthStart, $to);

        $apBills = DB::table('cbe_purchase_bills')->whereIn('cbe_node_id', $nodeIds)
            ->whereIn('status', ['UNPAID', 'PARTIALLY_PAID'])->get();
        $billsDueSoon = $apBills->filter(fn ($b) => $b->due_date && Carbon::parse($b->due_date)->lte(Carbon::parse($asOfDate)->addDays(7)) && Carbon::parse($b->due_date)->gte($asOfDate))->count();
        $billsOverdue = $apBills->filter(fn ($b) => $b->due_date && Carbon::parse($b->due_date)->lt($asOfDate))->count();

        $arOutstanding = (float) DB::table('cbe_contributions as c')
            ->join('cbe_events as e', 'e.event_id', '=', 'c.event_id')
            ->whereIn('e.cbe_node_id', $nodeIds)
            ->where('c.status', '!=', 'CANCELLED')
            ->selectRaw('SUM(pledged_amount - received_amount) as v')->value('v') ?: 0;

        // NEW 26 Aug 2026, 19th pass — per Chris: "you have to suggest
        // any feature that is good to know" — Top Donors and
        // Outstanding Pledges, scoped to THIS single temple's own
        // events only (not the whole district/group), since that's
        // the only scope where "who gave the most at OUR temple" is a
        // meaningful question. Both stay empty/zero for a district or
        // All-CBE view.
        $topDonors = collect();
        $outstandingPledges = 0;
        if ($node) {
            $topDonors = DB::table('cbe_contributions as c')
                ->join('cbe_events as e', 'e.event_id', '=', 'c.event_id')
                ->join('cbe_donors as d', 'd.donor_id', '=', 'c.donor_id')
                ->where('e.cbe_node_id', $node->node_id)
                ->where('c.status', '!=', 'CANCELLED')
                ->groupBy('c.donor_id', 'd.donor_name')
                ->select('c.donor_id', 'd.donor_name', DB::raw('SUM(c.received_amount) as total_received'), DB::raw('COUNT(*) as cnt'))
                ->orderByDesc('total_received')
                ->limit(5)
                ->get();

            $outstandingPledges = DB::table('cbe_contributions as c')
                ->join('cbe_events as e', 'e.event_id', '=', 'c.event_id')
                ->where('e.cbe_node_id', $node->node_id)
                ->where('c.status', 'PLEDGED')
                ->count();
        }

        // NEW 27 Aug 2026 — per Chris's 7-box CBE KPI Director dashboard
        // redesign (master spec Sections 53-57). Box 1 Events Overview:
        // This month / Next 3 months / Previous month counts, driven by
        // event_start_date, independent of the event's status.
        $thisMonthStart = $periodMonth->copy()->startOfMonth()->toDateString();
        $thisMonthEnd = $periodMonth->copy()->endOfMonth()->toDateString();
        $prevMonthStart = $periodMonth->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $prevMonthEnd = $periodMonth->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
        $next3MonthsEnd = $periodMonth->copy()->addMonths(3)->toDateString();
        $thisMonthEvents = DB::table('cbe_events')->whereIn('cbe_node_id', $nodeIds)
            ->whereBetween('event_start_date', [$thisMonthStart, $thisMonthEnd])->count();
        $next3MonthsEventsCount = DB::table('cbe_events')->whereIn('cbe_node_id', $nodeIds)
            ->whereBetween('event_start_date', [$to, $next3MonthsEnd])->count();
        $previousMonthEvents = DB::table('cbe_events')->whereIn('cbe_node_id', $nodeIds)
            ->whereBetween('event_start_date', [$prevMonthStart, $prevMonthEnd])->count();

        // NEW 27 Aug 2026 — Box 3 Financial Overview's Bank Balance row,
        // renamed from Cash Balance per Chris: "bank balance payment
        // description change to Bank Balance ya... a CBE may have more
        // than one bank account ya, so i need all bank statement... if
        // the selection date is August then you should [see] the July
        // statement and august statement." One row per active bank
        // account; each account's figure uses the SELECTED period
        // month's statement if uploaded, else falls back to the
        // previous month's — so the box always shows the latest
        // available closing balance instead of going blank the moment
        // this month's statement hasn't been uploaded yet. Zero-safe:
        // no bank accounts / no statements yet = RM 0.00, not a crash.
        $bankAccounts = Schema::hasTable('cbe_bank_accounts')
            ? DB::table('cbe_bank_accounts')->whereIn('cbe_node_id', $nodeIds)->where('is_active', true)->get()
            : collect();
        $bankAccountCount = $bankAccounts->count();
        $bankBalanceTotal = 0.0;
        if ($bankAccountCount && Schema::hasTable('cbe_bank_statements') && Schema::hasColumn('cbe_bank_statements', 'closing_balance')) {
            $prevPeriodMonth = $periodMonth->copy()->subMonthNoOverflow();
            foreach ($bankAccounts as $acct) {
                $stmt = DB::table('cbe_bank_statements')
                    ->where('bank_account_id', $acct->bank_account_id)
                    ->where(function ($q) use ($periodMonth, $prevPeriodMonth) {
                        $q->where(['statement_year' => $periodMonth->year, 'statement_month' => $periodMonth->month])
                          ->orWhere(['statement_year' => $prevPeriodMonth->year, 'statement_month' => $prevPeriodMonth->month]);
                    })
                    ->orderByDesc('statement_year')->orderByDesc('statement_month')
                    ->first();
                $bankBalanceTotal += (float) ($stmt->closing_balance ?? 0);
            }
        }

        // Box 2 Secretarial Overview: real zero-safe counts against the
        // new Committee/Correspondence tables (master spec Section 53) —
        // per Chris: "ALL boxes must build the logic even though there
        // is no records... show amount 0." Committee count is CURRENT
        // TERM only (today's date within term_start_date/term_end_date).
        $committeeCurrentTermCount = Schema::hasTable('cbe_committee_positions')
            ? DB::table('cbe_committee_positions')->whereIn('cbe_node_id', $nodeIds)
                ->where('term_start_date', '<=', $to)->where('term_end_date', '>=', $to)->count()
            : 0;
        $correspondenceTotalCount = Schema::hasTable('cbe_correspondence')
            ? DB::table('cbe_correspondence')->whereIn('cbe_node_id', $nodeIds)->count()
            : 0;
        $appointmentsThisYear = DB::table('cbe_appointments')->whereIn('cbe_node_id', $nodeIds)
            ->whereYear('appointment_date', $periodMonth->year)->count();

        // Box 4 Current Overview: Total Donors + the new multi-tag
        // Consultant/Volunteer count (replaces the old single-value
        // member_type — see cbe_member_role_tags, master spec Section 54).
        // Counts a donor if EITHER their home temple (cbe_donors.cbe_node_id)
        // OR any extra sponsorship link (cbe_donor_sponsorships) falls in
        // scope — a donor sponsoring multiple temples is still one person.
        $homeDonorIds = DB::table('cbe_donors')->whereIn('cbe_node_id', $nodeIds)->pluck('donor_id');
        $sponsoredDonorIds = DB::table('cbe_donor_sponsorships')->whereIn('cbe_node_id', $nodeIds)->pluck('donor_id');
        $totalDonors = $homeDonorIds->merge($sponsoredDonorIds)->unique()->count();
        $consultantVolunteerCount = Schema::hasTable('cbe_member_role_tags')
            ? DB::table('cbe_member_role_tags as t')
                ->join('cbe_group_memberships as m', 'm.membership_id', '=', 't.membership_id')
                ->whereIn('m.cbe_node_id', $nodeIds)
                ->whereIn('t.tag', ['CONSULTANT', 'VOLUNTEER'])
                ->when(Schema::hasColumn('cbe_member_role_tags', 'status'), fn ($q) => $q->where('t.status', 'ACTIVE'))
                ->distinct('t.membership_id')
                ->count('t.membership_id')
            : 0;

        // Box 5 Current Financial Snapshot: Outstanding Collection = AR
        // pledges (cbe_contributions, already computed as $arOutstanding
        // above) PLUS unpaid event participant purchases (new amount_due
        // field, master spec Section 54). Outstanding Payables reuses
        // $financial['ap_outstanding'] directly — staff reimbursement
        // claims are now just cbe_purchase_bills rows with
        // payee_type=STAFF_CLAIM, so no separate query is needed.
        $eventParticipantOutstanding = Schema::hasColumn('cbe_event_participants', 'amount_due')
            ? (float) DB::table('cbe_event_participants as p')
                ->join('cbe_events as e', 'e.event_id', '=', 'p.event_id')
                ->whereIn('e.cbe_node_id', $nodeIds)
                ->selectRaw('SUM(p.amount_due - p.amount_paid) as v')->value('v') ?: 0
            : 0;
        $outstandingCollectionTotal = $arOutstanding + $eventParticipantOutstanding;
        $outstandingPayablesTotal = (float) $financial['ap_outstanding'];
        $availableCashBalance = (float) $financial['cash_balance'] + $outstandingCollectionTotal - $outstandingPayablesTotal;

        // Box 6 Pending Tasks, Notifications & Alerts: internal approval
        // messages awaiting a Chairman/officer's OTP approval (master
        // spec Section 55), outstanding support tickets now scoped to
        // these CBE nodes, and outstanding (not-yet-completed) survey
        // invitations for meetings/events tied to these nodes.
        $pendingApprovalMessages = Schema::hasTable('cbe_internal_approval_messages')
            ? DB::table('cbe_internal_approval_messages')->whereIn('cbe_node_id', $nodeIds)->where('status', 'PENDING')->count()
            : 0;
        $outstandingSupportTickets = Schema::hasColumn('customer_support_tickets', 'cbe_node_id')
            ? DB::table('customer_support_tickets')->whereIn('cbe_node_id', $nodeIds)->whereIn('status', ['OPEN', 'IN_PROGRESS'])->count()
            : 0;
        $outstandingSurveyResponses = Schema::hasColumn('cbe_meeting_minutes', 'survey_id')
            ? DB::table('cbe_meeting_minutes as mm')
                ->join('survey_invitations as si', 'si.survey_id', '=', 'mm.survey_id')
                ->whereIn('mm.cbe_node_id', $nodeIds)
                ->whereIn('si.status', ['PENDING', 'SENT', 'OPENED'])
                ->count()
            : 0;

        // Box 7 Executive Analytics Dashboard: Last MTD / MTD / YTD / LYTD
        // columns for Income, Expense, Net Balance (reusing
        // CbeAccountingService::financialSummaryForNodeIds, same pattern
        // as $financial/$financialMonth above), plus Assets/Liabilities
        // at the matching 4 snapshot dates (balanceSheetSummaryForNodeIds,
        // master spec Section 57).
        $lastMonthStart = $prevMonthStart;
        $lastMonthEnd = $prevMonthEnd;
        $lastYearStart = $periodMonth->copy()->subYear()->startOfYear()->toDateString();
        $lastYearSameDay = Carbon::parse($asOfDate)->subYear()->toDateString();
        $financialLastMTD = CbeAccountingService::financialSummaryForNodeIds($nodeIds, $lastMonthStart, $lastMonthEnd);
        $financialLYTD = CbeAccountingService::financialSummaryForNodeIds($nodeIds, $lastYearStart, $lastYearSameDay);
        $balanceSheetLastMTD = CbeAccountingService::balanceSheetSummaryForNodeIds($nodeIds, $lastMonthEnd);
        $balanceSheetMTD = CbeAccountingService::balanceSheetSummaryForNodeIds($nodeIds, $to);
        $balanceSheetYTD = CbeAccountingService::balanceSheetSummaryForNodeIds($nodeIds, $to);
        $balanceSheetLYTD = CbeAccountingService::balanceSheetSummaryForNodeIds($nodeIds, $lastYearSameDay);

        $executiveAnalytics = [
            ['label_key' => 'row_total_income_collection', 'last_mtd' => $financialLastMTD['total_income'], 'mtd' => $financialMonth['total_income'], 'ytd' => $financial['total_income'], 'lytd' => $financialLYTD['total_income']],
            ['label_key' => 'row_total_payment_expenses', 'last_mtd' => $financialLastMTD['total_expense'], 'mtd' => $financialMonth['total_expense'], 'ytd' => $financial['total_expense'], 'lytd' => $financialLYTD['total_expense']],
            ['label_key' => 'row_income_expenditure_balance', 'last_mtd' => $financialLastMTD['net_surplus'], 'mtd' => $financialMonth['net_surplus'], 'ytd' => $financial['net_surplus'], 'lytd' => $financialLYTD['net_surplus']],
            ['label_key' => 'row_total_assets', 'last_mtd' => $balanceSheetLastMTD['total_assets'], 'mtd' => $balanceSheetMTD['total_assets'], 'ytd' => $balanceSheetYTD['total_assets'], 'lytd' => $balanceSheetLYTD['total_assets']],
            ['label_key' => 'row_total_liabilities', 'last_mtd' => $balanceSheetLastMTD['total_liabilities'], 'mtd' => $balanceSheetMTD['total_liabilities'], 'ytd' => $balanceSheetYTD['total_liabilities'], 'lytd' => $balanceSheetLYTD['total_liabilities']],
        ];

        $shared = [
            'hasSelection' => true,
            // NEW 26 Aug 2026 — per Chris: "from temple drill down you
            // should display view details which the profile meaning
            // contact person, address, contact number... 2 tap folder
            // on top, one is profile, one is kpi." Tabs only make sense
            // when a single real node is in view (not a district, and
            // not "All CBE Groups") — that node itself is what the
            // Profile tab edits.
            // CHANGED 27 Sep 2026 — per Chris: an HQ / State / Branch (anything
            // with entities under it or affiliated to it) opens the dashboard
            // boxes; the Profile / Contact / KPI … tabs only for one single
            // entity with nothing under it (a Temple).
            'showTabs' => (bool) $node && count($nodeIds) <= 1,
            'profileNode' => $node,
            'profileParentNode' => $profileParentNode,
            'profileParentLevelName' => $profileParentLevelName,
            'profileIsTopLevel' => $profileIsTopLevel,
            // NEW 26 Aug 2026, 10th pass — per Chris: "i have so many
            // contact number in my excel file, you didnt insert ?
            // contact 1, contact 2 with name?" — the 591-temple import
            // DID capture both contacts (contact_person_1/2) and every
            // phone number (cbe_hierarchy_node_phones), the Profile tab
            // just wasn't reading them yet. Fixed by pulling the real
            // imported data here instead of the unused singular
            // contact_person/contact_phone columns.
            'profilePhones' => $node
                ? DB::table('cbe_hierarchy_node_phones')->where('node_id', $node->node_id)->orderBy('display_order')->get()
                : collect(),
            'selectedGroupId' => $selectedGroupId,
            'drilledNodeId' => $drilledNodeId,
            // NEW 26 Aug 2026, 12th pass — per Chris: "why show Non klang
            // temple... Penang sekinchiang all Malaysia temple that is
            // wrong" — the "Temple 591 >" / level-breakdown drill links
            // need to carry the CURRENT scope (a district like Klang, or
            // its parent State) forward into the node browse list,
            // otherwise clicking them silently resets to the whole group.
            'currentDistrictName' => $districtName,
            'currentScopeParentId' => $scopeParentId,
            'scopeLabel' => $scopeLabel,
            'groupTier' => $groupTier,
            'levelBreakdown' => $levelBreakdown,
            'nodeCount' => count($nodeIds),
            'groupCount' => $selectedGroupId !== 'ALL' ? 1 : $groupCountAll,
            'childBranchCount' => $childBranchCount,
            'childBranchLabel' => $childBranchLabel,
            'totalMembers' => $totalMembers,
            'tierCounts' => $tierCounts,
            'newMembersThisMonth' => $newMembersThisMonth,
            'inactiveMembers' => $inactiveMembers,
            'branchesReporting' => $branchesReporting,
            'upcomingEvents' => $upcomingEvents,
            'completedEvents' => $completedEvents,
            'meetingsThisYear' => $meetingsThisYear,
            'financial' => $financial,
            'financialMonth' => $financialMonth,
            'bankAccountCount' => $bankAccountCount,
            'bankBalanceTotal' => $bankBalanceTotal,
            'billsDueSoon' => $billsDueSoon,
            'billsOverdue' => $billsOverdue,
            'billsDueOverdueTotal' => $billsDueSoon + $billsOverdue,
            'apBillCount' => $apBills->count(),
            // Month/Year period navigator (search bar) — drives every
            // MTD/YTD/LYTD/Last MTD figure above.
            'periodParam' => $periodMonth->format('Y-m'),
            'periodLabel' => $periodLabel,
            'periodPrevParam' => $periodPrevParam,
            'periodNextParam' => $periodNextParam,
            'isCurrentPeriod' => $isCurrentPeriod,
            'arOutstanding' => $arOutstanding,
            'topDonors' => $topDonors,
            'outstandingPledges' => $outstandingPledges,
            // Box 2 Secretarial Overview
            'committeeCurrentTermCount' => $committeeCurrentTermCount,
            'correspondenceTotalCount' => $correspondenceTotalCount,
            'appointmentsThisYear' => $appointmentsThisYear,
            // Box 1 Events Overview
            'thisMonthEvents' => $thisMonthEvents,
            'next3MonthsEventsCount' => $next3MonthsEventsCount,
            'previousMonthEvents' => $previousMonthEvents,
            // Box 4 Current Overview
            'totalDonorsBox4' => $totalDonors,
            'consultantVolunteerCount' => $consultantVolunteerCount,
            // Box 5 Current Financial Snapshot
            'outstandingCollectionTotal' => $outstandingCollectionTotal,
            'outstandingPayablesTotal' => $outstandingPayablesTotal,
            'availableCashBalance' => $availableCashBalance,
            // Box 6 Pending Tasks, Notifications & Alerts
            'pendingApprovalMessages' => $pendingApprovalMessages,
            'outstandingSupportTickets' => $outstandingSupportTickets,
            'outstandingSurveyResponses' => $outstandingSurveyResponses,
            // Box 7 Executive Analytics Dashboard
            'executiveAnalytics' => $executiveAnalytics,
        ];

        return match ($agent->department) {
            'FINANCE' => view('admin.cbe-kpi.finance', $shared),
            'SALES' => view('admin.cbe-kpi.membership', $shared),
            default => view('admin.cbe-kpi.director', $shared), // DIRECTOR, or no department set yet
        };
    }

    // NEW 18 Sep 2026 — per Chris: "ALL" KPI, reached via Next from the
    // main Executive KPI Dashboard rather than crammed onto that
    // already-full 7-box screen (his no-scroll, Prev/Next-only rule).
    // Re-resolves the SAME node/district/scope/period selection so the
    // numbers here always match whatever was showing on page 1.
    // SPLIT 18 Sep 2026 — per Chris: "it should have Overall Executive
    // KPI, Customer KPI, Vendor Market Place KPI, Communication KPI"
    // as 4 SEPARATE sidebar links, not one combined "More KPI" page.
    // The single-select scope resolution (node/district/scope=all) is
    // identical across all 3, extracted into extraKpiScope() below.
    private function extraKpiScope(Request $request): ?array
    {
        $nodeId = $request->get('node');
        $districtName = $request->get('district');
        $scopeParentId = $request->get('parent');
        $scopeAll = $request->get('scope') === 'all';
        $hasSelection = $nodeId || $districtName || $scopeAll;

        if (! $hasSelection) {
            return null;
        }

        $scope = $this->resolveScope($nodeId, $districtName, $scopeParentId);

        $periodParam = $request->get('period');
        $periodMonth = ($periodParam && preg_match('/^\d{4}-\d{2}$/', $periodParam))
            ? Carbon::createFromFormat('Y-m-d', $periodParam.'-01')->startOfMonth()
            : now()->startOfMonth();
        $isCurrentPeriod = $periodMonth->isSameMonth(now());
        $asOfDate = $isCurrentPeriod ? now()->toDateString() : $periodMonth->copy()->endOfMonth()->toDateString();
        $monthStart = $periodMonth->copy()->startOfMonth()->toDateString();

        return [
            'nodeIds' => $scope['nodeIds'],
            'scopeLabel' => $scope['scopeLabel'],
            'selectedGroupId' => $scope['selectedGroupId'],
            'monthStart' => $monthStart,
            'asOfDate' => $asOfDate,
            'backParams' => $request->query(),
        ];
    }

    // ADDED 19 Sep 2026 — per Chris: "why you dont ask the selection
    // criteria" — these 3 report screens (Communication/Vendor
    // Marketplace/Customer KPI) had no on-screen way to change which
    // CBE Group is in view; you had to already know the URL's ?node= /
    // ?scope=all, or go back to the Executive KPI Dashboard's own
    // typeahead picker first. This gives each screen a compact
    // "All CBE Groups (Combined)" + one-CBE-Group dropdown, same idea
    // as the reference generallink Customer KPI screen's own filter
    // row, submitted with one Go button — just the top-level CBE Groups
    // here (drilling into a specific Branch/Temple still goes through
    // the Executive KPI Dashboard's fuller typeahead picker, which
    // already covers that).
    private function cbeGroupOptions(): \Illuminate\Support\Collection
    {
        return DB::table('cbe_hierarchy_nodes as n')
            ->join('group_labels as gl', 'gl.group_label_id', '=', 'n.group_label_id')
            ->where('gl.group_type', 'CBE')
            ->whereNull('n.parent_node_id')
            ->orderBy('gl.group_name')
            ->select('n.node_id', 'gl.group_label_id', 'gl.group_name')
            ->get();
    }

    public function communicationKpi(Request $request)
    {
        $s = $this->extraKpiScope($request);
        if (! $s) {
            return redirect()->route('admin.cbe-kpi', ['scope' => 'all']);
        }

        return view('admin.cbe-kpi.communication-kpi', array_merge([
            'scopeLabel' => $s['scopeLabel'],
            'backParams' => $s['backParams'],
            'selectedGroupId' => $s['selectedGroupId'],
            'groupOptions' => $this->cbeGroupOptions(),
            'communicationKpi' => \App\Services\CbeExtraKpiService::communication($s['nodeIds'], $s['monthStart'], $s['asOfDate']),
        ], \App\Http\Controllers\Admin\GladeAnalyticsController::computeNoticeBoardMetrics()));
    }

    public function vendorMarketplaceKpi(Request $request)
    {
        $s = $this->extraKpiScope($request);
        if (! $s) {
            return redirect()->route('admin.cbe-kpi', ['scope' => 'all']);
        }

        return view('admin.cbe-kpi.vendor-marketplace-kpi', [
            'scopeLabel' => $s['scopeLabel'],
            'backParams' => $s['backParams'],
            'selectedGroupId' => $s['selectedGroupId'],
            'groupOptions' => $this->cbeGroupOptions(),
            'vendorMarketplaceKpi' => \App\Services\CbeExtraKpiService::vendorMarketplace($s['nodeIds'], $s['monthStart'], $s['asOfDate']),
        ]);
    }

    public function customerKpi(Request $request)
    {
        // REVERTED 19 Sep 2026 — per Chris: "it has to be the same
        // screen across 2 category of admin, platform and cbe. the only
        // different is the retrieval of records logic... based on
        // hierarchy" — redirecting here to the general platform Customer
        // KPI screen (customer-kpi/index.blade.php) was a mistake: that
        // screen has no concept of CBE node hierarchy at all (it only
        // understands one whole Customer Group / group_label_id, or
        // everything) — it can't do "Tao HQ sees every node below it,
        // Temple A with nothing below it sees only itself." This
        // screen's own scoping (extraKpiScope() below, same
        // descendant-node logic CbeExecDashboardController's officer
        // side already uses) is the correct, already-hierarchy-aware
        // foundation for BOTH the platform Admin and a CBE node officer
        // — same view, same data source, same logic, only the node(s)
        // in scope differ.
        $s = $this->extraKpiScope($request);
        if (! $s) {
            return redirect()->route('admin.cbe-kpi', ['scope' => 'all']);
        }

        return view('admin.cbe-kpi.customer-kpi', [
            'scopeLabel' => $s['scopeLabel'],
            'backParams' => $s['backParams'],
            'selectedGroupId' => $s['selectedGroupId'],
            'groupOptions' => $this->cbeGroupOptions(),
            'customerKpi' => \App\Services\CbeExtraKpiService::customer($s['nodeIds'], $s['monthStart']),
        ]);
    }

    // NEW 26 Aug 2026 — saves the Profile tab's Contact Person 1/2 and
    // address for whichever node is currently drilled into. Any of the
    // 3 platform Admin accounts can edit this (not just Director) —
    // it's contact/reference info, not a financial or governance
    // action, so no extra department gate beyond the normal
    // role:ADMIN middleware already on this whole route group.
    // UPDATED 26 Aug 2026, 10th pass — per Chris: "you didnt insert?
    // contact 1, contact 2 with name?" Now saves to the SAME columns
    // the 591-temple Excel import already populated
    // (contact_person_1/contact_person_2), plus a variable-length list
    // of phone numbers in cbe_hierarchy_node_phones (add/edit/remove).
    public function updateProfile(Request $request)
    {
        $nodeId = $request->get('node');
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        abort_if(! $node, 404);

        DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->update([
            'contact_person_1' => $request->get('contact_person_1') ?: null,
            'contact_person_2' => $request->get('contact_person_2') ?: null,
            'address' => $request->get('address') ?: null,
            'city' => $request->get('city') ?: null,
            'postcode' => $request->get('postcode') ?: null,
            'updated_at' => now(),
        ]);

        // NEW 12 Sep 2026 — per Chris: "you have found a temple A created
        // and there is a possibility to link to parent id you have to a
        // flag control linked to parent (show the parent name) because
        // sometime the Temple A management decision does not want to
        // link and stay by itself stand alone." The Profile tab's
        // "Stay standalone" checkbox is the only thing that ever sets or
        // clears link_locked — never done automatically. Not offered (and
        // never applied) for a community's own top level, which has no
        // parent concept to begin with.
        $levelOrders = DB::table('cbe_hierarchy_levels')->where('group_label_id', $node->group_label_id)->pluck('level_order', 'level_id');
        $isTopLevel = $levelOrders->isNotEmpty() && ($levelOrders[$node->level_id] ?? null) === $levelOrders->min();

        if (! $isTopLevel) {
            $wantsStandalone = $request->boolean('stay_standalone');
            if ($wantsStandalone && ! $node->link_locked) {
                \App\Services\CbeHierarchyLinkService::makeStandalone($node->group_label_id, $nodeId);
            } elseif (! $wantsStandalone && $node->link_locked) {
                \App\Services\CbeHierarchyLinkService::reenableAutoLink($node->group_label_id, $nodeId);
            } elseif (! $wantsStandalone && ! $node->link_locked && ! $node->parent_node_id) {
                // Still unlinked (not locked, wasn't locked before either)
                // and the postcode may have just been edited on this same
                // form — re-check now instead of waiting for the next
                // unrelated node to be saved elsewhere in this community.
                \App\Services\CbeHierarchyLinkService::relinkOrphans($node->group_label_id);
            }
        }

        // Each submitted row is either an existing phone (has 'id') or
        // a newly-added one (blank 'id') — rows the admin deleted in
        // the browser simply aren't present in the POST body anymore,
        // so anything not resubmitted gets removed below.
        $submittedIds = [];
        $order = 0;
        foreach ($request->input('phones', []) as $row) {
            $number = trim($row['number'] ?? '');
            if ($number === '') {
                continue;
            }
            $note = trim($row['note'] ?? '') ?: null;
            $phoneId = $row['id'] ?? null;

            if ($phoneId && DB::table('cbe_hierarchy_node_phones')->where('phone_id', $phoneId)->where('node_id', $nodeId)->exists()) {
                DB::table('cbe_hierarchy_node_phones')->where('phone_id', $phoneId)->update([
                    'phone_number' => $number,
                    'contact_note' => $note,
                    'display_order' => $order,
                    'updated_at' => now(),
                ]);
            } else {
                $phoneId = (string) Str::uuid();
                DB::table('cbe_hierarchy_node_phones')->insert([
                    'phone_id' => $phoneId,
                    'node_id' => $nodeId,
                    'phone_number' => $number,
                    'contact_note' => $note,
                    'display_order' => $order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $submittedIds[] = $phoneId;
            $order++;
        }

        DB::table('cbe_hierarchy_node_phones')
            ->where('node_id', $nodeId)
            ->whereNotIn('phone_id', $submittedIds ?: ['__none__'])
            ->delete();

        \App\Services\CbeTierService::afterSave($nodeId); // NEW 27 Sep 2026 — upline rule

        return redirect()->route('admin.cbe-kpi', ['node' => $nodeId])->with('cbe_profile_saved', true);
    }

    // NEW 27 Aug 2026 — extracted out of index() so the Box 6 Bills and
    // Approval Messages drill-down screens can resolve the identical
    // node/district/parent/scope selection without repeating the logic.
    // NEW 27 Sep 2026 — per Chris (Box 4): count every tier from the real
    // records. The selected CBE's own uplines count too (Cawangan Klang
    // -> its HQ 马来西亚道教总会 = HQ 1). A State / Branch / City counts only
    // when it is a real registered entity (has an address or reference
    // no.) — the 13 import-only State codes (SGR, JHR …) are groupings,
    // not CBEs, so State = 0 until a real CBE State is created.
    // Total Affiliate Entity = every real Branch / State / City / Entity
    // under the selection (own + affiliated), not the selection itself
    // and not its uplines. 道 = 1 Branch + 591 = 592; Cawangan Klang = 137.
    private function tierCounts($node, array $nodeIds, ?string $scopeParentId): array
    {
        $out = ['hq' => 0, 'state' => 0, 'branch' => 0, 'city' => 0, 'entity' => 0, 'total' => 0, 'entity_level' => null];

        $selfId = $node->node_id ?? null;
        $anchor = $node ?: ($scopeParentId ? DB::table('cbe_hierarchy_nodes')->where('node_id', $scopeParentId)->first() : null);
        $upIds = [];
        if ($anchor && $anchor->hierarchy_path) {
            $upIds = array_values(array_filter(explode('/', trim($anchor->hierarchy_path, '/'))));
            if ($node) {
                $upIds = array_values(array_diff($upIds, [$node->node_id]));
            }
        }

        $ids = array_values(array_unique(array_merge($nodeIds, $upIds, $selfId ? [$selfId] : [])));
        if (! $ids) {
            return $out;
        }
        $upSet = array_flip($upIds);
        $entityLevels = [];

        foreach (array_chunk($ids, 2000) as $chunk) {
            $rows = DB::table('cbe_hierarchy_nodes as n')
                ->join('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
                ->whereIn('n.node_id', $chunk)
                ->get(['n.node_id', 'n.address', 'n.external_reference_no', 'l.level_name']);
            foreach ($rows as $r) {
                $tier = self::tierOf($r->level_name);
                $real = \App\Services\CbeTierService::isReal($r);
                $isUp = isset($upSet[$r->node_id]);
                $isSelf = $r->node_id === $selfId;

                if ($tier === 'hq') {
                    $out['hq']++;
                    continue;
                }
                if ($tier === 'entity') {
                    if ($isUp) {
                        continue;
                    }
                    $out['entity']++;
                    $entityLevels[$r->level_name] = ($entityLevels[$r->level_name] ?? 0) + 1;
                    if (! $isSelf) {
                        $out['total']++;
                    }
                    continue;
                }
                // state / branch / city
                if (! $real) {
                    continue;
                }
                $out[$tier]++;
                if (! $isUp && ! $isSelf) {
                    $out['total']++;
                }
            }
        }

        if ($entityLevels) {
            arsort($entityLevels);
            $out['entity_level'] = array_key_first($entityLevels);
        }

        return $out;
    }

    private static function tierOf(?string $levelName): string
    {
        return \App\Services\CbeTierService::tierOf($levelName);
    }

    private function resolveScope(?string $nodeId, ?string $districtName, ?string $scopeParentId): array
    {
        $node = $nodeId ? DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first() : null;

        $profileParentNode = ($node && $node->parent_node_id)
            ? DB::table('cbe_hierarchy_nodes')->where('node_id', $node->parent_node_id)->first()
            : null;

        $selectedGroup = $node
            ? DB::table('group_labels')->where('group_label_id', $node->group_label_id)->where('group_type', 'CBE')->first()
            : null;

        $isRoot = $node && $selectedGroup && is_null($node->parent_node_id);
        $drilledNodeId = ($node && $selectedGroup && ! $isRoot) ? $node->node_id : null;

        if ($node && $selectedGroup) {
            $nodeIds = CbeAccountingService::descendantNodeIds($node->node_id);
            // NEW 27 Sep 2026 — per Chris: a Branch also covers the
            // entities AFFILIATED to it (e.g. Cawangan Klang + its 137
            // Klang-district temples of 马来西亚道教总会).
            if (\Illuminate\Support\Facades\Schema::hasColumn('cbe_hierarchy_nodes', 'affiliated_node_id')) {
                $affIds = DB::table('cbe_hierarchy_nodes')->whereIn('affiliated_node_id', $nodeIds)->pluck('node_id')->all();
                $nodeIds = array_values(array_unique(array_merge($nodeIds, $affIds)));
            }
            $selectedGroupId = $selectedGroup->group_label_id;
            [$nodePrimary, $nodeSecondary] = $this->localizedNames($node->node_name, $node->node_name_zh);
            $scopeLabel = $isRoot
                ? $selectedGroup->group_name
                : $selectedGroup->group_name.' — '.$nodePrimary.($nodeSecondary ? ' ('.$nodeSecondary.')' : '');
            $groupTier = $selectedGroup->subscription_tier;
        } elseif ($districtName) {
            $scopeParent = $scopeParentId
                ? DB::table('cbe_hierarchy_nodes')->where('node_id', $scopeParentId)->first()
                : null;
            $leafOrder = $this->leafLevelOrderForScope($scopeParentId);
            $cities = CbeDistrictService::citiesInDistrict($districtName);

            $nq = DB::table('cbe_hierarchy_nodes as n')
                ->join('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
                ->where('l.level_order', $leafOrder)
                ->whereIn('n.city', $cities);
            if ($scopeParent && $scopeParent->hierarchy_path) {
                $nq->where('n.hierarchy_path', 'like', $scopeParent->hierarchy_path.'%');
            }
            $nodeIds = $nq->pluck('n.node_id')->toArray();

            $districtGroup = $scopeParent
                ? DB::table('group_labels')->where('group_label_id', $scopeParent->group_label_id)->where('group_type', 'CBE')->first()
                : null;
            $selectedGroupId = $districtGroup->group_label_id ?? 'ALL';
            $scopeLabel = ($districtGroup->group_name ?? __('admin_cbe_kpi.all_cbe_groups')).' — '.$districtName;
            $groupTier = $districtGroup->subscription_tier ?? null;
        } else {
            $nodeIds = DB::table('cbe_hierarchy_nodes')->pluck('node_id')->toArray();
            $selectedGroupId = 'ALL';
            $scopeLabel = __('admin_cbe_kpi.all_cbe_groups');
            $groupTier = null;
        }

        return [
            'node' => $node,
            'profileParentNode' => $profileParentNode,
            'drilledNodeId' => $drilledNodeId,
            'nodeIds' => $nodeIds,
            'selectedGroupId' => $selectedGroupId,
            'scopeLabel' => $scopeLabel,
            'groupTier' => $groupTier,
        ];
    }

    // NEW 27 Aug 2026 — Box 6's merged "Bills Due/Overdue" row drill-down
    // (master spec Section 55 follow-up, per Chris: "group the bill due
    // and overdue into one when drill down show 2 tap folder"). Same
    // AP-bill scope as the dashboard's own Box 6 count, just listed out
    // per-bill instead of just a number, split into 2 tabs.
    public function bills()
    {
        $scope = $this->resolveScope(request('node'), request('district'), request('parent'));
        $nodeIds = $scope['nodeIds'];
        $asOfDate = now()->toDateString();

        $apBills = DB::table('cbe_purchase_bills as b')
            ->leftJoin('cbe_suppliers as s', 's.supplier_id', '=', 'b.supplier_id')
            ->whereIn('b.cbe_node_id', $nodeIds)
            ->whereIn('b.status', ['UNPAID', 'PARTIALLY_PAID'])
            ->select('b.*', 's.supplier_name')
            ->orderBy('b.due_date')
            ->get();

        $dueSoon = $apBills->filter(fn ($b) => $b->due_date && $b->due_date >= $asOfDate && $b->due_date <= now()->addDays(7)->toDateString());
        $overdue = $apBills->filter(fn ($b) => $b->due_date && $b->due_date < $asOfDate);

        return view('admin.cbe-kpi.bills', [
            'scopeLabel' => $scope['scopeLabel'],
            'dueSoon' => $dueSoon->values(),
            'overdue' => $overdue->values(),
            'backQuery' => request()->only(['node', 'district', 'parent', 'scope', 'period']),
        ]);
    }

    // NEW 27 Aug 2026 — Box 6's Pending Approval Messages row drill-down
    // (per Chris: "group approval into one and allow to drill down to
    // show the 2 tab" — confirmed as Pending / History, both from the
    // same internal-approval-messages table, master spec Section 55).
    public function approvals()
    {
        $scope = $this->resolveScope(request('node'), request('district'), request('parent'));
        $nodeIds = $scope['nodeIds'];

        $pending = collect();
        $history = collect();
        if (Schema::hasTable('cbe_internal_approval_messages')) {
            $base = DB::table('cbe_internal_approval_messages as m')
                ->leftJoin('agents as sender', 'sender.agent_id', '=', 'm.sender_agent_id')
                ->whereIn('m.cbe_node_id', $nodeIds)
                ->select('m.*', 'sender.full_name as sender_name');
            $pending = (clone $base)->where('m.status', 'PENDING')->orderByDesc('m.created_at')->get();
            $history = (clone $base)->whereIn('m.status', ['APPROVED', 'REJECTED'])->orderByDesc('m.responded_at')->limit(100)->get();
        }

        return view('admin.cbe-kpi.approvals', [
            'scopeLabel' => $scope['scopeLabel'],
            'pending' => $pending,
            'history' => $history,
            'backQuery' => request()->only(['node', 'district', 'parent', 'scope', 'period']),
        ]);
    }

    // NEW 27 Aug 2026 — Box 3 Financial Overview's 3 rows (Income,
    // Expenses, Bank Balance) drill down here (master spec Section 54),
    // per Chris: "the first thing is collection/Income... you should
    // display the details of the entries from the AR by individual
    // row... Expenditure and payments is to refer to AP ledger...
    // a CBE may have more than one bank account, so i need all bank
    // statement." Income/Expense entries read straight from the same
    // journal (cbe_journal_lines/cbe_journal_entries) that
    // CbeAccountingService::financialSummaryForNodeIds() totals up, so
    // the detail rows always add up to exactly the number shown on the
    // dashboard box — never a second, disagreeing calculation. Bank tab
    // lists every active bank account with its selected-period and
    // previous-period statement balance (master spec Section 55).
    public function financialDetail()
    {
        $scope = $this->resolveScope(request('node'), request('district'), request('parent'));
        $nodeIds = $scope['nodeIds'];

        $periodParam = request('period');
        $periodMonth = ($periodParam && preg_match('/^\d{4}-\d{2}$/', $periodParam))
            ? Carbon::createFromFormat('Y-m-d', $periodParam.'-01')->startOfMonth()
            : now()->startOfMonth();
        $isCurrentPeriod = $periodMonth->isSameMonth(now());
        $asOfDate = $isCurrentPeriod ? now()->toDateString() : $periodMonth->copy()->endOfMonth()->toDateString();
        $from = $periodMonth->copy()->startOfYear()->toDateString();
        $to = $asOfDate;

        $incomeEntries = DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->join('cbe_chart_of_accounts as a', 'a.account_id', '=', 'l.account_id')
            ->whereIn('j.cbe_node_id', $nodeIds)
            ->where('a.account_type', 'INCOME')
            ->whereBetween('j.entry_date', [$from, $to])
            ->select('j.journal_id', 'j.entry_date', 'j.description', 'j.source_type', 'a.account_name', DB::raw('SUM(l.credit) - SUM(l.debit) as amount'))
            ->groupBy('j.journal_id', 'j.entry_date', 'j.description', 'j.source_type', 'a.account_name')
            ->orderByDesc('j.entry_date')
            ->get();

        $expenseEntries = DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->join('cbe_chart_of_accounts as a', 'a.account_id', '=', 'l.account_id')
            ->whereIn('j.cbe_node_id', $nodeIds)
            ->where('a.account_type', 'EXPENSE')
            ->whereBetween('j.entry_date', [$from, $to])
            ->select('j.journal_id', 'j.entry_date', 'j.description', 'j.source_type', 'a.account_name', DB::raw('SUM(l.debit) - SUM(l.credit) as amount'))
            ->groupBy('j.journal_id', 'j.entry_date', 'j.description', 'j.source_type', 'a.account_name')
            ->orderByDesc('j.entry_date')
            ->get();

        $bankAccounts = Schema::hasTable('cbe_bank_accounts')
            ? DB::table('cbe_bank_accounts')->whereIn('cbe_node_id', $nodeIds)->where('is_active', true)->get()
            : collect();
        $prevPeriodMonth = $periodMonth->copy()->subMonthNoOverflow();
        $bankRows = $bankAccounts->map(function ($acct) use ($periodMonth, $prevPeriodMonth) {
            $curStmt = Schema::hasTable('cbe_bank_statements')
                ? DB::table('cbe_bank_statements')->where('bank_account_id', $acct->bank_account_id)
                    ->where('statement_year', $periodMonth->year)->where('statement_month', $periodMonth->month)->first()
                : null;
            $prevStmt = Schema::hasTable('cbe_bank_statements')
                ? DB::table('cbe_bank_statements')->where('bank_account_id', $acct->bank_account_id)
                    ->where('statement_year', $prevPeriodMonth->year)->where('statement_month', $prevPeriodMonth->month)->first()
                : null;

            return [
                'account' => $acct,
                'curStmt' => $curStmt,
                'prevStmt' => $prevStmt,
                'curLabel' => $periodMonth->translatedFormat('F Y'),
                'prevLabel' => $prevPeriodMonth->translatedFormat('F Y'),
            ];
        });

        return view('admin.cbe-kpi.financial-detail', [
            'scopeLabel' => $scope['scopeLabel'],
            'periodLabel' => $periodMonth->translatedFormat('F Y'),
            'incomeEntries' => $incomeEntries,
            'expenseEntries' => $expenseEntries,
            'bankRows' => $bankRows,
            'incomeTotal' => $incomeEntries->sum('amount'),
            'expenseTotal' => $expenseEntries->sum('amount'),
            'backQuery' => request()->only(['node', 'district', 'parent', 'scope', 'period']),
            'initialTab' => in_array(request('tab'), ['income', 'expenses', 'bank']) ? request('tab') : 'income',
        ]);
    }

    // NEW 27 Aug 2026 — Box 2 Secretarial Overview's 3 rows (Committee
    // Members, Meetings This Year, Correspondence on File) drill down
    // here (master spec Section 53, task #228), same 3-tab pattern as
    // Box 3's financialDetail(). Committee tab is CURRENT TERM only,
    // matching the dashboard box's own count exactly.
    public function secretarialDetail()
    {
        $scope = $this->resolveScope(request('node'), request('district'), request('parent'));
        $nodeIds = $scope['nodeIds'];

        $periodParam = request('period');
        $periodMonth = ($periodParam && preg_match('/^\d{4}-\d{2}$/', $periodParam))
            ? Carbon::createFromFormat('Y-m-d', $periodParam.'-01')->startOfMonth()
            : now()->startOfMonth();
        $isCurrentPeriod = $periodMonth->isSameMonth(now());
        $asOfDate = $isCurrentPeriod ? now()->toDateString() : $periodMonth->copy()->endOfMonth()->toDateString();

        $committee = Schema::hasTable('cbe_committee_positions')
            ? DB::table('cbe_committee_positions as p')
                ->leftJoin('cbe_group_memberships as m', 'm.membership_id', '=', 'p.membership_id')
                ->leftJoin('agents as ag', 'ag.agent_id', '=', 'm.agent_id')
                ->whereIn('p.cbe_node_id', $nodeIds)
                ->where('p.term_start_date', '<=', $asOfDate)->where('p.term_end_date', '>=', $asOfDate)
                ->select('p.*', 'ag.full_name as member_name')
                ->orderBy('p.sort_order')
                ->get()
            : collect();

        $meetings = DB::table('cbe_meeting_minutes')
            ->whereIn('cbe_node_id', $nodeIds)
            ->whereYear('meeting_date', $periodMonth->year)
            ->orderByDesc('meeting_date')
            ->get();

        $correspondence = Schema::hasTable('cbe_correspondence')
            ? DB::table('cbe_correspondence')->whereIn('cbe_node_id', $nodeIds)->orderByDesc('correspondence_date')->get()
            : collect();

        return view('admin.cbe-kpi.secretarial-detail', [
            'scopeLabel' => $scope['scopeLabel'],
            'periodLabel' => $periodMonth->translatedFormat('F Y'),
            'committee' => $committee,
            'meetings' => $meetings,
            'correspondence' => $correspondence,
            'backQuery' => request()->only(['node', 'district', 'parent', 'scope', 'period']),
            'initialTab' => in_array(request('tab'), ['committee', 'meetings', 'correspondence']) ? request('tab') : 'committee',
        ]);
    }

    // NEW 27 Aug 2026 — Box 6's Approve/Reject action, per Chris: "the
    // sender can attached the print out pdf attachment or you can use
    // internal messaging OTP approval method... I am not referring a
    // fully automatic workflow." Same OTP send/verify shape as
    // VendorAgreementController (vendor_agreement_acceptances +
    // vendor_agreement_otp_log) — mirrored onto
    // cbe_internal_approval_messages + cbe_internal_approval_otp_log so
    // the Chairman/Director approving a payment voucher/claim/waiver
    // etc. goes through the exact same "OTP emailed to the approver,
    // approver types it back in" evidence trail already proven for
    // vendor agreement acceptance.
    private function logApprovalOtpEvent(string $messageId, string $eventType, ?string $recipientEmail): void
    {
        try {
            DB::table('cbe_internal_approval_otp_log')->insert([
                'log_id' => (string) Str::uuid(),
                'message_id' => $messageId,
                'event_type' => $eventType,
                'recipient_email' => $recipientEmail,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('AdminCbeKpiController::logApprovalOtpEvent failed: '.$e->getMessage());
        }
    }

    // The review screen: message detail + Send OTP button, or the OTP
    // entry form once a code has been sent.
    public function approvalReview(string $message)
    {
        $msg = DB::table('cbe_internal_approval_messages as m')
            ->leftJoin('agents as sender', 'sender.agent_id', '=', 'm.sender_agent_id')
            ->leftJoin('agents as recipient', 'recipient.agent_id', '=', 'm.recipient_agent_id')
            ->where('m.message_id', $message)
            ->select('m.*', 'sender.full_name as sender_name', 'recipient.full_name as recipient_name', 'recipient.email as recipient_email')
            ->first();
        abort_if(! $msg, 404);

        return view('admin.cbe-kpi.approval-review', [
            'msg' => $msg,
            'backQuery' => request()->only(['node', 'district', 'parent', 'scope', 'period']),
        ]);
    }

    // Emails a fresh 6-digit OTP to the message's recipient (the
    // Chairman/Director who must approve), valid 10 minutes.
    // CHANGED 27 Aug 2026 — per Chris: "the email OTP is not using
    // external email, it is use internal messaging system, remember
    // when we key in a help desk message follow that concept." The OTP
    // is no longer emailed out — it's delivered as a bell notification
    // through the SAME internal NotificationService every other
    // in-app alert already uses (see HelpDeskController::store()'s
    // notify() call for the exact pattern being mirrored here). The
    // approver logs into GeneralLink, opens their notification bell,
    // reads the code, and types it back into this screen — the OTP
    // concept (a one-time code only the real approver can see) stays
    // intact, only the delivery channel moves from external email to
    // GeneralLink's own internal messaging.
    public function approvalSendOtp(Request $request, string $message)
    {
        $msg = DB::table('cbe_internal_approval_messages')->where('message_id', $message)->first();
        abort_if(! $msg, 404);
        if ($msg->status !== 'PENDING') {
            return back()->with('error', __('cbe_exec.approval_already_decided'));
        }

        $recipient = DB::table('agents')->where('agent_id', $msg->recipient_agent_id)->first();
        $otp = (string) random_int(100000, 999999);
        DB::table('cbe_internal_approval_messages')->where('message_id', $message)->update([
            'otp_code' => $otp,
            'otp_sent_at' => now(),
            'otp_expires_at' => now()->addMinutes(10),
            'otp_attempts' => 0,
            'updated_at' => now(),
        ]);

        if ($recipient) {
            app(\App\Services\NotificationService::class)->notify(
                [$recipient],
                'CBE_APPROVAL_OTP',
                'Approval Verification Code — '.$msg->subject,
                "Your one-time verification code to approve or reject \"{$msg->subject}\" is: {$otp}\n\nThis code expires in 10 minutes."
            );
        }

        $this->logApprovalOtpEvent($message, 'OTP_SENT', $recipient->email ?? null);

        return back()->with('success', __('cbe_exec.approval_otp_sent', ['name' => $recipient->full_name ?? '—']));
    }

    // Verifies the typed OTP and, if correct, records Approve or Reject.
    public function approvalDecide(Request $request, string $message)
    {
        $request->validate([
            'otp_code' => ['required', 'string', 'size:6'],
            'decision' => ['required', 'in:APPROVED,REJECTED'],
            'response_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $msg = DB::table('cbe_internal_approval_messages')->where('message_id', $message)->first();
        abort_if(! $msg, 404);
        $recipientEmail = DB::table('agents')->where('agent_id', $msg->recipient_agent_id)->value('email');

        if ($msg->status !== 'PENDING') {
            return back()->with('error', __('cbe_exec.approval_already_decided'));
        }
        if (! $msg->otp_code || ! $msg->otp_expires_at || now()->greaterThan($msg->otp_expires_at)) {
            $this->logApprovalOtpEvent($message, 'OTP_VERIFY_EXPIRED', $recipientEmail);
            return back()->with('error', __('cbe_exec.approval_otp_expired'));
        }
        if ($msg->otp_attempts >= 5) {
            $this->logApprovalOtpEvent($message, 'OTP_VERIFY_TOO_MANY_ATTEMPTS', $recipientEmail);
            return back()->with('error', __('cbe_exec.approval_otp_too_many'));
        }
        if (! hash_equals($msg->otp_code, $request->otp_code)) {
            DB::table('cbe_internal_approval_messages')->where('message_id', $message)->increment('otp_attempts');
            $this->logApprovalOtpEvent($message, 'OTP_VERIFY_FAILED', $recipientEmail);
            return back()->with('error', __('cbe_exec.approval_otp_incorrect'));
        }

        DB::table('cbe_internal_approval_messages')->where('message_id', $message)->update([
            'status' => $request->decision,
            'responded_at' => now(),
            'response_note' => $request->response_note ?: null,
            'otp_code' => null,
            'updated_at' => now(),
        ]);
        $this->logApprovalOtpEvent($message, 'OTP_VERIFY_SUCCESS', $recipientEmail);

        return redirect()->route('admin.cbe-kpi.approvals', request()->only(['node', 'district', 'parent', 'scope', 'period']))
            ->with('success', $request->decision === 'APPROVED' ? __('cbe_exec.approval_recorded_approved') : __('cbe_exec.approval_recorded_rejected'));
    }

    // NEW 26 Aug 2026 — the "no data before a completed selection" empty
    // state (see $hasSelection above) still needs every variable the
    // Blade views reference, just all zeroed/blank, so the view can
    // render its empty-state prompt without undefined-variable errors.
    private function emptyShared(): array
    {
        $zeroFinancial = ['cash_balance' => 0, 'total_income' => 0, 'total_expense' => 0, 'net_surplus' => 0, 'ap_outstanding' => 0];

        return [
            'hasSelection' => false,
            'showTabs' => false,
            'profileNode' => null,
            'profileParentNode' => null,
            'profileParentLevelName' => null,
            'profileIsTopLevel' => false,
            'selectedGroupId' => 'ALL',
            'drilledNodeId' => null,
            'currentDistrictName' => null,
            'currentScopeParentId' => null,
            'scopeLabel' => null,
            'groupTier' => null,
            'profilePhones' => collect(),
            'levelBreakdown' => collect(),
            'nodeCount' => 0,
            'groupCount' => DB::table('group_labels')->where('group_type', 'CBE')->count(),
            'childBranchCount' => 0,
            'childBranchLabel' => '',
            'totalMembers' => 0,
            'tierCounts' => ['hq' => 0, 'state' => 0, 'branch' => 0, 'city' => 0, 'entity' => 0, 'total' => 0, 'entity_level' => null],
            'newMembersThisMonth' => 0,
            'inactiveMembers' => 0,
            'branchesReporting' => 0,
            'upcomingEvents' => 0,
            'completedEvents' => 0,
            'meetingsThisYear' => 0,
            'financial' => $zeroFinancial,
            'financialMonth' => $zeroFinancial,
            'bankAccountCount' => 0,
            'bankBalanceTotal' => 0,
            'billsDueSoon' => 0,
            'billsOverdue' => 0,
            'billsDueOverdueTotal' => 0,
            'apBillCount' => 0,
            'arOutstanding' => 0,
            'periodParam' => now()->format('Y-m'),
            'periodLabel' => now()->translatedFormat('F Y'),
            'periodPrevParam' => now()->subMonthNoOverflow()->format('Y-m'),
            'periodNextParam' => null,
            'isCurrentPeriod' => true,
            'topDonors' => collect(),
            'outstandingPledges' => 0,
            'committeeCurrentTermCount' => 0,
            'correspondenceTotalCount' => 0,
            'appointmentsThisYear' => 0,
            'thisMonthEvents' => 0,
            'next3MonthsEventsCount' => 0,
            'previousMonthEvents' => 0,
            'totalDonorsBox4' => 0,
            'consultantVolunteerCount' => 0,
            'outstandingCollectionTotal' => 0,
            'outstandingPayablesTotal' => 0,
            'availableCashBalance' => 0,
            'pendingApprovalMessages' => 0,
            'outstandingSupportTickets' => 0,
            'outstandingSurveyResponses' => 0,
            'executiveAnalytics' => [
                ['label_key' => 'row_total_income_collection', 'last_mtd' => 0, 'mtd' => 0, 'ytd' => 0, 'lytd' => 0],
                ['label_key' => 'row_total_payment_expenses', 'last_mtd' => 0, 'mtd' => 0, 'ytd' => 0, 'lytd' => 0],
                ['label_key' => 'row_income_expenditure_balance', 'last_mtd' => 0, 'mtd' => 0, 'ytd' => 0, 'lytd' => 0],
                ['label_key' => 'row_total_assets', 'last_mtd' => 0, 'mtd' => 0, 'ytd' => 0, 'lytd' => 0],
                ['label_key' => 'row_total_liabilities', 'last_mtd' => 0, 'mtd' => 0, 'ytd' => 0, 'lytd' => 0],
            ],
        ];
    }

    // The deepest real hierarchy level for a given scope — either the
    // group a picked parent node belongs to, or (with no parent yet) the
    // deepest level defined ANYWHERE, which in practice means Tao's
    // Temple level (3). Never hardcoded to a fixed number for a specific
    // group — read from cbe_hierarchy_levels so it stays correct if a
    // future CBE community is deeper or shallower than Tao.
    private function leafLevelOrderForScope(?string $parentNodeId): int
    {
        $groupLabelId = null;
        if ($parentNodeId) {
            $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $parentNodeId)->first();
            $groupLabelId = $node->group_label_id ?? null;
        }

        $q = DB::table('cbe_hierarchy_levels');
        if ($groupLabelId) {
            $q->where('group_label_id', $groupLabelId);
        }

        return (int) ($q->max('level_order') ?: 3);
    }

    // NEW 26 Aug 2026 — per Chris: "you should display the name of
    // persatuan or temple name in other name if the selection screen
    // is chinese language." When the interface locale is zh and a
    // Chinese name exists, show it as the primary/bold name and the
    // English name as the secondary/bracketed one; otherwise keep
    // English primary. Used for KPI scope headers, filter-box
    // dropdown suggestions, and node browse lists.
    private function localizedNames(?string $nameEn, ?string $nameZh): array
    {
        if (app()->getLocale() === 'zh' && $nameZh) {
            return [$nameZh, $nameEn];
        }

        return [$nameEn, $nameZh];
    }

    // NEW 25 Aug 2026, 3rd pass — per Chris: "i want all search filter
    // show in one row NOT hidden" — replaces the earlier cascading
    // typeahead (one box, next box only appears after a pick) with 4
    // ALWAYS-VISIBLE boxes — CBE Group / State / Branch / Temple — every
    // box searches straight away, nothing waits to be revealed. Each box
    // searches ALL nodes at that position across every CBE community; if
    // an earlier box already has a pick, later boxes narrow their search
    // to just that pick's own descendants — but a box works completely
    // on its own too.
    //
    // REBUILT 26 Aug 2026 — per Chris: "the branch suppose to show the
    // city call klang... klang consists of 5 area." Tao's real hierarchy
    // is only 3 levels deep (HQ -> State -> Temple) — there is no real
    // 4th "Branch" level in cbe_hierarchy_levels, so Branch can no
    // longer be "just level_order 3". Branch is now its own thing: a
    // district grouping of the Temple level's own `city` values (see
    // CbeDistrictService/searchBranches below), and Temple always
    // targets the community's real deepest level, optionally narrowed
    // by whichever district Branch picked.
    public function searchLevel(Request $request)
    {
        $box = $request->get('box', 'temple');
        $q = trim($request->get('q', ''));
        $parentNodeId = $request->get('parent');
        $districtName = $request->get('district');

        // NEW 27 Sep 2026 — per Chris: "all selection data accurate as per
        // CBE". CBE Group box lists the CBE GROUP NAMES (e.g. Persekutuan
        // Pertubuhan Agama Tao Malaysia), not the name of its top entity.
        if ($box === 'group') {
            return $this->searchGroups($q);
        }

        if ($box === 'branch') {
            return $this->searchBranches($parentNodeId, $q);
        }

        $levelOrder = match ($box) {
            'group' => 1,
            'state' => 2,
            default => $this->leafLevelOrderForScope($parentNodeId), // 'temple'
        };

        $query = DB::table('cbe_hierarchy_nodes as n')
            ->join('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
            ->where('l.level_order', $levelOrder)
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('n.node_name', 'like', '%'.$q.'%')
                        ->orWhere('n.node_name_zh', 'like', '%'.$q.'%')
                        ->orWhere('n.city', 'like', '%'.$q.'%');
                });
            });

        if ($parentNodeId) {
            $parent = DB::table('cbe_hierarchy_nodes')->where('node_id', $parentNodeId)->first();
            if ($parent && $parent->hierarchy_path) {
                // NEW 27 Sep 2026 — per Chris: Entity box also lists the
                // entities AFFILIATED to this parent (Cawangan Klang -> its
                // 137 temples of 马来西亚道教总会), never the parent itself.
                $affOk = $box === 'temple' && Schema::hasColumn('cbe_hierarchy_nodes', 'affiliated_node_id');
                $descIds = $affOk ? CbeAccountingService::descendantNodeIds($parent->node_id) : [];
                $hasAff = $affOk && DB::table('cbe_hierarchy_nodes')->whereIn('affiliated_node_id', $descIds)->exists();
                if ($hasAff) {
                    $query = DB::table('cbe_hierarchy_nodes as n')
                        ->where(function ($w) use ($parent, $descIds, $levelOrder) {
                            $w->where(function ($own) use ($parent, $levelOrder) {
                                $own->where('n.group_label_id', $parent->group_label_id)
                                    ->where('n.hierarchy_path', 'like', $parent->hierarchy_path.'%')
                                    ->whereIn('n.level_id', DB::table('cbe_hierarchy_levels')->where('group_label_id', $parent->group_label_id)->where('level_order', $levelOrder)->pluck('level_id'));
                            })->orWhereIn('n.affiliated_node_id', $descIds);
                        })
                        ->where('n.node_id', '!=', $parent->node_id)
                        ->when($q !== '', function ($qq) use ($q) {
                            $qq->where(function ($w) use ($q) {
                                $w->where('n.node_name', 'like', '%'.$q.'%')
                                    ->orWhere('n.node_name_zh', 'like', '%'.$q.'%')
                                    ->orWhere('n.city', 'like', '%'.$q.'%');
                            });
                        });
                } else {
                    $query->where('n.hierarchy_path', 'like', $parent->hierarchy_path.'%');
                }
            }
        }

        if ($box === 'temple' && $districtName) {
            $query->whereIn('n.city', CbeDistrictService::citiesInDistrict($districtName));
        }

        $results = $query->orderBy('n.node_name')->limit(30)
            ->select('n.node_id', 'n.node_name', 'n.node_name_zh', 'n.city')
            ->get()
            ->map(function ($r) {
                [$primary, $secondary] = $this->localizedNames($r->node_name, $r->node_name_zh);

                return [
                    'node_id' => $r->node_id,
                    'label' => $primary.($r->city ? ' — '.$r->city : '').($secondary ? ' ('.$secondary.')' : ''),
                ];
            });

        return response()->json($results);
    }

    // NEW 26 Aug 2026 — per Chris: "the branch suppose to show the city
    // call klang and klang consists of 5 area, klang, Pelabuhan Klang,
    // Kapar, Meru, Pulau Ketam." Groups the deepest level's `city`
    // values into districts (CbeDistrictService) so "Klang" shows once
    // with its true combined temple count, instead of 5 separate tiny
    // entries. Returned node_id is a synthetic 'district:<name>' marker
    // (not a real cbe_hierarchy_nodes row) — the front end and index()
    // both know to treat that prefix as a district, not a node.
    // NEW 27 Sep 2026 — CBE Group box: one row per CBE group, label = the
    // group name, value = the group's top entity.
    private function searchGroups(string $q)
    {
        $rows = DB::table('group_labels as g')
            ->join('cbe_hierarchy_nodes as n', 'n.group_label_id', '=', 'g.group_label_id')
            ->join('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
            ->where('g.group_type', 'CBE')
            ->whereRaw('l.level_order = (select min(l2.level_order) from cbe_hierarchy_levels l2 where l2.group_label_id = g.group_label_id)')
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($x) use ($q) {
                    $x->where('g.group_name', 'like', '%'.$q.'%')->orWhere('n.node_name', 'like', '%'.$q.'%')->orWhere('n.node_name_zh', 'like', '%'.$q.'%');
                });
            })
            ->orderBy('g.group_name')->orderBy('n.node_name')
            ->get(['g.group_label_id', 'g.group_name', 'n.node_id', 'n.node_name', 'n.node_name_zh']);

        $perGroup = $rows->countBy('group_label_id');

        return response()->json($rows->map(function ($r) use ($perGroup) {
            [$primary, $secondary] = $this->localizedNames($r->node_name, $r->node_name_zh);
            $label = $r->group_name;
            // A group with several top entities shows which one.
            if (($perGroup[$r->group_label_id] ?? 1) > 1) {
                $label .= ' — '.$primary;
            } elseif (mb_stripos($r->group_name, $primary) === false && mb_stripos($primary, $r->group_name) === false) {
                // e.g. 马来西亚道教总会 (Malaysia Taoist Association (Federation))
                $label .= ' ('.$primary.')';
            }

            return ['node_id' => $r->node_id, 'label' => $label];
        })->values());
    }

    private function searchBranches(?string $parentNodeId, string $q)
    {
        // NEW 27 Sep 2026 — per Chris: a CBE whose own levels include a
        // real Branch entity (Persekutuan … Cawangan Bandar Di Raja Klang)
        // lists those real branches, with how many entities each covers
        // (own + affiliated), instead of city groupings.
        if ($parentNodeId) {
            $parent = DB::table('cbe_hierarchy_nodes')->where('node_id', $parentNodeId)->first();
            $branchLevelIds = $parent ? DB::table('cbe_hierarchy_levels')->where('group_label_id', $parent->group_label_id)
                ->whereRaw('LOWER(level_name) LIKE ?', ['%branch%'])->pluck('level_id') : collect();
            if ($parent && $branchLevelIds->isNotEmpty()) {
                $branches = DB::table('cbe_hierarchy_nodes as n')
                    ->where('n.group_label_id', $parent->group_label_id)
                    ->whereIn('n.level_id', $branchLevelIds)
                    ->where('n.hierarchy_path', 'like', ($parent->level_id && $branchLevelIds->contains($parent->level_id) ? '%' : $parent->hierarchy_path.'%'))
                    ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('n.node_name', 'like', '%'.$q.'%')->orWhere('n.node_name_zh', 'like', '%'.$q.'%')->orWhere('n.city', 'like', '%'.$q.'%')))
                    ->orderBy('n.node_name')->limit(30)->get(['n.node_id', 'n.node_name', 'n.node_name_zh']);
                $hasAff = Schema::hasColumn('cbe_hierarchy_nodes', 'affiliated_node_id');

                return response()->json($branches->map(function ($b) use ($hasAff) {
                    [$primary, $secondary] = $this->localizedNames($b->node_name, $b->node_name_zh);
                    $ids = CbeAccountingService::descendantNodeIds($b->node_id);
                    $n = count($ids) - 1 + ($hasAff ? DB::table('cbe_hierarchy_nodes')->whereIn('affiliated_node_id', $ids)->count() : 0);

                    return ['node_id' => $b->node_id, 'label' => $primary.($secondary ? ' ('.$secondary.')' : '').' ('.number_format($n).')'];
                })->values());
            }
        }

        $leafOrder = $this->leafLevelOrderForScope($parentNodeId);

        $query = DB::table('cbe_hierarchy_nodes as n')
            ->join('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
            ->where('l.level_order', $leafOrder)
            ->whereNotNull('n.city')
            ->where('n.city', '!=', '');

        if ($parentNodeId) {
            $parent = DB::table('cbe_hierarchy_nodes')->where('node_id', $parentNodeId)->first();
            if ($parent && $parent->hierarchy_path) {
                $query->where('n.hierarchy_path', 'like', $parent->hierarchy_path.'%');
            }
        }

        $cityCounts = $query->select('n.city', DB::raw('count(*) as cnt'))
            ->groupBy('n.city')
            ->pluck('cnt', 'city');

        if ($cityCounts->isEmpty()) {
            return response()->json([]);
        }

        $districts = CbeDistrictService::groupCities($cityCounts->keys()->toArray());

        $out = [];
        foreach ($districts as $districtName => $cities) {
            if ($q !== '' && stripos($districtName, $q) === false) {
                continue;
            }
            $total = 0;
            foreach ($cities as $c) {
                $total += $cityCounts[$c] ?? 0;
            }
            $out[] = [
                'sort' => $districtName,
                'node_id' => 'district:'.$districtName,
                'label' => $districtName.' ('.number_format($total).')',
            ];
        }

        usort($out, fn ($a, $b) => strcmp($a['sort'], $b['sort']));

        $out = array_slice($out, 0, 30);
        $out = array_map(fn ($r) => ['node_id' => $r['node_id'], 'label' => $r['label']], $out);

        return response()->json($out);
    }

    // NEW 25 Aug 2026 — per Chris: "why cannot drill down from 591?" —
    // the level-breakdown rows (e.g. "Temple: 591") on the KPI screen
    // are now clickable, landing here: a plain browsable, paginated list
    // of every node at that level, Prev/Next only per Chris's standing
    // navigation rule (no page-number jump links) — never a raw typed
    // search as the only way in. Each row links straight into that
    // node's own KPI view (same ?group=&node= the drill-down typeahead
    // already produces).
    public function nodes(Request $request)
    {
        $groupId = $request->get('group');
        $levelName = $request->get('level');
        // NEW 26 Aug 2026, 12th pass — per Chris: "why show Non klang
        // temple... Penang sekinchiang all Malaysia temple that is wrong"
        // — this list previously ignored any drilled-down scope and
        // always listed EVERY node at this level group-wide. Now accepts
        // the same scope shape used elsewhere: either a real ancestor
        // node ('node', e.g. the Selangor State node), or a district/
        // Branch city-grouping ('district' + the real node it sits under,
        // 'parent') — matching AdminCbeKpiController::index()'s district
        // handling and searchLevel()'s box='temple' district filter.
        $scopeNodeId = $request->get('node');
        $districtName = $request->get('district');
        $districtParentId = $request->get('parent');
        $cityFilter = $request->get('city');

        $group = DB::table('group_labels')->where('group_label_id', $groupId)->where('group_type', 'CBE')->firstOrFail();

        $query = DB::table('cbe_hierarchy_nodes as n')
            ->join('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
            ->where('l.level_name', $levelName);

        $scopeNode = $scopeNodeId ? DB::table('cbe_hierarchy_nodes')->where('node_id', $scopeNodeId)->first() : null;
        if ($scopeNode && $scopeNode->hierarchy_path) {
            // CHANGED 27 Sep 2026 — per Chris: also the entities AFFILIATED
            // to this node (e.g. Cawangan Klang's 137 temples, which belong
            // to the CBE 马来西亚道教总会).
            $scopeIds = CbeAccountingService::descendantNodeIds($scopeNode->node_id);
            $hasAff = \Illuminate\Support\Facades\Schema::hasColumn('cbe_hierarchy_nodes', 'affiliated_node_id');
            $query->where(function ($w) use ($groupId, $scopeNode, $scopeIds, $hasAff) {
                $w->where(function ($own) use ($groupId, $scopeNode) {
                    $own->where('n.group_label_id', $groupId)->where('n.hierarchy_path', 'like', $scopeNode->hierarchy_path.'%');
                });
                if ($hasAff) {
                    $w->orWhereIn('n.affiliated_node_id', $scopeIds);
                }
            });
        } else {
            $query->where('n.group_label_id', $groupId);
        }

        if ($scopeNode) {
            // handled above
        } elseif ($districtName) {
            $query->whereIn('n.city', CbeDistrictService::citiesInDistrict($districtName));
            if ($districtParentId) {
                $districtParent = DB::table('cbe_hierarchy_nodes')->where('node_id', $districtParentId)->first();
                if ($districtParent && $districtParent->hierarchy_path) {
                    $query->where('n.hierarchy_path', 'like', $districtParent->hierarchy_path.'%');
                }
            }
        }

        // NEW 26 Aug 2026, 13th pass — per Chris: "can you create folder
        // tap for group. Klang, Pelabuhan Klang, Pulau Ketam, Kapar, Meru.
        // dont hard code. cater for future other branch that have many
        // sub group (post code group)" — read the DISTINCT city values
        // that actually exist among the nodes already matched above
        // (never a fixed list), so this works for any future district
        // with any number of city sub-groups. Computed BEFORE the city
        // filter below so every tab stays visible even once one is
        // picked.
        $cities = (clone $query)->whereNotNull('n.city')->where('n.city', '!=', '')
            ->distinct()->orderBy('n.city')->pluck('n.city')->all();

        if ($cityFilter) {
            $query->where('n.city', $cityFilter);
        }

        // NOTE: paginate() (not simplePaginate()) — the Prev/Next view
        // shows a running total ("X-Y of :total"), which needs the extra
        // COUNT query paginate() runs and simplePaginate() deliberately
        // skips. Fine at this scale (hundreds of rows, not millions).
        // CHANGED 26 Aug 2026, 12th pass — per Chris: "display 10 records
        // in one screen" (20/page needed an inner scrollbar to see them
        // all, breaking the no-scroll rule) — 10 fits the row height with
        // no scrollbar and keeps every row's text fully readable.
        $nodes = $query->orderBy('n.node_name')
            ->select('n.node_id', 'n.node_name', 'n.node_name_zh', 'n.city')
            ->paginate(10)
            ->withQueryString();

        $groupRootNodeId = DB::table('cbe_hierarchy_nodes')
            ->where('group_label_id', $groupId)->whereNull('parent_node_id')->value('node_id');

        return view('admin.cbe-kpi.nodes', [
            'group' => $group,
            'levelName' => $levelName,
            'nodes' => $nodes,
            'cities' => $cities,
            'activeCity' => $cityFilter,
            'groupRootNodeId' => $groupRootNodeId,
        ]);
    }
}
