<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CbeFaithTerminologyService;
use App\Services\CbeReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// NEW 26 Aug 2026 — per Chris: "under this temple folder... we also
// need to know who are their member/follower if have register with
// temple/Club... may be open new screen show member profile and allow
// to search all field in the member profile, the search method like
// vendor profile or product profile where every field is a search
// criteria and with type ahead capabilities." This is the 4th tab
// (Members = agents registered to THIS specific temple, via
// cbe_group_memberships.cbe_node_id) — search-first (never a default
// full listing), every field its own criterion, results only after a
// deliberate Search. Each member's own profile shows their editable
// Volunteer/Follower/Officer/Committee status and full event
// participation history/count for this temple.
class AdminCbeMembersController extends Controller
{
    // Search screen. mode=main (default, no results shown yet) or
    // mode=search + do_search=1 (runs the query). Never lists every
    // member by default — per Chris: "dont just default all member
    // display ya."
    // NEW 26 Aug 2026, 21st pass — per Chris: "when records found the
    // profile screen stay put... user just click next and next record
    // will show... no need to display in a list of rows." Search no
    // longer returns a list — it jumps straight to the FIRST matching
    // member's full profile, and Prev/Next steps through the matched set
    // one full record at a time (same idea as flipping through index
    // cards). The list-building query below only PLUCKs the ordered IDs
    // (cheap), then the actual profile is loaded via the same
    // buildMemberProfileData() the direct-link show() route also uses.
    public function index(Request $request)
    {
        $nodeId = $request->get('node');
        $node = $nodeId ? DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first() : null;

        // NEW 28 Aug 2026 — per Chris: "develop all the soon programs" —
        // this screen used to hard 404 without a ?node=, which is why it
        // showed as "soon" for Admin (who has no single node, unlike an
        // officer). Now falls through to the same group→node picker
        // pattern already used by Entity Maintenance and Donor
        // Maintenance instead of failing. Covers Member Maintenance AND
        // Consultant Maintenance — both point at this same route/screen
        // (Consultant is a role tag set from within Member Maintenance,
        // per the 27 Aug 2026 comment further down this controller).
        if (! $node) {
            $groupId = $request->get('group');
            $groups = DB::table('group_labels')
                ->where('group_type', 'CBE')
                ->orderBy('group_name')
                ->get(['group_label_id', 'group_name']);

            $group = null;
            $nodes = collect();

            if ($groupId) {
                $group = DB::table('group_labels')
                    ->where('group_label_id', $groupId)
                    ->where('group_type', 'CBE')
                    ->first();

                if ($group) {
                    $nodes = DB::table('cbe_hierarchy_nodes as n')
                        ->join('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
                        ->where('n.group_label_id', $groupId)
                        ->orderBy('l.level_order')
                        ->orderBy('n.node_name')
                        ->select('n.node_id', 'n.node_name', 'n.node_name_zh', 'l.level_name')
                        ->get();
                }
            }

            return view('admin.cbe-kpi.node-picker', [
                'groups' => $groups,
                'group' => $group,
                'nodes' => $nodes,
                'pickerRoute' => 'admin.cbe-kpi.members',
                'pickerTitle' => __('cbe_masterfile.member_maintenance'),
            ]);
        }

        // REBUILT 28 Sep 2026 — per Chris (§96.23): the entity Members tab is a
        // view of the ONE member file, already filtered to this entity.
        // Search: Name, Mobile, Type tick boxes (any number), Plan, Fee
        // Status, Joined From–To, GO. Rows: # | Member Name | Mobile | Types |
        // Plan / Fee | Since | View / Edit (opens the member file record).
        // Role / Account Status / Member Code / Agent Code / Member Type
        // dropdown removed. "+ Add Member Here" searches the member file
        // first; a new person is added through Add New Member (duplicate
        // check) and comes straight back to this entity's Add Affiliation.
        [$primary, $secondary] = $this->localizedNames($node->node_name, $node->node_name_zh);
        $faithPracticeType = DB::table('group_labels')->where('group_label_id', $node->group_label_id)->value('faith_practice_type');
        $faithTerms = CbeFaithTerminologyService::terms($faithPracticeType);
        $tabCounts = AdminCbeParticipationController::tabCounts($nodeId);
        $typeLabels = \App\Services\MemberFileService::typeLabels();
        $plans = DB::table('cbe_membership_plans')->where('group_label_id', $node->group_label_id)->where('is_active', true)->orderBy('sort_order')->get();

        // CHANGED 28 Sep 2026 — per Chris: first display = Add | Search / View / Edit
        $doSearch = $request->has('do_search');
        $panel = in_array($request->get('panel'), ['add', 'list'], true) ? $request->get('panel') : ($doSearch ? 'list' : 'home');
        $rows = null;
        if ($doSearch) {
            $rows = $this->entityMembers($request, $node, $typeLabels);
        }

        // + Add Member Here: search the whole member file (name / mobile)
        $found = null;
        if ($panel === 'add' && ($request->filled('find_name') || $request->filled('find_mobile'))) {
            $fq = DB::table('agents as a')->where('a.is_deleted', false)->whereNotIn('a.role', ['ADMIN']);
            if ($v = trim((string) $request->get('find_name'))) {
                $fq->where(fn ($w) => $w->where('a.full_name', 'like', '%'.$v.'%')->orWhere('a.second_name', 'like', '%'.$v.'%')->orWhere('a.nick_name', 'like', '%'.$v.'%'));
            }
            if (($v = \App\Services\MemberFileService::normPhone($request->get('find_mobile'))) !== '') {
                $fq->whereRaw("REPLACE(REPLACE(REPLACE(a.phone,'-',''),' ',''),'+','') LIKE ?", ['%'.$v.'%']);
            }
            $found = $fq->orderBy('a.full_name')->limit(12)->get(['a.agent_id', 'a.full_name', 'a.second_name', 'a.nick_name', 'a.phone', 'a.city'])
                ->map(function ($a) use ($node, $typeLabels) {
                    $ex = collect(\App\Services\MemberFileService::existingAt($a->agent_id, $node->node_id));
                    $a->hereCodes = $ex->keys()->all();
                    $a->here = $ex->map(fn ($l, $t) => $t === 'COMMITTEE' ? ($l['plan'] ?? $typeLabels['COMMITTEE']) : ($typeLabels[$t] ?? $t))->values()->all();
                    $a->affiliated = \App\Services\MemberFileService::affiliatedTo($a->agent_id);
                    return $a;
                });
        }

        return view('admin.cbe-kpi.members.index', [
            'node' => $node, 'nodePrimary' => $primary, 'nodeSecondary' => $secondary,
            'faithTerms' => $faithTerms, 'tabCounts' => $tabCounts,
            'typeLabels' => $typeLabels, 'plans' => $plans, 'panel' => $panel,
            'memTypes' => DB::table('member_profile_options')->where('list_code', 'MEMTYPE')->where('is_active', true)->orderBy('sort_order')->get(['id', 'label']),
            'doSearch' => $doSearch, 'rows' => $rows, 'found' => $found,
        ]);
    }

    // People at this entity with their register lines here (one row per person).
    private function entityMembers(Request $request, object $node, array $typeLabels)
    {
        $nodeId = $node->node_id;
        $history = $request->get('status') === 'INACTIVE';   // dashboard "Suspended / ended" drill-down
        $ids = DB::table('cbe_group_memberships')->where('cbe_node_id', $nodeId)
            ->when($history, fn ($q) => $q->where('status', '!=', 'ACTIVE'))
            ->pluck('agent_id')->all();
        if (! $history && \Illuminate\Support\Facades\Schema::hasTable('cbe_practitioner_profiles')) {
            $ids = array_merge($ids, DB::table('cbe_practitioner_profiles')->where('cbe_node_id', $nodeId)->where('is_active', true)->pluck('agent_id')->all());
        }
        $ids = array_values(array_unique(array_filter($ids)));
        $q = DB::table('agents as a')->whereIn('a.agent_id', $ids ?: ['#'])->where('a.is_deleted', false);
        if ($v = trim((string) $request->get('name'))) {
            $q->where(fn ($w) => $w->where('a.full_name', 'like', '%'.$v.'%')->orWhere('a.second_name', 'like', '%'.$v.'%')->orWhere('a.nick_name', 'like', '%'.$v.'%'));
        }
        if (($v = \App\Services\MemberFileService::normPhone($request->get('mobile'))) !== '') {
            $q->whereRaw("REPLACE(REPLACE(REPLACE(a.phone,'-',''),' ',''),'+','') LIKE ?", ['%'.$v.'%']);
        }
        $people = $q->orderBy('a.full_name')->get(['a.agent_id', 'a.full_name', 'a.second_name', 'a.nick_name', 'a.phone']);

        $types = array_filter((array) $request->get('types', []));
        $from = trim((string) ($request->get('joined_from') ?? ''));
        $to = trim((string) ($request->get('joined_to') ?? ''));
        $plan = (string) $request->get('plan_id');
        $fee = (string) $request->get('fee_status');
        $memType = (string) $request->get('membership_type_id');
        $planType = DB::table('cbe_membership_plans')->pluck('membership_type_id', 'id')->all();
        $out = collect();
        foreach ($people as $p) {
            $lines = [];
            foreach (\App\Services\MemberFileService::register($p->agent_id) as $r) {
                if ($r['node_id'] !== $nodeId) {
                    continue;
                }
                foreach ($r['lines'] as $l) {
                    if ($history ? $l['status'] !== 'ACTIVE' : $l['status'] === 'ACTIVE') {
                        $lines[] = $l;
                    }
                }
            }
            if (! $history) {
                foreach (\App\Services\MemberFileService::committeePositions($p->agent_id) as $c) {
                    if ($c->current && $c->group_label_id === $node->group_label_id) {
                        $lines[] = ['type' => 'COMMITTEE', 'label' => $c->position_label, 'status' => 'ACTIVE', 'from' => $c->term_start_date, 'module' => 'committee', 'plan' => null, 'fee_status' => null, 'paid_until' => null, 'plan_id' => null];
                    }
                }
            }
            if (! $lines) {
                continue;
            }
            $has = collect($lines)->pluck('type')->all();
            if ($types && ! array_intersect($types, $has)) {
                continue;
            }
            $mem = collect($lines)->firstWhere('type', 'MEMBER');
            if ($plan !== '' && (! $mem || ($mem['plan_id'] ?? null) !== $plan)) {
                continue;
            }
            if ($memType !== '' && (! $mem || ($planType[$mem['plan_id'] ?? ''] ?? null) !== $memType)) {
                continue;
            }
            if ($fee !== '' && (! $mem || $mem['fee_status'] !== $fee)) {
                continue;
            }
            $since = collect($lines)->pluck('from')->filter()->min();
            if ($from !== '' && (! $since || substr($since, 0, 10) < $from)) {
                continue;
            }
            if ($to !== '' && (! $since || substr($since, 0, 10) > $to)) {
                continue;
            }
            $ord = array_flip(array_keys($typeLabels));
            usort($lines, fn ($x, $y) => ($ord[$x['type']] ?? 99) <=> ($ord[$y['type']] ?? 99));
            $p->lines = $lines;
            $p->member = $mem;
            $p->since = $since;
            $out->push($p);
        }
        $per = max(3, min(50, (int) $request->get('per_page', 12)));
        $page = max(1, (int) $request->get('page', 1));

        return new \Illuminate\Pagination\LengthAwarePaginator($out->slice(($page - 1) * $per, $per)->values(), $out->count(), $per, $page,
            ['path' => $request->url(), 'query' => $request->except('page')]);
    }

    // Name-field typeahead — quick jump straight to a member's profile
    // without needing the full Search button, scoped to this temple only.
    public function typeahead(Request $request)
    {
        $nodeId = $request->get('node');
        $q = trim((string) $request->get('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $rows = DB::table('cbe_group_memberships as m')
            ->join('agents as a', 'a.agent_id', '=', 'm.agent_id')
            ->where('m.cbe_node_id', $nodeId)
            ->where(function ($w) use ($q) {
                $w->where('a.full_name', 'like', '%'.$q.'%')
                    ->orWhere('a.agent_code', 'like', '%'.$q.'%');
            })
            ->orderBy('a.full_name')
            ->limit(20)
            ->select('m.membership_id', 'a.full_name', 'a.agent_code')
            ->get()
            ->map(fn ($r) => ['membership_id' => $r->membership_id, 'label' => $r->full_name.' ('.$r->agent_code.')']);

        return response()->json($rows);
    }

    // Full member profile — contact info, editable status, and event
    // participation history/count for THIS temple. Per Chris: "does the
    // member profile flag that the status is a volunteer or follower?
    // and would we able to see this member how many times he or she
    // visit or participate the temple/club event and history of he
    // participate events??"
    public function show(Request $request)
    {
        $nodeId = $request->get('node');
        $membershipId = $request->get('id');

        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        abort_if(! $node, 404);

        $profileData = $this->buildMemberProfileData($node, $membershipId);
        abort_if(! $profileData, 404);

        [$primary, $secondary] = $this->localizedNames($node->node_name, $node->node_name_zh);

        return view('admin.cbe-kpi.members.show', array_merge($profileData, [
            'node' => $node,
            'nodePrimary' => $primary,
            'nodeSecondary' => $secondary,
            'browsing' => false,
            'tabCounts' => AdminCbeParticipationController::tabCounts($nodeId),
        ]));
    }

    // Shared by show() (direct link, e.g. from a KPI drill-down or the
    // Appointments 7th tab) and index()'s search-browse path (Prev/Next
    // through matched results) — both render the exact same profile view,
    // just with a different navigation bar around it.
    private function buildMemberProfileData(object $node, ?string $membershipId): ?array
    {
        $nodeId = $node->node_id;

        $membership = DB::table('cbe_group_memberships as m')
            ->join('agents as a', 'a.agent_id', '=', 'm.agent_id')
            ->where('m.membership_id', $membershipId)
            ->where('m.cbe_node_id', $nodeId)
            ->select('m.membership_id', 'a.agent_id', 'a.agent_code', 'a.member_code', 'a.full_name', 'a.second_name', 'a.phone', 'a.email', 'a.role', 'a.status as account_status', 'm.member_type', 'm.status', 'm.joined_at')
            ->first();
        if (! $membership) {
            return null;
        }

        $history = DB::table('cbe_event_participants as p')
            ->join('cbe_events as e', 'e.event_id', '=', 'p.event_id')
            ->leftJoin('cbe_receipts as r', function ($j) {
                $j->on('r.source_id', '=', 'p.participant_record_id')->where('r.source_type', 'EVENT_SALE');
            })
            ->where('e.cbe_node_id', $nodeId)
            ->where('p.agent_id', $membership->agent_id)
            ->orderByDesc('p.paid_at')
            ->select('e.event_name', 'e.event_name_zh', 'p.item_name', 'p.quantity', 'p.amount_paid', 'p.paid_at', 'r.receipt_id')
            ->paginate(10, ['*'], 'hpage')
            ->withQueryString();

        $totals = DB::table('cbe_event_participants as p')
            ->join('cbe_events as e', 'e.event_id', '=', 'p.event_id')
            ->where('e.cbe_node_id', $nodeId)
            ->where('p.agent_id', $membership->agent_id)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(amount_paid),0) as total')
            ->first();

        $events = DB::table('cbe_events')->where('cbe_node_id', $nodeId)->orderByDesc('event_start_date')->get(['event_id', 'event_name', 'event_name_zh', 'event_start_date']);

        $advisors = DB::table('cbe_group_memberships as m')
            ->join('agents as a', 'a.agent_id', '=', 'm.agent_id')
            ->where('m.cbe_node_id', $nodeId)
            ->orderBy('a.full_name')
            ->select('a.agent_id', 'a.full_name', 'a.agent_code')
            ->get();

        $appointments = DB::table('cbe_appointments as ap')
            ->join('agents as adv', 'adv.agent_id', '=', 'ap.advisor_id')
            ->leftJoin('cbe_receipts as r', function ($j) {
                $j->on('r.source_id', '=', 'ap.appointment_id')->where('r.source_type', 'APPOINTMENT');
            })
            ->where('ap.cbe_node_id', $nodeId)
            ->where('ap.agent_id', $membership->agent_id)
            ->orderByDesc('ap.appointment_date')
            ->select('ap.appointment_type', 'ap.appointment_date', 'ap.fee_amount', 'ap.notes', 'adv.full_name as advisor_name', 'r.receipt_id')
            ->paginate(10, ['*'], 'apage')
            ->withQueryString();

        $appointmentCount = DB::table('cbe_appointments')
            ->where('cbe_node_id', $nodeId)
            ->where('agent_id', $membership->agent_id)
            ->count();

        // CHANGED 12 Sep 2026 — per Chris: a community can now enable
        // SEVERAL appointment positions at once (e.g. both "Sensei" and
        // "Legal Advisor"), not just one — see CbeFaithTerminologyService.
        // The Log Appointment form lets the member pick which position
        // they're booking with (hidden automatically when there's only
        // one), and each position's own reason list populates the
        // Reason dropdown via JS.
        $positions = \App\Services\CbeFaithTerminologyService::positionsForGroup($node->group_label_id);
        $faithTerms = $positions[0];

        // NEW 27 Aug 2026 — per Chris: "master file maintenance ... this
        // is where you set up consultant." Multi-select role tags
        // (VOLUNTEER/FOLLOWER/CONSULTANT) — a person can hold more than
        // one at once, unlike the single Member Status dropdown above.
        $roleTags = DB::table('cbe_member_role_tags')->where('membership_id', $membership->membership_id)
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('cbe_member_role_tags', 'status'), fn ($q) => $q->where('status', 'ACTIVE'))->pluck('tag')->all();   // CHANGED 28 Sep 2026 — active lines only

        // NEW 25 Sep 2026 -- per Chris: "have you inserted the committee
        // list to the member profile?" -- this person's own committee
        // position history (current + previous terms), read from the
        // same group_committee_members table the Group Name & Hierarchy
        // Levels > Committee/Management Team tab writes to. No separate
        // record kept here -- always the live source of truth.
        $today = now()->toDateString();
        $committeePositions = DB::table('group_committee_members as gcm')
            ->join('cbe_committee_position_types as pt', 'pt.id', '=', 'gcm.position_type_id')
            ->where('gcm.group_label_id', $node->group_label_id)
            ->where('gcm.agent_id', $membership->agent_id)
            ->select('gcm.id as member_id', 'gcm.term_start_date', 'gcm.term_end_date', 'pt.position_label')
            ->orderByDesc('gcm.term_start_date')
            ->get();
        $committeeCurrent = $committeePositions->filter(fn ($c) => ! $c->term_end_date || $c->term_end_date >= $today)->values();
        $committeePrevious = $committeePositions->filter(fn ($c) => $c->term_end_date && $c->term_end_date < $today)->values();

        // NEW 25 Sep 2026 -- per Chris: "it should work both way, add
        // customer look for existing member, add member look for
        // customer." This is the "look for customer FROM the member
        // side" half -- any cbe_customers row already linked to this
        // Agent (via the agent_id column added by migration
        // 2026_09_18_000002), scoped to this same CBE community, so a
        // member from Temple A never sees a customer row from an
        // unrelated Temple B community.
        $linkedCustomers = DB::table('cbe_customers')
            ->where('agent_id', $membership->agent_id)
            ->whereIn('cbe_node_id', DB::table('cbe_hierarchy_nodes')->where('group_label_id', $node->group_label_id)->pluck('node_id'))
            ->orderByDesc('created_at')
            ->get();

        // NEW 25 Sep 2026 -- per Chris: "a member can participate in
        // marketplace therefore he or she can be a vendor." Vendor
        // registration is platform-wide (not scoped to one CBE node,
        // unlike Customer/Donor), so this is simply every cbe_vendors
        // row already linked to this Agent, with no node filter.
        $linkedVendors = DB::table('cbe_vendors')
            ->where('agent_id', $membership->agent_id)
            ->orderByDesc('created_at')
            ->get();

        return [
            'membership' => $membership,
            'roleTags' => $roleTags,
            'history' => $history,
            'participationCount' => (int) $totals->cnt,
            'participationTotal' => (float) $totals->total,
            'events' => $events,
            'advisors' => $advisors,
            'appointments' => $appointments,
            'appointmentCount' => $appointmentCount,
            'faithTerms' => $faithTerms,
            'positions' => $positions,
            'committeeCurrent' => $committeeCurrent,
            'committeePrevious' => $committeePrevious,
            'linkedCustomers' => $linkedCustomers,
            'linkedVendors' => $linkedVendors,
        ];
    }

    // NEW 25 Sep 2026 -- per Chris: search for an existing, NOT-YET-
    // LINKED cbe_customers row (agent_id still null) within this same
    // CBE community, so a Customer record created before this Member
    // existed can be linked onto them instead of staying a separate,
    // disconnected record.
    public function customerTypeahead(Request $request)
    {
        $nodeId = $request->get('node');
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        abort_if(! $node, 404);

        $q = trim((string) $request->get('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $customers = DB::table('cbe_customers')
            ->whereIn('cbe_node_id', DB::table('cbe_hierarchy_nodes')->where('group_label_id', $node->group_label_id)->pluck('node_id'))
            ->whereNull('agent_id')
            ->where('customer_name', 'like', "%{$q}%")
            ->orderBy('customer_name')
            ->limit(20)
            ->get(['customer_id', 'customer_name', 'phone', 'email']);

        return response()->json($customers);
    }

    // NEW 25 Sep 2026 -- per Chris: links an existing, unlinked Customer
    // record onto this Member's Agent record. From this point on that
    // Customer's name/phone/email are the Member's own (synced once
    // here, same snapshot approach used when Add Customer links a
    // Member) -- never independently re-typed again.
    public function linkCustomer(Request $request)
    {
        $nodeId = $request->get('node');
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        abort_if(! $node, 404);

        $request->validate([
            'membership_id' => ['required', 'uuid'],
            'customer_id' => ['required', 'uuid', 'exists:cbe_customers,customer_id'],
        ]);

        $membership = DB::table('cbe_group_memberships')->where('membership_id', $request->membership_id)->first();
        abort_if(! $membership, 404);
        $agent = DB::table('agents')->where('agent_id', $membership->agent_id)->first();
        abort_if(! $agent, 404);

        $customer = DB::table('cbe_customers')->where('customer_id', $request->customer_id)->first();
        abort_if(! $customer || $customer->agent_id, 404);

        DB::table('cbe_customers')->where('customer_id', $customer->customer_id)->update([
            'agent_id' => $agent->agent_id,
            'customer_name' => $agent->full_name,
            'phone' => $agent->phone,
            'email' => $agent->email,
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.cbe-kpi.members.show', ['node' => $nodeId, 'id' => $request->membership_id])->with('cbe_customer_linked', true);
    }

    // NEW 25 Sep 2026 -- same "look for it from the member side" idea
    // as customerTypeahead(), for Vendor instead. Platform-wide search
    // (Vendor registration isn't tied to one CBE node).
    public function vendorTypeahead(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $vendors = DB::table('cbe_vendors')
            ->whereNull('agent_id')
            ->where('vendor_name', 'like', "%{$q}%")
            ->orderBy('vendor_name')
            ->limit(20)
            ->get(['vendor_id', 'vendor_name', 'phone', 'email']);

        return response()->json($vendors);
    }

    // NEW 25 Sep 2026 -- links an existing, unlinked Vendor record onto
    // this Member's Agent record, same rule Customer linking already
    // follows: name/phone/email become the Member's own from this
    // point on, never independently re-typed again.
    public function linkVendor(Request $request)
    {
        $nodeId = $request->get('node');
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        abort_if(! $node, 404);

        $request->validate([
            'membership_id' => ['required', 'uuid'],
            'vendor_id' => ['required', 'uuid', 'exists:cbe_vendors,vendor_id'],
        ]);

        $membership = DB::table('cbe_group_memberships')->where('membership_id', $request->membership_id)->first();
        abort_if(! $membership, 404);
        $agent = DB::table('agents')->where('agent_id', $membership->agent_id)->first();
        abort_if(! $agent, 404);

        $vendor = DB::table('cbe_vendors')->where('vendor_id', $request->vendor_id)->first();
        abort_if(! $vendor || $vendor->agent_id, 404);

        DB::table('cbe_vendors')->where('vendor_id', $vendor->vendor_id)->update([
            'agent_id' => $agent->agent_id,
            'vendor_name' => $agent->full_name,
            'phone' => $agent->phone,
            'email' => $agent->email,
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.cbe-kpi.members.show', ['node' => $nodeId, 'id' => $request->membership_id])->with('cbe_vendor_linked', true);
    }

    // NEW 27 Aug 2026 — per Chris: "master file maintenance ... this is
    // where you set up consultant donor, members." Search screen for
    // Find & Link Existing Agent posts here — adds a cbe_group_memberships
    // row for an agent already registered anywhere on the platform (or
    // moves their existing membership in this SAME CBE community to this
    // node, since one agent can only hold one membership per community —
    // see cbe_group_memberships' unique(agent_id, group_label_id)).
    public function link(Request $request)
    {
        $nodeId = $request->get('node');
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        abort_if(! $node, 404);

        $agentId = $request->get('agent_id');
        abort_if(! DB::table('agents')->where('agent_id', $agentId)->exists(), 404);

        // CHANGED 28 Sep 2026 — per Chris: one link per person per ENTITY
        // (he can be at Temple A and Temple B of the same CBE group); an
        // existing link at another entity is never moved. The link carries a
        // MEMBER line in the Affiliation Register.
        $existing = DB::table('cbe_group_memberships')
            ->where('agent_id', $agentId)
            ->where('cbe_node_id', $nodeId)
            ->first();

        if ($existing) {
            DB::table('cbe_group_memberships')->where('membership_id', $existing->membership_id)->update([
                'status' => 'ACTIVE',
                'updated_at' => now(),
            ]);
            $membershipId = $existing->membership_id;
            \App\Services\MemberFileService::addTypes($agentId, $nodeId, ['MEMBER'], null, 'STAFF');
        } else {
            $membershipId = (string) Str::uuid();
            $isPrimary = ! DB::table('cbe_group_memberships')->where('agent_id', $agentId)->where('is_primary', true)->exists();
            DB::table('cbe_group_memberships')->insert([
                'membership_id' => $membershipId,
                'agent_id' => $agentId,
                'group_label_id' => $node->group_label_id,
                'cbe_node_id' => $nodeId,
                'is_primary' => $isPrimary,
                'status' => 'ACTIVE',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            \App\Services\MemberFileService::addTypes($agentId, $nodeId, ['MEMBER'], null, 'STAFF');
        }

        return redirect()->route('admin.cbe-kpi.members.show', ['node' => $nodeId, 'id' => $membershipId])->with('cbe_link_saved', true);
    }

    // NEW 27 Aug 2026 — Register New Member: creates a brand-new agent
    // record (no such flow existed for CBE members before — only
    // linking someone already registered was possible) plus their
    // membership at this node, with optional initial role tags.
    public function store(Request $request)
    {
        $nodeId = $request->get('node');
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        abort_if(! $node, 404);

        $fullName = trim((string) $request->get('full_name'));
        $phone = trim((string) $request->get('phone'));

        if ($fullName === '' || $phone === '') {
            return back()->withErrors(array_filter([
                'full_name' => $fullName === '' ? __('admin_cbe_directory.err_name_required') : null,
                'phone' => $phone === '' ? __('admin_cbe_directory.err_phone_required') : null,
            ]))->withInput();
        }

        $email = trim((string) $request->get('email'));
        if ($email === '') {
            // No real email on file — most walk-in temple members won't
            // have one to give. agents.email is NOT NULL + unique, so a
            // synthetic placeholder satisfies the schema without forcing
            // every member through an email-verification flow they'll
            // never use. Never shown, never used to log in.
            $email = 'member-'.strtolower(Str::random(10)).'@noemail.generallink.local';
        } elseif (DB::table('agents')->where('email', strtolower($email))->exists()) {
            return back()->withErrors(['email' => __('admin_cbe_directory.err_email_taken')])->withInput();
        }

        $agentId = (string) Str::uuid();
        do {
            $agentCode = 'CBE-'.strtoupper(Str::random(8));
        } while (DB::table('agents')->where('agent_code', $agentCode)->exists());

        DB::table('agents')->insert([
            'agent_id' => $agentId,
            'agent_code' => $agentCode,
            'full_name' => $fullName,
            'second_name' => trim((string) $request->get('second_name')) ?: null,
            'email' => strtolower($email),
            'password_hash' => Hash::make(Str::random(32)),
            'phone' => $phone,
            'role' => 'INTRODUCER',
            'status' => 'ACTIVE',
            'parent_id' => null,
            'hierarchy_path' => '/'.$agentId.'/',
            'group_id' => null,
            'group_label_id' => $node->group_label_id,
            'cbe_node_id' => $nodeId,
            'qr_code_token' => Str::random(10),
            'created_by' => auth('agent')->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $membershipId = (string) Str::uuid();
        $isPrimary = ! DB::table('cbe_group_memberships')->where('agent_id', $agentId)->where('is_primary', true)->exists();
        DB::table('cbe_group_memberships')->insert([
            'membership_id' => $membershipId,
            'agent_id' => $agentId,
            'group_label_id' => $node->group_label_id,
            'cbe_node_id' => $nodeId,
            'is_primary' => $isPrimary,
            'status' => 'ACTIVE',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // CHANGED 28 Sep 2026 — Affiliation Register lines: MEMBER + the ticked types.
        $tags = array_intersect((array) $request->get('tags', []), ['VOLUNTEER', 'FOLLOWER', 'CONSULTANT']);
        \App\Services\MemberFileService::addTypes($agentId, $nodeId, array_merge(['MEMBER'], $tags), null, 'STAFF');

        return redirect()->route('admin.cbe-kpi.members.show', ['node' => $nodeId, 'id' => $membershipId])->with('cbe_member_saved', true);
    }

    // NEW 27 Aug 2026 — replaces the single Member Status dropdown for
    // Volunteer/Follower/Consultant specifically (a person can hold more
    // than one at once — see cbe_member_role_tags migration). Officer/
    // Committee stay on updateStatus()/member_type since those come from
    // a real login-role (cbe_node_officers) or a term-dated committee
    // assignment, not a status flag someone just ticks on and off.
    public function updateTags(Request $request)
    {
        $nodeId = $request->get('node');
        $membershipId = $request->get('membership_id');

        abort_if(
            ! DB::table('cbe_group_memberships')->where('membership_id', $membershipId)->where('cbe_node_id', $nodeId)->exists(),
            404
        );

        $tags = array_intersect((array) $request->get('tags', []), ['VOLUNTEER', 'FOLLOWER', 'CONSULTANT']);

        // CHANGED 28 Sep 2026 — per Chris: the Affiliation Register keeps full
        // history. Only these 3 types are touched here: unticked ones are
        // ENDED (moved to History, never deleted); newly ticked ones are
        // added. MEMBER / DONOR / SPONSOR … lines are left as they are.
        $m = DB::table('cbe_group_memberships')->where('membership_id', $membershipId)->first();
        foreach (DB::table('cbe_member_role_tags')->where('membership_id', $membershipId)->where('status', 'ACTIVE')
            ->whereIn('tag', ['VOLUNTEER', 'FOLLOWER', 'CONSULTANT'])->whereNotIn('tag', $tags ?: ['#'])->pluck('tag_id') as $tid) {
            \App\Services\MemberFileService::endLine($tid);
        }
        if ($tags) {
            \App\Services\MemberFileService::addTypes($m->agent_id, $nodeId, $tags, null, 'STAFF');
        }

        return redirect()->route('admin.cbe-kpi.members.show', ['node' => $nodeId, 'id' => $membershipId])->with('cbe_tags_saved', true);
    }

    // NEW 26 Aug 2026, 20th pass — per Chris: Members previously had no
    // way to actually LOG an event participation/purchase (only
    // Customers did) — closing that gap, same pattern as
    // AdminCbeCustomersController::storeParticipation().
    public function storeParticipation(Request $request)
    {
        $nodeId = $request->get('node');
        $agentId = $request->get('agent_id');

        abort_if(! DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->exists(), 404);
        abort_if(! DB::table('cbe_group_memberships')->where('cbe_node_id', $nodeId)->where('agent_id', $agentId)->exists(), 404);

        $eventId = $request->get('event_id');
        abort_if(! DB::table('cbe_events')->where('event_id', $eventId)->where('cbe_node_id', $nodeId)->exists(), 422);

        $itemName = trim((string) $request->get('item_name'));
        if ($itemName === '') {
            return back()->withErrors(['item_name' => 'Item / package name is required.']);
        }

        $participantId = (string) Str::uuid();
        $amountPaid = (float) ($request->get('amount_paid') ?: 0);
        DB::table('cbe_event_participants')->insert([
            'participant_record_id' => $participantId,
            'event_id' => $eventId,
            'agent_id' => $agentId,
            'customer_id' => null,
            'item_name' => $itemName,
            'quantity' => (int) ($request->get('quantity') ?: 1),
            'amount_paid' => $amountPaid,
            'paid_at' => $request->get('paid_at') ?: now()->toDateString(),
            'notes' => trim((string) $request->get('notes')) ?: null,
            'recorded_by' => auth('agent')->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // NEW 26 Aug 2026 — per Chris: every collection issues its own
        // receipt, the same standard as donations. Event sales had no
        // journal posting at all before this — CbeReceiptService posts it
        // now, same as everything else in the books.
        if ($amountPaid > 0) {
            $payerName = DB::table('agents')->where('agent_id', $agentId)->value('full_name') ?? 'Member';
            CbeReceiptService::issue($nodeId, 'EVENT_SALE', $participantId, $payerName, $itemName, $amountPaid, auth('agent')->id());
        }

        $membershipId = DB::table('cbe_group_memberships')->where('cbe_node_id', $nodeId)->where('agent_id', $agentId)->value('membership_id');

        return redirect()->route('admin.cbe-kpi.members.show', ['node' => $nodeId, 'id' => $membershipId])->with('cbe_participation_saved', true);
    }

    // NEW 26 Aug 2026, 20th pass — per Chris: "appointment with temple
    // for prayer and advise from sensei... all this is important."
    // Logs an appointment already held/arranged (not a forward booking
    // calendar, per Chris's answer) between this member and one of the
    // temple's own registered members acting as advisor.
    public function storeAppointment(Request $request)
    {
        $nodeId = $request->get('node');
        $agentId = $request->get('agent_id');

        abort_if(! DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->exists(), 404);
        abort_if(! DB::table('cbe_group_memberships')->where('cbe_node_id', $nodeId)->where('agent_id', $agentId)->exists(), 404);

        $advisorId = $request->get('advisor_id');
        abort_if(! DB::table('cbe_group_memberships')->where('cbe_node_id', $nodeId)->where('agent_id', $advisorId)->exists(), 422);

        // CHANGED 12 Sep 2026 — per Chris: a community can now enable
        // SEVERAL appointment positions at once, and each position has
        // its OWN admin-editable reason list (never a hardcoded set).
        // The submitted position must be one this community actually
        // has enabled, and the submitted reason must belong to THAT
        // position's own list — never trusted blindly from the form.
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        $positions = CbeFaithTerminologyService::positionsForGroup($node->group_label_id ?? null);
        $positionId = $request->get('practice_type_id');
        $position = collect($positions)->firstWhere('id', $positionId)
            ?? (count($positions) === 1 ? $positions[0] : null);
        if (! $position) {
            return back()->withErrors(['practice_type_id' => 'A valid position is required.']);
        }

        $type = trim((string) $request->get('appointment_type'));
        if ($type === '' || ! in_array($type, $position['reasons'], true)) {
            return back()->withErrors(['appointment_type' => 'A valid appointment type is required.']);
        }

        $appointmentId = (string) Str::uuid();
        $feeAmount = (float) ($request->get('fee_amount') ?: 0);
        DB::table('cbe_appointments')->insert([
            'appointment_id' => $appointmentId,
            'cbe_node_id' => $nodeId,
            'practice_type_id' => $position['id'],
            'advisor_id' => $advisorId,
            'agent_id' => $agentId,
            'customer_id' => null,
            'appointment_type' => $type,
            'fee_amount' => $feeAmount,
            'appointment_date' => $request->get('appointment_date') ?: now()->toDateString(),
            'notes' => trim((string) $request->get('notes')) ?: null,
            'recorded_by' => auth('agent')->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // NEW 26 Aug 2026 — per Chris: sensei service fees are a
        // collection too — can be zero for a free visit, but when a fee
        // is charged the system issues a receipt exactly like donations
        // and event sales.
        //
        // CHANGED 12 Sep 2026 — $type is now the Admin-typed reason text
        // itself (e.g. "Contract Review"), not a fixed code — passed
        // straight through, no translation-key lookup needed.
        if ($feeAmount > 0) {
            $payerName = DB::table('agents')->where('agent_id', $agentId)->value('full_name') ?? 'Member';
            CbeReceiptService::issue($nodeId, 'APPOINTMENT', $appointmentId, $payerName, $type, $feeAmount, auth('agent')->id());
        }

        $membershipId = DB::table('cbe_group_memberships')->where('cbe_node_id', $nodeId)->where('agent_id', $agentId)->value('membership_id');

        return redirect()->route('admin.cbe-kpi.members.show', ['node' => $nodeId, 'id' => $membershipId])->with('cbe_appointment_saved', true);
    }

    public function updateStatus(Request $request)
    {
        $nodeId = $request->get('node');
        $membershipId = $request->get('membership_id');

        abort_if(
            ! DB::table('cbe_group_memberships')->where('membership_id', $membershipId)->where('cbe_node_id', $nodeId)->exists(),
            404
        );

        $memberType = $request->get('member_type') ?: null;
        DB::table('cbe_group_memberships')->where('membership_id', $membershipId)->update([
            'member_type' => $memberType,
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.cbe-kpi.members.show', ['node' => $nodeId, 'id' => $membershipId])->with('cbe_status_saved', true);
    }

    private function localizedNames(?string $nameEn, ?string $nameZh): array
    {
        if (app()->getLocale() === 'zh' && $nameZh) {
            return [$nameZh, $nameEn];
        }

        return [$nameEn, $nameZh];
    }
}
