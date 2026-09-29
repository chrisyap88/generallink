@extends('layouts.dashboard')

@section('page-title', __('profile.edit_profile_title'))

@section('content')
{{-- REBUILT 5 Aug 2026 — per Chris's mandatory UI rule: no scrolling in
     any direction, folder-tab navigation instead of one long scrolling
     form, and a visible warning the moment any field changes so nothing
     gets lost by forgetting to click Save. All tabs still submit as ONE
     form/one POST — switching tabs never loses anything already typed,
     it's all still in the page, just hidden. --}}
<div style="height:calc(100vh - 66px); display:flex; flex-direction:column; padding:8px 12px 10px; box-sizing:border-box; overflow:hidden;">

    <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px; flex-shrink:0;">
        <a href="{{ route('tl.dashboard') }}" class="leave-link" style="color:#6b7280; text-decoration:none; font-size:12px;">{{ __('drilldown.back_to_dashboard_link') }}</a>
        <div style="color:#d1d5db;">|</div>
        <a href="{{ route('tl.profile.show') }}" class="leave-link" style="color:#6b7280; text-decoration:none; font-size:12px;">{{ __('profile.back_to_profile_link') }}</a>
        <div style="color:#d1d5db;">|</div>
    </div>

    <form method="POST" action="{{ route('tl.profile.update') }}" enctype="multipart/form-data" id="profileEditForm" style="flex:1; min-height:0; display:flex; flex-direction:column;">
        @csrf
        @method('PUT')

        <div style="display:flex; gap:2px; flex-shrink:0; padding:0 2px; flex-wrap:wrap;">
            <button type="button" class="ptab-btn active" data-tab="personal">{{ __('profile.tab_personal') }}</button>
            <button type="button" class="ptab-btn" data-tab="address">{{ __('profile.tab_address') }}</button>
            <button type="button" class="ptab-btn" data-tab="language">{{ __('profile.tab_language') }}</button>
            <button type="button" class="ptab-btn" data-tab="voice">{{ __('profile.tab_voice') }}</button>
            <button type="button" class="ptab-btn" data-tab="textchat">{{ __('profile.tab_textchat') }}</button>
            <button type="button" class="ptab-btn" data-tab="hub">{{ __('profile.tab_hub') }}</button>
        </div>

        <div style="flex:1; min-height:0; background:#fff; border-radius:0 10px 10px 10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:18px; overflow:hidden; display:flex; flex-direction:column;">

            {{-- Personal --}}
            <div class="ptab-panel active" data-tab="personal">
                <div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;">
                    <div id="photoPreviewWrap">
                        @if($profile->photo_path ?? null)
                            <img id="photoPreview" src="{{ asset('storage/' . $profile->photo_path) }}" style="width:64px; height:80px; border-radius:6px; object-fit:cover; border:2px solid #B2EBF2;">
                        @else
                            <div id="photoPreview" style="width:64px; height:80px; border-radius:6px; background:#E0F7FA; border:2px solid #B2EBF2; display:flex; align-items:center; justify-content:center;"><i class="ti ti-user" style="font-size:28px; color:#1565C0;"></i></div>
                        @endif
                    </div>
                    <div style="flex:1; min-width:0;">
                        <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('profile.profile_photo_label') }}</label>
                        <input type="file" name="photo" accept="image/*" onchange="previewPhoto(this)" style="font-size:11px;">
                        <div style="font-size:10px; color:#9ca3af; margin-top:3px;">{{ __('profile.jpg_png_note') }}</div>
                        @error('photo') <div class="field-error" style="color:#dc2626; font-size:11px; margin-top:3px;">{{ $message }}</div> @enderror
                    </div>
                    {{-- NEW 6 Aug 2026 — per Chris: this lived only on the read-only
                         My Profile page, which is no longer the default landing
                         screen, so it moved here too rather than being lost. --}}
                    <div style="text-align:center; flex-shrink:0; border-left:1px solid #f3f4f6; padding-left:14px;">
                        <div style="font-size:9.5px; font-weight:600; color:#374151; margin-bottom:3px; white-space:nowrap;">{{ __('profile.my_referral_qr_label') }}</div>
                        <div id="miniQr" style="display:flex; justify-content:center; margin-bottom:3px;"></div>
                        <a href="#" onclick="downloadMiniQr(event)" style="color:#1B9AE4; text-decoration:none; font-size:9.5px; font-weight:600;">{{ __('profile.download_label') }}</a>
                    </div>
                </div>

                <div style="margin-bottom:12px;">
                    <label style="display:block; font-size:11px; font-weight:600; color:#9ca3af; margin-bottom:4px;">{{ __('profile.field_full_name') }} <span style="color:#dc2626; font-size:10px;">{{ __('profile.locked_note') }}</span></label>
                    <input type="text" value="{{ $agent->full_name }}" disabled style="width:100%; border:1px solid #e5e7eb; border-radius:6px; padding:7px 10px; font-size:12px; background:#f9fafb; color:#9ca3af; box-sizing:border-box; cursor:not-allowed;">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('profile.field_phone_required') }}</label>
                        <input type="text" name="phone" value="{{ old('phone', $agent->phone) }}" required style="width:100%; border:1px solid {{ $errors->has('phone') ? '#f87171' : '#d1d5db' }}; border-radius:6px; padding:7px 10px; font-size:12px; outline:none; box-sizing:border-box;">
                        @error('phone') <div class="field-error" style="color:#dc2626; font-size:11px; margin-top:3px;">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('profile.field_email_required') }}</label>
                        <input type="email" name="email" value="{{ old('email', $agent->email) }}" required style="width:100%; border:1px solid {{ $errors->has('email') ? '#f87171' : '#d1d5db' }}; border-radius:6px; padding:7px 10px; font-size:12px; outline:none; box-sizing:border-box;">
                        @error('email') <div class="field-error" style="color:#dc2626; font-size:11px; margin-top:3px;">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div style="font-size:10px; color:#9ca3af; margin-top:14px;">{{ __('profile.phone_email_photo_note') }}</div>
            </div>

            {{-- Address --}}
            <div class="ptab-panel" data-tab="address">
                <div style="margin-bottom:12px;">
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('profile.field_address') }}</label>
                    <textarea name="address" rows="2" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; outline:none; box-sizing:border-box; resize:none;">{{ old('address', $profile->address ?? '') }}</textarea>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('profile.field_city') }}</label>
                        <input type="text" name="city" value="{{ old('city', $profile->city ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('profile.field_postcode') }}</label>
                        <input type="text" name="postcode" value="{{ old('postcode', $profile->postcode ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; outline:none; box-sizing:border-box;">
                    </div>
                </div>

                <div>
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('profile.field_state') }}</label>
                    <select name="state" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; background:#fff; outline:none; box-sizing:border-box;">
                        <option value="">{{ __('profile.select_state_dash_option') }}</option>
                        @foreach($states as $state)
                            <option value="{{ $state }}" {{ old('state', $profile->state ?? '') === $state ? 'selected' : '' }}>{{ $state }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Language --}}
            <div class="ptab-panel" data-tab="language">
                <div style="font-size:10px; color:#9ca3af; margin-bottom:14px;">{{ __('profile.language_change_note_helpdesk') }}</div>
                <div style="max-width:340px;">
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('profile.preferred_language_label') }}</label>
                    <select name="preferred_language" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; background:#fff; outline:none; box-sizing:border-box;">
                        <option value="EN" {{ old('preferred_language', $agent->preferred_language ?? 'EN') === 'EN' ? 'selected' : '' }}>{{ __('profile.lang_english') }}</option>
                        <option value="ZH" {{ old('preferred_language', $agent->preferred_language ?? 'EN') === 'ZH' ? 'selected' : '' }}>{{ __('profile.lang_chinese') }}</option>
                        <option value="MS" {{ old('preferred_language', $agent->preferred_language ?? 'EN') === 'MS' ? 'selected' : '' }}>{{ __('profile.lang_bahasa_malaysia') }}</option>
                    </select>
                </div>
            </div>

            {{-- Voice Assistant --}}
            <div class="ptab-panel" data-tab="voice">
                <div style="font-size:10px; color:#9ca3af; margin-bottom:14px;">{{ __('profile.voice_connect_own_note') }}</div>

                <div style="margin-bottom:12px;">
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('profile.voice_provider_label') }}</label>
                    <select name="voice_provider" id="voiceProviderSelect" onchange="aiVoiceProviderToggle(this.value)" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; background:#fff; outline:none; box-sizing:border-box;">
                        <option value="" {{ old('voice_provider', $agent->voice_provider ?? '') === '' ? 'selected' : '' }}>{{ __('profile.voice_none_option') }}</option>
                        <option value="ELEVENLABS" {{ old('voice_provider', $agent->voice_provider ?? '') === 'ELEVENLABS' ? 'selected' : '' }}>ElevenLabs</option>
                        <option value="OPENAI" {{ old('voice_provider', $agent->voice_provider ?? '') === 'OPENAI' ? 'selected' : '' }}>OpenAI</option>
                        <option value="GOOGLE" {{ old('voice_provider', $agent->voice_provider ?? '') === 'GOOGLE' ? 'selected' : '' }}>Google Cloud</option>
                    </select>
                    @error('voice_provider') <div class="field-error" style="color:#dc2626; font-size:11px; margin-top:3px;">{{ $message }}</div> @enderror
                </div>

                <div id="voiceProviderFields" style="{{ empty($agent->voice_provider ?? null) ? 'display:none;' : '' }}">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div>
                            <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('profile.api_key_label') }}</label>
                            <input type="password" name="voice_provider_api_key" placeholder="{{ !empty($agent->voice_provider_api_key_encrypted ?? null) ? __('profile.already_set_leave_blank_note') : __('profile.paste_api_key_placeholder') }}" autocomplete="new-password" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; outline:none; box-sizing:border-box;">
                            <div style="font-size:10px; color:#9ca3af; margin-top:3px;">{{ __('profile.stored_encrypted_leave_blank_note') }}</div>
                            @error('voice_provider_api_key') <div class="field-error" style="color:#dc2626; font-size:11px; margin-top:3px;">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('profile.voice_id_name_label') }} <span style="color:#9ca3af; font-weight:400;">{{ __('profile.optional_note') }}</span></label>
                            <input type="text" name="voice_id" value="{{ old('voice_id', $agent->voice_id ?? '') }}" placeholder="{{ __('profile.voice_id_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; outline:none; box-sizing:border-box;">
                            <div style="font-size:10px; color:#9ca3af; margin-top:3px;">{{ __('profile.leave_blank_default_voice_note') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Text Chat --}}
            <div class="ptab-panel" data-tab="textchat">
                <div style="font-size:10px; color:#9ca3af; margin-bottom:14px;">{{ __('profile.textchat_connect_own_note') }}</div>

                <div style="margin-bottom:8px;">
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('profile.anthropic_api_key_label') }}</label>
                    <input type="password" name="text_chat_api_key" placeholder="{{ !empty($agent->text_chat_api_key_encrypted ?? null) ? __('profile.already_set_leave_blank_note') : __('profile.paste_anthropic_key_placeholder') }}" autocomplete="new-password" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; outline:none; box-sizing:border-box;">
                    <div style="font-size:10px; color:#9ca3af; margin-top:3px;">{{ __('profile.stored_encrypted_note') }}</div>
                    @error('text_chat_api_key') <div class="field-error" style="color:#dc2626; font-size:11px; margin-top:3px;">{{ $message }}</div> @enderror
                </div>

                @if(!empty($agent->text_chat_api_key_encrypted ?? null))
                <label style="display:flex; align-items:center; gap:6px; font-size:11px; color:#374151; cursor:pointer; margin-bottom:16px;">
                    <input type="checkbox" name="text_chat_api_key_clear" value="1"> {{ __('profile.remove_key_checkbox_label') }}
                </label>
                @endif

                <div style="border-top:1px solid #f3f4f6; margin-top:8px; padding-top:14px; display:flex; align-items:center; justify-content:space-between;">
                    <div>
                        <div style="font-size:12px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('profile.what_carolyn_remembers_heading') }}</div>
                        <div style="font-size:9.5px; color:#9ca3af;">{{ __('profile.carolyn_remembers_note') }}</div>
                    </div>
                    <a href="{{ route('ai.memory.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 14px; font-size:10.5px; font-weight:600; white-space:nowrap;">{{ __('profile.view_button') }}</a>
                </div>
            </div>

            {{-- Integration Hub (Document Reading preference + Hub Security link) --}}
            <div class="ptab-panel" data-tab="hub">
                <div style="font-size:13px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('profile.document_reading_heading') }}</div>
                <div style="font-size:10px; color:#9ca3af; margin-bottom:14px;">{{ __('profile.document_reading_note_full') }}</div>

                @php
                    $deStatus = $documentExtractionStatus ?? ['OPENAI' => null, 'GEMINI' => null];
                    $deCurrent = old('document_extraction_provider', $agent->document_extraction_provider ?? 'COMPANY_CREDIT');
                @endphp

                <div style="margin-bottom:6px;">
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('profile.pay_with_label') }}</label>
                    <select name="document_extraction_provider" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; background:#fff; outline:none; box-sizing:border-box;">
                        <option value="COMPANY_CREDIT" {{ $deCurrent === 'COMPANY_CREDIT' ? 'selected' : '' }}>{{ __('profile.company_document_credit_option') }}</option>
                        <option value="OPENAI" {{ $deCurrent === 'OPENAI' ? 'selected' : '' }} {{ $deStatus['OPENAI'] !== 'CONNECTED' ? 'disabled' : '' }}>{{ __('profile.my_own_openai_key_option') }} {{ $deStatus['OPENAI'] === 'CONNECTED' ? __('profile.connected_check_note') : __('profile.not_connected_yet_note') }}</option>
                        <option value="GEMINI" {{ $deCurrent === 'GEMINI' ? 'selected' : '' }} {{ $deStatus['GEMINI'] !== 'CONNECTED' ? 'disabled' : '' }}>{{ __('profile.my_own_gemini_key_option') }} {{ $deStatus['GEMINI'] === 'CONNECTED' ? __('profile.connected_check_note') : __('profile.not_connected_yet_note') }}</option>
                    </select>
                    @error('document_extraction_provider') <div class="field-error" style="color:#dc2626; font-size:11px; margin-top:3px;">{{ $message }}</div> @enderror
                </div>

                @if($deStatus['OPENAI'] !== 'CONNECTED' || $deStatus['GEMINI'] !== 'CONNECTED')
                <div style="font-size:10px; color:#9ca3af; margin-top:6px;">
                    {!! __('profile.not_connected_openai_gemini_note', ['link' => '<a href="'.route('integrations.category', 'ai_services').'" style="color:#1565C0;">'.__('profile.connect_openai_gemini_link').'</a>']) !!}
                </div>
                @endif

                <div style="border-top:1px solid #f3f4f6; margin-top:16px; padding-top:16px; display:flex; align-items:center; justify-content:space-between;">
                    <div>
                        <div style="font-size:13px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('profile.hub_security_heading') }}</div>
                        <div style="font-size:10px; color:#9ca3af;">{{ __('profile.hub_security_note') }}</div>
                    </div>
                    <a href="{{ route('integrations.security') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:7px 16px; font-size:11px; font-weight:600; white-space:nowrap;">{{ __('profile.manage_button') }}</a>
                </div>
            </div>

        </div>

        <div style="display:flex; gap:10px; flex-shrink:0; padding-top:10px;">
            <button type="submit" style="background:#2563eb; color:#fff; border:none; border-radius:6px; padding:9px 24px; font-size:12px; font-weight:600; cursor:pointer;">
                {{ __('profile.save_changes_button') }}
            </button>
            <a href="{{ route('tl.profile.show') }}" class="leave-link" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:9px 18px; font-size:12px; font-weight:500;">
                {{ __('profile.cancel_button') }}
            </a>
        </div>
    </form>
</div>

<style>
.ptab-btn { padding:8px 16px; font-size:11.5px; font-weight:600; color:#6b7280; background:#e5e7eb; border:none; border-radius:8px 8px 0 0; cursor:pointer; white-space:nowrap; font-family:inherit; }
.ptab-btn.active { background:#fff; color:#1565C0; }
/* FIX 6 Aug 2026 — see admin/profile/edit.blade.php for the full writeup:
   fields were being silently cut off on shorter browser windows. Each
   panel now scrolls internally as a safety net; page/sidebar/tabs/buttons
   still never move. */
.ptab-panel { display:none; flex-direction:column; flex:1; min-height:0; overflow-y:auto; overflow-x:hidden; }
.ptab-panel.active { display:flex; }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('miniQr'), {
    text: '{{ $qrUrl }}',
    width: 56,
    height: 56,
    colorDark: '#1565C0',
    colorLight: '#ffffff'
});

function downloadMiniQr(e) {
    e.preventDefault();
    var canvas = document.querySelector('#miniQr canvas');
    if (!canvas) return;
    var link = document.createElement('a');
    link.download = 'my-referral-qr.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
}

function previewPhoto(input) {
    if (!input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        var wrap = document.getElementById('photoPreviewWrap');
        wrap.innerHTML = '<img id="photoPreview" src="' + e.target.result + '" style="width:64px; height:80px; border-radius:6px; object-fit:cover; border:2px solid #B2EBF2;">';
    };
    reader.readAsDataURL(input.files[0]);
}

function aiVoiceProviderToggle(value) {
    document.getElementById('voiceProviderFields').style.display = value ? 'block' : 'none';
}

(function() {
    var form = document.getElementById('profileEditForm');
    var submitting = false;
    var baseline = null;

    function snapshot() {
        return new URLSearchParams(new FormData(form)).toString();
    }
    setTimeout(function() { baseline = snapshot(); }, 400);

    function isDirty() {
        return baseline !== null && snapshot() !== baseline;
    }

    form.addEventListener('submit', function() { submitting = true; });

    window.addEventListener('beforeunload', function(e) {
        if (submitting || !isDirty()) return;
        e.preventDefault();
        e.returnValue = '';
    });

    document.querySelectorAll('.leave-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            if (!isDirty()) return;
            e.preventDefault();
            var destination = link.href;
            var leaveAnyway = confirm({{ json_encode(__('profile.unsaved_changes_confirm_note')) }});
            if (leaveAnyway) {
                submitting = true;
                window.location.href = destination;
            }
        });
    });

    var tabButtons = document.querySelectorAll('.ptab-btn');
    var panels = document.querySelectorAll('.ptab-panel');
    function showTab(name) {
        tabButtons.forEach(function(b) { b.classList.toggle('active', b.dataset.tab === name); });
        panels.forEach(function(p) { p.classList.toggle('active', p.dataset.tab === name); });
    }
    tabButtons.forEach(function(b) {
        b.addEventListener('click', function() { showTab(b.dataset.tab); });
    });

    var firstError = form.querySelector('.field-error');
    if (firstError) {
        var panel = firstError.closest('.ptab-panel');
        if (panel) showTab(panel.dataset.tab);
    }
})();
</script>
@endsection
