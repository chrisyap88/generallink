<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use App\Services\CbeCommitteeAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 17 Sep 2026 — per Chris: "same with support tickets, member
// complaint, request for preparation of the events, request to
// purchase etc." Any member of the entity can raise a ticket; the
// Secretary (or a node officer — same CbeCommitteeAuthService rule as
// Notice Board/Messaging) triages EVERY ticket first — sets its
// category if missing, assigns who's handling it, and moves its
// status along — per Chris's own explicit answer, never auto-routed
// to a specific officer by category. Ticket categories are an Admin-
// editable catalog (cbe_ticket_categories), never hardcoded.
class CbeTicketController extends Controller
{
    use ResolvesCbeActiveNode;

    public function index(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);

        if (! $nodeId && $agent->role === 'ADMIN') {
            return $this->renderCbeNodePicker('cbe.tickets.index', __('cbe_records.tickets_page_title'), leafOnly: true, countResolver: function (array $nodeIds) {
                return DB::table('cbe_tickets')
                    ->whereIn('cbe_node_id', $nodeIds)->where('is_deleted', false)
                    ->selectRaw('cbe_node_id, COUNT(*) as cnt')
                    ->groupBy('cbe_node_id')
                    ->pluck('cnt', 'cbe_node_id')
                    ->all();
            });
        }

        $canManage = CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId);
        $tab = $request->get('tab', 'open');

        $tickets = collect();
        if ($nodeId) {
            $query = DB::table('cbe_tickets as t')
                ->leftJoin('cbe_ticket_categories as c', 'c.id', '=', 't.category_id')
                ->leftJoin('agents as r', 'r.agent_id', '=', 't.raised_by_agent_id')
                ->where('t.cbe_node_id', $nodeId)->where('t.is_deleted', false);

            if (! $canManage) {
                // Ordinary members only ever see their OWN tickets — this
                // screen is not a public complaints board.
                $query->where('t.raised_by_agent_id', $agent->agent_id);
            }

            if ($tab === 'resolved') {
                $query->whereIn('t.status', ['RESOLVED', 'CLOSED']);
            } else {
                $query->whereNotIn('t.status', ['RESOLVED', 'CLOSED']);
            }

            $tickets = $query->orderByDesc('t.created_at')
                ->select('t.*', 'c.label as category_label', 'r.full_name as raised_by_name')
                ->paginate(8, ['*'], 'tkPage')->appends(['tab' => $tab]);
        }

        return view('cbe.tickets.index', [
            'tickets' => $tickets, 'hasNode' => (bool) $nodeId, 'canManage' => $canManage, 'tab' => $tab,
        ]);
    }

    public function create()
    {
        $agent = Auth::guard('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);
        abort_unless($nodeId, 404);

        $categories = DB::table('cbe_ticket_categories')->where('is_active', true)->orderBy('sort_order')->get();

        return view('cbe.tickets.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);
        abort_unless($nodeId, 404);

        $validated = $request->validate([
            'category_id' => ['nullable', 'uuid', 'exists:cbe_ticket_categories,id'],
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:3000'],
        ]);

        $ticketId = (string) Str::uuid();
        DB::table('cbe_tickets')->insert([
            'ticket_id' => $ticketId,
            'cbe_node_id' => $nodeId,
            'category_id' => $validated['category_id'] ?? null,
            'raised_by_agent_id' => $agent->agent_id,
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'status' => 'OPEN',
            'is_deleted' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('cbe.tickets.show', $ticketId)->with('success', __('cbe_records.ticket_raised_success'));
    }

    public function show(string $ticketId)
    {
        $agent = Auth::guard('agent')->user();

        $ticket = DB::table('cbe_tickets as t')
            ->leftJoin('cbe_ticket_categories as c', 'c.id', '=', 't.category_id')
            ->leftJoin('agents as r', 'r.agent_id', '=', 't.raised_by_agent_id')
            ->leftJoin('agents as a', 'a.agent_id', '=', 't.assigned_to_agent_id')
            ->where('t.ticket_id', $ticketId)->where('t.is_deleted', false)
            ->select('t.*', 'c.label as category_label', 'r.full_name as raised_by_name', 'a.full_name as assigned_to_name')
            ->first();
        abort_if(! $ticket, 404);

        $canManage = CbeCommitteeAuthService::isAuthorizedManager($agent, $ticket->cbe_node_id);
        $isRaiser = $ticket->raised_by_agent_id === $agent->agent_id;
        abort_unless($canManage || $isRaiser, 403);

        $replies = DB::table('cbe_ticket_replies as rp')
            ->leftJoin('agents as a', 'a.agent_id', '=', 'rp.sender_agent_id')
            ->where('rp.ticket_id', $ticketId)
            ->orderBy('rp.created_at')
            ->select('rp.*', 'a.full_name as sender_name')
            ->get();

        $categories = $canManage ? DB::table('cbe_ticket_categories')->where('is_active', true)->orderBy('sort_order')->get() : collect();

        return view('cbe.tickets.show', compact('ticket', 'replies', 'canManage', 'isRaiser', 'categories'));
    }

    public function reply(Request $request, string $ticketId)
    {
        $agent = Auth::guard('agent')->user();

        $ticket = DB::table('cbe_tickets')->where('ticket_id', $ticketId)->where('is_deleted', false)->first();
        abort_if(! $ticket, 404);

        $canManage = CbeCommitteeAuthService::isAuthorizedManager($agent, $ticket->cbe_node_id);
        $isRaiser = $ticket->raised_by_agent_id === $agent->agent_id;
        abort_unless($canManage || $isRaiser, 403);

        $validated = $request->validate(['body' => ['required', 'string', 'max:3000']]);

        DB::table('cbe_ticket_replies')->insert([
            'reply_id' => (string) Str::uuid(),
            'ticket_id' => $ticketId,
            'sender_agent_id' => $agent->agent_id,
            'body' => $validated['body'],
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('cbe_tickets')->where('ticket_id', $ticketId)->update(['updated_at' => now()]);

        return redirect()->route('cbe.tickets.show', $ticketId)->with('success', __('cbe_records.ticket_reply_success'));
    }

    // Secretary/officer-only — per Chris: "Secretary triaging everything
    // first." Sets/changes category, who's handling it, and status.
    public function triage(Request $request, string $ticketId)
    {
        $agent = Auth::guard('agent')->user();

        $ticket = DB::table('cbe_tickets')->where('ticket_id', $ticketId)->where('is_deleted', false)->first();
        abort_if(! $ticket, 404);
        abort_unless(CbeCommitteeAuthService::isAuthorizedManager($agent, $ticket->cbe_node_id), 403, __('cbe_records.not_authorized_note'));

        $validated = $request->validate([
            'category_id' => ['nullable', 'uuid', 'exists:cbe_ticket_categories,id'],
            'status' => ['required', 'in:OPEN,TRIAGED,IN_PROGRESS,RESOLVED,CLOSED'],
            'assigned_to_agent_id' => ['nullable', 'uuid'],
        ]);

        $wasTriaged = $ticket->status !== 'OPEN';

        $update = [
            'category_id' => $validated['category_id'] ?? $ticket->category_id,
            'status' => $validated['status'],
            'assigned_to_agent_id' => $validated['assigned_to_agent_id'] ?: null,
            'updated_at' => now(),
        ];
        if (! $wasTriaged) {
            $update['triaged_by_agent_id'] = $agent->agent_id;
            $update['triaged_at'] = now();
        }
        if (in_array($validated['status'], ['RESOLVED', 'CLOSED']) && ! $ticket->resolved_at) {
            $update['resolved_at'] = now();
        }

        DB::table('cbe_tickets')->where('ticket_id', $ticketId)->update($update);

        return redirect()->route('cbe.tickets.show', $ticketId)->with('success', __('cbe_records.ticket_triaged_success'));
    }

    // AJAX — "Assign to" typeahead, same active-members-of-this-entity
    // restriction as Messaging's recipient typeahead.
    public function assigneeTypeahead(Request $request, string $ticketId)
    {
        $agent = Auth::guard('agent')->user();
        $ticket = DB::table('cbe_tickets')->where('ticket_id', $ticketId)->first();
        abort_if(! $ticket, 404);
        abort_unless(CbeCommitteeAuthService::isAuthorizedManager($agent, $ticket->cbe_node_id), 403);

        $term = trim((string) $request->get('q', ''));
        if ($term === '') {
            return response()->json([]);
        }

        $results = DB::table('cbe_group_memberships as m')
            ->join('agents as a', 'a.agent_id', '=', 'm.agent_id')
            ->where('m.cbe_node_id', $ticket->cbe_node_id)
            ->where('m.status', 'ACTIVE')
            ->where('a.full_name', 'like', "%{$term}%")
            ->orderBy('a.full_name')
            ->limit(15)
            ->select('a.agent_id', 'a.full_name')
            ->get();

        return response()->json($results);
    }
}
