<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CbeFaithTerminologyService;
use App\Services\CbeReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 26 Aug 2026 — per Chris: "Customers(customer that participate
// their events example buy their anniversary dinner ticket) prayer
// package etc." 5th tab — customers who have participated in / bought
// something at THIS temple's events (via cbe_event_participants). No
// such data existed before this feature, so this screen also lets an
// admin find any existing customer (system-wide, since a customer isn't
// owned by one temple) and log a new participation/purchase for them
// at this temple.
class AdminCbeCustomersController extends Controller
{
    // Search screen — scoped to customers who already have a
    // participation record at THIS temple's events. mode=main (default)
    // or mode=search+do_search (runs the query). Search-first, never a
    // default full listing.
    // NEW 26 Aug 2026, 21st pass — per Chris: "when records found the
    // profile screen stay put... no need to display in a list of rows."
    // Same redesign as Members: search jumps straight to the first
    // matching customer's full profile, Prev/Next steps through the
    // matched set. The "search any customer to add" picker below is
    // UNCHANGED (per Chris) — that one is for finding and attaching an
    // existing record, not browsing, so a compact list to pick from
    // still makes sense there.
    public function index(Request $request)
    {
        $nodeId = $request->get('node');
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        abort_if(! $node, 404);

        $mode = $request->get('mode', 'main');
        $doSearch = $mode === 'search' && $request->has('do_search');

        // Optional broader "find any customer to add" search, system-wide
        // (a customer can belong to no temple yet, or belong to a
        // different temple's participation list already — either way
        // they can still be logged here too). Stays a compact list —
        // this is a picker, not a profile browse.
        $findMode = $request->get('find_mode', '');
        $findResults = null;
        if ($findMode === 'search' && $request->has('find_do_search')) {
            $fq = DB::table('customers')->where('is_deleted', false);
            if ($v = trim((string) $request->get('find_full_name'))) {
                $fq->where('full_name', 'like', '%'.$v.'%');
            }
            if ($v = trim((string) $request->get('find_phone'))) {
                $fq->where('phone', 'like', '%'.$v.'%');
            }
            if ($v = trim((string) $request->get('find_email'))) {
                $fq->where('email', 'like', '%'.$v.'%');
            }
            $findResults = $fq->orderBy('full_name')
                ->select('customer_id', 'full_name', 'phone', 'email', 'city')
                ->limit(50)
                ->get();
        }

        [$primary, $secondary] = $this->localizedNames($node->node_name, $node->node_name_zh);

        // NEW 27 Aug 2026 — per Chris: persistent Customers/Participation/
        // Appointments tab bar, must be in EVERY branch, before and after
        // search, never disappear.
        $faithPracticeType = DB::table('group_labels')->where('group_label_id', $node->group_label_id)->value('faith_practice_type');
        $faithTerms = CbeFaithTerminologyService::terms($faithPracticeType);
        $tabCounts = AdminCbeParticipationController::tabCounts($nodeId);

        if (! $doSearch) {
            return view('admin.cbe-kpi.customers.index', [
                'node' => $node, 'nodePrimary' => $primary, 'nodeSecondary' => $secondary,
                'mode' => $mode, 'doSearch' => $doSearch, 'resultTotal' => null,
                'findMode' => $findMode, 'findResults' => $findResults,
                'faithTerms' => $faithTerms, 'tabCounts' => $tabCounts,
            ]);
        }

        $q = DB::table('customers as c')
            ->whereIn('c.customer_id', function ($sub) use ($nodeId) {
                $sub->select('p.customer_id')
                    ->from('cbe_event_participants as p')
                    ->join('cbe_events as e', 'e.event_id', '=', 'p.event_id')
                    ->where('e.cbe_node_id', $nodeId)
                    ->whereNotNull('p.customer_id');
            })
            ->where('c.is_deleted', false);

        if ($v = trim((string) $request->get('full_name'))) {
            $q->where('c.full_name', 'like', '%'.$v.'%');
        }
        if ($v = trim((string) $request->get('phone'))) {
            $q->where('c.phone', 'like', '%'.$v.'%');
        }
        if ($v = trim((string) $request->get('email'))) {
            $q->where('c.email', 'like', '%'.$v.'%');
        }
        if ($v = trim((string) $request->get('city'))) {
            $q->where('c.city', 'like', '%'.$v.'%');
        }
        if ($v = trim((string) $request->get('address'))) {
            $q->where('c.address', 'like', '%'.$v.'%');
        }
        if ($v = trim((string) $request->get('postcode'))) {
            $q->where('c.postcode', 'like', '%'.$v.'%');
        }
        if ($v = trim((string) $request->get('state'))) {
            $q->where('c.state', 'like', '%'.$v.'%');
        }

        $orderedIds = $q->orderBy('c.full_name')->pluck('c.customer_id')->toArray();
        $total = count($orderedIds);

        if ($total === 0) {
            return view('admin.cbe-kpi.customers.index', [
                'node' => $node, 'nodePrimary' => $primary, 'nodeSecondary' => $secondary,
                'mode' => $mode, 'doSearch' => $doSearch, 'resultTotal' => 0,
                'findMode' => $findMode, 'findResults' => $findResults,
                'faithTerms' => $faithTerms, 'tabCounts' => $tabCounts,
            ]);
        }

        $pos = max(1, min($total, (int) $request->get('pos', 1)));
        $profileData = $this->buildCustomerProfileData($node, $orderedIds[$pos - 1]);

        $baseParams = $request->except(['pos']);
        $modifyParams = $request->except(['do_search', 'pos']);

        return view('admin.cbe-kpi.customers.show', array_merge($profileData, [
            'node' => $node, 'nodePrimary' => $primary, 'nodeSecondary' => $secondary,
            'browsing' => true,
            'resultPos' => $pos,
            'resultTotal' => $total,
            'prevUrl' => $pos > 1 ? route('admin.cbe-kpi.customers', array_merge($baseParams, ['pos' => $pos - 1])) : null,
            'nextUrl' => $pos < $total ? route('admin.cbe-kpi.customers', array_merge($baseParams, ['pos' => $pos + 1])) : null,
            'modifySearchUrl' => route('admin.cbe-kpi.customers', $modifyParams),
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

        $rows = DB::table('customers as c')
            ->whereIn('c.customer_id', function ($sub) use ($nodeId) {
                $sub->select('p.customer_id')
                    ->from('cbe_event_participants as p')
                    ->join('cbe_events as e', 'e.event_id', '=', 'p.event_id')
                    ->where('e.cbe_node_id', $nodeId)
                    ->whereNotNull('p.customer_id');
            })
            ->where('c.is_deleted', false)
            ->where(function ($w) use ($q) {
                $w->where('c.full_name', 'like', '%'.$q.'%')
                    ->orWhere('c.phone', 'like', '%'.$q.'%');
            })
            ->orderBy('c.full_name')
            ->limit(20)
            ->select('c.customer_id', 'c.full_name', 'c.phone')
            ->get()
            ->map(fn ($r) => ['customer_id' => $r->customer_id, 'label' => $r->full_name.' ('.$r->phone.')']);

        return response()->json($rows);
    }

    // Customer profile — participation history/count at THIS temple, and
    // a form to log a new participation/purchase.
    public function show(Request $request)
    {
        $nodeId = $request->get('node');
        $customerId = $request->get('id');

        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        abort_if(! $node, 404);

        $profileData = $this->buildCustomerProfileData($node, $customerId);
        abort_if(! $profileData, 404);

        [$primary, $secondary] = $this->localizedNames($node->node_name, $node->node_name_zh);

        return view('admin.cbe-kpi.customers.show', array_merge($profileData, [
            'node' => $node,
            'nodePrimary' => $primary,
            'nodeSecondary' => $secondary,
            'browsing' => false,
            'tabCounts' => AdminCbeParticipationController::tabCounts($nodeId),
        ]));
    }

    // Shared by show() (direct link) and index()'s search-browse path
    // (Prev/Next through matched results).
    private function buildCustomerProfileData(object $node, ?string $customerId): ?array
    {
        $nodeId = $node->node_id;

        $customer = DB::table('customers')->where('customer_id', $customerId)->where('is_deleted', false)->first();
        if (! $customer) {
            return null;
        }

        $history = DB::table('cbe_event_participants as p')
            ->join('cbe_events as e', 'e.event_id', '=', 'p.event_id')
            ->leftJoin('cbe_receipts as r', function ($j) {
                $j->on('r.source_id', '=', 'p.participant_record_id')->where('r.source_type', 'EVENT_SALE');
            })
            ->where('e.cbe_node_id', $nodeId)
            ->where('p.customer_id', $customerId)
            ->orderByDesc('p.paid_at')
            ->select('e.event_name', 'e.event_name_zh', 'p.item_name', 'p.quantity', 'p.amount_paid', 'p.paid_at', 'r.receipt_id')
            ->paginate(10, ['*'], 'hpage')
            ->withQueryString();

        $totals = DB::table('cbe_event_participants as p')
            ->join('cbe_events as e', 'e.event_id', '=', 'p.event_id')
            ->where('e.cbe_node_id', $nodeId)
            ->where('p.customer_id', $customerId)
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
            ->where('ap.customer_id', $customerId)
            ->orderByDesc('ap.appointment_date')
            ->select('ap.appointment_type', 'ap.appointment_date', 'ap.fee_amount', 'ap.notes', 'adv.full_name as advisor_name', 'r.receipt_id')
            ->paginate(10, ['*'], 'apage')
            ->withQueryString();

        $appointmentCount = DB::table('cbe_appointments')
            ->where('cbe_node_id', $nodeId)
            ->where('customer_id', $customerId)
            ->count();

        // CHANGED 12 Sep 2026 — per Chris: a community can now enable
        // SEVERAL appointment positions at once, not just one — see
        // CbeFaithTerminologyService.
        $positions = \App\Services\CbeFaithTerminologyService::positionsForGroup($node->group_label_id);
        $faithTerms = $positions[0];

        return [
            'customer' => $customer,
            'history' => $history,
            'participationCount' => (int) $totals->cnt,
            'participationTotal' => (float) $totals->total,
            'events' => $events,
            'advisors' => $advisors,
            'appointments' => $appointments,
            'appointmentCount' => $appointmentCount,
            'faithTerms' => $faithTerms,
            'positions' => $positions,
        ];
    }

    // NEW 26 Aug 2026, 20th pass — logs an appointment already held/
    // arranged (not a forward booking calendar) between this customer
    // and one of the temple's own registered members acting as advisor.
    public function storeAppointment(Request $request)
    {
        $nodeId = $request->get('node');
        $customerId = $request->get('customer_id');

        abort_if(! DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->exists(), 404);
        abort_if(! DB::table('customers')->where('customer_id', $customerId)->exists(), 404);

        $advisorId = $request->get('advisor_id');
        abort_if(! DB::table('cbe_group_memberships')->where('cbe_node_id', $nodeId)->where('agent_id', $advisorId)->exists(), 422);

        // CHANGED 12 Sep 2026 — per Chris: a community can now enable
        // SEVERAL appointment positions at once, each with its OWN
        // admin-editable reason list (never a hardcoded set). The
        // submitted position must be one this community has enabled,
        // and the submitted reason must belong to THAT position's list.
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        $positions = \App\Services\CbeFaithTerminologyService::positionsForGroup($node->group_label_id ?? null);
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
            'customer_id' => $customerId,
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
            $payerName = DB::table('customers')->where('customer_id', $customerId)->value('full_name') ?? 'Customer';
            CbeReceiptService::issue($nodeId, 'APPOINTMENT', $appointmentId, $payerName, $type, $feeAmount, auth('agent')->id());
        }

        return redirect()->route('admin.cbe-kpi.customers.show', ['node' => $nodeId, 'id' => $customerId])->with('cbe_appointment_saved', true);
    }

    public function storeParticipation(Request $request)
    {
        $nodeId = $request->get('node');
        $customerId = $request->get('customer_id');

        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        abort_if(! $node, 404);
        $customer = DB::table('customers')->where('customer_id', $customerId)->first();
        abort_if(! $customer, 404);

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
            'customer_id' => $customerId,
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
            CbeReceiptService::issue($nodeId, 'EVENT_SALE', $participantId, $customer->full_name ?? 'Customer', $itemName, $amountPaid, auth('agent')->id());
        }

        return redirect()->route('admin.cbe-kpi.customers.show', ['node' => $nodeId, 'id' => $customerId])->with('cbe_participation_saved', true);
    }

    private function localizedNames(?string $nameEn, ?string $nameZh): array
    {
        if (app()->getLocale() === 'zh' && $nameZh) {
            return [$nameZh, $nameEn];
        }

        return [$nameEn, $nameZh];
    }
}
