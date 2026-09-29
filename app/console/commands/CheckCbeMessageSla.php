<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// NEW 18 Sep 2026 — per Chris: "if payment recived and the receipt take
// long time to issue the donor or the sponsor ... may not be happy and
// no confident" — a slow reply on a CBE message (payment notification,
// receipt request, credit/debit note, cheque, TT slip) must not go
// unnoticed. Same safe-to-run-often, cooldown-guarded pattern as
// CheckTicketSla — one escalation engine reused, not duplicated.
//
// A thread is "waiting on the recipient" when the most recent message
// was sent by the INITIATOR (the recipient hasn't replied yet) and the
// thread isn't RESOLVED. 2 days waiting -> OUTSTANDING (reminds the
// recipient). A further 3 days with still no reply -> ESCALATED
// (reminds the recipient's upline too, same as Support Ticket SLA).
class CheckCbeMessageSla extends Command
{
    protected $signature = 'cbe-messaging:check-sla';
    protected $description = 'Flags CBE messages (payment notifications, receipts, CN/DN, cheque/TT slip requests) as Outstanding or Escalated when nobody has replied in time';

    private const OUTSTANDING_AFTER_HOURS = 48;
    private const ESCALATED_AFTER_HOURS = 120;
    private const RENOTIFY_COOLDOWN_HOURS = 24;

    public function handle()
    {
        $cutoffOutstanding = now()->subHours(self::OUTSTANDING_AFTER_HOURS);
        $renotifyCutoff = now()->subHours(self::RENOTIFY_COOLDOWN_HOURS);

        DB::table('cbe_message_threads as t')
            ->whereNotIn('t.status', ['RESOLVED'])
            ->where('t.last_message_at', '<=', $cutoffOutstanding)
            ->where(function ($q) use ($renotifyCutoff) {
                $q->whereNull('t.sla_notified_at')->orWhere('t.sla_notified_at', '<=', $renotifyCutoff);
            })
            // Waiting on the recipient = the last message in the thread
            // was sent by the initiator, not the recipient.
            ->whereRaw('(SELECT sender_agent_id FROM cbe_messages m WHERE m.thread_id = t.thread_id ORDER BY m.created_at DESC LIMIT 1) = t.initiator_agent_id')
            ->orderBy('t.thread_id')
            ->chunkById(200, function ($threads) {
                foreach ($threads as $thread) {
                    $this->processOne($thread);
                }
            }, 't.thread_id');

        $this->info('CBE message SLA sweep complete.');
    }

    private function processOne(object $thread): void
    {
        $recipient = Agent::find($thread->recipient_agent_id);
        if (! $recipient || $recipient->is_deleted) {
            return;
        }

        $hoursWaiting = now()->diffInHours($thread->last_message_at);
        $newStatus = $hoursWaiting >= self::ESCALATED_AFTER_HOURS ? 'ESCALATED' : 'OUTSTANDING';

        $notifyService = new NotificationService();

        if ($newStatus === 'ESCALATED') {
            // Same as Support Ticket SLA — the recipient's upline gets
            // pulled in too once it's gone on long enough.
            $recipients = collect([$recipient])
                ->merge($notifyService->recipientsForUplineChain($recipient))
                ->unique('agent_id')
                ->values()
                ->all();
        } else {
            $recipients = [$recipient];
        }

        $notifyService->notify(
            $recipients,
            'CBE_MESSAGE_SLA',
            __('cbe_records.messaging_escalated_notif_title'),
            $thread->subject,
            $recipient->agent_id
        );

        DB::table('cbe_message_threads')->where('thread_id', $thread->thread_id)->update([
            'status' => $newStatus,
            'sla_notified_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
