@extends('layouts.dashboard')
@section('title','My Profile')
@section('page-title','My Profile')

@section('content')

@if(session('success'))
<div style="background:#C6F6D5;border:1px solid #9AE6B4;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#22543D;font-size:13px">✓ {{ session('success') }}</div>
@endif
@if($errors->any())
<div style="background:#FED7D7;border:1px solid #FC8181;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#742A2A;font-size:13px">
    @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

    {{-- Profile info --}}
    <div class="card">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid #F7FAFC">
            <div style="width:60px;height:60px;border-radius:50%;background:#0D5A8E;display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;font-weight:700;flex-shrink:0">
                {{ strtoupper(substr($agent->full_name, 0, 2)) }}
            </div>
            <div>
                <div style="font-size:18px;font-weight:700;color:#1A202C">{{ $agent->full_name }}</div>
                <div style="font-size:13px;color:#718096">{{ $agent->email }}</div>
                <div style="margin-top:4px">
                    @php $roleColors = ['ADMIN'=>'#D97706','GROUP_LEADER'=>'#059669','TEAM_LEADER'=>'#0284C7','INTRODUCER'=>'#7C3AED']; @endphp
                    <span style="background:{{ $roleColors[$agent->role] ?? '#718096' }}20;color:{{ $roleColors[$agent->role] ?? '#718096' }};padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600">
                        {{ str_replace('_',' ',$agent->role) }}
                    </span>
                    <span class="status-badge status-{{ strtolower($agent->status) }}" style="margin-left:6px">{{ $agent->status }}</span>
                </div>
            </div>
        </div>

        <table style="width:100%;font-size:13px;border-collapse:collapse;margin-bottom:16px">
            @foreach([
                ['Member code', $agent->member_code ?? '—'],
                ['Agent code',  $agent->agent_code ?? '—'],
                ['NRIC',        $nricDecrypted],
                ['Commission balance', 'RM ' . number_format($agent->commission_balance, 2)],
                ['Reward points', number_format($pointsBalance, 0) . ' pts'],
                ['Account since', \Carbon\Carbon::parse($agent->created_at)->format('d M Y')],
            ] as [$lbl,$val])
            <tr style="border-bottom:1px solid #F7FAFC">
                <td style="padding:8px 0;color:#718096;width:140px;font-size:12px">{{ $lbl }}</td>
                <td style="padding:8px 0;font-weight:500">{{ $val }}</td>
            </tr>
            @endforeach
        </table>

        <a href="{{ route('profile.qr') }}"
            style="display:inline-flex;align-items:center;gap:6px;background:#EBF8FF;border:1px solid #BEE3F8;color:#2B6CB0;padding:8px 14px;border-radius:8px;font-size:13px;text-decoration:none;font-weight:600">
            <i class="ti ti-qrcode"></i> My Referral QR Code
        </a>
    </div>

    {{-- Edit profile --}}
    <div>
        <div class="card" style="margin-bottom:16px">
            <div class="card-title"><i class="ti ti-edit" style="color:#0D5A8E"></i> Edit Profile</div>
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf @method('PATCH')
                <div style="display:grid;gap:12px">
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Phone *</label>
                        <input type="text" name="phone" value="{{ $agent->phone }}" required
                            style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Bank name</label>
                        <select name="bank_name" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;background:#fff">
                            <option value="">— Select bank —</option>
                            @foreach(['Maybank','CIMB Bank','Public Bank','RHB Bank','Hong Leong Bank','AmBank','Bank Islam','Bank Rakyat','BSN','OCBC Bank','Standard Chartered','HSBC','UOB'] as $bank)
                            <option value="{{ $bank }}" {{ $agent->bank_name == $bank ? 'selected':'' }}>{{ $bank }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Bank account number</label>
                        <input type="text" name="bank_account" value="{{ $bankAccount }}" placeholder="Account number"
                            style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                    </div>

                    @if($agent->isAdmin())
                    <div style="background:#FFF8E6;border:1px solid #F6E05E;border-radius:8px;padding:12px;margin-top:4px">
                        <div style="font-size:12px;font-weight:600;color:#744210;margin-bottom:10px"><i class="ti ti-building-bank"></i> Admin bank account (for point purchases)</div>
                        <div style="display:grid;gap:10px">
                            <div>
                                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Admin bank name</label>
                                <select name="admin_bank_name" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;background:#fff">
                                    <option value="">— Select —</option>
                                    @foreach(['Maybank','CIMB Bank','Public Bank','RHB Bank','Hong Leong Bank','AmBank','Bank Islam','Bank Rakyat','BSN'] as $bank)
                                    <option value="{{ $bank }}" {{ $agent->admin_bank_name == $bank ? 'selected':'' }}>{{ $bank }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Admin bank account number</label>
                                <input type="text" name="admin_bank_account" value="{{ $adminBankAccount }}" placeholder="Account number"
                                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
                <button type="submit"
                    style="margin-top:14px;background:#0D5A8E;color:#fff;padding:9px 22px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
                    Save Changes
                </button>
            </form>
        </div>

        {{-- Change password --}}
        <div class="card">
            <div class="card-title"><i class="ti ti-lock" style="color:#0D5A8E"></i> Change Password</div>
            <form method="POST" action="{{ route('profile.password') }}">
                @csrf @method('PATCH')
                <div style="display:grid;gap:12px">
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Current password *</label>
                        <input type="password" name="current_password" required
                            style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">New password *</label>
                        <input type="password" name="new_password" required
                            style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                        <p style="font-size:11px;color:#A0AEC0;margin-top:3px">Min 10 chars · uppercase · lowercase · number · special character</p>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Confirm new password *</label>
                        <input type="password" name="new_password_confirmation" required
                            style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                    </div>
                </div>
                <button type="submit"
                    style="margin-top:14px;background:#0D5A8E;color:#fff;padding:9px 22px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
                    Change Password
                </button>
            </form>
        </div>
    </div>
</div>

@endsection
