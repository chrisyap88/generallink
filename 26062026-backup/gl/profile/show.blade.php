@extends('layouts.dashboard')

@section('page-title', 'My Profile')

@section('content')
<div style="padding:10px 14px; height:calc(100vh - 66px); overflow:hidden; display:flex; flex-direction:column; gap:8px;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:6px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0;">
        ✅ {{ session('success') }}
    </div>
    @endif

    {{-- 3-column grid --}}
    <div style="display:grid; grid-template-columns:2fr 1.2fr 1.2fr; gap:8px; flex:1; min-height:0;">

        {{-- Col 1: Personal Info + Address + Bank --}}
        <div style="display:flex; flex-direction:column; gap:8px; min-height:0;">
            <div style="background:#fff; border-radius:10px; padding:12px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; overflow:hidden;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; padding-bottom:4px; border-bottom:2px solid #e0f2fe;">
                    <span style="font-size:12px; font-weight:700; color:#1565C0;">Personal Information</span>
                    <a href="{{ route('gl.profile.edit') }}" title="Edit" style="color:#2563eb; text-decoration:none; font-size:16px;">✏️</a>
                </div>
                @php
                function glPRow($label, $value) {
                    $display = ($value !== null && $value !== '') ? $value : '—';
                    echo "<div style='display:flex; align-items:center; padding:5px 0; border-bottom:1px solid #f3f4f6;'>
                        <div style='width:100px; font-size:11px; color:#6b7280; font-weight:500; flex-shrink:0;'>$label</div>
                        <div style='font-size:11px; color:#111827; flex:1;'>" . htmlspecialchars($display) . "</div>
                    </div>";
                }
                @endphp
                @php
                    $nricFormatted = ($nric && strlen(preg_replace('/\D/', '', $nric)) === 12)
                        ? substr(preg_replace('/\D/', '', $nric), 0, 6) . '-' . substr(preg_replace('/\D/', '', $nric), 6, 2) . '-' . substr(preg_replace('/\D/', '', $nric), 8)
                        : ($nric ?: '—');
                @endphp
                {!! glPRow('Full Name', $agent->full_name) !!}
                {!! glPRow('IC Number', $nricFormatted) !!}
                {!! glPRow('Email', $agent->email) !!}
                {!! glPRow('Phone', $agent->phone) !!}
                {!! glPRow('Agent Code', $agent->agent_code) !!}
                {!! glPRow('Member Code', $agent->member_code) !!}
                {!! glPRow('Role', 'Group Leader') !!}
                {!! glPRow('Status', $agent->status) !!}
                {!! glPRow('Joined', $agent->created_at ? \Carbon\Carbon::parse($agent->created_at)->format('d M Y') : '—') !!}
            </div>
            <div style="background:#fff; border-radius:10px; padding:12px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; overflow:hidden;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; padding-bottom:4px; border-bottom:2px solid #e0f2fe;">
                    <span style="font-size:12px; font-weight:700; color:#1565C0;">Address & Bank</span>
                    <a href="{{ route('gl.profile.edit') }}" title="Edit" style="color:#2563eb; text-decoration:none; font-size:16px;">✏️</a>
                </div>
                {!! glPRow('Address', $profile->address ?? null) !!}
                {!! glPRow('Postcode', $profile->postcode ?? null) !!}
                {!! glPRow('City', $profile->city ?? null) !!}
                {!! glPRow('State', $profile->state ?? null) !!}
                {!! glPRow('Bank', $agent->bank_name) !!}
                <div style="display:flex; align-items:center; padding:5px 0; border-bottom:1px solid #f3f4f6;">
                    <div style="width:100px; font-size:11px; color:#6b7280; font-weight:500; flex-shrink:0;">Account No.</div>
                    <div style="font-size:11px; color:#9ca3af; flex:1;">•••••••••• 🔒</div>
                </div>
            </div>
        </div>

        {{-- Col 2: Change Password Button + Beneficiaries --}}
        <div style="display:flex; flex-direction:column; gap:8px; min-height:0;">
            <div style="background:#fff; border-radius:10px; padding:12px; box-shadow:0 1px 3px rgba(0,0,0,.08); display:flex; flex-direction:column; justify-content:center; align-items:center; gap:12px; flex:0 0 auto;">
                <div style="text-align:center;">
                    <div style="font-size:36px; margin-bottom:6px;">🔒</div>
                    <div style="font-size:12px; font-weight:600; color:#374151; margin-bottom:4px;">Change Password</div>
                    <div style="font-size:10px; color:#6b7280; margin-bottom:14px;">Keep your account secure.</div>
                    <a href="{{ route('gl.profile.change-password') }}"
                       style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600; display:inline-block;">
                        🔒 Change Password
                    </a>
                </div>
            </div>
            <div style="background:#fff; border-radius:10px; padding:12px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; overflow-y:auto;">
                <div style="font-size:12px; font-weight:700; color:#1565C0; margin-bottom:8px; padding-bottom:4px; border-bottom:2px solid #e0f2fe;">Beneficiaries</div>
                @forelse($beneficiaries as $b)
                <div style="background:#f9fafb; border-radius:6px; padding:8px 10px; margin-bottom:6px;">
                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                        <div><span style="font-size:9px; color:#6b7280;">Priority</span><br><span style="font-size:13px; font-weight:700; color:#2563eb;">{{ $b->priority_order }}</span></div>
                        <div><span style="font-size:9px; color:#6b7280;">Name</span><br><span style="font-size:11px; font-weight:600; color:#374151;">{{ $b->full_name }}</span></div>
                        <div><span style="font-size:9px; color:#6b7280;">Relationship</span><br><span style="font-size:11px; color:#374151;">{{ $b->relationship }}</span></div>
                        <div><span style="font-size:9px; color:#6b7280;">Phone</span><br><span style="font-size:11px; color:#374151;">{{ $b->phone ?? '—' }}</span></div>
                    </div>
                </div>
                @empty
                <div style="color:#9ca3af; font-size:11px; text-align:center; padding:16px 0;">No beneficiaries. Contact Admin.</div>
                @endforelse
            </div>
        </div>

        {{-- Col 3: Profile History --}}
        <div style="background:#fff; border-radius:10px; padding:12px; box-shadow:0 1px 3px rgba(0,0,0,.08); display:flex; flex-direction:column; min-height:0;">
            <div style="font-size:12px; font-weight:700; color:#1565C0; margin-bottom:8px; padding-bottom:4px; border-bottom:2px solid #e0f2fe; flex-shrink:0;">📋 Profile History</div>
            <div style="flex:1; overflow-y:auto; min-height:0;">
                <div style="display:flex; gap:8px; margin-bottom:10px;">
                    <div style="display:flex; flex-direction:column; align-items:center; flex-shrink:0;">
                        <div style="width:8px; height:8px; border-radius:50%; background:#16a34a; margin-top:2px;"></div>
                        <div style="width:1px; flex:1; background:#e5e7eb; margin-top:3px;"></div>
                    </div>
                    <div style="padding-bottom:10px;">
                        <div style="font-size:11px; font-weight:600; color:#374151;">Account Created</div>
                        <div style="font-size:10px; color:#6b7280;">{{ $agent->created_at ? \Carbon\Carbon::parse($agent->created_at)->format('d M Y, h:i A') : '—' }}</div>
                        <div style="font-size:10px; color:#16a34a;">Role: Group Leader</div>
                    </div>
                </div>
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
                        'PROFILE_UPDATE'  => 'Profile Updated',
                        'PASSWORD_CHANGE' => 'Password Changed',
                        'ROLE_CHANGE'     => 'Role Changed',
                        'STATUS_CHANGE'   => 'Status Changed',
                        default           => $log->action,
                    };
                @endphp
                <div style="display:flex; gap:8px; margin-bottom:10px;">
                    <div style="display:flex; flex-direction:column; align-items:center; flex-shrink:0;">
                        <div style="width:8px; height:8px; border-radius:50%; background:{{ $actionColor }}; margin-top:2px;"></div>
                        <div style="width:1px; flex:1; background:#e5e7eb; margin-top:3px;"></div>
                    </div>
                    <div style="padding-bottom:10px;">
                        <div style="font-size:11px; font-weight:600; color:#374151;">{{ $actionLabel }}</div>
                        <div style="font-size:10px; color:#6b7280;">{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y, h:i A') }}</div>
                    </div>
                </div>
                @empty
                <div style="color:#9ca3af; font-size:11px; text-align:center; padding:20px 0;">No history yet.</div>
                @endforelse
            </div>
        </div>

    </div>
</div>
@endsection
