@extends('layouts.dashboard')

@section('page-title', __('profile.my_profile_title'))

@section('content')
{{-- REBUILT 6 Aug 2026 (v2) — see admin/profile/show.blade.php for the
     full writeup. Back to folder-tab navigation (Personal / Address /
     Voice Assistant / Text Chat / Integration Hub / Beneficiaries /
     Account / History), all living directly under "My Profile" — no
     separate read-only page, no separate Edit Profile page. --}}
@php
    $deStatus = $documentExtractionStatus ?? ['OPENAI' => null, 'GEMINI' => null];
    $deCurrent = old('document_extraction_provider', $agent->document_extraction_provider ?? 'COMPANY_CREDIT');
@endphp
<div style="height:calc(100vh - 66px); display:flex; flex-direction:column; padding:6px 12px 8px; box-sizing:border-box; overflow:hidden;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:4px 12px; color:#065f46; font-size:10.5px; font-weight:500; flex-shrink:0; margin-bottom:5px;">
        ✅ {{ session('success') }}
    </div>
    @endif

    <form method="POST" action="{{ route('gl.profile.update') }}" enctype="multipart/form-data" id="profileEditForm" style="flex:1; min-height:0; display:flex; flex-direction:column;">
        @csrf
        @method('PUT')

        <div style="display:flex; gap:2px; flex-shrink:0; padding:0 2px; flex-wrap:wrap;">
            <button type="button" class="ptab-btn active" data-tab="profile">{{ __('profile.tab_profile') }}</button>
            <button type="button" class="ptab-btn" data-tab="language">{{ __('profile.tab_language') }}</button>
            <button type="button" class="ptab-btn" data-tab="voice">{{ __('profile.tab_voice') }}</button>
            <button type="button" class="ptab-btn" data-tab="textchat">{{ __('profile.tab_textchat') }}</button>
            <button type="button" class="ptab-btn" data-tab="hub">{{ __('profile.tab_hub') }}</button>
            <button type="button" class="ptab-btn" data-tab="beneficiaries">{{ __('profile.tab_beneficiaries') }}</button>
            <button type="button" class="ptab-btn" data-tab="history">{{ __('profile.tab_history') }}</button>
        </div>

        <div style="flex:1; min-height:0; background:#fff; border-radius:0 8px 8px 8px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 14px; overflow:hidden; display:flex; flex-direction:column;">

            {{-- Profile: Personal + Address + Account (password/QR) all merged, per Chris: ONE screen, no scroll at all. Every row is a single compact line (label to the left of its field) instead of label-above-input, so the whole tab fits comfortably within one view. --}}
            <div class="ptab-panel active" data-tab="profile">
                <div style="display:flex; gap:18px; align-items:flex-start;">
                    <div style="flex:1.6; min-width:0;">

                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                            <div id="photoPreviewWrap">
                                @if($profile->photo_path ?? null)
                                    <img id="photoPreview" src="{{ asset('storage/' . $profile->photo_path) }}" style="width:40px; height:52px; border-radius:5px; object-fit:cover; object-position:center top; border:2px solid #B2EBF2;">
                                @else
                                    <div id="photoPreview" style="width:40px; height:52px; border-radius:5px; background:#E0F7FA; border:2px solid #B2EBF2; display:flex; align-items:center; justify-content:center;"><i class="ti ti-user" style="font-size:17px; color:#1565C0;"></i></div>
                                @endif
                            </div>
                            <input type="file" name="photo" accept="image/*" onchange="previewPhoto(this)" style="font-size:10px; flex:1;">
                        </div>
                        @error('photo') <div class="field-error" style="color:#dc2626; font-size:10.5px; margin-bottom:4px;">{{ $message }}</div> @enderror

                        @php
                            function glPRow($label, $value) {
                                $display = ($value !== null && $value !== '') ? $value : '—';
                                echo "<div style='display:flex; align-items:center; padding:1px 0;'>
                                    <div style='width:78px; font-size:9.5px; color:#6b7280; font-weight:500; flex-shrink:0;'>$label</div>
                                    <div style='font-size:10.5px; color:#111827; flex:1;'>" . htmlspecialchars($display) . "</div>
                                </div>";
                            }
                        @endphp
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 14px; margin-bottom:5px;">
                            {!! glPRow(__('profile.field_agent_code'), $agent->agent_code) !!}
                            {!! glPRow(__('profile.field_member_code'), $agent->member_code) !!}
                            {!! glPRow(__('profile.field_role'), \App\Services\RoleLabelService::label('GROUP_LEADER')) !!}
                            {!! glPRow(__('profile.field_status'), $agent->status) !!}
                            {!! glPRow(__('profile.field_joined'), $agent->created_at ? \Carbon\Carbon::parse($agent->created_at)->format('d M Y') : '—') !!}
                        </div>

                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:5px;">
                            <label style="width:64px; font-size:10px; font-weight:600; color:#9ca3af; flex-shrink:0;">{{ __('profile.field_full_name') }}</label>
                            <input type="text" value="{{ $agent->full_name }}" disabled style="flex:1; border:1px solid #e5e7eb; border-radius:5px; padding:4px 8px; font-size:11px; background:#f9fafb; color:#9ca3af; box-sizing:border-box; cursor:not-allowed;">
                            <span style="font-size:9px; color:#dc2626; white-space:nowrap;">{{ __('profile.locked_note') }}</span>
                        </div>

                        <div style="display:flex; gap:12px; margin-bottom:5px;">
                            <div style="flex:1; display:flex; align-items:center; gap:8px;">
                                <label style="width:56px; font-size:10px; font-weight:600; color:#374151; flex-shrink:0;">{{ __('profile.field_phone_required') }}</label>
                                <input type="text" name="phone" value="{{ old('phone', $agent->phone) }}" required style="flex:1; min-width:0; border:1px solid {{ $errors->has('phone') ? '#f87171' : '#d1d5db' }}; border-radius:5px; padding:4px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                            </div>
                            <div style="flex:1; display:flex; align-items:center; gap:8px;">
                                <label style="width:56px; font-size:10px; font-weight:600; color:#374151; flex-shrink:0;">{{ __('profile.field_email_required') }}</label>
                                <input type="email" name="email" value="{{ old('email', $agent->email) }}" required style="flex:1; min-width:0; border:1px solid {{ $errors->has('email') ? '#f87171' : '#d1d5db' }}; border-radius:5px; padding:4px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                            </div>
                        </div>
                        @error('phone') <div class="field-error" style="color:#dc2626; font-size:10.5px; margin-bottom:3px;">{{ $message }}</div> @enderror
                        @error('email') <div class="field-error" style="color:#dc2626; font-size:10.5px; margin-bottom:3px;">{{ $message }}</div> @enderror

                        <div style="display:flex; align-items:flex-start; gap:8px; margin-bottom:5px;">
                            <label style="width:56px; font-size:10px; font-weight:600; color:#374151; flex-shrink:0; padding-top:4px;">{{ __('profile.field_address') }}</label>
                            <textarea name="address" rows="1" style="flex:1; min-width:0; border:1px solid #d1d5db; border-radius:5px; padding:4px 8px; font-size:11px; outline:none; box-sizing:border-box; resize:none;">{{ old('address', $profile->address ?? '') }}</textarea>
                        </div>

                        <div style="display:flex; gap:10px;">
                            <div style="flex:1; display:flex; align-items:center; gap:6px;">
                                <label style="font-size:10px; font-weight:600; color:#374151; flex-shrink:0;">{{ __('profile.field_city') }}</label>
                                <input type="text" name="city" value="{{ old('city', $profile->city ?? '') }}" style="flex:1; min-width:0; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:11px; outline:none; box-sizing:border-box;">
                            </div>
                            <div style="flex:1; display:flex; align-items:center; gap:6px;">
                                <label style="font-size:10px; font-weight:600; color:#374151; flex-shrink:0;">{{ __('profile.field_postcode') }}</label>
                                <input type="text" name="postcode" value="{{ old('postcode', $profile->postcode ?? '') }}" style="flex:1; min-width:0; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:11px; outline:none; box-sizing:border-box;">
                            </div>
                            <div style="flex:1; display:flex; align-items:center; gap:6px;">
                                <label style="font-size:10px; font-weight:600; color:#374151; flex-shrink:0;">{{ __('profile.field_state') }}</label>
                                <select name="state" style="flex:1; min-width:0; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:11px; background:#fff; outline:none; box-sizing:border-box;">
                                    <option value="">—</option>
                                    @foreach($states as $state)
                                        <option value="{{ $state }}" {{ old('state', $profile->state ?? '') === $state ? 'selected' : '' }}>{{ $state }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style="width:1px; align-self:stretch; background:#f3f4f6;"></div>

                    <div style="flex:0 0 190px; display:flex; flex-direction:column; align-items:center; gap:8px;">
                        <a href="{{ route('gl.profile.change-password') }}" class="leave-link"
                           style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:7px 16px; font-size:11px; font-weight:600; display:inline-block; white-space:nowrap;">
                            {{ __('profile.change_password_link') }}
                        </a>
                        <div id="miniQr" style="display:flex; justify-content:center;"></div>
                        <a href="#" onclick="downloadMiniQr(event)" style="color:#1B9AE4; text-decoration:none; font-size:10px; font-weight:600;">{{ __('profile.download_referral_qr_link') }}</a>
                    </div>
                </div>
            </div>

            {{-- Voice Assistant — REWIRED 6 Aug 2026: picks a provider already
                 connected in the Integration Hub instead of taking a raw key
                 here, so nothing is ever pasted twice. --}}
            {{-- Language — NEW 18 Aug 2026 — per Chris, opened up to
                 Group Leader too. Same preferred_language column and
                 quick-switch globe icon in the top bar. --}}
            <div class="ptab-panel" data-tab="language">
                <div style="font-size:10px; color:#9ca3af; margin-bottom:14px;">{{ __('profile.language_change_note') }}</div>
                <div style="max-width:340px;">
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('profile.preferred_language_label') }}</label>
                    <select name="preferred_language" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; background:#fff; outline:none; box-sizing:border-box;">
                        <option value="EN" {{ old('preferred_language', $agent->preferred_language ?? 'EN') === 'EN' ? 'selected' : '' }}>{{ __('profile.lang_english') }}</option>
                        <option value="MS" {{ old('preferred_language', $agent->preferred_language ?? 'EN') === 'MS' ? 'selected' : '' }}>{{ __('profile.lang_bahasa_malaysia') }}</option>
                        <option value="ZH" {{ old('preferred_language', $agent->preferred_language ?? 'EN') === 'ZH' ? 'selected' : '' }}>{{ __('profile.lang_chinese') }}</option>
                    </select>
                </div>
            </div>

            <div class="ptab-panel" data-tab="voice">
                <div style="font-size:10px; color:#9ca3af; margin-bottom:10px;">{{ __('profile.voice_pick_note') }}</div>
                <div style="margin-bottom:6px;">
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('profile.voice_provider_label') }}</label>
                    <select name="voice_provider" id="voiceProviderSelect" onchange="aiVoiceProviderToggle(this.value)" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; background:#fff; outline:none; box-sizing:border-box;">
                        <option value="" {{ old('voice_provider', $agent->voice_provider ?? '') === '' ? 'selected' : '' }}>{{ __('profile.voice_none_option') }}</option>
                        <option value="ELEVENLABS" {{ old('voice_provider', $agent->voice_provider ?? '') === 'ELEVENLABS' ? 'selected' : '' }} {{ ($voiceStatus['ELEVENLABS'] ?? null) !== 'CONNECTED' ? 'disabled' : '' }}>ElevenLabs {{ ($voiceStatus['ELEVENLABS'] ?? null) === 'CONNECTED' ? __('profile.connected_check_note') : __('profile.not_connected_yet_note') }}</option>
                        <option value="OPENAI" {{ old('voice_provider', $agent->voice_provider ?? '') === 'OPENAI' ? 'selected' : '' }} {{ ($voiceStatus['OPENAI'] ?? null) !== 'CONNECTED' ? 'disabled' : '' }}>OpenAI {{ ($voiceStatus['OPENAI'] ?? null) === 'CONNECTED' ? __('profile.connected_check_note') : __('profile.not_connected_yet_note') }}</option>
                    </select>
                    @error('voice_provider') <div class="field-error" style="color:#dc2626; font-size:11px; margin-top:3px;">{{ $message }}</div> @enderror
                </div>
                @if(($voiceStatus['ELEVENLABS'] ?? null) !== 'CONNECTED' || ($voiceStatus['OPENAI'] ?? null) !== 'CONNECTED')
                <div style="font-size:10px; color:#9ca3af; margin-bottom:8px;">{!! __('profile.not_connected_elevenlabs_openai_note', ['link' => '<a href="'.route('integrations.category', 'ai_services').'" style="color:#1565C0;">'.__('profile.connect_elevenlabs_openai_link').'</a>']) !!}</div>
                @endif
                @if(!$hubUnlocked)
                <div style="background:#fef3c7; border:1px solid #fde68a; border-radius:6px; padding:6px 10px; font-size:10px; color:#92400e; margin-bottom:8px;">{!! __('profile.hub_locked_note', ['link' => '<a href="'.route('integrations.security').'" style="color:#92400e; font-weight:600;">'.__('profile.unlock_with_hub_password_link').'</a>']) !!}</div>
                @endif
                <div id="voiceProviderFields" style="{{ empty($agent->voice_provider ?? null) ? 'display:none;' : '' }}">
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('profile.voice_id_name_label') }} <span style="color:#9ca3af; font-weight:400;">{{ __('profile.optional_note') }}</span></label>
                    <input type="text" name="voice_id" value="{{ old('voice_id', $agent->voice_id ?? '') }}" placeholder="{{ __('profile.voice_id_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; outline:none; box-sizing:border-box;">
                    <div style="font-size:10px; color:#9ca3af; margin-top:3px;">{{ __('profile.leave_blank_default_voice_note') }}</div>
                </div>
            </div>

            {{-- Text Chat — REWIRED 6 Aug 2026: same pattern as Voice Assistant. --}}
            <div class="ptab-panel" data-tab="textchat">
                <div style="font-size:10px; color:#9ca3af; margin-bottom:10px;">{{ __('profile.textchat_pick_note') }}</div>
                <div style="margin-bottom:6px;">
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('profile.text_chat_key_label') }}</label>
                    <select name="text_chat_provider" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; background:#fff; outline:none; box-sizing:border-box;">
                        <option value="" {{ old('text_chat_provider', $agent->text_chat_provider ?? '') === '' ? 'selected' : '' }}>{{ __('profile.textchat_none_option') }}</option>
                        <option value="ANTHROPIC" {{ old('text_chat_provider', $agent->text_chat_provider ?? '') === 'ANTHROPIC' ? 'selected' : '' }} {{ ($textChatStatus['ANTHROPIC'] ?? null) !== 'CONNECTED' ? 'disabled' : '' }}>{{ __('profile.my_own_anthropic_key_option') }} {{ ($textChatStatus['ANTHROPIC'] ?? null) === 'CONNECTED' ? __('profile.connected_check_note') : __('profile.not_connected_yet_note') }}</option>
                    </select>
                    @error('text_chat_provider') <div class="field-error" style="color:#dc2626; font-size:11px; margin-top:3px;">{{ $message }}</div> @enderror
                </div>
                @if(($textChatStatus['ANTHROPIC'] ?? null) !== 'CONNECTED')
                <div style="font-size:10px; color:#9ca3af; margin-bottom:8px;">{!! __('profile.not_connected_anthropic_note', ['link' => '<a href="'.route('integrations.category', 'ai_services').'" style="color:#1565C0;">'.__('profile.connect_anthropic_link').'</a>']) !!}</div>
                @endif
                @if(!$hubUnlocked)
                <div style="background:#fef3c7; border:1px solid #fde68a; border-radius:6px; padding:6px 10px; font-size:10px; color:#92400e; margin-bottom:8px;">{!! __('profile.hub_locked_note', ['link' => '<a href="'.route('integrations.security').'" style="color:#92400e; font-weight:600;">'.__('profile.unlock_with_hub_password_link').'</a>']) !!}</div>
                @endif
                <div style="border-top:1px solid #f3f4f6; margin-top:6px; padding-top:12px; display:flex; align-items:center; justify-content:space-between;">
                    <div>
                        <div style="font-size:12px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('profile.what_carolyn_remembers_heading') }}</div>
                        <div style="font-size:9.5px; color:#9ca3af;">{{ __('profile.carolyn_remembers_note') }}</div>
                    </div>
                    <a href="{{ route('ai.memory.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 14px; font-size:10.5px; font-weight:600; white-space:nowrap;">{{ __('profile.view_button') }}</a>
                </div>
            </div>

            {{-- Integration Hub --}}
            <div class="ptab-panel" data-tab="hub">
                <div style="font-size:13px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('profile.document_reading_heading') }}</div>
                <div style="font-size:10px; color:#9ca3af; margin-bottom:14px;">{{ __('profile.document_reading_note') }}</div>
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

            {{-- Beneficiaries — read-only, managed by Admin only --}}
            <div class="ptab-panel" data-tab="beneficiaries">
                <div style="font-size:13px; font-weight:600; color:#1565C0; margin-bottom:4px;">{{ __('profile.beneficiaries_heading') }}</div>
                <div style="font-size:10px; color:#9ca3af; margin-bottom:14px;">{{ __('profile.beneficiaries_managed_note') }}</div>
                @forelse($beneficiaries as $b)
                <div style="background:#f9fafb; border-radius:6px; padding:10px 12px; margin-bottom:8px;">
                    <div style="display:flex; gap:20px; flex-wrap:wrap;">
                        <div><span style="font-size:9.5px; color:#6b7280;">{{ __('profile.field_priority') }}</span><br><span style="font-size:14px; font-weight:700; color:#2563eb;">{{ $b->priority_order }}</span></div>
                        <div><span style="font-size:9.5px; color:#6b7280;">{{ __('profile.field_name') }}</span><br><span style="font-size:12px; font-weight:600; color:#374151;">{{ $b->full_name }}</span></div>
                        <div><span style="font-size:9.5px; color:#6b7280;">{{ __('profile.field_relationship') }}</span><br><span style="font-size:12px; color:#374151;">{{ $b->relationship }}</span></div>
                        <div><span style="font-size:9.5px; color:#6b7280;">{{ __('profile.field_phone') }}</span><br><span style="font-size:12px; color:#374151;">{{ $b->phone ?? '—' }}</span></div>
                    </div>
                </div>
                @empty
                <div style="color:#9ca3af; font-size:11px; text-align:center; padding:20px 0;">{{ __('profile.no_beneficiaries_note') }}</div>
                @endforelse
            </div>

            {{-- History — REBUILT 6 Aug 2026 (v2): one single table (was
                 two stacked tables before — that caused the misaligned
                 "Account Created" row Chris spotted, since two separate
                 tables can round percentage column widths slightly
                 differently). Also fixed a real bug here: this panel had
                 an inline "display:flex" style that beat the .ptab-panel
                 CSS class's "display:none", so History was ALWAYS visible
                 underneath whichever tab was actually open — that's what
                 caused Profile and History to visibly overlap. Removed. --}}
            <div class="ptab-panel" data-tab="history">
                <div style="font-size:13px; font-weight:600; color:#1565C0; margin-bottom:6px;">{{ __('profile.profile_history_heading') }}</div>
                <table style="width:100%; border-collapse:collapse; font-size:10.5px; table-layout:fixed;">
                    <thead>
                        <tr style="border-bottom:1px solid #e5e7eb;">
                            <th style="text-align:left; padding:4px 6px; font-size:9.5px; font-weight:600; color:#9ca3af; width:60%;">{{ __('profile.col_action') }}</th>
                            <th style="text-align:left; padding:4px 6px; font-size:9.5px; font-weight:600; color:#9ca3af; width:40%;">{{ __('profile.col_date_time') }}</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:4px 6px;"><span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#16a34a; margin-right:6px;"></span><span style="font-weight:600; color:#374151;">{{ __('profile.account_created_label') }}</span> <span style="color:#16a34a; font-size:9.5px;">({{ \App\Services\RoleLabelService::label('GROUP_LEADER') }})</span></td>
                            <td style="padding:4px 6px; color:#6b7280;">{{ $agent->created_at ? \Carbon\Carbon::parse($agent->created_at)->format('d M Y, h:i A') : '—' }}</td>
                        </tr>
                        @forelse($history as $log)
                        @php
                            $actionColor = match($log->action) {
                                'PROFILE_UPDATE'  => '#2563eb',
                                'PASSWORD_CHANGE' => '#7c3aed',
                                'ROLE_CHANGE'     => '#d97706',
                                'STATUS_CHANGE'   => '#dc2626',
                                default           => '#6b7280',
                            };
                            $actionLabel = match($log->action) {
                                'PROFILE_UPDATE'  => __('profile.action_profile_updated'),
                                'PASSWORD_CHANGE' => __('profile.action_password_changed'),
                                'ROLE_CHANGE'     => __('profile.action_role_changed'),
                                'STATUS_CHANGE'   => __('profile.action_status_changed'),
                                default           => $log->action,
                            };
                        @endphp
                        <tr class="history-row" style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:4px 6px;"><span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:{{ $actionColor }}; margin-right:6px;"></span><span style="font-weight:600; color:#374151;">{{ $actionLabel }}</span></td>
                            <td style="padding:4px 6px; color:#6b7280;">{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y, h:i A') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="2" style="color:#9ca3af; font-size:11px; text-align:center; padding:20px 0;">{{ __('profile.no_further_history_note') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div id="historyPagerWrap" style="display:none; align-items:center; justify-content:space-between; padding-top:8px;">
                    <button type="button" onclick="historyPage(-1)" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:5px 14px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('profile.pager_prev') }}</button>
                    <span id="historyPageLabel" style="font-size:10px; color:#6b7280; white-space:nowrap;"></span>
                    <button type="button" onclick="historyPage(1)" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:5px 14px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('profile.pager_next') }}</button>
                </div>
            </div>

        </div>

        <div style="display:flex; align-items:center; gap:10px; flex-shrink:0; padding-top:6px;">
            <a href="{{ route('gl.dashboard') }}" class="leave-link" style="color:#6b7280; text-decoration:none; font-size:11px; margin-right:auto;">{{ __('network.back_dashboard') }}</a>
            <button type="submit" style="background:#2563eb; color:#fff; border:none; border-radius:6px; padding:7px 20px; font-size:11.5px; font-weight:600; cursor:pointer;">
                {{ __('profile.save_changes_button') }}
            </button>
            <button type="button" onclick="if(confirm({{ Js::from(__('profile.discard_confirm_note')) }})) location.reload();" style="background:#f3f4f6; color:#374151; border:none; border-radius:6px; padding:7px 16px; font-size:11.5px; font-weight:500; cursor:pointer;">
                {{ __('profile.discard_changes_button') }}
            </button>
        </div>
    </form>
</div>

<style>
.ptab-btn { padding:5px 12px; font-size:10px; font-weight:600; color:#6b7280; background:#e5e7eb; border:none; border-radius:7px 7px 0 0; cursor:pointer; white-space:nowrap; font-family:inherit; }
.ptab-btn.active { background:#fff; color:#1565C0; }
.ptab-panel { display:none; flex-direction:column; flex:1; min-height:0; overflow-y:auto; overflow-x:hidden; }
.ptab-panel.active { display:flex; }
#miniQr { width:46px; height:46px; overflow:hidden; margin:0 auto; }
#miniQr canvas, #miniQr img { width:46px !important; height:46px !important; }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('miniQr'), {
    text: '{{ $qrUrl }}',
    width: 46,
    height: 46,
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
        wrap.innerHTML = '<img id="photoPreview" src="' + e.target.result + '" style="width:40px; height:52px; border-radius:5px; object-fit:cover; object-position:center top; border:2px solid #B2EBF2;">';
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
            var leaveAnyway = confirm({{ Js::from(__('profile.unsaved_changes_confirm_note')) }});
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

(function() {
    var rows = Array.prototype.slice.call(document.querySelectorAll('#historyTableBody .history-row'));
    var pageSize = 4;
    var totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
    var currentPage = 1;

    function render() {
        rows.forEach(function(row, i) {
            row.style.display = (Math.floor(i / pageSize) + 1 === currentPage) ? '' : 'none';
        });
        var pagerWrap = document.getElementById('historyPagerWrap');
        if (totalPages > 1) {
            pagerWrap.style.display = 'flex';
            document.getElementById('historyPageLabel').textContent = {{ Js::from(__('profile.pager_page_of', ['current' => '__CUR__', 'total' => '__TOT__'])) }}.replace('__CUR__', currentPage).replace('__TOT__', totalPages);
        } else {
            pagerWrap.style.display = 'none';
        }
    }

    window.historyPage = function(delta) {
        currentPage = Math.min(totalPages, Math.max(1, currentPage + delta));
        render();
    };

    render();
})();
</script>
@endsection
