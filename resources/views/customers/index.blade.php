@extends('layouts.dashboard')

@section('title', __('customers.customers_title'))

@section('page-title')
{{ __('customers.customers_title') }} <span style="font-size:12px;color:#9ca3af;font-weight:400;margin-left:10px;">{{ $isAdmin ? __('customers.all_customers_note') : __('customers.customers_under_scope_note') }}</span>
@endsection

@push('styles')
<style>
.cul-wrap{padding:6px 8px;display:flex;flex-direction:column;gap:5px;height:calc(100vh - 66px);box-sizing:border-box;overflow:hidden;}
.cul-card{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:8px 12px;}

.cul-table-card{flex:1;overflow:hidden;padding:8px 10px;min-height:0;display:flex;flex-direction:column;}
/* FIXED 19 Jul 2026 — table-layout:fixed + a <colgroup> with percentage
   widths (same pattern already used on the Sales Transaction and
   Document Template list screens) so the 6 columns always sum to 100%
   of the available width and can never push the Action/Search button
   off the right edge of the screen, no matter the window size. Without
   table-layout:fixed, each <th>'s own min-width plus its input's
   natural width could add up to wider than the container and there was
   no way to scroll right to reach it. */
.cul-table{width:100%;border-collapse:collapse;font-size:10px;table-layout:fixed;}
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

    {{-- NEW 19 Jul 2026 — per Chris: lets an agent add their own
         personal contact/lead (a Prospect) before any policy exists. --}}
    <div class="cul-card" style="flex-shrink:0; display:flex; align-items:center; justify-content:flex-end;">
        <a href="{{ route($rolePrefix . '.customers.prospects.create') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:6px 14px; font-size:10.5px; font-weight:700; white-space:nowrap;">{{ __('customers.add_prospect_button') }}</a>
    </div>

    <div class="cul-card cul-table-card">
        <form method="GET" action="{{ route($rolePrefix . '.customers.index') }}" style="display:flex;flex-direction:column;flex:1;min-height:0;">
        <table class="cul-table">
            <colgroup>
                @if($isSelfScoped)
                <col style="width:22%;"><col style="width:16%;"><col style="width:22%;">
                <col style="width:22%;"><col style="width:18%;">
                @else
                <col style="width:20%;"><col style="width:14%;"><col style="width:19%;">
                <col style="width:14%;"><col style="width:20%;"><col style="width:13%;">
                @endif
            </colgroup>
            <thead>
                <tr>
                    <th style="position:relative;">
                        <div class="cul-th-filter">
                            <span class="cul-th-label">{{ __('customers.col_customer') }}</span>
                            <input type="text" id="custSearchInput" autocomplete="off" name="search" value="{{ request('search') }}" placeholder="{{ __('gl.search_name_email_phone_placeholder') }}">
                            <div id="custTypeaheadBox" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #E2E8F0; border-radius:0 0 6px 6px; box-shadow:0 4px 10px rgba(0,0,0,.1); z-index:20; max-height:220px; overflow-y:auto;"></div>
                        </div>
                    </th>
                    {{-- FIXED 19 Jul 2026 — per Chris: this column used to
                         be labelled "Contact" with an empty filter box
                         underneath it, which looked broken since it had
                         nothing to actually filter by. Phone/email are
                         already covered by the Customer search box above,
                         so this is now just a plain display column with a
                         note instead of a fake filter input. --}}
                    <th>
                        <div class="cul-th-filter">
                            <span class="cul-th-label">{{ __('gl.col_contact') }}</span>
                            <span style="font-size:8.5px; color:#9ca3af; font-weight:400; height:22px; display:flex; align-items:center;">{{ __('customers.in_search_above_note') }}</span>
                        </div>
                    </th>
                    {{-- FIXED 19 Jul 2026 — per Chris: Location previously
                         only had an exact-match State dropdown, with no
                         way to key in an area/city as free text. Added an
                         "Area" text input under the State dropdown that
                         searches city or address. --}}
                    <th>
                        <div class="cul-th-filter">
                            <span class="cul-th-label">{{ __('gl.col_location') }}</span>
                            <select name="state">
                                <option value="">{{ __('gl.all_states_option') }}</option>
                                @foreach($states as $state)
                                <option value="{{ $state }}" {{ request('state')==$state?'selected':'' }}>{{ $state }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="area" value="{{ request('area') }}" placeholder="{{ __('customers.area_city_placeholder') }}" style="margin-top:2px;">
                        </div>
                    </th>
                    {{-- HIDDEN 19 Jul 2026 for Introducers — per Chris: an
                         Introducer's customer scope is locked to just
                         themselves (DataScopeService::getAgentIds — no
                         downline, no upline), so this dropdown can only
                         ever match one role. It stays for Admin/GL/TL,
                         whose scope genuinely spans multiple role tiers. --}}
                    @if(!$isSelfScoped)
                    <th>
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
                    @endif
                    {{-- REPLACED 19 Jul 2026 — per Chris: was a sort-order
                         dropdown (Newest/Oldest/Name A-Z...). Replaced
                         with an actual date-range filter — From / Until —
                         so an agent can pull up customers who joined
                         within a specific window. List now always shows
                         newest-first by default. --}}
                    <th style="white-space:nowrap;">
                        <div class="cul-th-filter">
                            <span class="cul-th-label">{{ __('customers.joined_from_until_label') }}</span>
                            <div style="display:flex;gap:3px;">
                                <input type="date" name="joined_from" value="{{ request('joined_from') }}" style="width:50%;">
                                <input type="date" name="joined_to" value="{{ request('joined_to') }}" style="width:50%;">
                            </div>
                        </div>
                    </th>
                    <th style="text-align:center;">
                        <div class="cul-th-filter">
                            <span class="cul-th-label">{{ __('gl.col_action') }}</span>
                            <div style="display:flex;gap:4px;">
                                <button type="submit" class="cul-btn" style="background:#1565C0;color:#fff;flex:1;">{{ __('gl.search_button') }}</button>
                                <a href="{{ route($rolePrefix . '.customers.index') }}" class="cul-btn" style="background:#F7FAFC;border:1px solid #E2E8F0;color:#4A5568;text-decoration:none;flex:1;justify-content:center;">{{ __('gl.reset_link') }}</a>
                            </div>
                        </div>
                    </th>
                </tr>
            </thead>
            @php $colCount = $isSelfScoped ? 5 : 6; @endphp
            <tbody>
                @if(!($hasQuery ?? false))
                <tr><td colspan="{{ $colCount }}" style="text-align:center;padding:40px;color:#A0AEC0;">
                    {{ __('gl.use_filters_to_search_customers_note') }}
                </td></tr>
                @elseif($customers->isEmpty())
                <tr><td colspan="{{ $colCount }}" style="text-align:center;padding:40px;color:#A0AEC0;">{{ __('gl.no_customers_found') }}</td></tr>
                @else
                @foreach($customers as $customer)
                <tr onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''">
                    <td style="font-weight:600;color:#1565C0;">
                        {{ $customer->full_name }}
                        @if(($customer->status_code ?? 'ACTIVE') !== 'ACTIVE')
                        <span style="background:#F3E8FF;color:#6B21A8;padding:1px 6px;border-radius:20px;font-size:8px;font-weight:700;margin-left:4px;">{{ strtoupper($customer->status_description ?? $customer->status_code) }}</span>
                        @endif
                        @if(!empty($customer->customer_type_description))
                        <span style="background:#DBEAFE;color:#1e40af;padding:1px 6px;border-radius:20px;font-size:8px;font-weight:700;margin-left:4px;">{{ strtoupper($customer->customer_type_description) }}</span>
                        @endif
                    </td>
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
                    @if(!$isSelfScoped)
                    <td>
                        {{ $customer->agent_name }}<br>
                        <span class="sub">{{ $customer->agent_code }}</span>
                    </td>
                    @endif
                    <td class="sub" style="white-space:nowrap;">{{ \Carbon\Carbon::parse($customer->created_at)->format('d M Y') }}</td>
                    <td style="text-align:center;white-space:nowrap;">
                        <a href="{{ route($rolePrefix . '.customers.show', $customer->customer_id) }}" style="color:#1565C0;text-decoration:none;font-weight:600;margin-right:8px;">{{ __('gl.view_link') }}</a>
                        <a href="{{ route($rolePrefix . '.customers.edit', $customer->customer_id) }}" style="color:#38A169;text-decoration:none;font-weight:600;">{{ __('gl.edit_link') }}</a>
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

{{-- NEW 19 Jul 2026 — per Chris: live type-ahead on the Customer search
     box. Picking a suggestion jumps straight to that customer's Detail
     screen instead of re-running a text search — faster when you
     already know exactly who you're looking for. --}}
<script>
(function() {
    var input = document.getElementById('custSearchInput');
    var box = document.getElementById('custTypeaheadBox');
    var timer = null;

    input.addEventListener('input', function() {
        clearTimeout(timer);
        var term = this.value.trim();
        if (term.length < 2) { box.style.display = 'none'; return; }
        timer = setTimeout(function() {
            fetch('{{ route($rolePrefix . ".customers.typeahead") }}?term=' + encodeURIComponent(term))
                .then(function(r) { return r.json(); })
                .then(function(rows) {
                    if (!rows.length) { box.style.display = 'none'; return; }
                    box.innerHTML = rows.map(function(r) {
                        return '<div class="cust-typeahead-opt" data-id="' + r.customer_id + '" style="padding:6px 8px; font-size:10.5px; cursor:pointer; border-bottom:1px solid #f3f4f6;">' +
                            '<span style="font-weight:600; color:#1565C0;">' + r.full_name + '</span>' +
                            '<span style="color:#9ca3af;"> &middot; ' + (r.phone || '') + '</span></div>';
                    }).join('');
                    box.style.display = 'block';
                    box.querySelectorAll('.cust-typeahead-opt').forEach(function(el) {
                        el.onmouseover = function() { this.style.background = '#EBF5FB'; };
                        el.onmouseout = function() { this.style.background = ''; };
                        el.onclick = function() {
                            window.location.href = '{{ route($rolePrefix . ".customers.show", ["id" => "__ID__"]) }}'.replace('__ID__', this.dataset.id);
                        };
                    });
                });
        }, 250);
    });

    document.addEventListener('click', function(e) {
        if (!box.contains(e.target) && e.target !== input) { box.style.display = 'none'; }
    });
})();
</script>
@endsection
