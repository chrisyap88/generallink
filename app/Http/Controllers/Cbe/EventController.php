<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use App\Services\CbeAccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 22 Aug 2026 — per Chris: Event, Donation, Sponsorship & Financial
// Management Module. This controller owns the Event itself — creating
// one, moving it through PLANNING -> IN_PROGRESS -> CLOSED, and the hub
// screen that links out to its Donor contributions, Expenses, and
// Reports. Scoped to the agent's own cbe_node_id, same as every other
// CBE screen built so far.
//
// UPDATED 28 Aug 2026 — per Chris: "develop all the program, all the
// program that label with the word soon." See ResolvesCbeActiveNode.
class EventController extends Controller
{
    use ResolvesCbeActiveNode;

    public function index(Request $request)
    {
        $agent = auth('agent')->user();
        // UPDATED 17 Sep 2026 — per Chris ("yes build all this for me" —
        // Event RSVP was one of the 7 approved secretarial gaps): an
        // ordinary member needs to see the event list/calendar to RSVP,
        // not just officers. Same member-safe fallback Notice Board and
        // Messaging already use — see ResolvesCbeActiveNode.
        $nodeId = $this->resolveCbeNodeIdForMember($agent);

        if (! $nodeId && $agent->role === 'ADMIN') {
            return $this->renderCbeNodePicker('cbe.events.index', __('cbe_events.events_page_title'), leafOnly: true, countResolver: function (array $nodeIds) {
                return DB::table('cbe_events')
                    ->whereIn('cbe_node_id', $nodeIds)
                    ->selectRaw('cbe_node_id, COUNT(*) as cnt')
                    ->groupBy('cbe_node_id')
                    ->pluck('cnt', 'cbe_node_id')
                    ->all();
            });
        }

        $events = $nodeId
            ? DB::table('cbe_events')->where('cbe_node_id', $nodeId)
                ->orderByDesc('event_start_date')->paginate(8, ['*'], 'evPage')
            : collect();

        $canManage = \App\Services\CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId);

        return view('cbe.events.index', ['events' => $events, 'hasNode' => (bool) $nodeId, 'canManage' => $canManage]);
    }

    public function create()
    {
        $agent = auth('agent')->user();
        if (! $this->resolveCbeNodeId($agent)) {
            return redirect()->route('cbe.events.index');
        }
        return view('cbe.events.create');
    }

    public function store(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        if (! $nodeId) {
            return redirect()->route('cbe.events.index');
        }

        $request->validate([
            'event_name'       => ['required', 'string', 'max:255'],
            'event_name_zh'    => ['nullable', 'string', 'max:255'],
            'event_start_date' => ['required', 'date'],
            'event_end_date'   => ['nullable', 'date', 'after_or_equal:event_start_date'],
            'description'      => ['nullable', 'string', 'max:3000'],
        ]);

        $eventId = (string) Str::uuid();
        DB::table('cbe_events')->insert([
            'event_id'         => $eventId,
            'cbe_node_id'       => $nodeId,
            'activity_id'       => null,
            'event_name'        => $request->input('event_name'),
            'event_name_zh'     => $request->input('event_name_zh'),
            'event_start_date'  => $request->input('event_start_date'),
            'event_end_date'    => $request->input('event_end_date'),
            'status'            => 'PLANNING',
            'description'       => $request->input('description'),
            'created_by'        => $agent->agent_id,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return redirect()->route('cbe.events.show', $eventId)->with('success', __('cbe_events.event_saved'));
    }

    public function show(string $eventId)
    {
        $agent = auth('agent')->user();
        // UPDATED 17 Sep 2026 — same member-safe fallback as index()
        // above, so an ordinary member can open an event to RSVP.
        $nodeId = $this->resolveCbeNodeIdForMember($agent);
        $event = DB::table('cbe_events')
            ->where('event_id', $eventId)->where('cbe_node_id', $nodeId)
            ->firstOrFail();

        $canManage = \App\Services\CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId);

        $contributionTotals = DB::table('cbe_contributions')
            ->where('event_id', $eventId)->where('status', '!=', 'CANCELLED')
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(received_amount),0) as received, COALESCE(SUM(pledged_amount),0) as pledged')
            ->first();

        $expenseTotal = (float) DB::table('cbe_event_expenses')->where('event_id', $eventId)->sum('amount');

        // NEW 17 Sep 2026 — Event RSVP/attendance.
        $rsvpCounts = DB::table('cbe_event_rsvps')->where('event_id', $eventId)
            ->selectRaw("response, COUNT(*) as cnt")->groupBy('response')->pluck('cnt', 'response');
        $myRsvp = DB::table('cbe_event_rsvps')->where('event_id', $eventId)->where('agent_id', $agent->agent_id)->value('response');

        return view('cbe.events.show', [
            'event' => $event,
            'contributionTotals' => $contributionTotals,
            'expenseTotal' => $expenseTotal,
            'canManage' => $canManage,
            'rsvpCounts' => $rsvpCounts,
            'myRsvp' => $myRsvp,
        ]);
    }

    // NEW 17 Sep 2026 — per Chris ("yes build all this for me"): any
    // member (not just officers) can RSVP to an event. One row per
    // (event, agent) — changing your response just updates the same
    // row rather than piling up duplicates (unique constraint in the
    // migration enforces this at the DB level too).
    public function rsvp(Request $request, string $eventId)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);
        $event = DB::table('cbe_events')->where('event_id', $eventId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate(['response' => ['required', 'in:GOING,NOT_GOING,MAYBE']]);

        $existing = DB::table('cbe_event_rsvps')->where('event_id', $eventId)->where('agent_id', $agent->agent_id)->first();
        if ($existing) {
            DB::table('cbe_event_rsvps')->where('rsvp_id', $existing->rsvp_id)->update([
                'response' => $request->input('response'),
                'responded_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('cbe_event_rsvps')->insert([
                'rsvp_id' => (string) Str::uuid(),
                'event_id' => $eventId,
                'agent_id' => $agent->agent_id,
                'response' => $request->input('response'),
                'responded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('cbe.events.show', $eventId)->with('success', __('cbe_events.rsvp_saved'));
    }

    public function start(string $eventId)
    {
        $agent = auth('agent')->user();
        DB::table('cbe_events')->where('event_id', $eventId)->where('cbe_node_id', $this->resolveCbeNodeId($agent))
            ->where('status', 'PLANNING')
            ->update(['status' => 'IN_PROGRESS', 'updated_at' => now()]);

        return back()->with('success', __('cbe_events.event_started'));
    }

    // Closing an event: lock it, then post its net result into the
    // Temple's own cbe_transactions ledger (per Chris — "full
    // traceability... financial transparency") so the Annual Income &
    // Expenditure Report automatically picks up event money too,
    // instead of it living in a disconnected set of books. Needs at
    // least one EXPENSE-type category to exist for the expense-side
    // posting; if none exists yet, only the income side posts and the
    // expense side is skipped with a warning (nothing is lost — the
    // expense detail still lives in cbe_event_expenses either way).
    public function close(string $eventId)
    {
        $agent = auth('agent')->user();
        $event = DB::table('cbe_events')
            ->where('event_id', $eventId)->where('cbe_node_id', $this->resolveCbeNodeId($agent))
            ->whereIn('status', ['PLANNING', 'IN_PROGRESS'])
            ->first();

        if (! $event) {
            return back()->with('error', __('cbe_events.already_closed'));
        }

        DB::transaction(function () use ($event, $eventId, $agent) {
            $receivedTotal = (float) DB::table('cbe_contributions')
                ->where('event_id', $eventId)->where('status', '!=', 'CANCELLED')->sum('received_amount');
            $expenseTotal = (float) DB::table('cbe_event_expenses')->where('event_id', $eventId)->sum('amount');

            if ($receivedTotal > 0) {
                $incomeCategoryId = DB::table('cbe_transaction_categories')
                    ->where(function ($q) use ($agent) {
                        $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
                    })->where('type', 'INCOME')->where('is_active', true)->value('category_id');

                if ($incomeCategoryId) {
                    $incomeTxnId = (string) Str::uuid();
                    DB::table('cbe_transactions')->insert([
                        'transaction_id' => $incomeTxnId,
                        'cbe_node_id' => $event->cbe_node_id,
                        'bank_statement_id' => null,
                        'event_id' => $eventId,
                        'category_id' => $incomeCategoryId,
                        'transaction_date' => now()->toDateString(),
                        'description' => 'Event income — ' . $event->event_name,
                        'amount' => $receivedTotal,
                        'entered_by' => $agent->agent_id,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    // NEW 25 Aug 2026 — mirrors into the double-entry
                    // ledger exactly like any other transaction. This is
                    // the ONLY point event donation/sponsorship income
                    // ever touches the formal books — see
                    // CbeAccountingService header comment for why
                    // contributions themselves are deliberately not
                    // posted a second time at pledge stage.
                    CbeAccountingService::postTransaction($incomeTxnId);
                }
            }

            if ($expenseTotal > 0) {
                $expenseCategoryId = DB::table('cbe_transaction_categories')
                    ->where(function ($q) use ($agent) {
                        $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
                    })->where('type', 'EXPENSE')->where('is_active', true)->value('category_id');

                if ($expenseCategoryId) {
                    $expenseTxnId = (string) Str::uuid();
                    DB::table('cbe_transactions')->insert([
                        'transaction_id' => $expenseTxnId,
                        'cbe_node_id' => $event->cbe_node_id,
                        'bank_statement_id' => null,
                        'event_id' => $eventId,
                        'category_id' => $expenseCategoryId,
                        'transaction_date' => now()->toDateString(),
                        'description' => 'Event expenses — ' . $event->event_name,
                        'amount' => $expenseTotal,
                        'entered_by' => $agent->agent_id,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    CbeAccountingService::postTransaction($expenseTxnId);
                }
            }

            DB::table('cbe_events')->where('event_id', $eventId)->update([
                'status' => 'CLOSED',
                'closed_at' => now(),
                'closed_by' => $agent->agent_id,
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('cbe.events.show', $eventId)->with('success', __('cbe_events.event_closed'));
    }
}
