@extends('layouts.dashboard')

@section('page-title', __('growth.distribute_survey_title'))

@section('content')

{{-- NEW 25 Jul 2026 — Survey Management Module, Phase 2 (task #232).
     Spec section 7 "Survey Distribution" — Link, QR, Email, WhatsApp,
     SMS, Telegram, Embed. Link/QR/Embed work with zero external
     dependency (same as My Referral Link). WhatsApp/Telegram/SMS reuse
     the wa.me/t.me/sms: share-link pattern — works today with no
     provider subscription; a targeted, tracked send to specific
     customers lives in the agent-facing "Send Survey" screen instead
     of here (this is the generic, anyone-can-open link). --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px;">
        <a href="{{ route('admin.growth.surveys.builder', $survey->survey_id) }}" style="color:#1565C0; text-decoration:none; font-size:9.5px; font-weight:600; display:inline-block; margin-bottom:4px;">&lsaquo; {{ $survey->name }}</a>
        <h4 style="font-weight:700; margin:0; font-size:13px; color:#1565C0;">{{ __('growth.distribute_heading', ['title' => $survey->title]) }}</h4>
        <div style="font-size:9px; color:#9ca3af; margin-top:2px;">
            {{ __('growth.status_colon') }} <strong>{{ __('growth.survey_status_'.strtolower($survey->status)) }}</strong>
            @if($survey->status !== 'ACTIVE')
            &middot; <span style="color:#dc2626;">{{ __('growth.not_accepting_responses_note') }}</span>
            @endif
        </div>
    </div>

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0; margin-bottom:6px;">✅ {{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fee2e2; border:1px solid #fca5a5; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; font-weight:500; flex-shrink:0; margin-bottom:6px;">⚠️ {{ session('error') }}</div>
    @endif

    {{-- NEW 25 Jul 2026 — Google Forms integration (task #227 follow-up,
         #241). Chris chose to run the real questionnaire on Google Forms
         instead of GeneralLink's own public form — GeneralLink's link/QR/
         embed below still work, but once a survey is pushed to Google,
         the Google link is the one that should actually be shared. --}}
    <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:8px 14px; flex-shrink:0; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        @if(!$googleConnected)
            <div style="font-size:10px; color:#0369a1;">{{ __('growth.google_connect_prompt') }}</div>
            <a href="{{ route('google.connect') }}" style="background:#0369a1; color:#fff; text-decoration:none; border-radius:6px; padding:6px 14px; font-size:9.5px; font-weight:600;">{{ __('growth.connect_google_account_button') }}</a>
        @elseif(!$survey->google_form_id)
            <div style="font-size:10px; color:#0369a1;">{{ __('growth.google_connected_push_prompt') }}</div>
            <form method="POST" action="{{ route('admin.growth.surveys.push-to-google', $survey->survey_id) }}">@csrf
                <button type="submit" style="background:#0369a1; color:#fff; border:none; border-radius:6px; padding:6px 14px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('growth.push_to_google_forms_button') }}</button>
            </form>
        @else
            <div style="font-size:10px; color:#065f46;">
                {{ __('growth.live_on_google_forms') }} @if($googleResponseCount !== null)&middot; {!! __('growth.response_count_so_far', ['count' => '<strong>'.$googleResponseCount.'</strong>']) !!} @endif
                @if($googleError)<br><span style="color:#991b1b;">{{ $googleError }}</span>@endif
            </div>
            <div style="display:flex; gap:8px;">
                <a href="{{ $survey->google_form_response_url }}" target="_blank" style="background:#166534; color:#fff; text-decoration:none; border-radius:6px; padding:6px 14px; font-size:9.5px; font-weight:600;">{{ __('growth.open_google_form_link_button') }}</a>
                <a href="{{ $survey->google_form_edit_url }}" target="_blank" style="background:#0369a1; color:#fff; text-decoration:none; border-radius:6px; padding:6px 14px; font-size:9.5px; font-weight:600;">{{ __('growth.edit_in_google_forms_button') }}</a>
            </div>
        @endif
    </div>

    <div style="display:flex; gap:12px; flex:1; min-height:0;">

        <div style="flex:0 0 200px; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px;">
            <img src="{{ $qrDataUri }}" alt="{{ __('growth.survey_qr_code_alt') }}" style="width:150px; height:150px;">
            <a href="{{ route('admin.growth.surveys.distribute.qr', $survey->survey_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:6px 14px; font-size:9.5px; font-weight:600;">{{ __('growth.download_qr_button') }}</a>
            @if($survey->google_form_id)
            <div style="font-size:8px; color:#9ca3af; text-align:center;">{{ __('growth.qr_points_to_google_note') }}</div>
            @endif
        </div>

        <div style="flex:1; display:flex; flex-direction:column; gap:8px; min-height:0;">

            <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:8px 14px; flex-shrink:0;">
                <div style="font-size:9px; color:#6b7280; text-transform:uppercase; font-weight:600; margin-bottom:4px;">{{ __('growth.public_survey_link_label') }}</div>
                <div style="display:flex; gap:6px;">
                    <input type="text" id="surveyLinkBox" value="{{ $publicUrl }}" readonly style="flex:1; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10px; color:#374151; background:#f9fafb;">
                    <button type="button" onclick="copySurveyLink()" id="copyLinkBtn" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:5px 14px; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('growth.copy_button') }}</button>
                </div>
                <div style="display:flex; gap:6px; margin-top:6px; flex-wrap:wrap;">
                    <button type="button" onclick="shareWhatsApp()" style="background:#25D366; color:#fff; border:none; border-radius:5px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">WhatsApp</button>
                    <button type="button" onclick="shareTelegram()" style="background:#229ED9; color:#fff; border:none; border-radius:5px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">Telegram</button>
                    <button type="button" onclick="shareSms()" style="background:#6366f1; color:#fff; border:none; border-radius:5px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">SMS</button>
                    <button type="button" onclick="shareEmail()" style="background:#6b7280; color:#fff; border:none; border-radius:5px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('growth.email') }}</button>
                </div>
            </div>

            <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:8px 14px; flex-shrink:0;">
                <div style="font-size:9px; color:#6b7280; text-transform:uppercase; font-weight:600; margin-bottom:4px;">{{ __('growth.embed_code_label') }}</div>
                <div style="display:flex; gap:6px;">
                    <input type="text" id="embedBox" value="{{ $embedCode }}" readonly style="flex:1; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:9.5px; color:#374151; background:#f9fafb; font-family:monospace;">
                    <button type="button" onclick="copyEmbed()" id="copyEmbedBtn" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:5px 14px; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('growth.copy_button') }}</button>
                </div>
            </div>

            <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 14px; flex:1; min-height:0; display:flex; flex-direction:column;">
                <div style="font-size:9px; color:#6b7280; text-transform:uppercase; font-weight:600; margin-bottom:6px; flex-shrink:0;">{{ __('growth.distribution_by_channel_heading') }}</div>
                <div style="flex:1; min-height:0; overflow-y:auto;">
                    <table style="width:100%; border-collapse:collapse; font-size:10px;">
                        <thead>
                            <tr style="background:#f0f9ff;">
                                <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.channel_col') }}</th>
                                <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.sent_col') }}</th>
                                <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.completed_col') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invitationStats as $code => $s)
                            <tr style="border-bottom:1px solid #f3f4f6;">
                                <td style="padding:5px 8px; color:#111827;">{{ $code }}</td>
                                <td style="padding:5px 8px; text-align:right; color:#6b7280;">{{ $s->total }}</td>
                                <td style="padding:5px 8px; text-align:right; color:#166534; font-weight:600;">{{ $s->completed }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" style="padding:12px; text-align:center; color:#9ca3af;">{{ __('growth.no_tracked_sends_note') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <a href="{{ route('admin.growth.surveys.responses', $survey->survey_id) }}" style="flex-shrink:0; margin-top:6px; font-size:9.5px; color:#1565C0; font-weight:600; text-decoration:none;">{{ __('growth.view_all_responses_link') }}</a>
            </div>
        </div>
    </div>
</div>

<script>
var i18nCopied = @json(__('growth.copied_notice'));
function copySurveyLink() {
    var box = document.getElementById('surveyLinkBox');
    box.select(); box.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(box.value).then(function() {
        var btn = document.getElementById('copyLinkBtn'); var o = btn.textContent;
        btn.textContent = i18nCopied; setTimeout(function() { btn.textContent = o; }, 1500);
    });
}
function copyEmbed() {
    var box = document.getElementById('embedBox');
    box.select(); box.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(box.value).then(function() {
        var btn = document.getElementById('copyEmbedBtn'); var o = btn.textContent;
        btn.textContent = i18nCopied; setTimeout(function() { btn.textContent = o; }, 1500);
    });
}
var surveyShareMsg = @json(__('growth.share_feedback_message', ['title' => $survey->title]));
function shareWhatsApp() {
    window.open('https://wa.me/?text=' + encodeURIComponent(surveyShareMsg + ' ' + document.getElementById('surveyLinkBox').value), '_blank');
}
function shareTelegram() {
    window.open('https://t.me/share/url?url=' + encodeURIComponent(document.getElementById('surveyLinkBox').value) + '&text=' + encodeURIComponent(surveyShareMsg), '_blank');
}
function shareSms() {
    window.location.href = 'sms:?&body=' + encodeURIComponent(surveyShareMsg + ' ' + document.getElementById('surveyLinkBox').value);
}
function shareEmail() {
    window.location.href = 'mailto:?subject=' + encodeURIComponent('{{ $survey->title }}') + '&body=' + encodeURIComponent(surveyShareMsg + ' ' + document.getElementById('surveyLinkBox').value);
}
</script>
@endsection
