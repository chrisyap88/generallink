@extends('layouts.dashboard')

@section('title', __('gl.customers_title'))

@section('page-title')
{{ __('gl.customers_title') }} <span style="font-size:12px;color:#9ca3af;font-weight:400;margin-left:10px;">{{ __('gl.all_customers_under_group_note') }}</span>
@endsection

@push('styles')
<style>
.cul-wrap{padding:6px 8px;display:flex;flex-direction:column;gap:5px;height:calc(100vh - 66px);box-sizing:border-box;overflow:hidden;}
.cul-card{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:8px 12px;}

.cul-table-card{flex:1;overflow:hidden;padding:8px 10px;min-height:0;display:flex;flex-direction:column;}
.cul-table{width:100%;border-collapse:collapse;font-size:10px;}
.cul-table th{padding:3px 6px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;background:#F7FAFC;vertical-align:bottom;}
.cul-table td{padding:3px 6px;line-height:1.25;border-bottom:1px solid #F7FAFC;}
.cul-table .sub{font-size:8px;color:#9ca3af;}

.cul-th-filter{display:flex;flex-direction:column;gap:3px;}
.cul-th-filter input,
.cul-th-filter select{padding:2px 4px;font-size:10px;border:1px solid #E2E8F0;border-radius:4px;height:22px;box-sizing:border-box;font-weight:400;width:100%;}
.cul-th-label{font-size:10px;font-weight:700;color:#0D5A8E;}

.cul-btn{padding:2px 10px;font-size:10px;height:24px;display:inline-flex;align-items:center;border-radius:4px;cursor:pointer;border:none;font-weight:600;}
</style>
@endpush

@section('content')
<div class="cul-wrap">

    <div class="cul-card cul-table-card">
        <form method="GET" action="{{ route('gl.customers.index') }}" style="display:flex;flex-direction:column;flex:1;min-height:0;">
        <table class="cul-table">
            <thead>
                <tr>
                    <th style="min-width:180px;">
                        <div class="cul-th-filter">
                            <span class="cul-th-label">{{ __('gl.customers_title') }}</span>
                            <div style="position:relative;">
                                <input type="text" name="search" id="culSearchInput" autocomplete="off" value="{{ request('search') }}" placeholder="{{ __('gl.search_name_email_phone_placeholder') }}">
                                <div id="culSearchDropdown" style="display:none; position:absolute; top:100%; left:0; width:300px; max-width:80vw; background:#fff; border:1px solid #E2E8F0; border-radius:6px; box-shadow:0 4px 10px rgba(0,0,0,0.12); max-height:220px; overflow-y:auto; z-index:50;"></div>
                            </div>
                        </div>
                    </th>
                    <th>
                        <div class="cul-th-filter">
                            <span class="cul-th-label">{{ __('gl.col_contact') }}</span>
                            <span style="height:22px;"></span>
                        </div>
                    </th>
                    <th style="min-width:120px;">
                        <div class="cul-th-filter">
                            <span class="cul-th-label">{{ __('gl.col_location') }}</span>
                            <select name="state">
                                <option value="">{{ __('gl.all_states_option') }}</option>
                                @foreach($states as $state)
                                <option value="{{ $state }}" {{ request('state')==$state?'selected':'' }}>{{ $state }}</option>
                                @endforeach
                            </select>
                        </div>
                    </th>
                    <th style="min-width:140px;">
                        <div class="cul-th-filter">
                            <span class="cul-th-label">{{ __('gl.col_owned_by') }}</span>
                            <select name="agent_role">
                                <option value="">{{ __('gl.all_roles_option') }}</option>
                                <option value="TEAM_LEADER" {{ request('agent_role')=='TEAM_LEADER'?'selected':'' }}>{{ \App\Services\RoleLabelService::label('TEAM_LEADER') }}</option>
                                <option value="INTRODUCER" {{ request('agent_role')=='INTRODUCER'?'selected':'' }}>{{ \App\Services\RoleLabelService::label('INTRODUCER') }}</option>
                                <option value="GROUP_LEADER" {{ request('agent_role')=='GROUP_LEADER'?'selected':'' }}>{{ \App\Services\RoleLabelService::label('GROUP_LEADER') }}</option>
                            </select>
                        </div>
                    </th>
                    <th style="min-width:130px;white-space:nowrap;">
                        <div class="cul-th-filter">
                            <span class="cul-th-label">{{ __('gl.col_joined') }}</span>
                            <select name="sort">
                                <option value="created_desc" {{ $sort=='created_desc'?'selected':'' }}>{{ __('gl.newest_first_option') }}</option>
                                <option value="created_asc" {{ $sort=='created_asc'?'selected':'' }}>{{ __('gl.oldest_first_option') }}</option>
                                <option value="name_asc" {{ $sort=='name_asc'?'selected':'' }}>{{ __('gl.name_a_z_option') }}</option>
                                <option value="name_desc" {{ $sort=='name_desc'?'selected':'' }}>{{ __('gl.name_z_a_option') }}</option>
                                <option value="state_asc" {{ $sort=='state_asc'?'selected':'' }}>{{ __('gl.state_a_z_option') }}</option>
                                <option value="owner_asc" {{ $sort=='owner_asc'?'selected':'' }}>{{ __('gl.owned_by_a_z_option') }}</option>
                            </select>
                        </div>
                    </th>
                    <th style="text-align:center;min-width:90px;">
                        <div class="cul-th-filter">
                            <span class="cul-th-label">{{ __('gl.col_action') }}</span>
                            <div style="display:flex;gap:4px;">
                                <button type="submit" class="cul-btn" style="background:#1565C0;color:#fff;flex:1;">{{ __('gl.search_button') }}</button>
                                <a href="{{ route('gl.customers.index') }}" class="cul-btn" style="background:#F7FAFC;border:1px solid #E2E8F0;color:#4A5568;text-decoration:none;flex:1;justify-content:center;">{{ __('gl.reset_link') }}</a>
                            </div>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody>
                @if(!($hasQuery ?? false))
                <tr><td colspan="6" style="text-align:center;padding:40px;color:#A0AEC0;">
                    {{ __('gl.use_filters_to_search_customers_note') }}
                </td></tr>
                @elseif($customers->isEmpty())
                <tr><td colspan="6" style="text-align:center;padding:40px;color:#A0AEC0;">{{ __('gl.no_customers_found') }}</td></tr>
                @else
                @foreach($customers as $customer)
                <tr onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''">
                    <td style="font-weight:600;color:#1565C0;">{{ $customer->full_name }}</td>
                    <td>
                        {{ $customer->phone }}<br>
                        <span class="sub">{{ $customer->email ?? '&mdash;' }}</span>
                    </td>
                    <td>
                        {{ $customer->city ?? '&mdash;' }}
                        @if($customer->state)
                        <br><span class="sub">{{ $customer->state }}</span>
                        @endif
                    </td>
                    <td>
                        {{ $customer->agent_name }}<br>
                        <span class="sub">{{ $customer->agent_code }}</span>
                    </td>
                    <td class="sub" style="white-space:nowrap;">{{ \Carbon\Carbon::parse($customer->created_at)->format('d M Y') }}</td>
                    <td style="text-align:center;white-space:nowrap;">
                        <a href="{{ route('gl.customers.show', $customer->customer_id) }}" style="color:#1565C0;text-decoration:none;font-weight:600;margin-right:8px;">{{ __('gl.view_link') }}</a>
                        <a href="{{ route('gl.customers.edit', $customer->customer_id) }}" style="color:#38A169;text-decoration:none;font-weight:600;">{{ __('gl.edit_link') }}</a>
                    </td>
                </tr>
                @endforeach
                @endif
            </tbody>
        </table>
        @if(($hasQuery ?? false) && $customers->hasPages())
        <div style="margin-top:8px;display:flex;align-items:center;justify-content:space-between;font-size:10px;">
            <span style="color:#9ca3af;">{{ __('gl.showing_x_to_y_of_z_records', ['first' => $customers->firstItem(), 'last' => $customers->lastItem(), 'total' => $customers->total()]) }}</span>
            <div style="font-size:10px;">{{ $customers->links() }}</div>
        </div>
        @endif
        </form>
    </div>

</div>
<script>
(function () {
    var input = document.getElementById('culSearchInput');
    var dropdown = document.getElementById('culSearchDropdown');
    if (!input || !dropdown) { return; }
    var timer = null;

    function hide() { dropdown.style.display = 'none'; dropdown.innerHTML = ''; }

    function render(items) {
        if (!items.length) { hide(); return; }
        dropdown.innerHTML = items.map(function (item) {
            return '<div class="cul-suggestion" data-name="' + item.full_name.replace(/"/g, '&quot;') + '" ' +
                'style="padding:6px 10px; font-size:10px; cursor:pointer; display:flex; justify-content:space-between; gap:10px; border-bottom:1px solid #F7FAFC;">' +
                '<span>' + item.full_name + '</span>' +
                '<span style="color:#9ca3af;">' + (item.phone || item.email || '') + '</span></div>';
        }).join('');
        dropdown.style.display = 'block';
        Array.prototype.forEach.call(dropdown.querySelectorAll('.cul-suggestion'), function (row) {
            row.onmousedown = function () {
                input.value = row.getAttribute('data-name');
                hide();
                input.form.submit();
            };
        });
    }

    input.addEventListener('input', function () {
        var q = input.value.trim();
        if (timer) { clearTimeout(timer); }
        if (q.length < 1) { hide(); return; }
        timer = setTimeout(function () {
            fetch('{{ route('gl.customers.typeahead') }}?term=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); }).then(render).catch(hide);
        }, 250);
    });

    input.addEventListener('blur', function () { setTimeout(hide, 100); });
})();
</script>
@endsection
