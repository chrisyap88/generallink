@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('masterfile.edit_role_title', ['role' => \App\Services\RoleLabelService::label('INTRODUCER')]))

@section('content')
@php
    // NEW 15 Jul 2026 — moved to top level (was previously only computed
    // inside the Resend Verification block) so it also covers the Back
    // link, form action, Cancel link, and undo-role form below — all of
    // which used to hardcode 'admin.masterfile...' and 403 for GL/TL.
    $rolePrefix = match(auth('agent')->user()->role) {
        'ADMIN' => 'admin', 'GROUP_LEADER' => 'gl', 'TEAM_LEADER' => 'tl', default => 'introducer',
    };
@endphp
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:6px 16px 0; box-sizing:border-box;">

    <div style="flex-shrink:0;">

    <div style="display:flex; align-items:baseline; gap:12px; margin-bottom:6px;">
        {{-- NEW 23 Jul 2026 — per Chris: honor a `?back=` link (e.g. the
             Organization Rewards Group Audit View) instead of always
             dumping back on the generic Introducer Maintenance index. --}}
        <a href="{{ request('back') ? urldecode(request('back')) : route($rolePrefix . '.masterfile.introducers') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">&larr; {{ request('back') ? __('masterfile.back') : __('masterfile.back_to_role_maintenance', ['role' => \App\Services\RoleLabelService::label('INTRODUCER')]) }}</a>
        <span style="font-size:11px; color:#9ca3af;">{{ $agent->agent_code }} &middot; {{ $agent->full_name }}</span>
    </div>

    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:6px 10px; font-size:11px; color:#b71c1c; margin-bottom:6px;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:6px 10px; font-size:11px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    @if(session('error'))
    <div style="background:#fde8e8; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:11px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    @if($agent->pending_email)
    <div style="background:#fff8e1; border-left:3px solid #D97706; border-radius:6px; padding:6px 10px; font-size:11px; color:#92400e; margin-bottom:6px;">
        <strong>{{ __('masterfile.pending_email_change_label') }}</strong> {{ __('masterfile.pending_email_change_note', ['email' => $agent->pending_email]) }}
    </div>
    @endif

    {{-- NEW 15 Jul 2026 — Resend Verification. Only shown when this
         agent hasn't verified their email yet. Available to Admin, GL,
         Team Leader (per the master authority policy enforced server-
         side in MasterFileController::resendVerification()). --}}
    @if(!$agent->email_verified_at)
    <div style="background:#fef3c7; border-left:3px solid #D97706; border-radius:6px; padding:8px 10px; font-size:11px; color:#92400e; margin-bottom:6px; display:flex; align-items:center; justify-content:space-between; gap:10px;">
        <span><strong>{{ __('masterfile.not_verified_label') }}</strong> {{ __('masterfile.not_verified_note', ['name' => $agent->full_name]) }}</span>
        <form method="POST" action="{{ route($rolePrefix . '.masterfile.resend-verification', $agent->agent_id) }}" onsubmit="return confirm({{ json_encode(__('masterfile.resend_confirm', ['email' => $agent->email])) }});" style="margin:0;">
            @csrf
            <button type="submit" style="background:#D97706; color:#fff; border:none; border-radius:6px; padding:6px 12px; font-size:10.5px; font-weight:700; cursor:pointer; white-space:nowrap;">{{ __('masterfile.resend_verification') }}</button>
        </form>
    </div>
    @endif

    </div>

    <form method="POST" action="{{ route($rolePrefix . '.masterfile.introducers.update', $agent->agent_id) }}" enctype="multipart/form-data" style="flex:1 1 auto; min-height:0; display:flex; flex-direction:column;">
        @csrf
        @method('PUT')

        {{-- Staged by the inline Status pencil-editor below; submitted
             together with everything else on Save Changes. --}}
        <input type="hidden" name="new_status" id="newStatusHidden" value="">
        <input type="hidden" name="reason_code_id" id="reasonCodeHidden" value="">
        <input type="hidden" name="reason_notes" id="reasonNotesHidden" value="">
        <input type="hidden" name="back" value="{{ request('back') }}">

        <div style="flex:1 1 auto; min-height:0; overflow-y:auto; padding-bottom:4px;">

        <div style="background:#fff; border-radius:8px; padding:10px 14px; margin-bottom:10px; position:relative;">

            {{-- Photo (Admin can upload/replace) + QR (read-only, permanent) --}}
            <div style="position:absolute; top:10px; right:12px; display:flex; gap:8px; align-items:center;">
                <div style="text-align:center; position:relative;">
                    <div id="photoPreviewWrap">
                        @if($profile->photo_path ?? null)
                            <img id="photoPreview" src="{{ asset('storage/' . $profile->photo_path) }}" style="width:46px; height:46px; border-radius:5px; object-fit:cover; border:1.5px solid #B2EBF2;">
                        @else
                            <div id="photoPreview" style="width:46px; height:46px; border-radius:5px; background:#E0F7FA; border:1.5px solid #B2EBF2; display:flex; align-items:center; justify-content:center;"><i class="ti ti-user" style="font-size:20px; color:#1565C0;"></i></div>
                        @endif
                    </div>
                    <div onclick="document.getElementById('photoUploadInput').click()" style="position:absolute; bottom:12px; right:-4px; width:16px; height:16px; background:#1565C0; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; border:1.5px solid #fff;">
                        <i class="ti ti-pencil" style="font-size:9px; color:#fff;"></i>
                    </div>
                    <input type="file" name="photo" id="photoUploadInput" accept="image/*" onchange="previewAdminPhoto(this)" style="display:none;">
                    <div style="font-size:8px; color:#9ca3af; margin-top:2px;">{{ __('masterfile.photo_label') }}</div>
                </div>
                <div style="text-align:center;">
                    <div id="adminQrPreview" style="width:46px; height:46px;"></div>
                    <div style="font-size:8px; color:#9ca3af; margin-top:2px;">{{ __('masterfile.referral_qr') }}</div>
                </div>
            </div>

            {{-- Row 1: Full Name, Phone --}}
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-bottom:7px; padding-right:110px;">
                <div>
                    <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('masterfile.full_name') }}</label>
                    <input type="text" value="{{ $agent->full_name }}" disabled style="width:100%; border:1px solid #B2EBF2; border-radius:5px; padding:4px 7px; font-size:10.5px; background:#E0F7FA; color:#1a1a1a; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('masterfile.name_other_language') }}</label>
                    <input type="text" name="second_name" value="{{ old('second_name', $agent->second_name) }}" placeholder="{{ __('masterfile.optional_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('masterfile.phone') }} <span style="color:#e53935;">*</span></label>
                    <input type="text" name="phone" value="{{ old('phone', $agent->phone) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:10.5px; box-sizing:border-box;">
                </div>
            </div>

            {{-- Row 2: Street Address, Postcode, City, State --}}
            <div style="display:grid; grid-template-columns:2fr 0.7fr 1fr 1fr; gap:8px; margin-bottom:7px;">
                <div>
                    <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('masterfile.street_address') }} <span style="color:#e53935;">*</span></label>
                    <input type="text" name="address" value="{{ old('address', $profile->address ?? '') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('masterfile.postcode') }} <span style="color:#e53935;">*</span></label>
                    <input type="text" name="postcode" id="postcodeEdit" value="{{ old('postcode', $profile->postcode ?? '') }}" required autocomplete="off" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="position:relative;">
                    <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('masterfile.city') }} <span style="color:#e53935;">*</span></label>
                    <input type="text" name="city" id="cityEdit" value="{{ old('city', $profile->city ?? '') }}" required autocomplete="off" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:10.5px; box-sizing:border-box;">
                    <div id="pcDropdownEdit" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:130px; overflow-y:auto;"></div>
                </div>
                <div>
                    <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('masterfile.state') }} <span style="color:#e53935;">*</span></label>
                    <select name="state" id="stateEdit" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:10.5px; box-sizing:border-box; background:#fff;">
                        <option value="">{{ __('masterfile.select_placeholder') }}</option>
                        @foreach($states as $st)
                            <option value="{{ $st }}" {{ old('state', $profile->state ?? '') == $st ? 'selected' : '' }}>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Row 3: Email, Agent Code --}}
            <div style="display:grid; grid-template-columns:2fr 0.7fr; gap:8px; margin-bottom:7px;">
                <div>
                    <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('masterfile.email') }} <span style="color:#e53935;">*</span></label>
                    <input type="email" name="email" value="{{ old('email', $agent->email) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('masterfile.agent_code') }}</label>
                    <input type="text" value="{{ $agent->agent_code }}" disabled style="width:100%; border:1px solid #B2EBF2; border-radius:5px; padding:4px 7px; font-size:10.5px; background:#E0F7FA; color:#1a1a1a; box-sizing:border-box;">
                </div>
            </div>

            {{-- Row 4: Sponsor, Status (pencil editor), Group, Role --}}
            <div style="display:grid; grid-template-columns:1.3fr 0.8fr 1fr 1fr; gap:8px; margin-bottom:7px;">
                <div>
                    <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('masterfile.sponsor_upline') }}</label>
                    <input type="text" value="{{ $sponsor->full_name ?? __('masterfile.admin_assign') }}" disabled style="width:100%; border:1px solid #B2EBF2; border-radius:5px; padding:4px 7px; font-size:10.5px; background:#E0F7FA; color:#1a1a1a; box-sizing:border-box;">
                </div>
                <div style="position:relative;">
                    <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('masterfile.status') }}</label>
                    <div id="statusFieldWrap" style="position:relative;">
                        <div id="statusField" style="border:1px solid #B2EBF2; background:#E0F7FA; border-radius:5px; padding:4px 7px; font-size:10.5px; font-weight:600; color:#1565C0; display:flex; justify-content:space-between; align-items:center; cursor:pointer;">
                            <span id="statusValue">{{ $agent->status }}</span>
                            <i class="ti ti-pencil" id="statusPencil" style="font-size:12px; color:#1565C0;"></i>
                        </div>
                        <div id="statusTriangle" style="display:none; position:absolute; top:-3px; right:-3px; width:0; height:0; border-style:solid; border-width:0 10px 10px 0; border-color:transparent #E24B4A transparent transparent; cursor:default;"></div>
                    </div>

                    <div id="statusModalBackdrop" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.35); z-index:999;"></div>

                    <div id="statusPopup" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); width:280px; max-height:80vh; overflow-y:auto; background:#fff; border-radius:10px; padding:16px; box-shadow:0 10px 30px rgba(0,0,0,.25); z-index:1000;">
                        <div style="font-size:12px; font-weight:700; color:#1565C0; margin-bottom:10px;">{{ __('masterfile.update_status') }}</div>
                        @if($activeDownlineCount > 0)
                        <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:5px; padding:6px 8px; font-size:10px; color:#b71c1c; margin-bottom:8px;">
                            {{ __('masterfile.active_downline_warning_sub', ['count' => $activeDownlineCount, 'role' => \App\Services\RoleLabelService::label('INTRODUCER')]) }}
                        </div>
                        @endif
                        <label style="font-size:10px; color:#374151; display:block; margin-bottom:3px;">{{ __('masterfile.new_status_label') }}</label>
                        <select id="newStatusSelect" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; margin-bottom:10px; box-sizing:border-box;">
                            <option value="ACTIVE" {{ $agent->status=='ACTIVE'?'selected':'' }}>{{ __('masterfile.active') }}</option>
                            <option value="INACTIVE" {{ $agent->status=='INACTIVE'?'selected':'' }}>{{ __('masterfile.inactive') }}</option>
                        </select>
                        <div id="reasonSection" style="display:none;">
                            <label style="font-size:10px; color:#374151; display:block; margin-bottom:3px;">{{ __('masterfile.reason_label') }} <span style="color:#e53935;">*</span></label>
                            <select id="reasonSelect" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; margin-bottom:10px; box-sizing:border-box;">
                                <option value="">{{ __('masterfile.select_placeholder') }}</option>
                                @foreach($inactiveReasons as $reason)
                                    <option value="{{ $reason->reason_code_id }}">{{ $reason->description }}</option>
                                @endforeach
                            </select>
                            <label style="font-size:10px; color:#374151; display:block; margin-bottom:3px;">{{ __('masterfile.notes_label') }} <span style="font-weight:400; color:#9ca3af;">({{ __('masterfile.optional') }})</span></label>
                            <textarea id="notesInput" rows="3" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; margin-bottom:10px; resize:vertical; box-sizing:border-box;"></textarea>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <div id="statusApplyBtn" style="background:#1565C0; color:#fff; border-radius:6px; padding:7px 16px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('masterfile.apply') }}</div>
                            <div id="statusCancelBtn" style="background:#f3f4f6; color:#374151; border-radius:6px; padding:7px 16px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('masterfile.cancel') }}</div>
                        </div>
                    </div>
                </div>
                <div>
                    <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('masterfile.field_group') }}</label>
                    <input type="text" value="{{ $group->group_name ?? '—' }}" disabled style="width:100%; border:1px solid #B2EBF2; border-radius:5px; padding:4px 7px; font-size:10.5px; background:#E0F7FA; color:#1a1a1a; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('masterfile.field_role') }}</label>
                    <input type="text" value="{{ \App\Services\RoleLabelService::label($agent->role, $agent->group_label_id) }}" disabled style="width:100%; border:1px solid #B2EBF2; border-radius:5px; padding:4px 7px; font-size:10.5px; background:#E0F7FA; color:#1a1a1a; box-sizing:border-box;">
                </div>
            </div>

            @if($latestRoleHistory)
            <div style="border-top:1px solid #f3f4f6; margin:6px 0 7px;"></div>
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:7px; background:#f9fafb; border-radius:6px; padding:6px 10px;">
                <div style="font-size:10.5px; color:#4b5563;">
                    <strong style="color:#374151;">{{ __('masterfile.last_role_change') }}</strong>
                    {{ $latestRoleHistory->old_role }} &rarr; {{ $latestRoleHistory->new_role }}
                    <span style="color:#9ca3af;">{{ __('masterfile.role_change_meta', ['date' => \Carbon\Carbon::parse($latestRoleHistory->effective_date)->format('d M Y, h:i A'), 'reason' => $latestRoleHistory->reason]) }}</span>
                </div>
                @if($hasPendingUndo)
                    <span style="background:#fff8e1; color:#92400e; padding:3px 10px; border-radius:20px; font-size:9.5px; font-weight:600;">{{ __('masterfile.undo_pending') }}</span>
                @else
                    <span onclick="document.getElementById('undoModal').style.display='block'; document.getElementById('undoBackdrop').style.display='block';" style="background:#e53935; color:#fff; padding:4px 12px; border-radius:5px; font-size:10px; font-weight:600; cursor:pointer;">{{ __('masterfile.request_undo') }}</span>
                @endif
            </div>
            @endif

            <div style="border-top:1px solid #f3f4f6; margin:5px 0 5px;"></div>

            {{-- System Info — opens as a centered popup (like the Status
                 editor above) instead of expanding inline. An in-place
                 expand still pushed content past the viewport on shorter
                 windows; a popup floats above everything so it can never
                 cause scrolling no matter how tall its content is. --}}
            <div>
                <span onclick="document.getElementById('sysInfoPopup').style.display='block'; document.getElementById('sysInfoBackdrop').style.display='block';" style="font-size:9.5px; color:#1565C0; cursor:pointer; font-weight:600; user-select:none;">&#9656; {{ __('masterfile.system_info') }}</span>
            </div>

            <div id="sysInfoBackdrop" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.35); z-index:999;" onclick="this.style.display='none'; document.getElementById('sysInfoPopup').style.display='none';"></div>
            <div id="sysInfoPopup" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); width:360px; max-height:80vh; overflow-y:auto; background:#fff; border-radius:10px; padding:16px; box-shadow:0 10px 30px rgba(0,0,0,.25); z-index:1000;">
                <div style="font-size:12px; font-weight:700; color:#1565C0; margin-bottom:10px;">{{ __('masterfile.system_info') }}</div>
                <div style="display:flex; flex-direction:column; gap:6px; font-size:11px; color:#374151;">
                    <div><span style="color:#9ca3af;">{{ __('masterfile.earning_income_balance') }}:</span> {{ $agent->commission_balance }}</div>
                    <div><span style="color:#9ca3af;">{{ __('masterfile.email_verified_at') }}:</span> {{ $agent->email_verified_at ?? __('masterfile.not_verified_value') }}</div>
                    <div><span style="color:#9ca3af;">{{ __('masterfile.recruit_blocked') }}:</span> {{ $agent->recruitment_blocked ? __('masterfile.yes') : __('masterfile.no') }}</div>
                    <div><span style="color:#9ca3af;">{{ __('masterfile.next_child_seq') }}:</span> {{ $agent->next_child_seq }}</div>
                    <div><span style="color:#9ca3af;">{{ __('masterfile.failed_login_attempts') }}:</span> {{ $agent->failed_login_attempts }}</div>
                    <div><span style="color:#9ca3af;">{{ __('masterfile.locked_until') }}:</span> {{ $agent->locked_until ?? '—' }}</div>
                    <div><span style="color:#9ca3af;">{{ __('masterfile.security_phrase_set') }}:</span> {{ $agent->security_phrase_set ? __('masterfile.yes') : __('masterfile.no') }}</div>
                    <div><span style="color:#9ca3af;">{{ __('masterfile.created_by') }}:</span> {{ $createdByAgent->full_name ?? '—' }}</div>
                    <div><span style="color:#9ca3af;">{{ __('masterfile.created_at') }}:</span> {{ $agent->created_at }}</div>
                    <div><span style="color:#9ca3af;">{{ __('masterfile.updated_by') }}:</span> {{ $updatedByAgent->full_name ?? '—' }}</div>
                    <div><span style="color:#9ca3af;">{{ __('masterfile.updated_at') }}:</span> {{ $agent->updated_at }}</div>
                </div>
                <div style="margin-top:12px; text-align:right;">
                    <span onclick="document.getElementById('sysInfoPopup').style.display='none'; document.getElementById('sysInfoBackdrop').style.display='none';" style="background:#f3f4f6; color:#374151; border-radius:6px; padding:6px 16px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('masterfile.close') }}</span>
                </div>
            </div>
        </div>

    </div>

        <div style="flex-shrink:0; display:flex; gap:8px; padding:8px 0 10px; background:#fff; border-top:1px solid #f3f4f6;">
            <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:6px 20px; font-size:11.5px; font-weight:700; cursor:pointer;">{{ __('masterfile.save_changes') }}</button>
            <a href="{{ request('back') ? urldecode(request('back')) : route($rolePrefix . '.masterfile.introducers') }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:11.5px; font-weight:500;">{{ __('masterfile.cancel') }}</a>
        </div>
    </form>
</div>

<script>
(function() {
    var pc = document.getElementById('postcodeEdit');
    var city = document.getElementById('cityEdit');
    var dd = document.getElementById('pcDropdownEdit');
    var timer;

    pc.addEventListener('input', function() {
        clearTimeout(timer);
        var v = this.value.trim();
        if (v.length < 3) { dd.style.display = 'none'; return; }
        timer = setTimeout(function() {
            fetch('{{ route('register.postcode-lookup') }}?postcode=' + encodeURIComponent(v) + '&partial=1')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data || !data.length) { dd.style.display = 'none'; return; }
                    dd.innerHTML = '';
                    data.forEach(function(item) {
                        var d = document.createElement('div');
                        d.style.cssText = 'padding:5px 8px; font-size:10.5px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                        d.innerHTML = '<strong>' + item.postcode + '</strong> — ' + item.city + ' (' + item.state + ')';
                        d.onmousedown = function(e) {
                            e.preventDefault();
                            pc.value = item.postcode;
                            city.value = item.city;
                            var s = document.getElementById('stateEdit');
                            for (var i = 0; i < s.options.length; i++) { if (s.options[i].value === item.state) { s.selectedIndex = i; break; } }
                            dd.style.display = 'none';
                        };
                        dd.appendChild(d);
                    });
                    dd.style.display = 'block';
                }).catch(function() { dd.style.display = 'none'; });
        }, 300);
    });

    document.addEventListener('click', function(e) { if (e.target !== pc) dd.style.display = 'none'; });
})();

// --- Inline Status pencil-editor ---
var reasonMap = {};
@foreach($inactiveReasons as $reason)
reasonMap['{{ $reason->reason_code_id }}'] = @json($reason->description);
@endforeach

document.getElementById('statusPencil').onclick = function(e) {
    e.stopPropagation();
    document.getElementById('statusPopup').style.display = 'block';
    document.getElementById('statusModalBackdrop').style.display = 'block';
};
document.getElementById('statusCancelBtn').onclick = function() {
    document.getElementById('statusPopup').style.display = 'none';
    document.getElementById('statusModalBackdrop').style.display = 'none';
};
document.getElementById('statusModalBackdrop').onclick = function() {
    document.getElementById('statusPopup').style.display = 'none';
    document.getElementById('statusModalBackdrop').style.display = 'none';
};
document.getElementById('newStatusSelect').onchange = function() {
    document.getElementById('reasonSection').style.display = this.value === 'INACTIVE' ? 'block' : 'none';
};
document.getElementById('statusApplyBtn').onclick = function() {
    var status = document.getElementById('newStatusSelect').value;
    var reasonId = document.getElementById('reasonSelect') ? document.getElementById('reasonSelect').value : '';
    var notes = document.getElementById('notesInput') ? document.getElementById('notesInput').value : '';

    if (status === 'INACTIVE' && !reasonId) {
        alert(@json(__('masterfile.please_select_reason')));
        return;
    }

    document.getElementById('statusValue').textContent = status;
    document.getElementById('newStatusHidden').value = status;
    document.getElementById('reasonCodeHidden').value = reasonId;
    document.getElementById('reasonNotesHidden').value = notes;

    var triangle = document.getElementById('statusTriangle');
    triangle.style.display = (reasonId || notes) ? 'block' : 'none';
    triangle.dataset.reason = reasonMap[reasonId] || '';
    triangle.dataset.notes = notes;

    document.getElementById('statusPopup').style.display = 'none';
    document.getElementById('statusModalBackdrop').style.display = 'none';
};

var triangle = document.getElementById('statusTriangle');
var statusTooltip = document.createElement('div');
statusTooltip.style.cssText = 'display:none; position:fixed; background:#2c2c2a; color:#fff; font-size:10px; padding:6px 9px; border-radius:5px; max-width:200px; z-index:100; white-space:pre-line;';
document.body.appendChild(statusTooltip);
var i18nReasonPrefix = @json(__('masterfile.reason_label'));
var i18nNotesPrefix = @json(__('masterfile.notes_label'));
triangle.addEventListener('mouseenter', function(e) {
    var text = i18nReasonPrefix + ': ' + (triangle.dataset.reason || '-');
    if (triangle.dataset.notes) text += '\n' + i18nNotesPrefix + ': ' + triangle.dataset.notes;
    statusTooltip.textContent = text;
    var rect = triangle.getBoundingClientRect();
    statusTooltip.style.left = (rect.left - 80) + 'px';
    statusTooltip.style.top = (rect.top - 50) + 'px';
    statusTooltip.style.display = 'block';
});
triangle.addEventListener('mouseleave', function() { statusTooltip.style.display = 'none'; });
</script>

<script>
function previewAdminPhoto(input) {
    if (!input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        var wrap = document.getElementById('photoPreviewWrap');
        wrap.innerHTML = '<img id="photoPreview" src="' + e.target.result + '" style="width:46px; height:46px; border-radius:5px; object-fit:cover; border:1.5px solid #B2EBF2;">';
    };
    reader.readAsDataURL(input.files[0]);
}
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('adminQrPreview'), {
    text: '{{ $qrUrl }}',
    width: 46,
    height: 46,
    colorDark: '#1565C0',
    colorLight: '#ffffff'
});
</script>

{{-- Request Undo route is genuinely Admin-only (no GL/TL equivalent
     exists in web.php), so gate visibility to match — otherwise GL/TL
     would see a working-looking modal that 403s on submit. --}}
@if($rolePrefix === 'admin' && $latestRoleHistory && !$hasPendingUndo)
<div id="undoBackdrop" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.35); z-index:999;" onclick="document.getElementById('undoModal').style.display='none'; this.style.display='none';"></div>
<div id="undoModal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); width:320px; background:#fff; border-radius:10px; padding:16px; box-shadow:0 10px 30px rgba(0,0,0,.25); z-index:1000;">
    <div style="font-size:12px; font-weight:700; color:#e53935; margin-bottom:6px;">{{ __('masterfile.request_undo') }}</div>
    <div style="font-size:10.5px; color:#4b5563; margin-bottom:10px;">{{ __('masterfile.undo_modal_text', ['name' => $agent->full_name, 'new_role' => $latestRoleHistory->new_role, 'old_role' => $latestRoleHistory->old_role]) }}</div>
    <form method="POST" action="{{ route('admin.masterfile.introducers.request-undo-role', $agent->agent_id) }}">
        @csrf
        <label style="font-size:10px; color:#374151; display:block; margin-bottom:3px;">{{ __('masterfile.reason_for_undo') }} <span style="color:#e53935;">*</span></label>
        <textarea name="notes" required rows="3" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; margin-bottom:10px; resize:vertical; box-sizing:border-box;"></textarea>
        <div style="display:flex; gap:8px;">
            <button type="submit" style="background:#e53935; color:#fff; border:none; border-radius:6px; padding:7px 16px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('masterfile.submit_request') }}</button>
            <div onclick="document.getElementById('undoModal').style.display='none'; document.getElementById('undoBackdrop').style.display='none';" style="background:#f3f4f6; color:#374151; border-radius:6px; padding:7px 16px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('masterfile.cancel') }}</div>
        </div>
    </form>
</div>
@endif
@endsection
