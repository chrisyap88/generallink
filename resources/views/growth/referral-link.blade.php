@extends('layouts.dashboard')

@section('page-title', __('growth.my_referral_link_title'))

@section('content')

{{-- NEW 25 Jul 2026 (task #210) — Growth & Outreach Center. Share this
     link or QR anywhere (WhatsApp, social media, printed flyers) — the
     moment someone registers through it, they're automatically
     attributed to you as sponsor. Click/signup counts are read live
     from referral_clicks, so this always reflects real traffic. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; align-items:flex-start; justify-content:space-between; gap:10px;">
        <div>
            <div style="font-size:9.5px; color:#9ca3af; margin-top:2px;">{{ __('growth.referral_link_subtitle') }}</div>
        </div>
        @include('partials.feature-video-widget', ['featureKey' => 'REFERRAL_LINK'])
    </div>

    <div style="display:flex; gap:12px; flex:1; min-height:0;">

        <div style="flex:0 0 220px; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px;">
            <img src="{{ $qrDataUri }}" alt="{{ __('growth.referral_qr_code_alt') }}" style="width:160px; height:160px;">
            <a href="{{ route('referral-link.download-qr') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10px; font-weight:600;">{{ __('growth.download_qr_button') }}</a>
        </div>

        <div style="flex:1; display:flex; flex-direction:column; gap:10px; min-height:0;">

            <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:8px 14px; flex-shrink:0;">
                <div style="font-size:9px; color:#6b7280; text-transform:uppercase; font-weight:600; margin-bottom:4px;">{{ __('growth.your_referral_link_label') }}</div>
                <div style="display:flex; gap:6px;">
                    <input type="text" id="refLinkBox" value="{{ $referralUrl }}" readonly style="flex:1; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10px; color:#374151; background:#f9fafb;">
                    <button type="button" onclick="copyRefLink()" id="copyBtn" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:5px 14px; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('growth.copy_button') }}</button>
                </div>
            </div>

            <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:8px 14px; flex-shrink:0;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                    <div style="font-size:9px; color:#92400e; text-transform:uppercase; font-weight:600;">{{ __('growth.send_friendly_invite_label') }}</div>
                    <select id="inviteLangSelect" onchange="loadInviteTemplate()" style="border:1px solid #fde68a; border-radius:5px; padding:2px 6px; font-size:9.5px; color:#92400e; background:#fff;">
                        <option value="en">{{ __('growth.lang_english') }}</option>
                        <option value="ms">{{ __('growth.lang_bahasa_malaysia') }}</option>
                        <option value="zh">{{ __('growth.lang_chinese') }}</option>
                    </select>
                </div>
                <textarea id="inviteMsgBox" rows="3" style="width:100%; box-sizing:border-box; border:1px solid #fde68a; border-radius:5px; padding:6px 8px; font-size:10px; color:#374151; background:#fff; resize:none; font-family:inherit;"></textarea>
                <div style="display:flex; gap:6px; margin-top:6px; flex-wrap:wrap;">
                    <button type="button" onclick="shareWhatsApp()" style="background:#25D366; color:#fff; border:none; border-radius:5px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">WhatsApp</button>
                    <button type="button" onclick="shareTelegram()" style="background:#229ED9; color:#fff; border:none; border-radius:5px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">Telegram</button>
                    <button type="button" onclick="shareLine()" style="background:#06C755; color:#fff; border:none; border-radius:5px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">LINE</button>
                    <button type="button" onclick="shareEmail()" style="background:#6b7280; color:#fff; border:none; border-radius:5px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('growth.email') }}</button>
                    <button type="button" onclick="shareFacebook()" id="fbBtn" style="background:#1877F2; color:#fff; border:none; border-radius:5px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">Facebook</button>
                    <button type="button" onclick="shareLinkedIn()" id="liBtn" style="background:#0A66C2; color:#fff; border:none; border-radius:5px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">LinkedIn</button>
                    <button type="button" onclick="shareTwitter()" style="background:#000000; color:#fff; border:none; border-radius:5px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">X (Twitter)</button>
                    <button type="button" onclick="copyInviteMsg()" id="copyMsgBtn" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('growth.copy_message_button') }}</button>
                </div>
                <div style="font-size:8.5px; color:#92400e; margin-top:4px;">{{ __('growth.share_note') }}</div>
            </div>

            <div style="display:flex; gap:8px; flex-shrink:0;">
                <div style="flex:1; background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:8px 12px;">
                    <div style="font-size:8.5px; color:#0369a1; text-transform:uppercase; font-weight:600;">{{ __('growth.link_clicks_label') }}</div>
                    <div style="font-size:16px; font-weight:700; color:#0369a1;">{{ $clickCount }}</div>
                </div>
                <div style="flex:1; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:8px 12px;">
                    <div style="font-size:8.5px; color:#166534; text-transform:uppercase; font-weight:600;">{{ __('growth.registrations_label') }}</div>
                    <div style="font-size:16px; font-weight:700; color:#166534;">{{ $signupCount }}</div>
                </div>
            </div>

            <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
                <div style="font-size:9px; color:#6b7280; text-transform:uppercase; font-weight:600; margin-bottom:6px; flex-shrink:0;">{{ __('growth.people_joined_through_link_label') }}</div>
                <div style="flex:1; min-height:0; overflow:hidden;">
                    <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                        <thead>
                            <tr style="background:#f0f9ff;">
                                <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.name') }}</th>
                                <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.role') }}</th>
                                <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.status') }}</th>
                                <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.joined_col') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentSignups as $s)
                            <tr style="border-bottom:1px solid #f3f4f6;">
                                <td style="padding:5px 8px; color:#111827;">{{ $s->full_name }} <span style="color:#9ca3af;">({{ $s->agent_code }})</span></td>
                                <td style="padding:5px 8px; color:#6b7280;">{{ \App\Services\RoleLabelService::label($s->role) }}</td>
                                <td style="padding:5px 8px; color:#6b7280;">{{ $s->status }}</td>
                                <td style="padding:5px 8px; text-align:right; color:#6b7280;">{{ \Illuminate\Support\Carbon::parse($s->clicked_at)->format('d M Y') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('growth.nobody_joined_yet') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:6px;">
                    @if($recentSignups->onFirstPage())
                        <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.prev') }}</span>
                    @else
                        <a href="{{ $recentSignups->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.prev') }}</a>
                    @endif
                    <span style="font-size:9px; color:#6b7280;">{{ __('growth.page_of', ['current' => $recentSignups->currentPage(), 'last' => $recentSignups->lastPage()]) }}</span>
                    @if($recentSignups->hasMorePages())
                        <a href="{{ $recentSignups->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.next') }}</a>
                    @else
                        <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.next') }}</span>
                    @endif
                </div>
            </div>

        </div>
    </div>

</div>

<script>
// Multi-language invite templates (task follow-up 25 Jul 2026) — Chris
// has Malay/Chinese/Indian customers and wants a professional message
// per language rather than one English-only text. {name}/{link} are
// swapped for the real agent name and referral URL below. Editable
// after loading, same as before.
var inviteAgentName = @json($agent->full_name);
var inviteReferralUrl = @json($referralUrl);
var i18nCopied = @json(__('growth.copied_notice'));
var i18nCopiedPasteIt = @json(__('growth.copied_paste_it_notice'));
var i18nEmailSubject = @json(__('growth.email_invite_subject'));
var inviteTemplates = {
    en: "Hi! I'm {name} with GeneralLink. I help people build a real side income as a licensed insurance affiliate - flexible hours, genuine commissions, and full training provided. I thought you'd be a great fit to join our team. Have a look here: {link}\n\nHappy to walk you through it if you have any questions!",
    ms: "Hai! Saya {name} dari GeneralLink. Saya membantu orang ramai membina pendapatan sampingan yang sah sebagai ejen insurans berlesen - waktu fleksibel, komisen sebenar, dan latihan penuh disediakan. Saya rasa anda amat sesuai untuk menyertai pasukan kami. Sila lihat di sini: {link}\n\nSaya sedia membantu jika ada sebarang pertanyaan!",
    zh: "你好!我是GeneralLink的{name}。我帮助大家以持牌保险代理的身份建立真实的副业收入 - 时间灵活、佣金真实,并提供完整培训。我觉得你很适合加入我们的团队。请点击这里了解详情:{link}\n\n如有任何问题,我很乐意为你解答!"
};
function loadInviteTemplate() {
    var lang = document.getElementById('inviteLangSelect').value;
    var text = inviteTemplates[lang].replace('{name}', inviteAgentName).replace('{link}', inviteReferralUrl);
    document.getElementById('inviteMsgBox').value = text;
}
loadInviteTemplate();

function copyRefLink() {
    var box = document.getElementById('refLinkBox');
    box.select();
    box.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(box.value).then(function() {
        var btn = document.getElementById('copyBtn');
        var original = btn.textContent;
        btn.textContent = i18nCopied;
        setTimeout(function() { btn.textContent = original; }, 1500);
    });
}

// Share buttons all read the (editable) invite message box, so whatever
// the agent types/edits gets sent — not just the bare link. These use
// standard public share-link schemes (wa.me, t.me, mailto) which work
// today with no provider subscription needed; once a real Channel
// Connection (task #209) is subscribed, Broadcast Campaigns can send in
// bulk through the actual API instead.
function shareWhatsApp() {
    var msg = document.getElementById('inviteMsgBox').value;
    window.open('https://wa.me/?text=' + encodeURIComponent(msg), '_blank');
}
function shareTelegram() {
    var msg = document.getElementById('inviteMsgBox').value;
    var link = document.getElementById('refLinkBox').value;
    window.open('https://t.me/share/url?url=' + encodeURIComponent(link) + '&text=' + encodeURIComponent(msg), '_blank');
}
function shareEmail() {
    var msg = document.getElementById('inviteMsgBox').value;
    window.location.href = 'mailto:?subject=' + encodeURIComponent(i18nEmailSubject) + '&body=' + encodeURIComponent(msg);
}
function shareFacebook() {
    var msg = document.getElementById('inviteMsgBox').value;
    var link = document.getElementById('refLinkBox').value;
    navigator.clipboard.writeText(msg).then(function() {
        var btn = document.getElementById('fbBtn');
        var original = btn.textContent;
        btn.textContent = i18nCopiedPasteIt;
        setTimeout(function() { btn.textContent = original; }, 2500);
    });
    window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(link), '_blank');
}
// LINE's official share scheme only opens the LINE app's own chat
// picker (like WhatsApp/Telegram) — works great on a phone with LINE
// installed; on desktop with no LINE app it will just fail to open.
function shareLine() {
    var msg = document.getElementById('inviteMsgBox').value;
    window.open('https://line.me/R/share?text=' + encodeURIComponent(msg), '_blank');
}
// LinkedIn has the same anti-spam rule as Facebook — link only, no
// pre-filled caption — so copy the message first, same pattern as FB.
function shareLinkedIn() {
    var msg = document.getElementById('inviteMsgBox').value;
    var link = document.getElementById('refLinkBox').value;
    navigator.clipboard.writeText(msg).then(function() {
        var btn = document.getElementById('liBtn');
        var original = btn.textContent;
        btn.textContent = i18nCopiedPasteIt;
        setTimeout(function() { btn.textContent = original; }, 2500);
    });
    window.open('https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(link), '_blank');
}
// Twitter/X intent posts publicly (not a private message) — fine for
// public recruiting reach, not for a 1-to-1 personal invite.
function shareTwitter() {
    var msg = document.getElementById('inviteMsgBox').value;
    window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent(msg), '_blank');
}
function copyInviteMsg() {
    var box = document.getElementById('inviteMsgBox');
    box.select();
    box.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(box.value).then(function() {
        var btn = document.getElementById('copyMsgBtn');
        var original = btn.textContent;
        btn.textContent = i18nCopied;
        setTimeout(function() { btn.textContent = original; }, 1500);
    });
}
</script>
@endsection
