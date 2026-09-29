@extends('layouts.dashboard')

@section('title', __('gl.edit_customer_title'))
@section('page-title', __('gl.edit_customer_title'))

@section('content')

{{-- REBUILT 19 Jul 2026 — per Chris: the old plain form-style Edit
     screen didn't match the Profile tab layout he's used to, and
     Classification (Status/Type/Category/Occupation/Source) was
     Admin-only with no way for the owning agent to set it. Rebuilt to
     reuse the same box layout as the Customer Detail Profile tab
     (Contact / Address / Classification / Identity boxes, 2-column
     grid), and Classification is now editable by whoever can see this
     record — every field there is a dropdown of the live master-file
     records, never free text. Only Full Name and NRIC stay Admin-locked
     (identity/duplicate-matching integrity). --}}
{{-- COMPACTED 19 Jul 2026 per Chris ("all screen no scroll"): shrunk
     header/box padding and field spacing throughout, and turned the
     5-field Classification box into a 2-column mini-grid (3 rows
     instead of 5) so the whole form fits in one screen with no
     scrolling on a normal laptop window. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:0 16px; box-sizing:border-box;">

<div style="flex-shrink:0;">
    <a href="{{ route($rolePrefix . '.customers.show', $customer->customer_id) }}" style="font-size:9.5px; color:#546E7A; text-decoration:none;">{{ __('gl.back_to_customer_link') }}</a>
    <h4 style="font-weight:700; margin:2px 0 6px; font-size:13px; color:#1565C0;">{{ __('gl.edit_dash_name', ['name' => $customer->full_name]) }}</h4>

    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:5px 10px; font-size:10px; color:#b71c1c; margin-bottom:6px;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif
</div>

<div style="flex:1 1 auto; min-height:0; overflow-y:auto; padding-bottom:8px;">

<form method="POST" action="{{ route($rolePrefix . '.customers.update', $customer->customer_id) }}">
    @csrf
    @method('PUT')

    <div style="background:#F7FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:6px;">
    <div style="display:grid; grid-template-columns:1.4fr 1fr; gap:5px;">

        <div style="display:flex; flex-direction:column; gap:5px;">
            {{-- CONTACT — editable by anyone who can reach this screen --}}
            <div style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:6px 8px;">
                <p style="font-size:8.5px; font-weight:700; color:#1565C0; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">{{ __('gl.col_contact') }}</p>
                <div style="display:flex; gap:8px;">
                    <div style="flex:1;">
                        <label style="font-size:8.5px; font-weight:600; color:#9ca3af; display:block; margin-bottom:1px;">{{ __('gl.field_phone_required') }}</label>
                        <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9.5px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:8.5px; font-weight:600; color:#9ca3af; display:block; margin-bottom:1px;">{{ __('gl.field_email') }}</label>
                        <input type="email" name="email" value="{{ old('email', $customer->email) }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9.5px; box-sizing:border-box;">
                    </div>
                </div>
            </div>

            {{-- ADDRESS — editable --}}
            <div style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:6px 8px;">
                <p style="font-size:8.5px; font-weight:700; color:#1565C0; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">{{ __('gl.field_address') }}</p>
                <div style="margin-bottom:4px;">
                    <label style="font-size:8.5px; font-weight:600; color:#9ca3af; display:block; margin-bottom:1px;">{{ __('gl.field_address') }}</label>
                    <textarea name="address" rows="2" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9.5px; box-sizing:border-box; resize:vertical;">{{ old('address', $customer->address) }}</textarea>
                </div>
                <div style="display:flex; gap:8px;">
                    <div style="flex:0.7;">
                        <label style="font-size:8.5px; font-weight:600; color:#9ca3af; display:block; margin-bottom:1px;">{{ __('gl.field_postcode') }}</label>
                        <input type="text" name="postcode" value="{{ old('postcode', $customer->postcode) }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9.5px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:8.5px; font-weight:600; color:#9ca3af; display:block; margin-bottom:1px;">{{ __('gl.field_city') }}</label>
                        <input type="text" name="city" value="{{ old('city', $customer->city) }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9.5px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:8.5px; font-weight:600; color:#9ca3af; display:block; margin-bottom:1px;">{{ __('gl.field_state') }}</label>
                        <select name="state" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9.5px; box-sizing:border-box; background:#fff;">
                            <option value="">{{ __('gl.select_state_option') }}</option>
                            @foreach($states as $state)
                            <option value="{{ $state }}" {{ old('state', $customer->state)==$state?'selected':'' }}>{{ $state }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:5px;">
            {{-- CLASSIFICATION — CHANGED 19 Jul 2026 per Chris: now
                 editable by everyone who reaches this screen (not
                 Admin-only), every field a dropdown from the live
                 master-file records so nothing is ever free-typed.
                 Laid out as a 2-column mini-grid (3 rows instead of 5
                 stacked rows) to save vertical space. --}}
            <div style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:6px 8px;">
                <p style="font-size:8.5px; font-weight:700; color:#1565C0; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">{{ __('customers.classification_heading') }}</p>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-bottom:4px;">
                    <div>
                        <label style="font-size:8.5px; font-weight:600; color:#9ca3af; display:block; margin-bottom:1px;">{{ __('customers.field_status_required') }}</label>
                        <select name="status_id" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9.5px; box-sizing:border-box; background:#fff;">
                            @foreach($statuses as $s)
                            <option value="{{ $s->status_id }}" {{ old('status_id', $customer->status_id)==$s->status_id?'selected':'' }}>{{ $s->description }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size:8.5px; font-weight:600; color:#9ca3af; display:block; margin-bottom:1px;">{{ __('customers.field_type') }}</label>
                        <select name="customer_type_id" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9.5px; box-sizing:border-box; background:#fff;">
                            <option value="">{{ __('customers.none_option') }}</option>
                            @foreach($customerTypes as $t)
                            <option value="{{ $t->type_id }}" {{ old('customer_type_id', $customer->customer_type_id)==$t->type_id?'selected':'' }}>{{ $t->description }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-bottom:4px;">
                    <div>
                        <label style="font-size:8.5px; font-weight:600; color:#9ca3af; display:block; margin-bottom:1px;">{{ __('customers.field_category') }}</label>
                        <select name="customer_category_id" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9.5px; box-sizing:border-box; background:#fff;">
                            <option value="">{{ __('customers.none_option') }}</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->category_id }}" {{ old('customer_category_id', $customer->customer_category_id)==$cat->category_id?'selected':'' }}>{{ $cat->description }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size:8.5px; font-weight:600; color:#9ca3af; display:block; margin-bottom:1px;">{{ __('customers.field_occupation_group') }}</label>
                        <select name="occupation_group_id" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9.5px; box-sizing:border-box; background:#fff;">
                            <option value="">{{ __('customers.none_option') }}</option>
                            @foreach($occupationGroups as $o)
                            <option value="{{ $o->occupation_group_id }}" {{ old('occupation_group_id', $customer->occupation_group_id)==$o->occupation_group_id?'selected':'' }}>{{ $o->description }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                    <div>
                        <label style="font-size:8.5px; font-weight:600; color:#9ca3af; display:block; margin-bottom:1px;">{{ __('customers.field_source') }}</label>
                        <select name="source_id" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9.5px; box-sizing:border-box; background:#fff;">
                            <option value="">{{ __('customers.none_option') }}</option>
                            @foreach($sources as $src)
                            <option value="{{ $src->source_id }}" {{ old('source_id', $customer->source_id)==$src->source_id?'selected':'' }}>{{ $src->description }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div></div>
                </div>
            </div>

            {{-- IDENTITY — Full Name/NRIC stay Admin-locked (used for
                 duplicate-matching integrity by the Customer Resolution
                 Service). Everyone else sees these read-only. Side by
                 side in one row for Admin to save vertical space. --}}
            <div style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:6px 8px;">
                <p style="font-size:8.5px; font-weight:700; color:{{ $isAdmin ? '#1565C0' : '#B45309' }}; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">
                    {{ $isAdmin ? __('customers.identity_heading') : __('customers.identity_locked_note') }}
                </p>
                @if($isAdmin)
                <div style="display:flex; gap:8px;">
                    <div style="flex:1;">
                        <label style="font-size:8.5px; font-weight:600; color:#9ca3af; display:block; margin-bottom:1px;">{{ __('customers.field_full_name_required') }}</label>
                        <input type="text" name="full_name" value="{{ old('full_name', $customer->full_name) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9.5px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:8.5px; font-weight:600; color:#9ca3af; display:block; margin-bottom:1px;">{{ __('customers.field_nric_blank_unchanged') }}</label>
                        <input type="text" name="nric" placeholder="{{ __('customers.enter_to_change_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9.5px; box-sizing:border-box;">
                    </div>
                </div>
                @else
                <div style="display:flex; gap:8px; align-items:center;">
                    <span style="font-weight:700; font-size:9.5px; color:#374151;">{{ $customer->full_name }}</span>
                    <span style="color:#9ca3af; font-size:9px;">{{ __('gl.field_nric') }} &bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>
                </div>
                @endif
            </div>
        </div>

    </div>
    </div>

    <div style="display:flex; gap:10px; margin-top:8px;">
        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:6px 18px; font-size:11px; font-weight:700; cursor:pointer;">{{ __('gl.save_changes_button') }}</button>
        <a href="{{ route($rolePrefix . '.customers.show', $customer->customer_id) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:11px; font-weight:500; display:inline-flex; align-items:center;">{{ __('gl.cancel_button') }}</a>
    </div>
</form>

</div>
</div>

@endsection
