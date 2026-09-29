@extends('layouts.dashboard')

@section('title', __('gl.edit_customer_title'))
@section('page-title', __('gl.edit_customer_title'))

@section('content')

<div class="d-flex justify-content:space-between align-items-center mb-4">
    <div>
        <a href="{{ route('gl.customers.show', $customer->customer_id) }}" style="font-size:12px;color:#546E7A;text-decoration:none;">
            {{ __('gl.back_to_customer_link') }}
        </a>
        <h4 class="fw-bold mb-0 mt-1" style="color:#1565C0;">{{ __('gl.edit_dash_name', ['name' => $customer->full_name]) }}</h4>
    </div>
</div>

<div style="max-width:600px;">

    {{-- Info notice --}}
    <div style="background:#E6F1FB;border:0.5px solid #85B7EB;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:12px;color:#0C447C;">
        <i class="ti ti-info-circle"></i>
        &nbsp;{!! __('gl.update_contact_note') !!}
    </div>

    <div style="background:#fff;border-radius:12px;border:1px solid #B2EBF2;padding:24px;box-shadow:0 2px 8px rgba(0,150,200,0.06);">

        @if($errors->any())
        <div class="alert alert-danger mb-4">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                <li style="font-size:13px;">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Locked fields --}}
        <div style="margin-bottom:20px;padding-bottom:20px;border-bottom:0.5px solid #f0f4f8;">
            <p style="font-size:11px;font-weight:700;color:#A32D2D;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;">
                <i class="ti ti-lock"></i> {{ __('gl.locked_contact_admin_note') }}
            </p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#546E7A;display:block;margin-bottom:6px;">{{ __('gl.field_full_name') }}</label>
                    <input type="text" class="form-control" value="{{ $customer->full_name }}" disabled
                        style="background:#f8f9fa;color:#999;cursor:not-allowed;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#546E7A;display:block;margin-bottom:6px;">{{ __('gl.field_nric') }}</label>
                    <input type="text" class="form-control" value="••••••••••••" disabled
                        style="background:#f8f9fa;color:#999;cursor:not-allowed;">
                </div>
            </div>
        </div>

        {{-- Editable fields --}}
        <form method="POST" action="{{ route('gl.customers.update', $customer->customer_id) }}">
            @csrf
            @method('PUT')

            <p style="font-size:11px;font-weight:700;color:#2e7d32;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;">
                <i class="ti ti-edit"></i> {{ __('gl.editable_fields_note') }}
            </p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#546E7A;display:block;margin-bottom:6px;">{{ __('gl.field_phone_required') }}</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $customer->phone) }}" required>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#546E7A;display:block;margin-bottom:6px;">{{ __('gl.field_email') }}</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $customer->email) }}">
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label style="font-size:12px;font-weight:600;color:#546E7A;display:block;margin-bottom:6px;">{{ __('gl.field_address') }}</label>
                <textarea name="address" class="form-control" rows="2">{{ old('address', $customer->address) }}</textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:24px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#546E7A;display:block;margin-bottom:6px;">{{ __('gl.field_postcode') }}</label>
                    <input type="text" name="postcode" class="form-control" value="{{ old('postcode', $customer->postcode) }}">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#546E7A;display:block;margin-bottom:6px;">{{ __('gl.field_city') }}</label>
                    <input type="text" name="city" class="form-control" value="{{ old('city', $customer->city) }}">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#546E7A;display:block;margin-bottom:6px;">{{ __('gl.field_state') }}</label>
                    <select name="state" class="form-select">
                        <option value="">{{ __('gl.select_state_option') }}</option>
                        @foreach(['Johor','Kedah','Kelantan','Melaka','Negeri Sembilan','Pahang','Perak','Perlis','Pulau Pinang','Sabah','Sarawak','Selangor','Terengganu','Kuala Lumpur','Labuan','Putrajaya'] as $state)
                        <option value="{{ $state }}" {{ old('state', $customer->state)==$state?'selected':'' }}>{{ $state }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary">{{ __('gl.save_changes_button') }}</button>
                <a href="{{ route('gl.customers.show', $customer->customer_id) }}" class="btn btn-outline-secondary">{{ __('gl.cancel_button') }}</a>
            </div>
        </form>
    </div>
</div>

@endsection
