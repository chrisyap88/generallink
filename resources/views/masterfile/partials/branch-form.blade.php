<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">

    {{-- BRANCH INFORMATION --}}
    <div style="grid-column:1/-1; font-size:11px; font-weight:700; color:#1565C0; padding-bottom:4px; border-bottom:2px solid #e0f2fe;">
        🏪 Branch Information
    </div>

    <div>
        <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">Branch Name <span style="color:#dc2626;">*</span></label>
        <input type="text" name="branch_name" value="{{ old('branch_name', $branch->branch_name ?? '') }}" required
            placeholder="e.g. Kuala Lumpur Branch"
            style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;">
    </div>

    <div>
        <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">Branch Code <span style="color:#dc2626;">*</span></label>
        <input type="text" name="branch_code" value="{{ old('branch_code', $branch->branch_code ?? '') }}"
            {{ $branch ? 'readonly style=background:#f9fafb;width:100%;border:1px solid #d1d5db;border-radius:6px;padding:7px 10px;font-size:11px;outline:none;box-sizing:border-box;' : 'required style=width:100%;border:1px solid #d1d5db;border-radius:6px;padding:7px 10px;font-size:11px;outline:none;box-sizing:border-box;text-transform:uppercase;' }}>
        @if(!$branch)<div style="font-size:10px; color:#6b7280; margin-top:2px;">Short unique code, cannot be changed after creation.</div>@endif
    </div>

    <div>
        <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">Phone</label>
        <input type="text" name="phone" value="{{ old('phone', $branch->phone ?? '') }}"
            placeholder="+603-XXXX XXXX"
            style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;">
    </div>

    <div style="grid-column:span 2;">
        <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">Email</label>
        <input type="email" name="email" value="{{ old('email', $branch->email ?? '') }}"
            placeholder="branch@example.com"
            style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;">
    </div>

    {{-- BRANCH ADDRESS --}}
    <div style="grid-column:1/-1; font-size:11px; font-weight:700; color:#1565C0; padding-bottom:4px; border-bottom:2px solid #e0f2fe; margin-top:8px;">
        📍 Branch Address
    </div>

    <div style="grid-column:1/-1;">
        <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">Address</label>
        <input type="text" name="address" value="{{ old('address', $branch->address ?? '') }}"
            placeholder="Street address"
            style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;">
    </div>

    <div>
        <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">Postcode</label>
        <input type="text" name="postcode" class="postcode-input" value="{{ old('postcode', $branch->postcode ?? '') }}"
            placeholder="e.g. 41050" maxlength="10"
            style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;"
            oninput="lookupPostcode(this.value, 'branch_city', 'branch_state')">
    </div>

    <div>
        <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">City</label>
        <input type="text" name="city" id="branch_city" class="city-input" value="{{ old('city', $branch->city ?? '') }}"
            placeholder="City"
            style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;">
    </div>

    <div>
        <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">State</label>
        <select name="state" id="branch_state" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
            <option value="">— Select State —</option>
            @foreach($states as $state)
            <option value="{{ $state }}" {{ old('state', $branch->state ?? '') === $state ? 'selected' : '' }}>{{ $state }}</option>
            @endforeach
        </select>
    </div>

    {{-- PIC DETAILS --}}
    <div style="grid-column:1/-1; font-size:11px; font-weight:700; color:#1565C0; padding-bottom:4px; border-bottom:2px solid #e0f2fe; margin-top:8px;">
        👤 Person In Charge (PIC)
    </div>

    <div>
        <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">PIC Name</label>
        <input type="text" name="pic_name" value="{{ old('pic_name', $branch->pic_name ?? '') }}"
            placeholder="Full name of PIC"
            style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;">
    </div>

    <div>
        <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">PIC Phone</label>
        <input type="text" name="pic_phone" value="{{ old('pic_phone', $branch->pic_phone ?? '') }}"
            placeholder="+601X-XXXX XXXX"
            style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;">
    </div>

    @if($showStatus)
    <div>
        <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">Status</label>
        <select name="is_active" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
            <option value="1" {{ old('is_active', $branch->is_active ?? 1) == 1 ? 'selected' : '' }}>Active</option>
            <option value="0" {{ old('is_active', $branch->is_active ?? 1) == 0 ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>
    @endif

    <div style="grid-column:1/-1; margin-top:8px;">
        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:10px 28px; font-size:12px; font-weight:600; cursor:pointer;">
            {{ $submitLabel }}
        </button>
    </div>

</div>
