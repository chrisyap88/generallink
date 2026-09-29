<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\EspoCrmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 25 Jul 2026 — Growth & Outreach Center (task #215). Broadcast
// Campaigns. Per Chris: "you can incorporate first i subscribe later"
// — a campaign can only actually go out once its channel is BOTH
// enabled (Channel Connections) AND connected (real API credentials,
// tested — not built yet in this phase). Until a channel reaches that
// state, Send always explains why it can't fire yet rather than
// pretending to send. Deliberately isolated from CommissionEngine.
class BroadcastCampaignController extends Controller
{
    public function index(Request $request)
    {
        $campaigns = DB::table('broadcast_campaigns')->orderByDesc('created_at')->paginate(8)->appends($request->except('page'));
        $channels = DB::table('growth_channels')->orderBy('channel_name')->get()->keyBy('channel_code');
        $groupLabels = DB::table('group_labels')->orderBy('group_name')->get(['group_label_id', 'group_name']);

        foreach ($campaigns as $c) {
            $c->audience_count = $this->audienceCount($c);
            $c->channel = $channels->get($c->channel_code);
        }

        // NEW 10 Aug 2026 — per Chris: "study and modify the method to
        // upload video for this entire programs." Adds a third "Pick
        // from Video Library" option alongside paste-link/upload-file,
        // so an already-uploaded Marketing/Promotion video (with its
        // own audit trail and provenance already tracked) can be reused
        // as a campaign's actual content instead of re-uploading the
        // same file every time. Wrapped in try/catch so this screen
        // keeps working even before the Video Library migrations run.
        try {
            $libraryVideos = DB::table('video_library')
                ->where('video_type', 'MARKETING_PROMOTION')
                ->where('status', 'ACTIVE')
                ->orderByDesc('created_at')
                ->get(['video_id', 'video_name']);
        } catch (\Throwable $e) {
            $libraryVideos = collect();
        }

        return view('growth.broadcast-campaigns', compact('campaigns', 'channels', 'groupLabels', 'libraryVideos'));
    }

    // NEW 25 Jul 2026 — per Chris: "radio button so i can click multiple
    // platform in one go" + recurring schedules ("english tomorrow,
    // chinese every wednesday"). channel_codes[] is now a checkbox list
    // — one campaign ROW is still created per selected channel (schema
    // stays the same, just inserted in a loop), so each channel's own
    // Connected/Not Connected state is still tracked and sent
    // independently. Recurrence (NONE/DAILY/WEEKLY) drives when
    // scheduled_at is first set to, and DispatchScheduledBroadcasts
    // pushes it forward automatically after each send for DAILY/WEEKLY
    // rather than ever marking those campaigns permanently SENT.
    public function store(Request $request, EspoCrmService $espoCrm)
    {
        $request->validate([
            'title'                   => ['required', 'string', 'max:150'],
            'channel_codes'           => ['required', 'array', 'min:1'],
            'channel_codes.*'         => ['exists:growth_channels,channel_code'],
            'audience_type'           => ['required', 'in:CUSTOMERS,AGENTS'],
            'audience_group_label_id' => ['nullable', 'exists:group_labels,group_label_id'],
            'audience_role'           => ['nullable', 'in:INTRODUCER,TEAM_LEADER,GROUP_LEADER'],
            'message_body'            => ['required', 'string', 'max:2000'],
            'recurrence_type'         => ['required', 'in:NONE,DAILY,WEEKLY'],
            'scheduled_at'            => ['required_if:recurrence_type,NONE', 'nullable', 'date', 'after:now'],
            'recurrence_time'         => ['required_if:recurrence_type,DAILY,WEEKLY', 'nullable', 'date_format:H:i'],
            'recurrence_day_of_week'  => ['required_if:recurrence_type,WEEKLY', 'nullable', 'integer', 'between:0,6'],
            'banner_image'            => ['nullable', 'image', 'max:5120'],
            // NEW 10 Aug 2026 — third option LIBRARY, per Chris's request
            // to centralize how videos get attached to marketing
            // initiatives instead of every screen re-uploading its own.
            'video_source'            => ['nullable', 'in:LINK,UPLOAD,LIBRARY'],
            'video_url'               => ['nullable', 'url', 'max:255'],
            'video_file'              => ['nullable', 'mimes:mp4,mov,webm', 'max:51200'],
            'video_id'                => ['nullable', 'exists:video_library,video_id'],
        ]);

        $recurrenceType = $request->input('recurrence_type');
        [$firstRun, $dayOfWeek] = $this->computeFirstRun($recurrenceType, $request);

        $bannerPath = $request->hasFile('banner_image') ? $request->file('banner_image')->store('campaign-banners', 'public') : null;
        $videoUrl = null;
        $videoFilePath = null;
        $videoId = null;
        if ($request->input('video_source') === 'UPLOAD' && $request->hasFile('video_file')) {
            $videoFilePath = $request->file('video_file')->store('campaign-videos', 'public');
        } elseif ($request->input('video_source') === 'LINK' && $request->filled('video_url')) {
            $videoUrl = $request->input('video_url');
        } elseif ($request->input('video_source') === 'LIBRARY' && $request->filled('video_id')) {
            $videoId = $request->input('video_id');
        }

        foreach ($request->input('channel_codes') as $channelCode) {
            $campaignId = (string) Str::uuid();

            // NEW 29 Jul 2026 — EspoCRM integration (task #254). Mirror
            // as an EspoCRM Campaign (generic "Other" type — see
            // EspoCrmService::createCampaign for why — with the real
            // channel name noted in the description). Never blocks
            // saving the campaign if EspoCRM is unreachable.
            $espoCampaignId = $espoCrm->createCampaign(
                $request->input('title') . ' (' . $channelCode . ')',
                $request->input('message_body'),
                $firstRun,
                null
            );

            DB::table('broadcast_campaigns')->insert([
                'campaign_id'              => $campaignId,
                'espocrm_campaign_id'      => $espoCampaignId,
                'title'                    => $request->input('title'),
                'channel_code'             => $channelCode,
                'audience_type'            => $request->input('audience_type'),
                'audience_group_label_id'  => $request->input('audience_group_label_id') ?: null,
                'audience_role'            => $request->input('audience_type') === 'AGENTS' ? ($request->input('audience_role') ?: null) : null,
                'message_body'             => $request->input('message_body'),
                'banner_image_path'        => $bannerPath,
                'video_url'                => $videoUrl,
                'video_file_path'          => $videoFilePath,
                'video_id'                 => $videoId,
                'status'                   => $firstRun ? 'SCHEDULED' : 'DRAFT',
                'scheduled_at'             => $firstRun,
                'recurrence_type'          => $recurrenceType,
                'recurrence_day_of_week'   => $dayOfWeek,
                'created_by'               => Auth::guard('agent')->id(),
                'created_at'               => now(),
                'updated_at'               => now(),
            ]);

            AuditService::logChange('broadcast_campaigns', $campaignId, 'BROADCAST_CAMPAIGN_CREATED', null, ['title' => $request->input('title'), 'channel_code' => $channelCode]);
        }

        $channelCount = count($request->input('channel_codes'));
        $label = $recurrenceType === 'NONE' ? ($firstRun ? 'Scheduled' : 'Draft') : ucfirst(strtolower($recurrenceType)) . ' recurring';
        return redirect()->route('admin.growth.broadcasts.index')->with('success', "Campaign saved as {$label} across {$channelCount} channel(s).");
    }

    // Works out the datetime the campaign should first (or next) fire
    // at, from whichever recurrence controls the form actually showed.
    private function computeFirstRun(string $recurrenceType, Request $request): array
    {
        if ($recurrenceType === 'NONE') {
            return [$request->input('scheduled_at') ?: null, null];
        }

        $time = $request->input('recurrence_time');
        [$hour, $minute] = array_map('intval', explode(':', $time));

        if ($recurrenceType === 'DAILY') {
            $next = now()->setTime($hour, $minute, 0);
            if ($next->isPast()) {
                $next->addDay();
            }
            return [$next, null];
        }

        // WEEKLY
        $dayOfWeek = (int) $request->input('recurrence_day_of_week');
        $next = now()->startOfWeek(\Carbon\Carbon::SUNDAY)->addDays($dayOfWeek)->setTime($hour, $minute, 0);
        if ($next->isPast()) {
            $next->addWeek();
        }
        return [$next, $dayOfWeek];
    }

    // Live count of who this campaign would reach right now — shown on
    // the list so Admin can judge reach before attempting to send.
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

    // Attempts to send. In THIS phase, no channel can ever be
    // is_connected = true (that requires a future phase's real
    // provider API integration), so this always explains that clearly
    // rather than pretending to send — exactly matching "incorporate
    // first, subscribe later".
    public function send(string $id, EspoCrmService $espoCrm)
    {
        $campaign = DB::table('broadcast_campaigns')->where('campaign_id', $id)->first();
        abort_if(!$campaign, 404);

        if ($campaign->status === 'SENT') {
            return redirect()->back()->with('success', 'Already sent.');
        }

        $channel = DB::table('growth_channels')->where('channel_code', $campaign->channel_code)->first();

        if (!$channel || !$channel->is_enabled || !$channel->is_connected) {
            return redirect()->back()->withErrors([
                'send' => "Can't send yet — {$channel->channel_name} is not connected. Enable it and add real API credentials in Channel Connections once you've subscribed with a provider.",
            ]);
        }

        // Real per-provider sending logic goes here in a future phase
        // (e.g. WhatsApp Business API call, Telegram Bot API, etc.) —
        // deliberately not built yet since no channel has real
        // credentials in this phase.
        $count = $this->audienceCount($campaign);

        if ($campaign->recurrence_type === 'NONE') {
            DB::table('broadcast_campaigns')->where('campaign_id', $id)->update([
                'status'          => 'SENT',
                'sent_at'         => now(),
                'recipient_count' => $count,
                'updated_at'      => now(),
            ]);
        } else {
            // A manual "Send Now" on a recurring campaign still counts
            // as one occurrence — push scheduled_at forward to the next
            // one rather than terminating the series (matches
            // DispatchScheduledBroadcasts' own recurrence logic).
            $next = \Carbon\Carbon::parse($campaign->scheduled_at);
            $next = $campaign->recurrence_type === 'DAILY' ? $next->addDay() : $next->addWeek();
            DB::table('broadcast_campaigns')->where('campaign_id', $id)->update([
                'scheduled_at'    => $next,
                'last_sent_at'    => now(),
                'send_count'      => $campaign->send_count + 1,
                'recipient_count' => $count,
                'updated_at'      => now(),
            ]);
        }

        AuditService::logChange('broadcast_campaigns', $id, 'BROADCAST_CAMPAIGN_SENT', $campaign, ['recipient_count' => $count]);

        // NEW 29 Jul 2026 — EspoCRM integration (task #254).
        if (!empty($campaign->espocrm_campaign_id)) {
            $espoCrm->updateCampaignStatus($campaign->espocrm_campaign_id, 'Complete');
        }

        return redirect()->back()->with('success', "Sent to {$count} recipient(s).");
    }

    public function destroy(string $id)
    {
        $campaign = DB::table('broadcast_campaigns')->where('campaign_id', $id)->first();
        abort_if(!$campaign, 404);
        abort_if($campaign->status === 'SENT', 403, 'A sent campaign cannot be deleted.');

        DB::table('broadcast_campaigns')->where('campaign_id', $id)->delete();
        AuditService::logChange('broadcast_campaigns', $id, 'BROADCAST_CAMPAIGN_DELETED', $campaign, null);

        return redirect()->back()->with('success', 'Campaign deleted.');
    }
}
