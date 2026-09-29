@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('network.edit_agent_title'))
@section('content')
<div style="height:calc(100vh - 66px); overflow-y:auto; padding:8px 12px;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; margin-bottom:8px;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; margin-bottom:8px;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    {{-- Header --}}
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:14px 18px; margin-bottom:10px; display:flex; align-items:center; justify-content:space-between;">
        <div style="display:flex; align-items:center; gap:14px;">
            <div style="width:48px; height:48px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:18px; font-weight:700; flex-shrink:0;
                background:{{ $agent->role === 'GROUP_LEADER' ? 'linear-gradient(135deg,#38A169,#0D5A8E)' : ($agent->role === 'TEAM_LEADER' ? 'linear-gradient(135deg,#1B9AE4,#0D5A8E)' : 'linear-gradient(135deg,#7C3AED,#4C1D95)') }};">
                {{ strtoupper(substr($agent->full_name,0,2)) }}
            </div>
            <div>
                <div style="font-size:16px; font-weight:700; color:#0D5A8E;">{{ __('network.edit_dash_name', ['name' => $agent->full_name]) }}</div>
                <div style="display:flex; gap:8px; margin-top:4px;">
                    <span style="font-size:11px; font-weight:700; padding:2px 10px; border-radius:20px;
                        background:{{ $agent->role === 'GROUP_LEADER' ? '#C8E6C9' : ($agent->role === 'TEAM_LEADER' ? '#BBDEFB' : '#E9D5FF') }};
                        color:{{ $agent->role === 'GROUP_LEADER' ? '#1B5E20' : ($agent->role === 'TEAM_LEADER' ? '#1565C0' : '#4C1D95') }};">
                        {{ __('network.role_'.strtolower($agent->role)) }}
                    </span>
                    <span style="font-size:11px; color:#718096;">{{ $agent->agent_code }}</span>
                    <span style="font-size:11px; color:#718096;">{{ $agent->member_code }}</span>
                </div>
            </div>
        </div>
        <a href="{{ $back }}" style="font-size:11px; color:#6b7280; text-decoration:none; background:#f3f4f6; padding:6px 14px; border-radius:6px;">{{ __('network.back') }}</a>
    </div>

    {{-- Read-only info banner --}}
    <div style="background:#fffbeb; border:1px solid #fcd34d; border-radius:8px; padding:8px 14px; margin-bottom:10px; font-size:11px; color:#92400e;">
        ⚠ {!! __('network.system_controlled_note', ['fields' => '<strong>'.__('network.system_controlled_fields_label').'</strong> ('.__('network.system_controlled_fields_list').')']) !!}
    </div>

    <form method="POST" action="{{ route('admin.network.agent.update', $agent->agent_id) }}" autocomplete="off">
        @csrf @method('PUT')
        <input type="hidden" name="back" value="{{ $back }}">

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">

            {{-- LEFT: Personal Details --}}
            <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:14px 16px;">
                <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:12px; padding-bottom:6px; border-bottom:1px solid #e0f2fe;">{{ __('network.personal_details_heading') }}</div>
                <div style="display:flex; flex-direction:column; gap:10px;">

                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('network.full_name') }} <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="full_name" value="{{ old('full_name', $agent->full_name) }}" required maxlength="200"
                            style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>

                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('network.name_other_language') }}</label>
                        <input type="text" name="second_name" value="{{ old('second_name', $agent->second_name) }}" maxlength="200" placeholder="{{ __('network.optional_placeholder') }}"
                            style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>

                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('network.email') }} <span style="color:#ef4444;">*</span></label>
                        <input type="email" name="email" value="{{ old('email', $agent->email) }}" required maxlength="200"
                            style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>

                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('network.phone') }} <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="phone" value="{{ old('phone', $agent->phone) }}" required maxlength="20"
                            style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>

                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('network.status_field_label') }} <span style="color:#ef4444;">*</span></label>
                        <select name="status" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                            @foreach(['ACTIVE','INACTIVE','TERMINATED','RISK_DEBT','RESIGNED','DECEASED'] as $s)
                            <option value="{{ $s }}" {{ old('status', $agent->status) === $s ? 'selected' : '' }}>{{ __('network.'.strtolower($s)) }}</option>
                            @endforeach
                        </select>
                        @if($agent->role !== 'GROUP_LEADER')
                        <div style="font-size:10px; color:#6b7280; margin-top:2px;">{{ __('network.role_promotion_note') }}</div>
                        @endif
                    </div>

                    {{-- Read-only fields --}}
                    <div style="background:#f9fafb; border-radius:6px; padding:10px; border:1px solid #e5e7eb;">
                        <div style="font-size:10px; font-weight:600; color:#9ca3af; margin-bottom:6px;">{{ __('network.read_only_system_controlled') }}</div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                            <div>
                                <div style="font-size:10px; color:#9ca3af;">{{ __('network.role_label') }}</div>
                                <div style="font-size:11px; font-weight:600; color:#374151;">{{ __('network.role_'.strtolower($agent->role)) }}</div>
                            </div>
                            <div>
                                <div style="font-size:10px; color:#9ca3af;">{{ __('network.agent_code_label') }}</div>
                                <div style="font-size:11px; font-weight:600; color:#374151; font-family:monospace;">{{ $agent->agent_code ?? '—' }}</div>
                            </div>
                            <div>
                                <div style="font-size:10px; color:#9ca3af;">{{ __('network.member_code_label') }}</div>
                                <div style="font-size:11px; font-weight:600; color:#374151; font-family:monospace;">{{ $agent->member_code ?? '—' }}</div>
                            </div>
                            <div>
                                <div style="font-size:10px; color:#9ca3af;">{{ __('network.joined_label') }}</div>
                                <div style="font-size:11px; color:#374151;">{{ \Carbon\Carbon::parse($agent->created_at)->format('d M Y') }}</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- RIGHT: Bank + Group (GL only) --}}
            <div style="display:flex; flex-direction:column; gap:10px;">

                {{-- Bank Details --}}
                <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:14px 16px;">
                    <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:12px; padding-bottom:6px; border-bottom:1px solid #e0f2fe;">{{ __('network.bank_details_heading') }}</div>
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <div>
                            <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('network.bank_name_label') }}</label>
                            <input type="text" name="bank_name" value="{{ old('bank_name', $agent->bank_name) }}" maxlength="100" placeholder="{{ __('network.bank_name_placeholder') }}"
                                style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                        </div>
                        <div style="background:#f9fafb; border-radius:6px; padding:10px; border:1px solid #e5e7eb;">
                            <div style="font-size:10px; font-weight:600; color:#9ca3af; margin-bottom:4px;">{{ __('network.read_only_encrypted') }}</div>
                            <div style="font-size:10px; color:#9ca3af;">{{ __('network.bank_account_encrypted_note') }}</div>
                        </div>
                    </div>
                </div>

                {{-- Group Details (GL only) --}}
                @if($agent->role === 'GROUP_LEADER' && $group)
                <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:14px 16px;">
                    <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:12px; padding-bottom:6px; border-bottom:1px solid #e0f2fe;">{{ __('network.group_details_heading') }}</div>
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <div style="background:#f9fafb; border-radius:6px; padding:10px; border:1px solid #e5e7eb;">
                            <div style="font-size:10px; font-weight:600; color:#9ca3af; margin-bottom:6px;">{{ __('network.read_only_system_controlled') }}</div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                                <div>
                                    <div style="font-size:10px; color:#9ca3af;">{{ __('network.group_name_label') }}</div>
                                    <div style="font-size:11px; font-weight:600; color:#374151;">{{ $group->group_name }}</div>
                                </div>
                                <div>
                                    <div style="font-size:10px; color:#9ca3af;">{{ __('network.group_code_label') }}</div>
                                    <div style="font-size:11px; font-weight:600; color:#374151; font-family:monospace;">{{ $group->group_code }}</div>
                                </div>
                                <div>
                                    <div style="font-size:10px; color:#9ca3af;">{{ __('network.separator_label') }}</div>
                                    <div style="font-size:11px; font-weight:700; color:#1565C0; font-family:monospace;">{{ $group->separator_char }}</div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('network.group_email_label') }} <span style="color:#6b7280;">{{ __('network.editable_hint') }}</span></label>
                            <input type="email" name="group_email" value="{{ old('group_email', $group->group_email) }}" maxlength="200"
                                style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                        </div>
                    </div>
                </div>
                @endif

                {{-- Submit --}}
                <div style="display:flex; gap:8px;">
                    <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:10px 28px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('network.save_changes_button') }}</button>
                    <a href="{{ $back }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:10px 20px; font-size:12px; font-weight:600;">{{ __('network.cancel') }}</a>
                </div>

            </div>
        </div>
    </form>
</div>
@endsection
