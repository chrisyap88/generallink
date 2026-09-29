<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AuditService;
use App\Services\DataScopeService;
use App\Services\EspoCrmService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// -------------------------------------------------------
// NEW 29 Jul 2026 — Support Tickets (task #259). Per Chris: "make full
// use of EspoCRM" — this is the Help Desk / Ticket Management / Case
// Management / Complaint Management / Service Request module identified
// in the EspoCRM Feature Reuse Review as a genuine, low-cost reuse win
// (EspoCRM's Case entity is free, core, and already has full REST API
// access — see EspoCRM_Feature_Reuse_Review.docx, Section 3). Same
// pattern as every other integration this session: GeneralLink is the
// only screen any agent ever touches; EspoCRM's Case entity is a
// one-way mirror behind it, kept in sync via EspoCrmService.
//
// Scope follows the same rule as the Customer record itself
// (DataScopeService::applyToCustomers — owning agent only, never
// downline) since a ticket only ever exists attached to a customer.
// -------------------------------------------------------
class SupportTicketController extends Controller
{
    // SLA targets by priority — GeneralLink's OWN escalation logic (not
    // EspoCRM's paid Workflow/BPM add-on — see Feature Reuse Review,
    // Section 5). Checked by the tickets:check-sla scheduled command.
    private const SLA_HOURS = [
        'HIGH' => 4,
        'MEDIUM' => 24,
        'LOW' => 72,
    ];

    private function rolePrefix($agent): string
    {
        return match ($agent->role) {
            'ADMIN' => 'admin',
            'GROUP_LEADER' => 'gl',
            'TEAM_LEADER' => 'tl',
            default => 'introducer',
        };
    }

    /**
     * Standalone "Support Tickets" screen — every ticket within the
     * logged-in agent's own scope, across all their customers, so
     * tickets can be triaged without opening each customer individually.
     */
    public function index(Request $request)
    {
        $agent = auth('agent')->user();
        $scope = new DataScopeService();
        $rolePrefix = $this->rolePrefix($agent);

        $query = DB::table('customer_support_tickets as t')
            ->join('customers as c', 't.customer_id', '=', 'c.customer_id')
            ->where('t.is_deleted', false);
        $scope->applyToCustomers($query, 'c');

        if ($request->filled('status')) {
            $query->where('t.status', $request->status);
        } else {
            // Default view: hide Closed tickets so the list stays
            // focused on what still needs attention — Closed is still
            // reachable via the Status filter.
            $query->where('t.status', '!=', 'CLOSED');
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('t.subject', 'like', "%{$search}%")
                  ->orWhere('c.full_name', 'like', "%{$search}%");
            });
        }

        $tickets = $query->orderByRaw("t.status = 'CLOSED' asc")
            ->orderByRaw("t.due_at is null asc")
            ->orderBy('t.due_at')
            ->select('t.*', 'c.full_name as customer_name')
            ->paginate(10)->appends($request->query());

        return view('support-tickets.index', compact('tickets', 'rolePrefix'));
    }

    // NEW — type-ahead suggestions for the search box above, matching
    // ticket subject and the customer's name, within the same scope
    // rule as index() so an agent never sees a suggestion for a ticket
    // outside their own scope.
    public function typeahead(Request $request)
    {
        $scope = new DataScopeService();
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 1) {
            return response()->json([]);
        }
        $needle = '%'.$q.'%';

        $query = DB::table('customer_support_tickets as t')
            ->join('customers as c', 't.customer_id', '=', 'c.customer_id')
            ->where('t.is_deleted', false);
        $scope->applyToCustomers($query, 'c');

        $results = $query
            ->where(function ($qr) use ($needle) {
                $qr->where('t.subject', 'like', $needle)->orWhere('c.full_name', 'like', $needle);
            })
            ->orderByDesc('t.created_at')->limit(15)
            ->get(['t.ticket_id', 't.subject', 'c.full_name as customer_name']);

        return response()->json($results);
    }

    public function store(Request $request, string $customerId, EspoCrmService $espoCrm)
    {
        $agent = auth('agent')->user();
        $scope = new DataScopeService();
        $rolePrefix = $this->rolePrefix($agent);

        $query = DB::table('customers')->where('customer_id', $customerId)->where('is_deleted', false);
        $scope->applyToCustomers($query);
        $customer = $query->first();
        abort_if(!$customer, 404);

        $validated = $request->validate([
            'subject'     => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'ticket_type' => ['required', 'in:COMPLAINT,SERVICE_REQUEST,QUESTION,OTHER'],
            'priority'    => ['required', 'in:LOW,MEDIUM,HIGH'],
        ]);

        $ticketId = (string) Str::uuid();
        $dueAt = now()->addHours(self::SLA_HOURS[$validated['priority']]);

        $typeLabel = ucwords(strtolower(str_replace('_', ' ', $validated['ticket_type'])));

        // NEW 29 Jul 2026 — EspoCRM integration (task #259). Mirrors this
        // ticket as a Case in EspoCRM. Never blocks the ticket from being
        // saved in GeneralLink if EspoCRM is down/misconfigured — see
        // EspoCrmService::createCase().
        $espoCaseId = $espoCrm->createCase(
            "[{$typeLabel}] {$validated['subject']} — {$customer->full_name}",
            $validated['description'] ?? null,
            $customer->espocrm_contact_id ?? null,
            $validated['priority']
        );

        DB::table('customer_support_tickets')->insert([
            'ticket_id'         => $ticketId,
            'customer_id'       => $customerId,
            'owned_by_agent_id' => $agent->agent_id,
            'subject'           => $validated['subject'],
            'description'       => $validated['description'] ?? null,
            'ticket_type'       => $validated['ticket_type'],
            'priority'          => $validated['priority'],
            'status'            => 'OPEN',
            'due_at'            => $dueAt,
            'espocrm_case_id'   => $espoCaseId,
            'is_deleted'        => false,
            'created_by'        => $agent->agent_id,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        AuditService::logChange('customer_support_tickets', $ticketId, 'CREATE', null, ['subject' => $validated['subject'], 'type' => $validated['ticket_type']], $agent->agent_id);

        return redirect()->route($rolePrefix . '.customers.show', $customerId)->with('success', 'Support ticket logged.')->withFragment('cuTickets');
    }

    /**
     * Change a ticket's status — In Progress / Resolved / Closed.
     * Notifies the ticket's owning agent's upline chain when a ticket is
     * marked Resolved or Closed (same "keep everyone informed" pattern
     * as the Follow Up Reminder's Save & Send), so a closed complaint
     * doesn't just quietly disappear.
     */
    public function updateStatus(Request $request, string $ticketId, EspoCrmService $espoCrm)
    {
        $agent = auth('agent')->user();
        $scope = new DataScopeService();
        $rolePrefix = $this->rolePrefix($agent);

        $ticket = DB::table('customer_support_tickets as t')
            ->join('customers as c', 't.customer_id', '=', 'c.customer_id')
            ->where('t.ticket_id', $ticketId)
            ->where('t.is_deleted', false)
            ->select('t.*', 'c.full_name as customer_name')
            ->first();
        abort_if(!$ticket, 404);

        $custQuery = DB::table('customers')->where('customer_id', $ticket->customer_id);
        $scope->applyToCustomers($custQuery);
        abort_if(!$custQuery->exists(), 403);

        $validated = $request->validate([
            'status' => ['required', 'in:OPEN,IN_PROGRESS,RESOLVED,CLOSED'],
        ]);

        $update = [
            'status'     => $validated['status'],
            'updated_at' => now(),
        ];
        if (in_array($validated['status'], ['RESOLVED', 'CLOSED']) && !$ticket->resolved_at) {
            $update['resolved_at'] = now();
        }

        DB::table('customer_support_tickets')->where('ticket_id', $ticketId)->update($update);

        // NEW 29 Jul 2026 — EspoCRM integration (task #259).
        if (!empty($ticket->espocrm_case_id)) {
            $espoCrm->updateCaseStatus($ticket->espocrm_case_id, $validated['status']);
        }

        if (in_array($validated['status'], ['RESOLVED', 'CLOSED'])) {
            $owner = Agent::find($ticket->owned_by_agent_id);
            if ($owner) {
                $notifyService = new NotificationService();
                $notifyService->notify(
                    $notifyService->recipientsForUplineChain($owner),
                    'SUPPORT_TICKET_RESOLVED',
                    'Support ticket ' . strtolower($validated['status']) . ' — ' . $ticket->customer_name,
                    "\"{$ticket->subject}\" for {$ticket->customer_name} was marked " . strtolower($validated['status']) . " by {$agent->full_name}.",
                    $agent->agent_id
                );
            }
        }

        return redirect()->back()->with('success', 'Ticket updated.');
    }
}
