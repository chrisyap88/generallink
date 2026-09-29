{{-- CREATED 18 Aug 2026 — this partial was referenced by branch-profile.blade.php
     (Add New Branch / Edit Branch) but did not exist as a file, so those two
     screens were throwing a "view not found" error. Built to match the fields
     BranchController@store / @update actually read (branch_name, branch_code,
     vendor_id, address, postcode, city, state, phone, email, pic_name,
     pic_phone, is_active), using the same layout/style pattern and postcode
     autocomplete hooks (brPC/brCity, ids br_postcode/br_city/br_state) already
     wired up in branch-profile.blade.php's script block. Strict no-scroll
     rule, one standard font/colour scheme, fully translated from the start. --}}
<div style="display:grid; grid-template-columns:repeat(6,1fr); gap:5px;">
    <div style="grid-column:1/-1; font-size:9.5px; font-weight:700; color:#1565C0; border-bottom:1px solid #e0f2fe; padding-bottom:1px;">{{ __('masterfile.branch_information_heading') }}</div>
    <div>
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.branch_name_label') }} @if(!$b)*@endif</label>
        <input type="text" name="branch_name" value="{{ old('branch_name', $b->branch_name ?? '') }}" @if(!$b) required @endif style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box;">
    </div>
    <div>
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.branch_code_label') }} @if(!$b)*@endif @if($b)<span style="color:#9ca3af;">({{ __('masterfile.locked_label') }})</span>@endif</label>
        <input type="text" name="branch_code" value="{{ old('branch_code', $b->branch_code ?? '') }}" @if($b) readonly @else required @endif placeholder="{{ __('masterfile.branch_code_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box; text-transform:uppercase; {{ $b ? 'background:#f9fafb;' : '' }}">
    </div>
    <div style="grid-column:span 2;">
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.parent_vendor_label') }} @if(!$b)*@endif @if($b)<span style="color:#9ca3af;">({{ __('masterfile.company_fixed_note') }})</span>@endif</label>
        @if($b)
        <div style="background:#f3f4f6; border-radius:5px; padding:3px 6px; font-size:10.5px; color:#374151; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $b->vendor_name ?? '—' }} <span style="color:#9ca3af;">({{ $b->vendor_code ?? '' }})</span></div>
        @else
        <select name="vendor_id" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box; background:#fff;">
            <option value="">{{ __('masterfile.select_option') }}</option>
            @foreach($vendors as $vendor)
            <option value="{{ $vendor->vendor_id }}" {{ old('vendor_id') === $vendor->vendor_id ? 'selected' : '' }}>{{ $vendor->vendor_name }} ({{ $vendor->vendor_code }})</option>
            @endforeach
        </select>
        @endif
    </div>
    <div>
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.phone') }}</label>
        <input type="text" name="phone" value="{{ old('phone', $b->phone ?? '') }}" placeholder="{{ __('masterfile.phone_placeholder_603') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box;">
    </div>
    <div>
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.email') }}</label>
        <input type="email" name="email" value="{{ old('email', $b->email ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box;">
    </div>
    <div style="grid-column:1/-1; font-size:10px; font-weight:700; color:#1565C0; border-bottom:1px solid #e0f2fe; padding-bottom:2px; margin-top:2px;">{{ __('masterfile.head_office_address') }}</div>
    <div style="grid-column:span 2;">
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.address_label') }}</label>
        <input type="text" name="address" value="{{ old('address', $b->address ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box;">
    </div>
    <div style="position:relative;">
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.postcode') }}</label>
        <div style="position:relative;">
            <input type="text" name="postcode" id="br_postcode" value="{{ old('postcode', $b->postcode ?? '') }}" placeholder="{{ __('masterfile.postcode_placeholder_410') }}" maxlength="5" autocomplete="off" oninput="brPC(this)" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 26px 3px 6px; font-size:10.5px; outline:none; box-sizing:border-box;">
            <span style="position:absolute; right:8px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:10px; pointer-events:none;">▼</span>
        </div>
        <div id="br_pc_dd" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,.15);z-index:9999;max-height:180px;overflow-y:auto;"></div>
    </div>
    <div style="position:relative;">
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.city') }}</label>
        <div style="position:relative;">
            <input type="text" name="city" id="br_city" value="{{ old('city', $b->city ?? '') }}" autocomplete="off" oninput="brCity(this)" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 26px 3px 6px; font-size:10.5px; outline:none; box-sizing:border-box;">
            <span style="position:absolute; right:8px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:10px; pointer-events:none;">▼</span>
        </div>
        <div id="br_ct_dd" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,.15);z-index:9999;max-height:180px;overflow-y:auto;"></div>
    </div>
    <div>
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.state') }}</label>
        <select name="state" id="br_state" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box; background:#fff;">
            <option value="">{{ __('masterfile.select_state_option') }}</option>
            @foreach($states as $state)
            <option value="{{ $state }}" {{ old('state', $b->state ?? '') === $state ? 'selected' : '' }}>{{ $state }}</option>
            @endforeach
        </select>
    </div>
    <div style="grid-column:1/-1; font-size:10px; font-weight:700; color:#1565C0; border-bottom:1px solid #e0f2fe; padding-bottom:2px; margin-top:2px;">{{ __('masterfile.person_in_charge') }}</div>
    <div>
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.col_pic_name') }}</label>
        <input type="text" name="pic_name" value="{{ old('pic_name', $b->pic_name ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box;">
    </div>
    <div>
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.pic_phone_label') }}</label>
        <input type="text" name="pic_phone" value="{{ old('pic_phone', $b->pic_phone ?? '') }}" placeholder="{{ __('masterfile.phone_placeholder_603') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box;">
    </div>
    @if($b)
    <div>
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.status') }}</label>
        <select name="is_active" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box; background:#fff;">
            <option value="1" {{ ($b->is_active ?? 1) == 1 ? 'selected' : '' }}>{{ __('masterfile.active') }}</option>
            <option value="0" {{ ($b->is_active ?? 1) == 0 ? 'selected' : '' }}>{{ __('masterfile.inactive') }}</option>
        </select>
    </div>
    @endif
    <div style="grid-column:1/-1; margin-top:3px; display:flex; gap:10px; align-items:center;">
        <a href="{{ $cancelUrl }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:7px 20px; font-size:12px; font-weight:700; display:inline-flex; align-items:center;">{{ __('masterfile.prev') }}</a>
        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 26px; font-size:12px; font-weight:600; cursor:pointer;">{{ $submitLabel }}</button>
    </div>
</div>
