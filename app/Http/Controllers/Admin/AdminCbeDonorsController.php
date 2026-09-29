<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CbeFaithTerminologyService;
use App\Services\CbeReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// NEW 26 Aug 2026 — per Chris: "donor/sponsor is retrieve from Sponsor
// profile, remember ya Sponsor/donor can particiate many temple many
// places also ya, Penang donor / sponsor can be a Klang one of the
// temple as well as his homwtown in Penang ya." 6th tab. A donor's
// "home" temple stays on cbe_donors.cbe_node_id (unchanged, per that
// table's own design), and any EXTRA temple links live in the new
// cbe_donor_sponsorships pivot — a donor shows up in a temple's search
// if EITHER points to that temple.
class AdminCbeDonorsController extends Controller
{
    // Search screen — donors linked (home OR pivot) to THIS temple.
    // mode=main (default) or mode=search+do_search (runs the query).
    // NEW 26 Aug 2026, 21st pass — per Chris: "when records found the
    // profile screen stay put... no need to display in a list of rows."
    // Same redesign as Members/Customers: search jumps straight to the
    // first matching donor's full profile, Prev/Next steps through the
    // matched set. The "find any donor to link" picker below is
    // UNCHANGED (per Chris) — that's a picker for attaching an existing
    // donor to this temple, not a profile browse.
    public function index(Request $request)
    {
        $nodeId = $request->get('node');
        $node = $nodeId ? DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first() : null;

        // NEW 28 Aug 2026 — per Chris: "develop all the soon programs" —
        // this screen used to hard 404 without a ?node=, which is why it
        // showed as "soon" for Admin (who has no single node, unlike an
        // officer). Now falls through to the same group→node picker
        // pattern already used by Entity Maintenance
        // (AdminCbeHierarchyNodeController::create()) instead of failing.
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
                'pickerRoute' => 'admin.cbe-kpi.donors',
                'pickerTitle' => __('cbe_masterfile.donor_maintenance'),
            ]);
        }

        $mode = $request->get('mode', 'main');
        $doSearch = $mode === 'search' && $request->has('do_search');

        // Broader "find any donor to link" search, system-wide — used to
        // link a donor already registered at a DIFFERENT temple to this
        // one too (per Chris's Penang/Klang example). Stays a compact
        // list — this is a picker, not a profile browse.
        $findMode = $request->get('find_mode', '');
        $findResults = null;
        if ($findMode === 'search' && $request->has('find_do_search')) {
            $fq = DB::table('cbe_donors as d')
                ->where('d.cbe_node_id', '!=', $nodeId)
                ->whereNotIn('d.donor_id', function ($sub) use ($nodeId) {
                    $sub->select('donor_id')->from('cbe_donor_sponsorships')->where('cbe_node_id', $nodeId);
                });
            if ($v = trim((string) $request->get('find_donor_name'))) {
                $fq->where('d.donor_name', 'like', '%'.$v.'%');
            }
            if ($v = trim((string) $request->get('find_phone'))) {
                $fq->where('d.phone', 'like', '%'.$v.'%');
            }
            $findResults = $fq->orderBy('d.donor_name')
                ->select('d.donor_id', 'd.donor_name', 'd.phone', 'd.email')
                ->limit(50)
                ->get();
        }

        [$primary, $secondary] = $this->localizedNames($node->node_name, $node->node_name_zh);

        // NEW 27 Aug 2026 — per Chris: persistent Sponsor & Donor/
        // Participation/Appointments tab bar, must be in EVERY branch,
        // before and after search, never disappear (same rule as
        // Members/Customers).
        $faithPracticeType = DB::table('group_labels')->where('group_label_id', $node->group_label_id)->value('faith_practice_type');
        $faithTerms = CbeFaithTerminologyService::terms($faithPracticeType);
        $tabCounts = AdminCbeParticipationController::tabCounts($nodeId);

        if (! $doSearch) {
            // NEW 28 Aug 2026 — per Chris: "this row put inside Total
            // Donor box 4 as drill down screen, remove from Entity KPI
            // screen. make more space for box 7 in Entity KPI" — the Top
            // Donors/Sponsors ranking (used to be its own standalone box
            // under Box 7 on the KPI dashboard) now lives here instead,
            // since this IS the screen Box 4's "Total Donors" row already
            // drills into. Same query AdminCbeKpiController used, unmoved.
            $topDonors = DB::table('cbe_contributions as c')
                ->join('cbe_events as e', 'e.event_id', '=', 'c.event_id')
                ->join('cbe_donors as d', 'd.donor_id', '=', 'c.donor_id')
                ->where('e.cbe_node_id', $nodeId)
                ->where('c.status', '!=', 'CANCELLED')
                ->groupBy('c.donor_id', 'd.donor_name')
                ->select('c.donor_id', 'd.donor_name', DB::raw('SUM(c.received_amount) as total_received'), DB::raw('COUNT(*) as cnt'))
                ->orderByDesc('total_received')
                ->limit(5)
                ->get();

            return view('admin.cbe-kpi.donors.index', [
                'node' => $node, 'nodePrimary' => $primary, 'nodeSecondary' => $secondary,
                'mode' => $mode, 'doSearch' => $doSearch, 'resultTotal' => null,
                'findMode' => $findMode, 'findResults' => $findResults,
                'faithTerms' => $faithTerms, 'tabCounts' => $tabCounts,
                'topDonors' => $topDonors,
            ]);
        }

        $q = DB::table('cbe_donors as d')
            ->where(function ($w) use ($nodeId) {
                $w->where('d.cbe_node_id', $nodeId)
                    ->orWhereIn('d.donor_id', function ($sub) use ($nodeId) {
                        $sub->select('donor_id')->from('cbe_donor_sponsorships')->where('cbe_node_id', $nodeId);
                    });
            });

        if ($v = trim((string) $request->get('donor_name'))) {
            $q->where('d.donor_name', 'like', '%'.$v.'%');
        }
        if ($v = $request->get('donor_type')) {
            $q->where('d.donor_type', $v);
        }
        if ($v = trim((string) $request->get('contact_person'))) {
            $q->where('d.contact_person', 'like', '%'.$v.'%');
        }
        if ($v = trim((string) $request->get('phone'))) {
            $q->where('d.phone', 'like', '%'.$v.'%');
        }
        if ($v = trim((string) $request->get('email'))) {
            $q->where('d.email', 'like', '%'.$v.'%');
        }
        if ($v = trim((string) $request->get('address'))) {
            $q->where('d.address', 'like', '%'.$v.'%');
        }
        if ($v = trim((string) $request->get('notes'))) {
            $q->where('d.notes', 'like', '%'.$v.'%');
        }

        $orderedIds = $q->orderBy('d.donor_name')->pluck('d.donor_id')->toArray();
        $total = count($orderedIds);

        if ($total === 0) {
            return view('admin.cbe-kpi.donors.index', [
                'node' => $node, 'nodePrimary' => $primary, 'nodeSecondary' => $secondary,
                'mode' => $mode, 'doSearch' => $doSearch, 'resultTotal' => 0,
                'findMode' => $findMode, 'findResults' => $findResults,
                'faithTerms' => $faithTerms, 'tabCounts' => $tabCounts,
            ]);
        }

        $pos = max(1, min($total, (int) $request->get('pos', 1)));
        $profileData = $this->buildDonorProfileData($node, $orderedIds[$pos - 1]);

        $baseParams = $request->except(['pos']);
        $modifyParams = $request->except(['do_search', 'pos']);

        return view('admin.cbe-kpi.donors.show', array_merge($profileData, [
            'node' => $node, 'nodePrimary' => $primary, 'nodeSecondary' => $secondary,
            'browsing' => true,
            'resultPos' => $pos,
            'resultTotal' => $total,
            'prevUrl' => $pos > 1 ? route('admin.cbe-kpi.donors', array_merge($baseParams, ['pos' => $pos - 1])) : null,
            'nextUrl' => $pos < $total ? route('admin.cbe-kpi.donors', array_merge($baseParams, ['pos' => $pos + 1])) : null,
            'modifySearchUrl' => route('admin.cbe-kpi.donors', $modifyParams),
            'tabCounts' => $tabCounts,
        ]));
    }

    public function typeahead(Request $request)
    {
        $nodeId = $request->get('node');
        $q = trim((string) $request->get('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $rows = DB::table('cbe_donors as d')
            ->where(function ($w) use ($nodeId) {
                $w->where('d.cbe_node_id', $nodeId)
                    ->orWhereIn('d.donor_id', function ($sub) use ($nodeId) {
                        $sub->select('donor_id')->from('cbe_donor_sponsorships')->where('cbe_node_id', $nodeId);
                    });
            })
            ->where('d.donor_name', 'like', '%'.$q.'%')
            ->orderBy('d.donor_name')
            ->limit(20)
            ->select('d.donor_id', 'd.donor_name')
            ->get()
            ->map(fn ($r) => ['donor_id' => $r->donor_id, 'label' => $r->donor_name]);

        return response()->json($rows);
    }

    // Donor profile — every linked temple (home + pivot rows) and full
    // contribution history across all of them (labeled per temple/event).
    public function show(Request $request)
    {
        $nodeId = $request->get('node');
        $donorId = $request->get('id');

        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        abort_if(! $node, 404);

        $profileData = $this->buildDonorProfileData($node, $donorId);
        abort_if(! $profileData, 404);

        [$primary, $secondary] = $this->localizedNames($node->node_name, $node->node_name_zh);

        return view('admin.cbe-kpi.donors.show', array_merge($profileData, [
            'node' => $node,
            'nodePrimary' => $primary,
            'nodeSecondary' => $secondary,
            'browsing' => false,
            'tabCounts' => AdminCbeParticipationController::tabCounts($nodeId),
        ]));
    }

    // Shared by show() (direct link) and index()'s search-browse path
    // (Prev/Next through matched results).
    private function buildDonorProfileData(object $node, ?string $donorId): ?array
    {
        $nodeId = $node->node_id;

        $donor = DB::table('cbe_donors')->where('donor_id', $donorId)->first();
        if (! $donor) {
            return null;
        }

        $homeNode = DB::table('cbe_hierarchy_nodes')->where('node_id', $donor->cbe_node_id)->first();

        $linkedNodeIds = DB::table('cbe_donor_sponsorships')->where('donor_id', $donorId)->pluck('cbe_node_id');
        $linkedTemples = DB::table('cbe_hierarchy_nodes')->whereIn('node_id', $linkedNodeIds)
            ->orderBy('node_name')->get(['node_id', 'node_name', 'node_name_zh', 'city']);

        $contributions = DB::table('cbe_contributions as c')
            ->join('cbe_events as e', 'e.event_id', '=', 'c.event_id')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'e.cbe_node_id')
            ->leftJoin('cbe_receipts as r', function ($j) {
                $j->on('r.source_id', '=', 'c.contribution_id')->where('r.source_type', 'DONATION');
            })
            ->where('c.donor_id', $donorId)
            ->orderByDesc('c.created_at')
            ->select('c.contribution_id', 'c.contribution_type', 'c.item_description', 'c.pledged_amount', 'c.received_amount', 'c.estimated_value', 'c.status', 'c.receipt_no', 'c.receipt_attachment_path', 'r.receipt_id', 'e.event_name', 'e.event_name_zh', 'n.node_name', 'n.node_name_zh', 'n.node_id', 'c.created_at')
            ->paginate(10, ['*'], 'hpage')
            ->withQueryString();

        $totals = DB::table('cbe_contributions')
            ->where('donor_id', $donorId)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(received_amount),0) as total_received, COALESCE(SUM(estimated_value),0) as total_estimated')
            ->first();

        $events = DB::table('cbe_events')->where('cbe_node_id', $nodeId)->orderByDesc('event_start_date')->get(['event_id', 'event_name', 'event_name_zh', 'event_start_date']);

        // NEW 27 Aug 2026 — per Chris: "i want similar like member and
        // customers with the tap with events participant(whether what
        // events he attend, which festival, what offer he purchase from
        // the temple, what appoint he have with the sensei in the
        // temple)." Same event-participation + appointment history the
        // Members/Customers profiles already have, now for donors too
        // (via the new donor_id column on both tables).
        $history = DB::table('cbe_event_participants as p')
            ->join('cbe_events as e', 'e.event_id', '=', 'p.event_id')
            ->leftJoin('cbe_receipts as r', function ($j) {
                $j->on('r.source_id', '=', 'p.participant_record_id')->where('r.source_type', 'EVENT_SALE');
            })
            ->where('e.cbe_node_id', $nodeId)
            ->where('p.donor_id', $donorId)
            ->orderByDesc('p.paid_at')
            ->select('e.event_name', 'e.event_name_zh', 'p.item_name', 'p.quantity', 'p.amount_paid', 'p.paid_at', 'r.receipt_id')
            ->paginate(10, ['*'], 'hpage')
            ->withQueryString();

        $partTotals = DB::table('cbe_event_participants as p')
            ->join('cbe_events as e', 'e.event_id', '=', 'p.event_id')
            ->where('e.cbe_node_id', $nodeId)
            ->where('p.donor_id', $donorId)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(amount_paid),0) as total')
            ->first();

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
            ->where('ap.donor_id', $donorId)
            ->orderByDesc('ap.appointment_date')
            ->select('ap.appointment_type', 'ap.appointment_date', 'ap.fee_amount', 'ap.notes', 'adv.full_name as advisor_name', 'r.receipt_id')
            ->paginate(10, ['*'], 'apage')
            ->withQueryString();

        $appointmentCount = DB::table('cbe_appointments')
            ->where('cbe_node_id', $nodeId)
            ->where('donor_id', $donorId)
            ->count();

        // CHANGED 12 Sep 2026 — per Chris: a community can now enable
        // SEVERAL appointment positions at once, not just one — see
        // CbeFaithTerminologyService.
        $positions = CbeFaithTerminologyService::positionsForGroup($node->group_label_id);
        $faithTerms = $positions[0];

        return [
            'donor' => $donor,
            'homeNode' => $homeNode,
            'linkedTemples' => $linkedTemples,
            'contributions' => $contributions,
            'contributionCount' => (int) $totals->cnt,
            'totalReceived' => (float) $totals->total_received,
            'totalEstimated' => (float) $totals->total_estimated,
            'events' => $events,
            'history' => $history,
            'participationCount' => (int) $partTotals->cnt,
            'participationTotal' => (float) $partTotals->total,
            'advisors' => $advisors,
            'appointments' => $appointments,
            'appointmentCount' => $appointmentCount,
            'faithTerms' => $faithTerms,
            'positions' => $positions,
        ];
    }

    // NEW 27 Aug 2026 — logs a donor's own attendance/purchase at one of
    // THIS temple's events (festival ticket, prayer package, etc), same
    // pattern as Members/Customers storeParticipation().
    public function storeParticipation(Request $request)
    {
        $nodeId = $request->get('node');
        $donorId = $request->get('donor_id');

        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        abort_if(! $node, 404);
        $donor = DB::table('cbe_donors')->where('donor_id', $donorId)->first();
        abort_if(! $donor, 404);

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
            'agent_id' => null,
            'customer_id' => null,
            'donor_id' => $donorId,
            'item_name' => $itemName,
            'quantity' => (int) ($request->get('quantity') ?: 1),
            'amount_paid' => $amountPaid,
            'paid_at' => $request->get('paid_at') ?: now()->toDateString(),
            'notes' => trim((string) $request->get('notes')) ?: null,
            'recorded_by' => auth('agent')->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($amountPaid > 0) {
            CbeReceiptService::issue($nodeId, 'EVENT_SALE', $participantId, $donor->donor_name ?? 'Donor', $itemName, $amountPaid, auth('agent')->id());
        }

        return redirect()->route('admin.cbe-kpi.donors.show', ['node' => $nodeId, 'id' => $donorId])->with('cbe_participation_saved', true);
    }

    // NEW 27 Aug 2026 — logs an appointment already held/arranged
    // between this donor and one of the temple's own registered members
    // acting as advisor, same pattern as Members/Customers
    // storeAppointment().
    public function storeAppointment(Request $request)
    {
        $nodeId = $request->get('node');
        $donorId = $request->get('donor_id');

        abort_if(! DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->exists(), 404);
        abort_if(! DB::table('cbe_donors')->where('donor_id', $donorId)->exists(), 404);

        $advisorId = $request->get('advisor_id');
        abort_if(! DB::table('cbe_group_memberships')->where('cbe_node_id', $nodeId)->where('agent_id', $advisorId)->exists(), 422);

        // CHANGED 12 Sep 2026 — per Chris: a community can now enable
        // SEVERAL appointment positions at once, each with its OWN
        // admin-editable reason list (never a hardcoded set). The
        // submitted position must be one this community has enabled,
        // and the submitted reason must belong to THAT position's list.
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
            'agent_id' => null,
            'customer_id' => null,
            'donor_id' => $donorId,
            'appointment_type' => $type,
            'fee_amount' => $feeAmount,
            'appointment_date' => $request->get('appointment_date') ?: now()->toDateString(),
            'notes' => trim((string) $request->get('notes')) ?: null,
            'recorded_by' => auth('agent')->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // CHANGED 12 Sep 2026 — $type is now the Admin-typed reason text
        // itself, not a fixed code — passed straight through.
        if ($feeAmount > 0) {
            $donor = DB::table('cbe_donors')->where('donor_id', $donorId)->first();
            $payerName = $donor->donor_name ?? 'Donor';
            CbeReceiptService::issue($nodeId, 'APPOINTMENT', $appointmentId, $payerName, $type, $feeAmount, auth('agent')->id());
        }

        return redirect()->route('admin.cbe-kpi.donors.show', ['node' => $nodeId, 'id' => $donorId])->with('cbe_appointment_saved', true);
    }

    // NEW 26 Aug 2026, 18th pass — records a donation/sponsorship/
    // auction win for this donor at one of THIS temple's events. Covers
    // every form Chris described: cash donations, in-kind gifts
    // (crystal, hampers, artwork), sponsorships, and auction items won.
    public function storeContribution(Request $request)
    {
        $nodeId = $request->get('node');
        $donorId = $request->get('donor_id');

        abort_if(! DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->exists(), 404);
        abort_if(! DB::table('cbe_donors')->where('donor_id', $donorId)->exists(), 404);

        $eventId = $request->get('event_id');
        abort_if(! DB::table('cbe_events')->where('event_id', $eventId)->where('cbe_node_id', $nodeId)->exists(), 422);

        $type = $request->get('contribution_type');
        if (! in_array($type, ['CASH_DONATION', 'SPONSORSHIP', 'IN_KIND_GIFT', 'SERVICE_SPONSORSHIP', 'AUCTION_ITEM'], true)) {
            return back()->withErrors(['contribution_type' => 'A valid contribution type is required.'])->withInput();
        }

        // NEW 26 Aug 2026, 20th pass — per Chris: "i cannot be double
        // standard one is upload one is key in." The temple no longer
        // types a receipt number by hand — the moment money is actually
        // received, the system issues the official receipt itself (see
        // CbeReceiptService), the same way for every collection point in
        // the app. The photo-upload field stays as an OPTIONAL extra
        // attachment only, for the rare case a temple also has a separate
        // physical receipt book they still want scanned on file.
        $request->validate([
            'receipt_attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ]);
        $receiptPath = null;
        if ($request->hasFile('receipt_attachment')) {
            $receiptPath = $request->file('receipt_attachment')->store('cbe-contribution-receipts', 'local');
        }

        $donor = DB::table('cbe_donors')->where('donor_id', $donorId)->first();
        $receivedAmount = (float) ($request->get('received_amount') ?: 0);

        $contributionId = (string) Str::uuid();
        DB::table('cbe_contributions')->insert([
            'contribution_id' => $contributionId,
            'event_id' => $eventId,
            'donor_id' => $donorId,
            'contribution_type' => $type,
            'item_description' => trim((string) $request->get('item_description')) ?: null,
            'pledged_amount' => $request->get('pledged_amount') !== null && $request->get('pledged_amount') !== '' ? (float) $request->get('pledged_amount') : null,
            'received_amount' => $receivedAmount,
            'estimated_value' => $request->get('estimated_value') !== null && $request->get('estimated_value') !== '' ? (float) $request->get('estimated_value') : null,
            'status' => $request->get('status') ?: 'PLEDGED',
            'receipt_no' => null,
            'receipt_issued_at' => null,
            'receipt_attachment_path' => $receiptPath,
            'notes' => trim((string) $request->get('notes')) ?: null,
            'recorded_by' => auth('agent')->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($receivedAmount > 0) {
            $receipt = CbeReceiptService::issue(
                $nodeId,
                'DONATION',
                $contributionId,
                $donor->donor_name ?? 'Donor',
                trim(($type ? __('admin_cbe_directory.contribution_type_'.strtolower($type)) : 'Donation').(($item = trim((string) $request->get('item_description'))) ? ' — '.$item : '')),
                $receivedAmount,
                auth('agent')->id()
            );
            if ($receipt) {
                DB::table('cbe_contributions')->where('contribution_id', $contributionId)->update([
                    'receipt_no' => $receipt->receipt_no,
                    'receipt_issued_at' => $receipt->issued_at,
                    'updated_at' => now(),
                ]);
            }
        }

        return redirect()->route('admin.cbe-kpi.donors.show', ['node' => $nodeId, 'id' => $donorId])->with('cbe_contribution_saved', true);
    }

    // Streams the attached receipt scan/photo back for viewing/download.
    public function downloadReceipt(Request $request)
    {
        $contributionId = $request->get('id');
        $contribution = DB::table('cbe_contributions')->where('contribution_id', $contributionId)->first();
        abort_if(! $contribution, 404);
        abort_if(! $contribution->receipt_attachment_path || ! Storage::disk('local')->exists($contribution->receipt_attachment_path), 404);

        return response()->file(Storage::disk('local')->path($contribution->receipt_attachment_path));
    }

    // Links an EXISTING donor (registered at another temple) to the
    // current temple too, per Chris's "Penang donor can also be a Klang
    // one of the temple" requirement. cbe_donors itself is untouched —
    // this only adds a cbe_donor_sponsorships row.
    public function linkTemple(Request $request)
    {
        $nodeId = $request->get('node');
        $donorId = $request->get('donor_id');

        abort_if(! DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->exists(), 404);
        abort_if(! DB::table('cbe_donors')->where('donor_id', $donorId)->exists(), 404);

        $alreadyHome = DB::table('cbe_donors')->where('donor_id', $donorId)->where('cbe_node_id', $nodeId)->exists();
        $alreadyLinked = DB::table('cbe_donor_sponsorships')->where('donor_id', $donorId)->where('cbe_node_id', $nodeId)->exists();

        if (! $alreadyHome && ! $alreadyLinked) {
            DB::table('cbe_donor_sponsorships')->insert([
                'sponsorship_id' => (string) Str::uuid(),
                'donor_id' => $donorId,
                'cbe_node_id' => $nodeId,
                'created_by' => auth('agent')->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('admin.cbe-kpi.donors.show', ['node' => $nodeId, 'id' => $donorId])->with('cbe_link_saved', true);
    }

    // Registers a brand-new donor with THIS temple as their home
    // (cbe_donors.cbe_node_id), for a temple onboarding a donor no one
    // else has registered yet.
    public function store(Request $request)
    {
        $nodeId = $request->get('node');
        abort_if(! DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->exists(), 404);

        $name = trim((string) $request->get('donor_name'));
        if ($name === '') {
            return back()->withErrors(['donor_name' => 'Donor name is required.'])->withInput();
        }

        $donorId = (string) Str::uuid();
        DB::table('cbe_donors')->insert([
            'donor_id' => $donorId,
            'cbe_node_id' => $nodeId,
            'donor_name' => $name,
            'donor_type' => $request->get('donor_type') ?: 'INDIVIDUAL',
            'contact_person' => trim((string) $request->get('contact_person')) ?: null,
            'phone' => trim((string) $request->get('phone')) ?: null,
            'email' => trim((string) $request->get('email')) ?: null,
            'address' => trim((string) $request->get('address')) ?: null,
            'notes' => trim((string) $request->get('notes')) ?: null,
            'created_by' => auth('agent')->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // NEW 28 Sep 2026 — member file item 26: link to the person at once when Mobile / Email match exactly one member
        \App\Services\MemberFileService::autoLinkDonor($donorId);

        return redirect()->route('admin.cbe-kpi.donors.show', ['node' => $nodeId, 'id' => $donorId])->with('cbe_donor_saved', true);
    }

    private function localizedNames(?string $nameEn, ?string $nameZh): array
    {
        if (app()->getLocale() === 'zh' && $nameZh) {
            return [$nameZh, $nameEn];
        }

        return [$nameEn, $nameZh];
    }
}
