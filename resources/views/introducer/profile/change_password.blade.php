@extends('layouts.dashboard')

@section('page-title', 'Change Password')

@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; display:flex; align-items:center; justify-content:center; padding:12px;">

    <div style="background:#fff; border-radius:12px; padding:32px; box-shadow:0 2px 8px rgba(0,0,0,.1); width:100%; max-width:440px;">

        {{-- Success State --}}
        @if(session('password_success'))
        <div style="text-align:center;">
            <div style="font-size:48px; margin-bottom:16px;">✅</div>
            <div style="font-size:18px; font-weight:700; color:#16a34a; margin-bottom:8px;">Password Changed Successfully!</div>
            <div style="font-size:13px; color:#6b7280; margin-bottom:32px;">Your password has been updated. What would you like to do next?</div>
            <div style="display:flex; gap:12px; justify-content:center;">
                <a href="{{ route('introducer.profile.show') }}"
                   style="background:#1565C0; color:#fff; text-decoration:none; border-radius:8px; padding:10px 24px; font-size:13px; font-weight:600;">
                    ✅ Yes, Back to Profile
                </a>
                <a href="{{ route('introducer.profile.change-password') }}"
                   style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:8px; padding:10px 24px; font-size:13px; font-weight:600;">
                    🔄 Change Again
                </a>
            </div>
        </div>

        {{-- Form State --}}
        @else
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
            <div>
                <div style="font-size:16px; font-weight:700; color:#111827;">🔒 Change Password</div>
                <div style="font-size:11px; color:#6b7280; margin-top:2px;">{{ \App\Services\RoleLabelService::label('INTRODUCER') }}</div>
            </div>
            <a href="{{ route('introducer.profile.show') }}"
               style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:7px 16px; font-size:12px; font-weight:500;">
                ← Cancel
            </a>
        </div>

        @if($errors->has('current_password'))
        <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:8px; padding:10px 14px; margin-bottom:16px; font-size:12px; color:#991b1b;">
            ⚠ {{ $errors->first('current_password') }}
        </div>
        @endif

        @if($errors->has('new_password'))
        <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:8px; padding:10px 14px; margin-bottom:16px; font-size:12px; color:#991b1b;">
            ⚠ {{ $errors->first('new_password') }}
        </div>
        @endif

        <form method="POST" action="{{ route('introducer.profile.password') }}" autocomplete="off">
            @csrf
            @method('PUT')
            <input type="text" style="display:none;" name="fake_user">
            <input type="password" style="display:none;" name="fake_pass">

            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">
                    Current Password <span style="color:#dc2626;">*</span>
                </label>
                <div style="position:relative;">
                    <input type="password" name="current_password" id="cur_pw"
                        autocomplete="new-password"
                        placeholder="Enter your current password"
                        style="width:100%; border:1px solid {{ $errors->has('current_password') ? '#f87171' : '#d1d5db' }}; border-radius:8px; padding:10px 40px 10px 12px; font-size:13px; outline:none; box-sizing:border-box;">
                    <button type="button" onclick="togglePw('cur_pw', 'eye1')"
                        style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:16px; color:#9ca3af; padding:0;">
                        <span id="eye1">👁</span>
                    </button>
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">
                    New Password <span style="color:#dc2626;">*</span>
                </label>
                <div style="position:relative;">
                    <input type="password" name="new_password" id="new_pw"
                        autocomplete="new-password"
                        placeholder="Minimum 8 characters"
                        style="width:100%; border:1px solid {{ $errors->has('new_password') ? '#f87171' : '#d1d5db' }}; border-radius:8px; padding:10px 40px 10px 12px; font-size:13px; outline:none; box-sizing:border-box;">
                    <button type="button" onclick="togglePw('new_pw', 'eye2')"
                        style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:16px; color:#9ca3af; padding:0;">
                        <span id="eye2">👁</span>
                    </button>
                </div>
            </div>

            <div style="margin-bottom:24px;">
                <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">
                    Confirm New Password <span style="color:#dc2626;">*</span>
                </label>
                <div style="position:relative;">
                    <input type="password" name="new_password_confirmation" id="con_pw"
                        autocomplete="new-password"
                        placeholder="Re-enter new password"
                        style="width:100%; border:1px solid #d1d5db; border-radius:8px; padding:10px 40px 10px 12px; font-size:13px; outline:none; box-sizing:border-box;">
                    <button type="button" onclick="togglePw('con_pw', 'eye3')"
                        style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:16px; color:#9ca3af; padding:0;">
                        <span id="eye3">👁</span>
                    </button>
                </div>
            </div>

            <div style="display:flex; gap:12px;">
                <button type="submit"
                    style="flex:1; background:#1565C0; color:#fff; border:none; border-radius:8px; padding:11px 16px; font-size:13px; font-weight:600; cursor:pointer;">
                    🔒 Change Password
                </button>
                <a href="{{ route('introducer.profile.show') }}"
                   style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:8px; padding:11px 20px; font-size:13px; font-weight:500; white-space:nowrap;">
                    Cancel
                </a>
            </div>
        </form>
        @endif

    </div>
</div>

@push('scripts')
<script>
function togglePw(inputId, eyeId) {
    var input = document.getElementById(inputId);
    var eye = document.getElementById(eyeId);
    if (input.type === 'password') {
        input.type = 'text';
        eye.textContent = '🙈';
    } else {
        input.type = 'password';
        eye.textContent = '👁';
    }
}
</script>
@endpush

@endsection
