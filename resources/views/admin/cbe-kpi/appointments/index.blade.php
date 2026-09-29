@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __($faithTerms['tab_label_key']))

@section('content')

<style>
.cbd-box{flex:1; min-height:0; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; box-shadow:0 2px 8px rgba(21,101,192,0.08); display:flex; flex-direction:column; overflow:hidden; box-sizing:border-box;}
.cbd-field{display:flex; flex-direction:column; gap:3px;}
.cbd-field label{font-size:8px; font-weight:700; color:#6b7280; text-transform:uppercase;}
.cbd-field input, .cbd-field select{font-family:'Poppins',sans-serif; font-size:9px; color:#263238; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; box-sizing:border-box; width:100%;}
.cbd-btn{background:var(--gl-blue); color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:9px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;}
.cbd-btn-outline{background:var(--gl-light); color:var(--gl-blue); border:1px solid var(--gl-cyan2); border-radius:5px; padding:6px 16px; font-size:9px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;}
.cbd-row{display:flex; align-items:center; justify-content:space-between; padding:6px 12px; border-bottom:1px solid #eef2f7; font-size:9px;}
.cbd-row:last-child{border-bottom:none;}
.cbd-pg-btn{background:#0D5A8E; color:#fff; text-decoration:none; border-radius:5px; padding:4px 12px; font-size:9px; font-weight:700;}
.cbd-pg-btn-disabled{background:#f3f4f6; color:#9ca3af; border-radius:5px; padding:4px 12px; font-size:9px; font-weight:700;}
.cbd-back-btn{display:inline-flex; align-items:center; gap:4px; background:var(--gl-blue); color:#fff; text-decoration:none; font-size:8.5px; font-weight:700; padding:5px 14px; border-radius:5px;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; gap:7px;">

    <div style="flex-shrink:0;">
        <div style="font-size:13px; font-weight:700; color:#263238; white-space:nowrap;">{{ __($faithTerms['tab_label_key']) }}</div>
        <div style="font-size:9px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $nodePrimary }}{{ $nodeSecondary ? ' ('.$nodeSecondary.')' : '' }}</div>
    </div>

    @include('admin.cbe-kpi.partials.persistent-tabs', ['activeTab' => 'appointments', 'primaryTabKey' => $primaryTabKey, 'primaryTabLabel' => $primaryTabLabel, 'primaryTabRoute' => $primaryTabRoute])

    <div class="cbd-box" style="flex:0 0 auto; padding:10px 12px;">
        <form method="GET" action="{{ route('admin.cbe-kpi.appointments') }}" autocomplete="off">
            <input type="hidden" name="node" value="{{ $node->node_id }}">
            <input type="hidden" name="mode" value="search">
            <input type="hidden" name="do_search" value="1">
            <input type="hidden" name="from" value="{{ $primaryTabKey }}">
            <div style="font-size:8.5px; color:#94A3B8; margin-bottom:8px;">{{ __('admin_cbe_directory.search_prompt') }}</div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <div class="cbd-field" style="flex:1.2; min-width:160px;">
                    <label>{{ __('admin_cbe_directory.search_person_name') }}</label>
                    <input type="text" name="person_name" value="{{ request('person_name') }}">
                </div>
                <div class="cbd-field" style="flex:1.2; min-width:160px;">
                    <label>{{ __('admin_cbe_directory.label_practitioner_name', ['term' => __($faithTerms['practitioner_label_key'])]) }}</label>
                    <input type="text" name="advisor_name" value="{{ request('advisor_name') }}">
                </div>
                <div class="cbd-field" style="flex:1; min-width:140px;">
                    {{-- CHANGED 12 Sep 2026 — per Chris: this dropdown was
                    hardcoded to PRAYER/COUNSELING/BLESSING/OTHER, which
                    only made sense for a temple. Now built from every
                    reason across every position this community has
                    enabled (each position's own admin-editable list) —
                    never a fixed set. --}}
                    <label>{{ __('admin_cbe_directory.search_appointment_type') }}</label>
                    <select name="appointment_type">
                        <option value="">{{ __('admin_cbe_directory.select_all') }}</option>
                        @foreach($allReasons ?? [] as $reason)
                            <option value="{{ $reason }}" @selected(request('appointment_type')===$reason)>{{ $reason }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="cbd-field" style="flex:0.8; min-width:120px;">
                    <label>{{ __('admin_cbe_directory.search_date_from') }}</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}">
                </div>
                <div class="cbd-field" style="flex:0.8; min-width:120px;">
                    <label>{{ __('admin_cbe_directory.search_date_to') }}</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}">
                </div>
                <div class="cbd-field" style="flex:1.4; min-width:170px;">
                    <label>{{ __('admin_cbe_directory.search_notes') }}</label>
                    <input type="text" name="notes" value="{{ request('notes') }}">
                </div>
            </div>
            <div style="display:flex; gap:8px; margin-top:10px;">
                <button type="submit" class="cbd-btn">🔍 {{ __('admin_cbe_directory.btn_search') }}</button>
                @if($doSearch)
                <a href="{{ route('admin.cbe-kpi.appointments', ['node' => $node->node_id, 'from' => $primaryTabKey]) }}" class="cbd-btn-outline">{{ __('admin_cbe_directory.btn_new_search') }}</a>
                @endif
            </div>
        </form>
    </div>

    @if($doSearch)
    <div class="cbd-box">
        <div style="flex-shrink:0; padding:8px 12px; font-size:8.5px; color:#6b7280; border-bottom:1px solid #eef2f7;">{{ __('admin_cbe_directory.results_appointments_found', ['count' => $results->total()]) }}</div>
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($results as $r)
            @php
                $personName = $r->agent_name ?? $r->customer_name ?? $r->donor_name;
                $personUrl = $r->agent_name
                    ? route('admin.cbe-kpi.members.show', ['node' => $node->node_id, 'id' => $r->membership_id])
                    : ($r->customer_name
                        ? route('admin.cbe-kpi.customers.show', ['node' => $node->node_id, 'id' => $r->customer_id])
                        : ($r->donor_name ? route('admin.cbe-kpi.donors.show', ['node' => $node->node_id, 'id' => $r->donor_id]) : null));
            @endphp
            <div class="cbd-row">
                <div style="min-width:0; display:flex; gap:10px; flex:1;">
                    <span style="font-weight:600; color:#263238; flex:1.1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $personName ?: '—' }}</span>
                    <span style="color:var(--gl-blue); font-weight:600; flex:0.9; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $r->advisor_name }}</span>
                    {{-- CHANGED 12 Sep 2026 — appointment_type is now
                    free text (an Admin-typed reason from the booked
                    position's own list), not a fixed translation-key
                    code — shown as-is. --}}
                    <span style="color:#6b7280; flex:0.8;">{{ $r->appointment_type }}</span>
                    <span style="color:#94A3B8; flex:0.7;">{{ \Carbon\Carbon::parse($r->appointment_date)->format('d M Y') }}</span>
                    <span style="color:#6b7280; flex:1.3; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $r->notes ?: '—' }}</span>
                </div>
                @if($personUrl)
                <a href="{{ $personUrl }}" class="cbd-btn" style="padding:4px 12px;">{{ __('admin_cbe_directory.btn_view') }}</a>
                @endif
            </div>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('admin_cbe_directory.no_results') }}</div>
            @endforelse
        </div>
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding:8px 12px; border-top:1px solid #eef2f7;">
            <span style="font-size:8.5px; color:#718096;">{{ $results->firstItem() ?? 0 }}–{{ $results->lastItem() ?? 0 }} {{ __('admin_cbe_kpi.of_total', ['total' => $results->total()]) }}</span>
            <div style="display:flex; gap:5px;">
                @if($results->onFirstPage())
                    <span class="cbd-pg-btn-disabled">← {{ __('admin_cbe_kpi.prev') }}</span>
                @else
                    <a href="{{ $results->previousPageUrl() }}" class="cbd-pg-btn">← {{ __('admin_cbe_kpi.prev') }}</a>
                @endif
                @if($results->hasMorePages())
                    <a href="{{ $results->nextPageUrl() }}" class="cbd-pg-btn">{{ __('admin_cbe_kpi.next') }} →</a>
                @else
                    <span class="cbd-pg-btn-disabled">{{ __('admin_cbe_kpi.next') }} →</span>
                @endif
            </div>
        </div>
    </div>
    @else
    <div style="flex:1; display:flex; align-items:center; justify-content:center;">
        <div style="text-align:center; color:#94A3B8; font-size:10px; max-width:420px;">{{ __('admin_cbe_directory.search_prompt') }}</div>
    </div>
    @endif

    <div style="flex-shrink:0;">
        <a href="{{ route('admin.cbe-kpi', ['node' => $node->node_id]) }}" class="cbd-back-btn"><i class="ti ti-arrow-back-up"></i> {{ __('admin_cbe_directory.back_to_temple') }}</a>
    </div>
</div>
@endsection
