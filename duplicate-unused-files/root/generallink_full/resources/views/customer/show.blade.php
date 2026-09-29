@extends('layouts.dashboard')
@section('title','Add Customer')
@section('page-title','Add New Customer')

@section('content')

<div style="max-width:700px">
<div class="card">
    <div class="card-title"><i class="ti ti-user-plus" style="color:#0D5A8E"></i> New Customer</div>

    <form method="POST" action="{{ route('customers.store') }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">

            <div style="grid-column:span 2">
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Full name (as per MyKad) *</label>
                <input type="text" name="full_name" value="{{ old('full_name') }}" required
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                @error('full_name')<p style="color:#E53E3E;font-size:11px;margin-top:3px">{{ $message }}</p>@enderror
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">NRIC (12 digits, no dashes) *</label>
                <input type="text" name="nric" value="{{ old('nric') }}" maxlength="12" pattern="[0-9]{12}" required
                    placeholder="e.g. 900101015678"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;font-family:monospace">
                @error('nric')<p style="color:#E53E3E;font-size:11px;margin-top:3px">{{ $message }}</p>@enderror
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Phone *</label>
                <input type="text" name="phone" value="{{ old('phone') }}" required placeholder="+601X-XXXXXXXX"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                @error('phone')<p style="color:#E53E3E;font-size:11px;margin-top:3px">{{ $message }}</p>@enderror
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="customer@email.com"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Postcode</label>
                <input type="text" name="postcode" value="{{ old('postcode') }}" maxlength="5" placeholder="e.g. 41000"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">City</label>
                <input type="text" name="city" value="{{ old('city') }}"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">State</label>
                <select name="state" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;background:#fff">
                    <option value="">— Select state —</option>
                    @foreach(['Johor','Kedah','Kelantan','Melaka','Negeri Sembilan','Pahang','Penang','Perak','Perlis','Sabah','Sarawak','Selangor','Terengganu','Kuala Lumpur','Labuan','Putrajaya'] as $state)
                    <option value="{{ $state }}" {{ old('state') == $state ? 'selected':'' }}>{{ $state }}</option>
                    @endforeach
                </select>
            </div>

            <div style="grid-column:span 2">
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Full address</label>
                <textarea name="address" rows="3"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;resize:vertical">{{ old('address') }}</textarea>
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:18px">
            <button type="submit"
                style="background:#0D5A8E;color:#fff;padding:9px 22px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
                <i class="ti ti-user-plus"></i> Create Customer
            </button>
            <a href="{{ route('customers.index') }}"
                style="padding:9px 18px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;color:#4A5568;text-decoration:none">
                Cancel
            </a>
        </div>
    </form>
</div>
</div>

@endsection
