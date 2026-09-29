@extends('layouts.dashboard')

@section('page-title', 'Edit Profile')

@section('content')
<div style="height:calc(100vh - 66px); overflow:auto; padding:8px 12px 60px;">

    <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px;">
        <a href="{{ route('gl.profile.show') }}" style="color:#6b7280; text-decoration:none; font-size:12px;">← Back to Profile</a>
        <div style="color:#d1d5db;">|</div>
        <div style="font-size:15px; font-weight:700; color:#111827;">Edit Profile</div>
    </div>

    <div style="background:#dbeafe; border:1px solid #93c5fd; border-radius:8px; padding:10px 14px; margin-bottom:16px; display:flex; gap:8px; align-items:flex-start;">
        <span style="font-size:14px; flex-shrink:0;">ℹ️</span>
        <div style="font-size:12px; color:#1e40af;">
            <strong>Phone, email, bank name and address can be edited freely.</strong>
            Full Name and NRIC are permanently locked.
        </div>
    </div>

    <form method="POST" action="{{ route('gl.profile.update') }}" style="max-width:560px;">
        @csrf
        @method('PUT')

        @if($errors->any())
        <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:8px; padding:10px 14px; margin-bottom:14px;">
            @foreach($errors->all() as $error)
                <div style="font-size:12px; color:#991b1b; margin-bottom:3px;">⚠ {{ $error }}</div>
            @endforeach
        </div>
        @endif

        {{-- Personal Details --}}
        <div style="background:#fff; border-radius:10px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,.08); margin-bottom:12px;">
            <div style="font-size:13px; font-weight:600; color:#374151; margin-bottom:14px;">Personal Details</div>

            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:11px; font-weight:600; color:#9ca3af; margin-bottom:4px;">Full Name <span style="color:#dc2626; font-size:10px;">🔒 Locked</span></label>
                <input type="text" value="{{ $agent->full_name }}" disabled style="width:100%; border:1px solid #e5e7eb; border-radius:6px; padding:7px 10px; font-size:12px; background:#f9fafb; color:#9ca3af; box-sizing:border-box; cursor:not-allowed;">
            </div>

            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:11px; font-weight:600; color:#9ca3af; margin-bottom:4px;">NRIC <span style="color:#dc2626; font-size:10px;">🔒 Permanently Locked</span></label>
                <input type="text" value="••••••••••••" disabled style="width:100%; border:1px solid #e5e7eb; border-radius:6px; padding:7px 10px; font-size:12px; background:#f9fafb; color:#9ca3af; box-sizing:border-box; cursor:not-allowed;">
            </div>

            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">Phone <span style="color:#dc2626;">*</span></label>
                <input type="text" name="phone" value="{{ old('phone', $agent->phone) }}" required style="width:100%; border:1px solid {{ $errors->has('phone') ? '#f87171' : '#d1d5db' }}; border-radius:6px; padding:7px 10px; font-size:12px; outline:none; box-sizing:border-box;">
                @error('phone') <div style="color:#dc2626; font-size:11px; margin-top:3px;">{{ $message }}</div> @enderror
            </div>

            <div>
                <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">Email <span style="color:#dc2626;">*</span></label>
                <input type="email" name="email" value="{{ old('email', $agent->email) }}" required style="width:100%; border:1px solid {{ $errors->has('email') ? '#f87171' : '#d1d5db' }}; border-radius:6px; padding:7px 10px; font-size:12px; outline:none; box-sizing:border-box;">
                @error('email') <div style="color:#dc2626; font-size:11px; margin-top:3px;">{{ $message }}</div> @enderror
            </div>
        </div>

        {{-- Address Information --}}
        <div style="background:#fff; border-radius:10px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,.08); margin-bottom:12px;">
            <div style="font-size:13px; font-weight:600; color:#374151; margin-bottom:14px;">Address Information</div>

            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">Address</label>
                <textarea name="address" rows="2" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; outline:none; box-sizing:border-box; resize:none;">{{ old('address', $profile->address ?? '') }}</textarea>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                <div>
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">City</label>
                    <input type="text" name="city" value="{{ old('city', $profile->city ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; outline:none; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">Postcode</label>
                    <input type="text" name="postcode" value="{{ old('postcode', $profile->postcode ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; outline:none; box-sizing:border-box;">
                </div>
            </div>

            <div>
                <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">State</label>
                <select name="state" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; background:#fff; outline:none; box-sizing:border-box;">
                    <option value="">— Select State —</option>
                    @foreach($states as $state)
                        <option value="{{ $state }}" {{ old('state', $profile->state ?? '') === $state ? 'selected' : '' }}>{{ $state }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Bank Information --}}
        <div style="background:#fff; border-radius:10px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,.08); margin-bottom:18px;">
            <div style="font-size:13px; font-weight:600; color:#374151; margin-bottom:14px;">Bank Information</div>
            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:4px;">Bank Name</label>
                <select name="bank_name" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; background:#fff; outline:none; box-sizing:border-box;">
                    <option value="">— Select Bank —</option>
                    @foreach($banks as $bank)
                        <option value="{{ $bank }}" {{ old('bank_name', $agent->bank_name) === $bank ? 'selected' : '' }}>{{ $bank }}</option>
                    @endforeach
                </select>
            </div>
            <div style="background:#f9fafb; border-radius:6px; padding:8px 10px; font-size:11px; color:#6b7280;">
                🔒 Bank account number is encrypted and can only be changed by Admin.
            </div>
        </div>

        <div style="display:flex; gap:10px;">
            <button type="submit" style="background:#2563eb; color:#fff; border:none; border-radius:6px; padding:9px 24px; font-size:12px; font-weight:600; cursor:pointer;">
                💾 Save Changes
            </button>
            <a href="{{ route('gl.profile.show') }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:9px 18px; font-size:12px; font-weight:500;">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
