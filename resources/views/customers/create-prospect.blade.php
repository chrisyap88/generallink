@extends('layouts.dashboard')

@section('title', __('customers.add_prospect_title'))
@section('page-title', __('customers.add_prospect_title'))

{{-- NEW 19 Jul 2026 — per Chris: lets an agent (e.g. an Introducer)
     save their OWN personal contact/lead they're trying to close a
     sale with, before any policy exists. No NRIC required — that's
     only captured once a real Sales Transaction is submitted for them
     and they become a proper CUSTOMER-type record. --}}

@section('content')

<div style="padding:16px;">

<div style="margin-bottom:16px;">
    <a href="{{ route($rolePrefix . '.customers.index') }}" style="font-size:10.5px; color:#546E7A; text-decoration:none;">{{ __('gl.back_to_customers_link') }}</a>
    <p style="font-size:10.5px; color:#9ca3af; margin:2px 0 0;">{{ __('customers.prospect_intro_note') }}</p>
</div>

<div style="max-width:600px;">

    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:8px 12px; font-size:11.5px; color:#b71c1c; margin-bottom:14px;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    <div style="background:#fff; border-radius:12px; border:1px solid #B2EBF2; padding:20px; box-shadow:0 2px 8px rgba(0,150,200,0.06);">
        <form method="POST" action="{{ route($rolePrefix . '.customers.prospects.store') }}">
            @csrf

            <div style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
                <div style="flex:1 1 240px; min-width:200px;">
                    <label style="font-size:11px; font-weight:600; color:#546E7A; display:block; margin-bottom:5px;">{{ __('customers.field_full_name_required') }}</label>
                    <input type="text" name="full_name" value="{{ old('full_name') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box;">
                </div>
                <div style="flex:1 1 240px; min-width:200px;">
                    <label style="font-size:11px; font-weight:600; color:#546E7A; display:block; margin-bottom:5px;">{{ __('gl.field_phone_required') }}</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box;">
                </div>
            </div>

            <div style="margin-bottom:14px;">
                <label style="font-size:11px; font-weight:600; color:#546E7A; display:block; margin-bottom:5px;">{{ __('gl.field_email') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box;">
            </div>

            <div style="margin-bottom:14px;">
                <label style="font-size:11px; font-weight:600; color:#546E7A; display:block; margin-bottom:5px;">{{ __('gl.field_address') }}</label>
                <textarea name="address" rows="2" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box; resize:vertical;">{{ old('address') }}</textarea>
            </div>

            <div style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
                <div style="flex:0.7 1 120px; min-width:100px;">
                    <label style="font-size:11px; font-weight:600; color:#546E7A; display:block; margin-bottom:5px;">{{ __('gl.field_postcode') }}</label>
                    <input type="text" name="postcode" value="{{ old('postcode') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box;">
                </div>
                <div style="flex:1 1 160px; min-width:140px;">
                    <label style="font-size:11px; font-weight:600; color:#546E7A; display:block; margin-bottom:5px;">{{ __('gl.field_city') }}</label>
                    <input type="text" name="city" value="{{ old('city') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box;">
                </div>
                <div style="flex:1 1 160px; min-width:140px;">
                    <label style="font-size:11px; font-weight:600; color:#546E7A; display:block; margin-bottom:5px;">{{ __('gl.field_state') }}</label>
                    <select name="state" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box; background:#fff;">
                        <option value="">{{ __('gl.select_state_option') }}</option>
                        @foreach($states as $state)
                        <option value="{{ $state }}" {{ old('state')==$state?'selected':'' }}>{{ $state }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="display:flex; gap:10px;">
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 22px; font-size:12.5px; font-weight:700; cursor:pointer;">{{ __('customers.save_prospect_button') }}</button>
                <a href="{{ route($rolePrefix . '.customers.index') }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12.5px; font-weight:500; display:inline-flex; align-items:center;">{{ __('gl.cancel_button') }}</a>
            </div>
        </form>
    </div>
</div>

</div>

@endsection
