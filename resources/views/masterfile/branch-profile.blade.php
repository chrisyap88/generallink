@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.branch_outlet_management_title'))
@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; flex-shrink:0;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    {{-- MAIN SCREEN --}}
    @if($mode === 'main')
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:20px; flex-shrink:0;">
        <div style="display:flex; gap:16px;">
            <a href="{{ route('admin.branches.index', ['mode'=>'add']) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:8px; padding:12px 30px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:8px; box-shadow:0 2px 8px rgba(21,101,192,.3);">➕ {{ __('masterfile.add_new_branch_button') }}</a>
            <a href="{{ route('admin.branches.index', ['mode'=>'search']) }}" style="background:#fff; color:#1565C0; text-decoration:none; border-radius:8px; padding:12px 30px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:8px; box-shadow:0 2px 8px rgba(0,0,0,.08); border:2px solid #1565C0;">🔍 {{ __('masterfile.search_branch_button') }}</a>
        </div>
    </div>

    {{-- ADD NEW BRANCH --}}
    @elseif($mode === 'add')
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0;">
        <div style="padding:10px 16px; border-bottom:1px solid #e0f2fe; display:flex; align-items:center; justify-content:space-between;">
            <div style="font-size:13px; font-weight:700; color:#1565C0;">➕ {{ __('masterfile.add_new_branch_outlet_heading') }}</div>
            <a href="{{ route('admin.branches.index', ['mode'=>'main']) }}" style="font-size:11px; color:#6b7280; text-decoration:none;">← {{ __('masterfile.back') }}</a>
        </div>
        <div style="padding:12px 16px;">
            <form method="POST" action="{{ route('admin.branches.store') }}" autocomplete="off">
                @csrf
                @include('masterfile.partials.branch-fields', ['b'=>null, 'vendors'=>$vendors, 'states'=>$states, 'submitLabel'=>'➕ '.__('masterfile.add_branch_submit_label'), 'cancelUrl'=>route('admin.branches.index', ['mode'=>'main'])])
            </form>
        </div>
    </div>

    {{-- SEARCH CRITERIA --}}
    @elseif($mode === 'search' && $branches === null)
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0;">
        <div style="padding:10px 16px; border-bottom:1px solid #e0f2fe; display:flex; align-items:center; justify-content:space-between;">
            <div style="font-size:13px; font-weight:700; color:#1565C0;">🔍 {{ __('masterfile.search_branch_outlet_heading') }}</div>
            <a href="{{ route('admin.branches.index', ['mode'=>'main']) }}" style="font-size:11px; color:#6b7280; text-decoration:none;">← {{ __('masterfile.back') }}</a>
        </div>
        <div style="padding:12px 16px;">
            <form method="GET" action="{{ route('admin.branches.index') }}" autocomplete="off">
                <input type="hidden" name="mode" value="search">
                <div style="display:grid; grid-template-columns:repeat(5,1fr); gap:8px;">
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.branch_name_label') }}</label>
                        <input type="text" name="branch_name" value="{{ request('branch_name') }}" placeholder="{{ __('masterfile.any_part_of_name') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.branch_code_label') }}</label>
                        <input type="text" name="branch_code" value="{{ request('branch_code') }}" placeholder="{{ __('masterfile.branch_code_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.parent_vendor_label') }}</label>
                        <select name="vendor_id" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                            <option value="">{{ __('masterfile.all_vendors_option') }}</option>
                            @foreach($vendors as $vendor)
                            <option value="{{ $vendor->vendor_id }}" {{ request('vendor_id') === $vendor->vendor_id ? 'selected' : '' }}>{{ $vendor->vendor_name }} ({{ $vendor->vendor_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.phone') }}</label>
                        <input type="text" name="phone" value="{{ request('phone') }}" placeholder="{{ __('masterfile.phone_placeholder_603') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_pic_name') }}</label>
                        <input type="text" name="pic_name" value="{{ request('pic_name') }}" placeholder="{{ __('masterfile.any_part') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div style="grid-column:span 2;">
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.address_label') }}</label>
                        <input type="text" name="address" value="{{ request('address') }}" placeholder="{{ __('masterfile.any_part_of_address') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div style="position:relative;">
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_postcode') }}</label>
                        <div style="position:relative;">
                            <input type="text" name="postcode" id="br_search_postcode" value="{{ request('postcode') }}" placeholder="{{ __('masterfile.postcode_placeholder_410') }}" maxlength="5" autocomplete="off" oninput="brPCSearch(this)" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 28px 6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                            <span style="position:absolute; right:8px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:10px; pointer-events:none;">▼</span>
                        </div>
                        <div id="br_spc_dd" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,.15);z-index:9999;max-height:180px;overflow-y:auto;"></div>
                    </div>
                    <div style="position:relative;">
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_city') }}</label>
                        <div style="position:relative;">
                            <input type="text" name="city" id="br_search_city" value="{{ request('city') }}" placeholder="{{ __('masterfile.city_placeholder_type') }}" autocomplete="off" oninput="brCitySearch(this)" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 28px 6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                            <span style="position:absolute; right:8px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:10px; pointer-events:none;">▼</span>
                        </div>
                        <div id="br_sct_dd" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,.15);z-index:9999;max-height:180px;overflow-y:auto;"></div>
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_state') }}</label>
                        <select name="state" id="br_search_state" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                            <option value="">{{ __('masterfile.all_states_option') }}</option>
                            @foreach($states as $state)
                            <option value="{{ $state }}" {{ request('state') === $state ? 'selected' : '' }}>{{ $state }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.status') }}</label>
                        <select name="is_active" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                            <option value="">{{ __('masterfile.all_option') }}</option>
                            <option value="1" {{ request('is_active')==='1' ? 'selected' : '' }}>{{ __('masterfile.active') }}</option>
                            <option value="0" {{ request('is_active')==='0' ? 'selected' : '' }}>{{ __('masterfile.inactive') }}</option>
                        </select>
                    </div>
                    <div style="grid-column:span 2; display:flex; align-items:flex-end; gap:8px;">
                        <button type="submit" name="do_search" value="1" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 24px; font-size:12px; font-weight:600; cursor:pointer;">🔍 {{ __('masterfile.search') }}</button>
                        <a href="{{ route('admin.branches.index', ['mode'=>'search']) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:7px 16px; font-size:12px;">{{ __('masterfile.clear') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- SEARCH RESULTS --}}
    @elseif($mode === 'search' && $branches !== null)
    @php $backToResults = request()->fullUrl(); @endphp
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0; padding:10px 16px; display:flex; align-items:center; justify-content:space-between;">
        <div style="font-size:11px; font-weight:700; color:#1565C0;">{{ __('masterfile.search_results_count', ['count' => $branches->total()]) }}</div>
        <div style="display:flex; gap:8px; align-items:center;">
            <a href="{{ route('admin.branches.index', ['mode'=>'search']) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:600;">{{ __('masterfile.new_search') }}</a>
            <a href="{{ route('admin.branches.index', ['mode'=>'main']) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:600;">🏠 {{ __('masterfile.main_button') }}</a>
        </div>
    </div>
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        <div style="flex:1; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:11px;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:2px solid #e0f2fe; position:sticky; top:0; z-index:1;">
                        <th style="text-align:center; padding:8px 10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.col_no') }}</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.col_branch_name') }}</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.col_code') }}</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.col_parent_vendor') }}</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.phone') }}</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.email') }}</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.col_postcode') }}</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.col_city') }}</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.col_state') }}</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.col_pic_name') }}</th>
                        <th style="text-align:center; padding:8px 10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.status') }}</th>
                        <th style="text-align:center; padding:8px 10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.col_action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($branches as $br)
                    <tr style="border-bottom:1px solid #f3f4f6; {{ $loop->even ? 'background:#fafafa;' : '' }}">
                        <td style="padding:8px 10px; text-align:center; color:#9ca3af;">{{ $loop->iteration }}</td>
                        <td style="padding:8px 10px; font-weight:600; color:#111827;">{{ $br->branch_name }}</td>
                        <td style="padding:8px 10px; font-family:monospace; color:#6b7280;">{{ $br->branch_code }}</td>
                        <td style="padding:8px 10px; color:#374151;">{{ $br->vendor_name ?? '—' }} <span style="color:#9ca3af;">({{ $br->vendor_code ?? '' }})</span></td>
                        <td style="padding:8px 10px;">{{ $br->phone ?? '—' }}</td>
                        <td style="padding:8px 10px;">{{ $br->email ?? '—' }}</td>
                        <td style="padding:8px 10px;">{{ $br->postcode ?? '—' }}</td>
                        <td style="padding:8px 10px;">{{ $br->city ?? '—' }}</td>
                        <td style="padding:8px 10px;">{{ $br->state ?? '—' }}</td>
                        <td style="padding:8px 10px;">{{ $br->pic_name ?? '—' }}</td>
                        <td style="padding:8px 10px; text-align:center;"><span style="background:{{ $br->is_active ? '#d1fae5' : '#fee2e2' }}; color:{{ $br->is_active ? '#065f46' : '#991b1b' }}; font-size:10px; font-weight:600; padding:2px 8px; border-radius:20px;">{{ $br->is_active ? __('masterfile.active') : __('masterfile.inactive') }}</span></td>
                        <td style="padding:8px 10px; text-align:center;">
                            <a href="{{ route('admin.branches.index', ['mode'=>'edit', 'branch_id'=>$br->branch_id, 'back'=>urlencode($backToResults)]) }}" style="background:#e0f2fe; color:#1565C0; text-decoration:none; border-radius:6px; padding:3px 10px; font-size:10px; font-weight:600;">✏️ {{ __('masterfile.edit') }}</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="padding:8px 12px; border-top:1px solid #f3f4f6; flex-shrink:0; display:flex; align-items:center; justify-content:space-between;">
            @if($branches->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</span>
            @else
                <a href="{{ $branches->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</a>
            @endif
            <span style="font-size:11px; color:#6b7280;">{{ __('masterfile.showing_records_page_dot', ['first' => $branches->firstItem(), 'lastItem' => $branches->lastItem(), 'total' => $branches->total(), 'current' => $branches->currentPage(), 'lastPage' => $branches->lastPage()]) }}</span>
            @if($branches->hasMorePages())
                <a href="{{ $branches->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</span>
            @endif
        </div>
    </div>

    {{-- EDIT BRANCH --}}
    @elseif($mode === 'edit' && $selectedBranch)
    @php $backUrl = request('back') ? urldecode(request('back')) : route('admin.branches.index', ['mode'=>'search']); @endphp
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0;">
        <div style="padding:10px 16px; border-bottom:1px solid #e0f2fe; display:flex; align-items:center; justify-content:space-between;">
            <div style="font-size:13px; font-weight:700; color:#1565C0;">✏️ {{ __('masterfile.edit_branch_heading', ['name' => $selectedBranch->branch_name]) }}</div>
            <div style="display:flex; gap:10px; align-items:center;">
                <a href="{{ $backUrl }}" style="font-size:11px; color:#6b7280; text-decoration:none;">← {{ __('masterfile.back_to_results') }}</a>
                <a href="{{ route('admin.branches.index', ['mode'=>'main']) }}" style="font-size:11px; color:#6b7280; text-decoration:none;">🏠 {{ __('masterfile.main_button') }}</a>
            </div>
        </div>
        <div style="padding:12px 16px;">
            <form method="POST" action="{{ route('admin.branches.update', $selectedBranch->branch_id) }}" autocomplete="off">
                @csrf @method('PUT')
                <input type="hidden" name="back" value="{{ urlencode($backUrl) }}">
                @include('masterfile.partials.branch-fields', ['b'=>$selectedBranch, 'vendors'=>$vendors, 'states'=>$states, 'submitLabel'=>'💾 '.__('masterfile.update_branch_submit_label'), 'cancelUrl'=>$backUrl])
            </form>
        </div>
    </div>
    @endif

</div>

@push('scripts')
<script>
var _brSPCt, _brSCTt, _brPCt, _brCTt;
function _brPcLookup(v, ddId, pcId, cityId, stateId) {
    var dd = document.getElementById(ddId);
    if (v.length < 3) { dd.style.display = 'none'; return; }
    fetch('/admin/postcode-lookup?postcode=' + encodeURIComponent(v) + '&partial=1')
    .then(function(r) { return r.json(); }).then(function(data) {
        if (!data || !data.length) { dd.style.display = 'none'; return; }
        dd.innerHTML = '';
        data.forEach(function(item) {
            var d = document.createElement('div');
            d.style.cssText = 'padding:7px 12px;cursor:pointer;font-size:12px;border-bottom:1px solid #f3f4f6;';
            d.innerHTML = '<strong>' + item.postcode + '</strong> — ' + item.city + ' (' + item.state + ')';
            d.onmouseover = function() { this.style.background = '#f0f9ff'; };
            d.onmouseout  = function() { this.style.background = ''; };
            d.onmousedown = function(e) {
                e.preventDefault();
                var pi = document.getElementById(pcId), ci = document.getElementById(cityId), si = document.getElementById(stateId);
                if (pi) pi.value = item.postcode;
                if (ci) ci.value = item.city;
                if (si) { for (var i = 0; i < si.options.length; i++) { if (si.options[i].value === item.state) { si.selectedIndex = i; break; } } }
                dd.style.display = 'none';
            };
            dd.appendChild(d);
        });
        dd.style.display = 'block';
    }).catch(function() { dd.style.display = 'none'; });
}
function _brCityLookup(v, ddId, cityId, stateId) {
    var dd = document.getElementById(ddId);
    if (v.length < 2) { dd.style.display = 'none'; return; }
    fetch('/admin/postcode-lookup?city=' + encodeURIComponent(v))
    .then(function(r) { return r.json(); }).then(function(data) {
        if (!data || !data.length) { dd.style.display = 'none'; return; }
        dd.innerHTML = ''; var seen = {};
        data.forEach(function(item) {
            if (seen[item.city]) return; seen[item.city] = true;
            var d = document.createElement('div');
            d.style.cssText = 'padding:7px 12px;cursor:pointer;font-size:12px;border-bottom:1px solid #f3f4f6;';
            d.innerHTML = item.city + ' (' + item.state + ')';
            d.onmouseover = function() { this.style.background = '#f0f9ff'; };
            d.onmouseout  = function() { this.style.background = ''; };
            d.onmousedown = function(e) {
                e.preventDefault();
                var ci = document.getElementById(cityId), si = document.getElementById(stateId);
                if (ci) ci.value = item.city;
                if (si) { for (var i = 0; i < si.options.length; i++) { if (si.options[i].value === item.state) { si.selectedIndex = i; break; } } }
                dd.style.display = 'none';
            };
            dd.appendChild(d);
        });
        dd.style.display = 'block';
    }).catch(function() { dd.style.display = 'none'; });
}
function brPC(inp)        { clearTimeout(_brPCt);  _brPCt  = setTimeout(function() { _brPcLookup(inp.value.trim(),   'br_pc_dd',  'br_postcode',       'br_city',        'br_state');        }, 300); }
function brCity(inp)      { clearTimeout(_brCTt);  _brCTt  = setTimeout(function() { _brCityLookup(inp.value.trim(), 'br_ct_dd',  'br_city',           'br_state');                          }, 300); }
function brPCSearch(inp)  { clearTimeout(_brSPCt); _brSPCt = setTimeout(function() { _brPcLookup(inp.value.trim(),   'br_spc_dd', 'br_search_postcode', 'br_search_city', 'br_search_state'); }, 300); }
function brCitySearch(inp){ clearTimeout(_brSCTt); _brSCTt = setTimeout(function() { _brCityLookup(inp.value.trim(),'br_sct_dd', 'br_search_city',    'br_search_state');                    }, 300); }
document.addEventListener('click', function(e) {
    ['br_pc_dd','br_ct_dd','br_spc_dd','br_sct_dd'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el && !el.contains(e.target)) el.style.display = 'none';
    });
});
</script>
@endpush
@endsection
