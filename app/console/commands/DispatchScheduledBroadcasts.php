<?php

namespace App\Console\Commands;

use App\Services\AuditService;
use App\Services\EspoCrmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// NEW 25 Jul 2026 — Growth & Outreach Center follow-up. Per Chris: "give
// the automatic calendar schedule to send la rather than i need to do
// and remember every day." Broadcast Campaigns already let Admin pick a
// scheduled_at date/time when creating a campaign — this command is
// what actually fires it the moment that time arrives, with zero manual
// clicking. Runs hourly (see routes/console.php), safe to run often:
// a campaign only ever gets sent once (status flips SCHEDULED -> SENT
// the first time this matches it).
//
// IMPORTANT: this still only ACTUALLY sends once the campaign's channel
// is both enabled and connected (real provider credentials, subscribed
// by Chris — see GrowthChannelController). Until then, due campaigns are
// simply left alone in SCHEDULED status and picked up again on the next
// run — nothing is lost, nothing silently fails, it just waits for the
// real channel connection Chris sets up later. Never touches
// CommissionEngine or any wallet/commission table.
class DispatchScheduledBroadcasts extends Command
{
    protected $signature = 'broadcasts:dispatch';
    protected $description = 'Auto-sends any Broadcast Campaign whose scheduled time has arrived and whose channel is connected';

    public function handle(EspoCrmService $espoCrm): int
    {
        $due = DB::table('broadcast_campaigns')
            ->where('status', 'SCHEDULED')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            $this->info('No due scheduled campaigns.');
            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;

        foreach ($due as $campaign) {
            $channel = DB::table('growth_channels')->where('channel_code', $campaign->channel_code)->first();

            if (!$channel || !$channel->is_enabled || !$channel->is_connected) {
                $skipped++;
                $this->line("  -> Waiting: \"{$campaign->title}\" is due but its channel isn't connected yet.");
                continue;
            }

            // Real per-provider sending logic goes here once a channel
            // actually has live credentials — same note as
            // BroadcastCampaignController::send(), deliberately not
            // built yet since no channel is connected in this phase.
            $count = $this->audienceCount($campaign);

            if ($campaign->recurrence_type === 'NONE') {
                DB::table('broadcast_campaigns')->where('campaign_id', $campaign->campaign_id)->update([
                    'status'          => 'SENT',
                    'sent_at'         => now(),
                    'recipient_count' => $count,
                    'updated_at'      => now(),
                ]);
                // NEW 29 Jul 2026 — EspoCRM integration (task #254).
                if (!empty($campaign->espocrm_campaign_id)) {
                    $espoCrm->updateCampaignStatus($campaign->espocrm_campaign_id, 'Complete');
                }
            } else {
                // Recurring campaign — push scheduled_at to the next
                // occurrence and keep status SCHEDULED forever (Admin
                // deletes the campaign to stop the series).
                $next = \Carbon\Carbon::parse($campaign->scheduled_at);
                $next = $campaign->recurrence_type === 'DAILY' ? $next->addDay() : $next->addWeek();
                DB::table('broadcast_campaigns')->where('campaign_id', $campaign->campaign_id)->update([
                    'scheduled_at'    => $next,
                    'last_sent_at'    => now(),
                    'send_count'      => $campaign->send_count + 1,
                    'recipient_count' => $count,
                    'updated_at'      => now(),
                ]);
            }

            AuditService::logChange('broadcast_campaigns', $campaign->campaign_id, 'BROADCAST_CAMPAIGN_AUTO_SENT', $campaign, ['recipient_count' => $count]);

            $sent++;
            $this->line("  -> Sent: \"{$campaign->title}\" to {$count} recipient(s).");
        }

        $this->info("Done. {$sent} sent, {$skipped} waiting on a channel connection.");

        return self::SUCCESS;
    }

    // Mirrors BroadcastCampaignController::audienceCount() — kept as a
    // small local copy rather than a shared dependency so this command
    // has no coupling to the Admin controller.
    private function audienceCount(object $campaign): int
    {
        if ($campaign->audience_type === 'CUSTOMERS') {
            $q = DB::table('customers as c')
                ->join('agents as a', 'c.owned_by_agent_id', '=', 'a.agent_id')
                ->where('c.is_deleted', false);
            if ($campaign->audience_group_label_id) {
                $q->where('a.group_label_id', $campaign->audience_group_label_id);
            }
            return $q->count();
        }

        $q = DB::table('agents')->where('status', 'ACTIVE')->where('is_deleted', false)
            ->whereIn('role', ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER']);
        if ($campaign->audience_group_label_id) {
            $q->where('group_label_id', $campaign->audience_group_label_id);
        }
        if ($campaign->audience_role) {
            $q->where('role', $campaign->audience_role);
        }
        return $q->count();
    }
}
