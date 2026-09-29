@extends('layouts.dashboard')

@section('page-title', __('growth.broadcast_campaigns_title'))

@section('content')

{{-- NEW 25 Jul 2026 (task #215) — Growth & Outreach Center. A campaign
     can only actually send once its channel is enabled AND connected
     (real API credentials from a provider) in Channel Connections —
     until then it stays Draft/Scheduled. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; align-items:flex-start; justify-content:space-between; gap:10px;">
        <div>
            <div style="font-size:9.5px; color:#9ca3af; margin-top:2px;">{{ __('growth.broadcast_campaigns_subtitle') }}</div>
        </div>
        @include('partials.feature-video-widget', ['featureKey' => 'BROADCAST_CAMPAIGNS'])
    </div>

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0; margin-bottom:6px;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; flex-shrink:0; margin-bottom:6px;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    <div style="display:flex; gap:12px; flex:1; min-height:0;">

        <div style="flex:1; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; display:flex; flex-direction:column; min-height:0;">
            <div style="flex:1; min-height:0; overflow-y:auto;">
                @forelse($campaigns as $c)
                <div style="border-bottom:1px solid #f3f4f6; padding:8px 4px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div style="font-size:11px; font-weight:700; color:#111827;">{{ $c->title }}</div>
                        @if($c->recurrence_type !== 'NONE')
                        <span style="background:#fef3c7; color:#92400e; font-size:8px; font-weight:700; padding:2px 8px; border-radius:20px;">{{ __('growth.recurring_badge') }}</span>
                        @else
                        <span style="background:{{ $c->status === 'SENT' ? '#f0fdf4' : ($c->status === 'SCHEDULED' ? '#f0f9ff' : '#f3f4f6') }}; color:{{ $c->status === 'SENT' ? '#166534' : ($c->status === 'SCHEDULED' ? '#0369a1' : '#6b7280') }}; font-size:8px; font-weight:700; padding:2px 8px; border-radius:20px;">{{ __('growth.broadcast_status_'.strtolower($c->status)) }}</span>
                        @endif
                    </div>
                    <div style="font-size:9.5px; color:#6b7280; margin:3px 0;">
                        {{ $c->channel->channel_name ?? $c->channel_code }} &middot;
                        {{ $c->audience_type === 'CUSTOMERS' ? __('growth.customers_option') : __('growth.agents_option') }}
                        ({{ __('growth.reachable_now_suffix', ['count' => $c->audience_count]) }})
                        @if($c->recurrence_type === 'DAILY')
                        &middot; {{ __('growth.repeats_daily_at', ['time' => \Illuminate\Support\Carbon::parse($c->scheduled_at)->format('H:i')]) }}
                        &middot; {{ __('growth.next_colon', ['datetime' => \Illuminate\Support\Carbon::parse($c->scheduled_at)->format('d M Y, H:i')]) }}
                        &middot; {{ __('growth.sent_x_times', ['value' => $c->send_count.'x']) }}{{ $c->last_sent_at ? __('growth.last_sent_suffix', ['date' => \Illuminate\Support\Carbon::parse($c->last_sent_at)->format('d M')]) : '' }}
                        @elseif($c->recurrence_type === 'WEEKLY')
                        &middot; {{ __('growth.repeats_weekly_at', ['day' => __('growth.day_'.strtolower(['sunday','monday','tuesday','wednesday','thursday','friday','saturday'][$c->recurrence_day_of_week])), 'time' => \Illuminate\Support\Carbon::parse($c->scheduled_at)->format('H:i')]) }}
                        &middot; {{ __('growth.next_colon', ['datetime' => \Illuminate\Support\Carbon::parse($c->scheduled_at)->format('d M Y, H:i')]) }}
                        &middot; {{ __('growth.sent_x_times', ['value' => $c->send_count.'x']) }}{{ $c->last_sent_at ? __('growth.last_sent_suffix', ['date' => \Illuminate\Support\Carbon::parse($c->last_sent_at)->format('d M')]) : '' }}
                        @elseif($c->status === 'SENT')
                        &middot; {{ __('growth.sent_to_on', ['count' => $c->recipient_count, 'datetime' => \Illuminate\Support\Carbon::parse($c->sent_at)->format('d M Y, H:i')]) }}
                        @elseif($c->scheduled_at)
                        &middot; {{ __('growth.scheduled_for', ['datetime' => \Illuminate\Support\Carbon::parse($c->scheduled_at)->format('d M Y, H:i')]) }}
                        @endif
                    </div>
                    <div style="display:flex; gap:6px; margin-bottom:4px;">
                        @if($c->banner_image_path)
                        <img src="{{ asset('storage/' . $c->banner_image_path) }}" alt="" style="width:36px; height:36px; object-fit:cover; border-radius:4px; flex-shrink:0;">
                        @endif
                        <div style="font-size:9.5px; color:#374151; background:#f9fafb; border-radius:5px; padding:5px 7px; flex:1; max-height:36px; overflow:hidden;">{{ Str::limit($c->message_body, 140) }}</div>
                    </div>
                    @if($c->video_id ?? null)
                    <div style="margin-bottom:4px;"><a href="{{ route('video-library.stream', $c->video_id) }}" target="_blank" style="font-size:9px; color:#1565C0; font-weight:600; text-decoration:none;">{{ __('growth.view_content_library_link') }}</a></div>
                    @elseif($c->video_url || $c->video_file_path)
                    <div style="margin-bottom:4px;"><a href="{{ $c->video_url ?: asset('storage/' . $c->video_file_path) }}" target="_blank" style="font-size:9px; color:#1565C0; font-weight:600; text-decoration:none;">{{ __('growth.watch_video') }}</a></div>
                    @endif
                    @if($c->status !== 'SENT')
                    <div style="display:flex; gap:10px;">
                        <form method="POST" action="{{ route('admin.growth.broadcasts.send', $c->campaign_id) }}">
                            @csrf
                            <button type="submit" style="background:none; border:none; color:#1565C0; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('growth.send_now_button') }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.growth.broadcasts.destroy', $c->campaign_id) }}" onsubmit="return confirm({{ Js::from(__('growth.delete_campaign_confirm')) }});">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none; border:none; color:#dc2626; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('growth.delete_button') }}</button>
                        </form>
                    </div>
                    @endif
                </div>
                @empty
                <div style="text-align:center; color:#9ca3af; padding:20px 0; font-size:10.5px;">{{ __('growth.no_campaigns_yet') }}</div>
                @endforelse
            </div>
            <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
                @if($campaigns->onFirstPage())
                    <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.prev') }}</span>
                @else
                    <a href="{{ $campaigns->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.prev') }}</a>
                @endif
                <span style="font-size:9px; color:#6b7280;">{{ __('growth.page_of', ['current' => $campaigns->currentPage(), 'last' => $campaigns->lastPage()]) }}</span>
                @if($campaigns->hasMorePages())
                    <a href="{{ $campaigns->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.next') }}</a>
                @else
                    <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.next') }}</span>
                @endif
            </div>
        </div>

        <div style="flex:0 0 300px; display:flex; flex-direction:column; min-height:0;">
            <form method="POST" action="{{ route('admin.growth.broadcasts.store') }}" enctype="multipart/form-data" style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px 14px; display:flex; flex-direction:column; min-height:0; flex:1;">
                @csrf
                <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:6px; flex-shrink:0;">{{ __('growth.new_campaign_heading') }}</div>
                <div style="flex:1; min-height:0; overflow-y:auto; display:flex; flex-direction:column; gap:6px; padding-right:2px;">
                <div>
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.title_label') }}</div>
                    <input type="text" name="title" id="campaignTitle" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.channels_tick_label') }}</div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:2px 6px; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px;">
                        @foreach($channels as $ch)
                        <label style="display:flex; align-items:center; gap:4px; font-size:9.5px; color:#374151; cursor:pointer;">
                            <input type="checkbox" name="channel_codes[]" value="{{ $ch->channel_code }}">
                            {{ $ch->channel_name }}
                        </label>
                        @endforeach
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                    <div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.audience_label') }}</div>
                        <select name="audience_type" id="audType" onchange="toggleRoleField()" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                            <option value="CUSTOMERS">{{ __('growth.customers_option') }}</option>
                            <option value="AGENTS">{{ __('growth.agents_option') }}</option>
                        </select>
                    </div>
                    <div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.group_optional_label') }}</div>
                        <select name="audience_group_label_id" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                            <option value="">{{ __('growth.all_groups_option') }}</option>
                            @foreach($groupLabels as $g)
                            <option value="{{ $g->group_label_id }}">{{ $g->group_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div id="roleField" style="display:none;">
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.role_optional_label') }}</div>
                    <select name="audience_role" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                        <option value="">{{ __('growth.all_roles_option') }}</option>
                        <option value="INTRODUCER">{{ __('growth.role_introducer_option') }}</option>
                        <option value="TEAM_LEADER">{{ __('growth.role_team_leader_option') }}</option>
                        <option value="GROUP_LEADER">{{ __('growth.role_group_leader_option') }}</option>
                    </select>
                </div>
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                        <div style="font-size:8.5px; color:#6b7280;">{{ __('growth.message_label') }}</div>
                        <select onchange="fillCampaignLang(this.value)" style="border:1px solid #d1d5db; border-radius:5px; padding:1px 4px; font-size:8.5px; background:#fff;">
                            <option value="">{{ __('growth.quick_fill_language_placeholder') }}</option>
                            <option value="en">{{ __('growth.lang_english') }}</option>
                            <option value="ms">{{ __('growth.lang_bahasa_malaysia') }}</option>
                            <option value="zh">{{ __('growth.lang_chinese') }}</option>
                        </select>
                    </div>
                    <textarea name="message_body" id="campaignMsgBox" rows="4" required maxlength="2000" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box; resize:none;"></textarea>
                    @include('partials.carolyn-write-helper', [
                        'uid' => 'campaign',
                        'bodyFieldId' => 'campaignMsgBox',
                        'titleFieldId' => 'campaignTitle',
                        'contentType' => 'broadcast_campaign',
                        'assistUrl' => route('ai-write-assist'),
                    ])
                </div>
                <div style="border-top:1px dashed #d1d5db; padding-top:6px;">
                    <div style="font-size:9px; font-weight:700; color:#1565C0; margin-bottom:4px;">{{ __('growth.banner_video_optional_heading') }}</div>
                    <div style="margin-bottom:6px;">
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.banner_image_label2') }}</div>
                        <input type="file" name="banner_image" accept="image/*" style="width:100%; font-size:9.5px;">
                    </div>
                    <div style="display:flex; gap:8px; font-size:9px; color:#374151; margin-bottom:4px; flex-wrap:wrap;">
                        <label><input type="radio" name="video_source" value="LIBRARY" {{ $libraryVideos->isEmpty() ? 'disabled' : 'checked' }} onchange="toggleCampaignVideoSource()"> {{ __('growth.from_content_library_option') }}</label>
                        <label><input type="radio" name="video_source" value="LINK" {{ $libraryVideos->isEmpty() ? 'checked' : '' }} onchange="toggleCampaignVideoSource()"> {{ __('growth.paste_link_option') }}</label>
                        <label><input type="radio" name="video_source" value="UPLOAD" onchange="toggleCampaignVideoSource()"> {{ __('growth.upload_file_option') }}</label>
                    </div>
                    {{-- NEW 10 Aug 2026 — per Chris: reuse an
                         already-uploaded Marketing/Promotion video
                         (with its own provenance/audit trail already
                         tracked in Video Library) instead of every
                         campaign re-uploading its own copy. --}}
                    <div id="campaignVideoLibrary" style="display:{{ $libraryVideos->isEmpty() ? 'none' : 'block' }};">
                        @if($libraryVideos->isEmpty())
                        <div style="font-size:8.5px; color:#9ca3af;">{{ __('growth.no_active_library_videos') }}</div>
                        @else
                        <select name="video_id" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                            <option value="">{{ __('growth.select_a_video_placeholder') }}</option>
                            @foreach($libraryVideos as $lv)
                            <option value="{{ $lv->video_id }}">{{ $lv->video_name }}</option>
                            @endforeach
                        </select>
                        <div style="font-size:8px; color:#9ca3af; margin-top:2px;">{{ __('growth.manage_in_content_library_note') }}</div>
                        @endif
                    </div>
                    <div id="campaignVideoLink" style="display:none;">
                        <input type="url" name="video_url" placeholder="{{ __('growth.video_url_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div id="campaignVideoUpload" style="display:none;">
                        <input type="file" name="video_file" accept="video/mp4,video/quicktime,video/webm" style="width:100%; font-size:9.5px;">
                        <div style="font-size:8px; color:#9ca3af; margin-top:2px;">{{ __('growth.video_max_size_note') }}</div>
                    </div>
                </div>
                <div>
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.schedule_label') }}</div>
                    <select name="recurrence_type" id="recurType" onchange="toggleRecurFields()" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box; margin-bottom:4px;">
                        <option value="NONE">{{ __('growth.one_time_option') }}</option>
                        <option value="DAILY">{{ __('growth.every_day_option') }}</option>
                        <option value="WEEKLY">{{ __('growth.every_week_option') }}</option>
                    </select>
                    <div id="recurNone">
                        <input type="datetime-local" name="scheduled_at" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                        <div style="font-size:8px; color:#9ca3af; margin-top:2px;">{{ __('growth.leave_blank_draft_note') }}</div>
                    </div>
                    <div id="recurDailyWeekly" style="display:none;">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                            <div id="recurDayField" style="display:none;">
                                <select name="recurrence_day_of_week" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                                    <option value="0">{{ __('growth.day_sunday') }}</option>
                                    <option value="1">{{ __('growth.day_monday') }}</option>
                                    <option value="2">{{ __('growth.day_tuesday') }}</option>
                                    <option value="3">{{ __('growth.day_wednesday') }}</option>
                                    <option value="4">{{ __('growth.day_thursday') }}</option>
                                    <option value="5">{{ __('growth.day_friday') }}</option>
                                    <option value="6">{{ __('growth.day_saturday') }}</option>
                                </select>
                            </div>
                            <div>
                                <input type="time" name="recurrence_time" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                            </div>
                        </div>
                        <div style="font-size:8px; color:#9ca3af; margin-top:2px;">{{ __('growth.repeats_forever_note') }}</div>
                    </div>
                </div>
                </div>
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 14px; font-size:10.5px; font-weight:600; cursor:pointer; margin-top:6px; flex-shrink:0;">{{ __('growth.save_campaign_button') }}</button>
            </form>
        </div>
    </div>

</div>

<script>
function toggleRoleField() {
    var v = document.getElementById('audType').value;
    document.getElementById('roleField').style.display = (v === 'AGENTS') ? 'block' : 'none';
}

function toggleRecurFields() {
    var v = document.getElementById('recurType').value;
    document.getElementById('recurNone').style.display = (v === 'NONE') ? 'block' : 'none';
    document.getElementById('recurDailyWeekly').style.display = (v === 'NONE') ? 'none' : 'block';
    document.getElementById('recurDayField').style.display = (v === 'WEEKLY') ? 'block' : 'none';
}

function toggleCampaignVideoSource() {
    var checked = document.querySelector('input[name="video_source"]:checked');
    var v = checked ? checked.value : 'LINK';
    document.getElementById('campaignVideoLibrary').style.display = (v === 'LIBRARY') ? 'block' : 'none';
    document.getElementById('campaignVideoLink').style.display = (v === 'LINK') ? 'block' : 'none';
    document.getElementById('campaignVideoUpload').style.display = (v === 'UPLOAD') ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleCampaignVideoSource);

// Same 3 professional templates as My Referral Link — quick-fill only,
// still fully editable after. campaignAgentName/campaignReferralUrl are
// set once below from the page's own Blade data.
var campaignTemplates = {
    en: "Hi! GeneralLink is inviting you to grow a real side income as a licensed insurance affiliate - flexible hours, genuine commissions, and full training provided. Join us today: {link}\n\nQuestions? Just reply and our team will help you get started!",
    ms: "Hai! GeneralLink menjemput anda membina pendapatan sampingan yang sah sebagai ejen insurans berlesen - waktu fleksibel, komisen sebenar, dan latihan penuh disediakan. Sertai kami hari ini: {link}\n\nAda soalan? Balas sahaja mesej ini dan pasukan kami akan bantu anda bermula!",
    zh: "你好!GeneralLink 诚邀您以持牌保险代理的身份建立真实的副业收入 - 时间灵活、佣金真实,并提供完整培训。立即加入我们:{link}\n\n有任何问题?回复这则消息,我们的团队将协助您开始!"
};
function fillCampaignLang(lang) {
    if (!lang) return;
    var text = campaignTemplates[lang].replace('{link}', @json(url('/register')));
    document.getElementById('campaignMsgBox').value = text;
}
</script>
@endsection
