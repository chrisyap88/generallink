<?php

namespace App\Services;

use App\Services\Integrations\Communication\TwilioSmsConnector;
use App\Services\Integrations\Communication\VonageSmsConnector;
use App\Services\Integrations\Communication\WhatsAppCloudConnector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

// NEW 8 Aug 2026 — GLADE Ecosystem Engagement, Phase 1 (Task #84). When
// Admin posts a Notice Board item, this pushes it out through each
// agent's own chosen channels (agent_notification_preferences) — beyond
// just waiting for them to browse the Notice Board. Portal is always
// implicit (the Notice Board itself) and never logged here. Every other
// channel attempt is logged to notice_deliveries so the same notice is
// never pushed twice to the same agent on the same channel, and so each
// agent's weekly frequency cap can be enforced by counting recent rows.
//
// Channel readiness today: EMAIL and WHATSAPP can actually deliver.
// TELEGRAM/LINE/WECHAT/SMS are accepted as valid preference choices (per
// Chris: agents pick once, never redo it) but are recorded as SKIPPED
// with a plain-language reason until Task #85 builds those connectors.
class NoticeDeliveryService
{
    public function __construct(
        private HubVaultService $vault,
        private WhatsAppCloudConnector $whatsapp,
        private TwilioSmsConnector $twilio,
        private VonageSmsConnector $vonage,
    ) {}

    public function deliver(string $noticeId): void
    {
        $notice = DB::table('notices')->where('notice_id', $noticeId)->first();
        if (!$notice) return;

        $agents = DB::table('agents')->where('status', 'ACTIVE')->where('agent_id', '!=', $notice->posted_by_agent_id)->get(['agent_id', 'email', 'phone']);

        foreach ($agents as $agent) {
            $pref = DB::table('agent_notification_preferences')->where('agent_id', $agent->agent_id)->first();

            // No preference row yet = default (all categories, Portal only) — nothing to push beyond the Notice Board itself.
            if (!$pref) continue;

            $categories = $pref->categories ? json_decode($pref->categories, true) : null;
            if ($categories && !in_array($notice->category, $categories, true)) continue;

            $channels = json_decode($pref->channels ?? '["PORTAL"]', true) ?: ['PORTAL'];
            $pushChannels = array_diff($channels, ['PORTAL']); // Portal needs no delivery row
            if (empty($pushChannels)) continue;

            if ($pref->frequency_cap_per_week) {
                $sentThisWeek = DB::table('notice_deliveries')
                    ->where('agent_id', $agent->agent_id)
                    ->where('status', 'SENT')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count();
                if ($sentThisWeek >= $pref->frequency_cap_per_week) continue;
            }

            foreach ($pushChannels as $channel) {
                $this->pushOne($notice, $agent, $channel);
            }
        }
    }

    private function pushOne(object $notice, object $agent, string $channel): void
    {
        $exists = DB::table('notice_deliveries')
            ->where('notice_id', $notice->notice_id)->where('agent_id', $agent->agent_id)->where('channel', $channel)
            ->exists();
        if ($exists) return;

        $row = [
            'delivery_id' => (string) Str::uuid(),
            'notice_id' => $notice->notice_id,
            'agent_id' => $agent->agent_id,
            'channel' => $channel,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($channel === 'EMAIL') {
            try {
                Mail::html('<h3>' . e($notice->title) . '</h3><p>' . nl2br(e($notice->body)) . '</p><p style="color:#9ca3af;font-size:11px;">Sent by GeneralLink based on your notification preferences — manage them in My Profile.</p>', function ($m) use ($agent, $notice) {
                    $m->to($agent->email)->subject('[GeneralLink] ' . $notice->title);
                });
                $row['status'] = 'SENT';
                $row['sent_at'] = now();
            } catch (\Throwable $e) {
                Log::warning('NoticeDelivery: email failed', ['agent_id' => $agent->agent_id, 'notice_id' => $notice->notice_id]);
                $row['status'] = 'FAILED';
                $row['detail'] = 'Email could not be sent.';
            }
        } elseif ($channel === 'WHATSAPP') {
            $result = $this->sendWhatsApp($notice, $agent);
            $row['status'] = $result['success'] ? 'SENT' : ($result['skip'] ? 'SKIPPED' : 'FAILED');
            $row['detail'] = $result['message'] ?? null;
            if ($result['success']) $row['sent_at'] = now();
        } elseif ($channel === 'SMS') {
            // NEW 8 Aug 2026 (Task #85) — SMS uses real phone numbers, so
            // (unlike Telegram/LINE/WeChat) this can actually send today.
            // Tries whichever SMS provider the notice's poster has
            // connected — Twilio first, then Vonage.
            $result = $this->sendSms($notice, $agent);
            $row['status'] = $result['success'] ? 'SENT' : ($result['skip'] ? 'SKIPPED' : 'FAILED');
            $row['detail'] = $result['message'] ?? null;
            if ($result['success']) $row['sent_at'] = now();
        } else {
            // TELEGRAM / LINE / WECHAT — real connectors exist (Task #85),
            // but sending needs each agent's platform-specific chat/user ID,
            // which nowhere in GeneralLink collects yet. Honest SKIP rather
            // than pretending a phone number works on these three.
            $row['status'] = 'SKIPPED';
            $row['detail'] = ucfirst(strtolower($channel)) . ' needs the agent to link their account first — not available yet.';
        }

        DB::table('notice_deliveries')->insert($row);
    }

    private function sendWhatsApp(object $notice, object $agent): array
    {
        if (!$agent->phone) {
            return ['success' => false, 'skip' => true, 'message' => 'No phone number on file for this agent.'];
        }

        // Sends via the notice POSTER's own connected WhatsApp (per-agent
        // Integration Hub — same vault discipline as everywhere else).
        // If Admin's own Hub is locked this session, WhatsApp pushes are
        // skipped (not failed) rather than blocking the whole notice post.
        $posterId = $notice->posted_by_agent_id;
        if (!$this->vault->isUnlocked($posterId)) {
            return ['success' => false, 'skip' => true, 'message' => 'Poster\'s Integration Hub is locked this session.'];
        }

        $row = DB::table('agent_integrations')->where('agent_id', $posterId)->where('category', 'communication')->where('provider', 'whatsapp')->first();
        if (!$row || $row->status !== 'CONNECTED') {
            return ['success' => false, 'skip' => true, 'message' => 'Poster has no connected WhatsApp number.'];
        }

        try {
            $credentials = [
                'client_id' => $this->vault->decryptSecret($posterId, $row->client_id_encrypted),
                'client_secret' => $this->vault->decryptSecret($posterId, $row->client_secret_encrypted),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'skip' => true, 'message' => 'Poster\'s WhatsApp credentials could not be read.'];
        }

        $message = $notice->title . "\n\n" . $notice->body;
        $result = $this->whatsapp->sendMessage($credentials, $agent->phone, $message);

        return ['success' => $result['success'], 'skip' => false, 'message' => $result['message']];
    }

    private function sendSms(object $notice, object $agent): array
    {
        if (!$agent->phone) {
            return ['success' => false, 'skip' => true, 'message' => 'No phone number on file for this agent.'];
        }

        $posterId = $notice->posted_by_agent_id;
        if (!$this->vault->isUnlocked($posterId)) {
            return ['success' => false, 'skip' => true, 'message' => 'Poster\'s Integration Hub is locked this session.'];
        }

        $twilioRow = DB::table('agent_integrations')->where('agent_id', $posterId)->where('category', 'communication')->where('provider', 'twilio')->where('status', 'CONNECTED')->first();
        $vonageRow = DB::table('agent_integrations')->where('agent_id', $posterId)->where('category', 'communication')->where('provider', 'vonage')->where('status', 'CONNECTED')->first();

        if (!$twilioRow && !$vonageRow) {
            return ['success' => false, 'skip' => true, 'message' => 'Poster has no connected SMS provider (Twilio or Vonage).'];
        }

        $message = $notice->title . "\n\n" . $notice->body;

        try {
            if ($twilioRow) {
                $credentials = [
                    'client_id' => $this->vault->decryptSecret($posterId, $twilioRow->client_id_encrypted),
                    'client_secret' => $this->vault->decryptSecret($posterId, $twilioRow->client_secret_encrypted),
                ];
                $result = $this->twilio->sendMessage($credentials, $agent->phone, $message);
            } else {
                $credentials = [
                    'client_id' => $this->vault->decryptSecret($posterId, $vonageRow->client_id_encrypted),
                    'client_secret' => $this->vault->decryptSecret($posterId, $vonageRow->client_secret_encrypted),
                ];
                $result = $this->vonage->sendMessage($credentials, $agent->phone, $message);
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'skip' => true, 'message' => 'Poster\'s SMS credentials could not be read.'];
        }

        return ['success' => $result['success'], 'skip' => false, 'message' => $result['message']];
    }
}
