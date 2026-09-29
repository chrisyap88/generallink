<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:8px;">
    <div style="grid-column:1/-1; font-size:10px; font-weight:700; color:#1565C0; border-bottom:1px solid #e0f2fe; padding-bottom:3px;">Branch Information</div>
    <div>
        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">Branch Name @if(!$b)*@endif</label>
        <input type="text" name="branch_name" value="{{ old('branch_name', $b->branch_name ?? '') }}" @if(!$b) required @endif placeholder="e.g. KL Branch" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
    </div>
    <div>
        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">Branch Code @if(!$b)*@endif @if($b)<span style="color:#9ca3af;">(fixed)</span>@endif</label>
        <input type="text" name="branch_code" value="{{ old('branch_code', $b->branch_code ?? '') }}" @if($b) readonly @else required @endif placeholder="e.g. KL001" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; text-transform:uppercase; {{ $b ? 'background:#f9fafb;' : '' }}">
    </div>
    <div>
        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">Phone</label>
        <input type="text" name="phone" value="{{ old('phone', $b->phone ?? '') }}" placeholder="+603-XXXX XXXX" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
    </div>
    <div>
        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">Email</label>
        <input type="email" name="email" value="{{ old('email', $b->email ?? '') }}" placeholder="branch@example.com" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
    </div>
    <div>
        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">PIC Name</label>
        <input type="text" name="pic_name" value="{{ old('pic_name', $b->pic_name ?? '') }}" placeholder="Person in charge" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
    </div>
    <div>
        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">PIC Phone</label>
        <input type="text" name="pic_phone" value="{{ old('pic_phone', $b->pic_phone ?? '') }}" placeholder="+601X-XXXX XXXX" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
    </div>
    @if($b)
    <div>
        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">Status</label>
        <select name="is_active" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
            <option value="1" {{ ($b->is_active ?? 1) == 1 ? 'selected' : '' }}>Active</option>
            <option value="0" {{ ($b->is_active ?? 1) == 0 ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>
    @else
    <div></div>
    @endif
    <div style="grid-column:1/-1; font-size:10px; font-weight:700; color:#1565C0; border-bottom:1px solid #e0f2fe; padding-bottom:3px; margin-top:4px;">Branch Address</div>
    <div style="grid-column:span 2;">
        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">Address</label>
        <input type="text" name="address" value="{{ old('address', $b->address ?? '') }}" placeholder="Street address" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
    </div>
    <div style="position:relative;">
        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">Postcode</label>
        <div style="position:relative;">
            <input type="text" name="postcode" id="gl_branch_postcode" value="{{ old('postcode', $b->postcode ?? '') }}" placeholder="e.g. 41050" maxlength="5" autocomplete="off" oninput="glBPC(this)" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 28px 6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
            <span style="position:absolute; right:8px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:10px; pointer-events:none;">▼</span>
        </div>
        <div id="gl_bpc_dd" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,.15);z-index:9999;max-height:180px;overflow-y:auto;"></div>
    </div>
    <div style="position:relative;">
        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">City</label>
        <div style="position:relative;">
            <input type="text" name="city" id="gl_branch_city" value="{{ old('city', $b->city ?? '') }}" placeholder="Type or auto-fill" autocomplete="off" oninput="glBCity(this)" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 28px 6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
            <span style="position:absolute; right:8px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:10px; pointer-events:none;">▼</span>
        </div>
        <div id="gl_bct_dd" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,.15);z-index:9999;max-height:180px;overflow-y:auto;"></div>
    </div>
    <div style="grid-column:span 2;">
        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">State</label>
        <select name="state" id="gl_branch_state" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
            <option value="">— Select State —</option>
            @foreach($states as $state)
            <option value="{{ $state }}" {{ old('state', $b->state ?? '') === $state ? 'selected' : '' }}>{{ $state }}</option>
            @endforeach
        </select>
    </div>
    <div style="grid-column:1/-1; margin-top:6px; display:flex; gap:10px;">
        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 28px; font-size:12px; font-weight:600; cursor:pointer;">{{ $submitLabel }}</button>
        <a href="{{ $cancelUrl }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:500;">Cancel</a>
    </div>
</div>
