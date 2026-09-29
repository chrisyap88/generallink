{{-- FIXED 8 Aug 2026 per Chris: strict no-scroll rule — this partial is
     rendered inside a fixed-height panel (see vendor-profile.blade.php's
     Edit > Vendor Details tab) with no scroll allowed. Widened from 4 to
     5 columns so the 10 Company Information fields (after adding Vendor
     Type) pack into 2 rows instead of 3, and tightened padding/gap
     throughout to reclaim more vertical space. --}}
<div style="display:grid; grid-template-columns:repeat(6,1fr); gap:5px;">
    <div style="grid-column:1/-1; font-size:9.5px; font-weight:700; color:#1565C0; border-bottom:1px solid #e0f2fe; padding-bottom:1px;">{{ __('masterfile.company_information') }}</div>
    <div>
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.col_vendor_name') }} @if(!$v)*@endif</label>
        <input type="text" name="vendor_name" value="{{ old('vendor_name', $v->vendor_name ?? '') }}" @if(!$v) required @endif placeholder="e.g. Allianz Malaysia" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box;">
    </div>
    <div>
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.name_other_language') }}</label>
        <input type="text" name="second_name" value="{{ old('second_name', $v->second_name ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box;">
    </div>
    <div>
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.col_vendor_code') }} @if(!$v)*@endif @if($v)<span style="color:#9ca3af;">({{ __('masterfile.locked_label') }})</span>@endif</label>
        <input type="text" name="vendor_code" value="{{ old('vendor_code', $v->vendor_code ?? '') }}" @if($v) readonly @else required @endif placeholder="e.g. ALZ" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box; text-transform:uppercase; {{ $v ? 'background:#f9fafb;' : '' }}">
    </div>
    <div style="position:relative;">
        {{-- CHANGED 9 Aug 2026 per Chris: plain select replaced with a searchable
             type-to-filter box (same pattern as the vendor self-registration
             form), reading from the same updated 19-category INDUSTRIES list. --}}
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.industry_label') }} *</label>
        <input type="text" id="gl_vendorfield_industryBox" autocomplete="off" placeholder="{{ __('masterfile.type_to_search') }}" value="{{ \App\Http\Controllers\Admin\VendorController::INDUSTRIES[old('industry', $v->industry ?? '')] ?? '' }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box;">
        <input type="hidden" name="industry" id="gl_vendorfield_industryValue" value="{{ old('industry', $v->industry ?? '') }}">
        <div id="gl_vendorfield_industryDropdown" style="display:none;position:fixed;z-index:9999;background:#fff;border:1px solid #1565C0;border-radius:6px;max-height:170px;overflow-y:auto;box-shadow:0 4px 16px rgba(0,0,0,.15);"></div>
    </div>
    <div>
        {{-- NEW 8 Aug 2026 — Vendor Management Phase 1 (spec Section 3): classifies how the vendor works with GeneralLink, feeds the Phase 2 catalogue later. RENAMED 9 Aug 2026 per Chris (was "What Do They Sell?") to avoid confusion with the vendor's own free-text "Nature of Business" description, now collected at self-registration. --}}
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.business_category') }} *</label>
        <select name="vendor_type" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box; background:#fff;">
            <option value="">{{ __('masterfile.select_option') }}</option>
            @foreach(\App\Models\Vendor::TYPES as $key => $label)
            <option value="{{ $key }}" {{ old('vendor_type', $v->vendor_type ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.col_office_phone') }}</label>
        <input type="text" name="vendor_office_phone" value="{{ old('vendor_office_phone', $v->vendor_office_phone ?? '') }}" placeholder="+603-XXXX XXXX" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box;">
    </div>
    <div>
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.email') }}</label>
        <input type="email" name="vendor_email" value="{{ old('vendor_email', $v->vendor_email ?? '') }}" placeholder="vendor@example.com" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box;">
    </div>
    <div>
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.website_label') }}</label>
        <input type="text" name="vendor_website" value="{{ old('vendor_website', $v->vendor_website ?? '') }}" placeholder="https://example.com" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box;">
    </div>
    <div>
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.col_pic_name') }}</label>
        <input type="text" name="pic_name" value="{{ old('pic_name', $v->pic_name ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box;">
    </div>
    <div>
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.pic_phone_label') }}</label>
        <input type="text" name="pic_phone" value="{{ old('pic_phone', $v->pic_phone ?? '') }}" placeholder="+601X-XXXX XXXX" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box;">
    </div>
    <div>
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.sst_registration_label') }}</label>
        <input type="text" name="sst_registration_number" value="{{ old('sst_registration_number', $v->sst_registration_number ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box;">
    </div>
    <div style="grid-column:1/-1; font-size:10px; font-weight:700; color:#1565C0; border-bottom:1px solid #e0f2fe; padding-bottom:2px; margin-top:2px;">{{ __('masterfile.head_office_address') }}</div>
    <div style="grid-column:span 2;">
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.col_address') }}</label>
        <input type="text" name="vendor_address" value="{{ old('vendor_address', $v->vendor_address ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box;">
    </div>
    <div style="position:relative;">
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.postcode') }}</label>
        <div style="position:relative;">
            <input type="text" name="vendor_postcode" id="gl_vendor_postcode" value="{{ old('vendor_postcode', $v->vendor_postcode ?? '') }}" placeholder="e.g. 41050" maxlength="5" autocomplete="off" oninput="glPC(this)" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 26px 3px 6px; font-size:10.5px; outline:none; box-sizing:border-box;">
            <span style="position:absolute; right:8px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:10px; pointer-events:none;">▼</span>
        </div>
        <div id="gl_pc_dd" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,.15);z-index:9999;max-height:180px;overflow-y:auto;"></div>
    </div>
    <div style="position:relative;">
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.city') }}</label>
        <div style="position:relative;">
            <input type="text" name="vendor_city" id="gl_vendor_city" value="{{ old('vendor_city', $v->vendor_city ?? '') }}" autocomplete="off" oninput="glCity(this)" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 26px 3px 6px; font-size:10.5px; outline:none; box-sizing:border-box;">
            <span style="position:absolute; right:8px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:10px; pointer-events:none;">▼</span>
        </div>
        <div id="gl_city_dd" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,.15);z-index:9999;max-height:180px;overflow-y:auto;"></div>
    </div>
    <div style="grid-column:span 2;">
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.state') }}</label>
        <select name="vendor_state" id="gl_vendor_state" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box; background:#fff;">
            <option value="">{{ __('masterfile.select_state_option') }}</option>
            @foreach($states as $state)
            <option value="{{ $state }}" {{ old('vendor_state', $v->vendor_state ?? '') === $state ? 'selected' : '' }}>{{ $state }}</option>
            @endforeach
        </select>
    </div>
    @if($v)
    <div>
        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.status') }}</label>
        <select name="is_active" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10.5px; outline:none; box-sizing:border-box; background:#fff;">
            <option value="1" {{ ($v->is_active ?? 1) == 1 ? 'selected' : '' }}>{{ __('masterfile.active') }}</option>
            <option value="0" {{ ($v->is_active ?? 1) == 0 ? 'selected' : '' }}>{{ __('masterfile.inactive_warning') }}</option>
        </select>
    </div>
    @endif
    <div style="grid-column:1/-1; margin-top:3px; display:flex; gap:10px; align-items:center;">
        <a href="{{ $cancelUrl }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:7px 20px; font-size:12px; font-weight:700; display:inline-flex; align-items:center;">{{ __('masterfile.prev') }}</a>
        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 26px; font-size:12px; font-weight:600; cursor:pointer;">{{ $submitLabel }}</button>
    </div>
</div>
<script>
    // Searchable Industry box (Admin Add/Edit Vendor form) — same
    // client-side filter pattern as the vendor self-registration screen.
    (function(){
        var box = document.getElementById('gl_vendorfield_industryBox');
        var hidden = document.getElementById('gl_vendorfield_industryValue');
        var dropdown = document.getElementById('gl_vendorfield_industryDropdown');
        if (!box) return;
        var entries = Object.entries(@json(\App\Http\Controllers\Admin\VendorController::INDUSTRIES));

        function render(filter){
            var f = filter.trim().toLowerCase();
            var matches = entries.filter(function(e){ return e[1].toLowerCase().indexOf(f) !== -1; });
            if (!matches.length){ dropdown.style.display = 'none'; return; }
            dropdown.innerHTML = matches.map(function(e){
                return '<div data-key="' + e[0] + '" data-label="' + e[1].replace(/"/g,'&quot;') + '" style="padding:4px 8px; font-size:10.5px; color:#374151; cursor:pointer;" onmouseover="this.style.background=\'#e0f2fe\'" onmouseout="this.style.background=\'#fff\'">' + e[1] + '</div>';
            }).join('');
            var rect = box.getBoundingClientRect();
            dropdown.style.left = rect.left + 'px';
            dropdown.style.top = rect.bottom + 'px';
            dropdown.style.width = rect.width + 'px';
            dropdown.style.display = 'block';
        }
        box.addEventListener('input', function(){ hidden.value = ''; render(box.value); });
        box.addEventListener('focus', function(){ render(box.value); });
        box.addEventListener('blur', function(){ setTimeout(function(){ dropdown.style.display = 'none'; }, 150); });
        dropdown.addEventListener('mousedown', function(e){
            var opt = e.target.closest('[data-key]');
            if (!opt) return;
            box.value = opt.getAttribute('data-label');
            hidden.value = opt.getAttribute('data-key');
            dropdown.style.display = 'none';
        });
        box.closest('form').addEventListener('submit', function(e){
            if (!hidden.value) {
                e.preventDefault();
                alert(@json(__('masterfile.industry_required_alert')));
                box.focus();
            }
        });
    })();
</script>
