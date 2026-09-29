<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// NEW 17 Sep 2026 — per Chris ("yes build all this for me" — Committee
// term-expiry alerts was one of the 7 approved secretarial gaps):
// notifies the outgoing committee member themselves, plus this
// entity's officers and Admin, once a cbe_committee_positions term is
// within 30 days of its term_end_date. Safe to run daily —
// term_expiry_notified_at guards against sending the same alert twice
// for the same term, same pattern as CheckFraudReviewReminders.
class CheckCommitteeTermExpiry extends Command
{
    protected $signature = 'cbe:check-committee-term-expiry';
    protected $description = 'Notifies outgoing committee members and officers when a committee position term is about to expire';

    public function handle()
    {
        $leadDays = 30;
        $cutoff = now()->addDays($leadDays)->toDateString();
        $today = now()->toDateString();

        $notificationService = app(NotificationService::class);

        $expiring = DB::table('cbe_committee_positions as p')
            ->join('cbe_group_memberships as m', 'm.membership_id', '=', 'p.membership_id')
            ->join('agents as a', 'a.agent_id', '=', 'm.agent_id')
            ->whereNull('p.term_expiry_notified_at')
            ->whereDate('p.term_end_date', '>=', $today)
            ->whereDate('p.term_end_date', '<=', $cutoff)
            ->get(['p.position_id', 'p.cbe_node_id', 'p.position_title', 'p.term_end_date', 'a.agent_id', 'a.full_name', 'a.email']);

        foreach ($expiring as $row) {
            $outgoing = Agent::find($row->agent_id);
            $officers = Agent::whereIn('agent_id', DB::table('cbe_node_officers')
                ->where('node_id', $row->cbe_node_id)->where('is_active', true)->pluck('agent_id'))
                ->where('is_deleted', false)->get();
            $admins = Agent::where('role', 'ADMIN')->where('is_deleted', false)->get();

            $recipients = collect($outgoing ? [$outgoing] : [])->merge($officers)->merge($admins)->unique('agent_id');

            $daysLeft = now()->diffInDays($row->term_end_date, false);
            $notificationService->notify(
                $recipients->all(),
                'CBE_COMMITTEE_TERM_EXPIRY',
                'Committee Term Ending Soon',
                "{$row->full_name}'s term as {$row->position_title} ends on " . \Carbon\Carbon::parse($row->term_end_date)->format('d M Y') . " ({$daysLeft} day(s) left). Please arrange the next term's appointment.",
                $row->agent_id
            );

            DB::table('cbe_committee_positions')->where('position_id', $row->position_id)->update(['term_expiry_notified_at' => now()]);
            $this->info("Notified: {$row->position_id}");
        }

        $this->info('Committee term-expiry check complete.');
    }
}
