<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 29 Jul 2026 — Support Ticket SLA/escalation (task #260). Per the
// EspoCRM Feature Reuse Review (Section 5): SLA tracking and escalation
// rules are NOT implemented inside EspoCRM's paid Workflow/BPM add-on.
// Instead this reuses the exact same "safe to run often, cooldown-guarded
// reminder" pattern already built for CheckUnclaimedCommissions and the
// renewal/follow-up reminder commands — one engine for every kind of
// reminder/escalation in GeneralLink, rather than splitting SLA logic
// off into EspoCRM's own tooling.
//
// A ticket is "breached" once due_at has passed and it's still open
// (not RESOLVED/CLOSED). sla_notified_at re-arms every 24 hours, so a
// still-breached ticket keeps reminding daily rather than spamming
// hourly or going silent after the first notice.
// -------------------------------------------------------
class CheckTicketSla extends Command
{
    protected $signature = 'tickets:check-sla';
    protected $description = 'Notifies the handling agent (and their upline) about Support Tickets that have breached their SLA due date';

    public function handle()
    {
        $cutoff = now()->subHours(24);

        DB::table('customer_support_tickets as t')
            ->where('t.is_deleted', false)
            ->whereNotIn('t.status', ['RESOLVED', 'CLOSED'])
            ->whereNotNull('t.due_at')
            ->where('t.due_at', '<=', now())
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('t.sla_notified_at')
                  ->orWhere('t.sla_notified_at', '<=', $cutoff);
            })
            ->join('customers as c', 't.customer_id', '=', 'c.customer_id')
            ->select('t.ticket_id', 't.subject', 't.priority', 't.owned_by_agent_id', 'c.full_name as customer_name')
            ->orderBy('t.ticket_id')
            ->chunkById(200, function ($tickets) {
                foreach ($tickets as $ticket) {
                    $this->processOne($ticket);
                }
            }, 't.ticket_id');

        $this->info('Support ticket SLA sweep complete.');
    }

    private function processOne(object $ticket): void
    {
        $owner = Agent::find($ticket->owned_by_agent_id);
        if (!$owner || $owner->is_deleted) {
            return;
        }

        $title = 'Support ticket overdue — ' . $ticket->customer_name;
        $message = "\"{$ticket->subject}\" ({$ticket->priority} priority) for {$ticket->customer_name} has passed its response target and is still open. Please follow up.";

        // Unlike the "mark resolved" notification (where the acting agent
        // already knows what they just did), the handling agent here has
        // taken no action yet — they need the notice too, not just their
        // upline. recipientsForUplineChain() always excludes its own
        // subject, so the owner is merged back in explicitly.
        $notifyService = new NotificationService();
        $recipients = collect([$owner])
            ->merge($notifyService->recipientsForUplineChain($owner))
            ->unique('agent_id')
            ->values()
            ->all();
        $notifyService->notify(
            $recipients,
            'SUPPORT_TICKET_SLA_BREACH',
            $title,
            $message,
            $owner->agent_id
        );

        DB::table('customer_support_tickets')
            ->where('ticket_id', $ticket->ticket_id)
            ->update(['sla_notified_at' => now(), 'updated_at' => now()]);
    }
}
