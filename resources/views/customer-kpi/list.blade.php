@extends('layouts.dashboard')

@section('page-title', __('customer_kpi.view_all_title'))

@section('content')

{{-- NEW 25 Jul 2026 — Customer KPI Dashboard (task #225). "View All"
     ranked list — every customer in the current scope/filter, sorted by
     Sales or Earning Income, paginated Prev/Next, one screen no-scroll.
     Same visual language as the index screen. --}}

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; font-family:'Poppins',sans-serif; background:linear-gradient(135deg,#DFF8F7 0%,#CFF4F1 100%);">

    <div style="flex-shrink:0; margin-bottom:6px;">
        {{-- Plain text link, NOT a blue pill — a blue pill with a left
             arrow reads as a second Prev button sitting at the top of
             the screen, which is exactly what Chris flagged. Prev/Next
             belong at the bottom only; this is just a "go back" link.
             NEW 25 Jul 2026 — per Chris's drill-down chain ("click prev
             back to all customer row click prev back to state click
             prev back to dashboard"): if this list was reached via a
             state/status drill-down, go back to that intermediate
             screen, not straight to the Dashboard. --}}
        {{-- 26 Jul 2026 — $parentUrl/$parentLabel computed once here and
             reused both by this top breadcrumb AND by the bottom Prev
             button on page 1 (see footer below), so Prev always does
             something real instead of going dead-gray on page 1 — this
             is exactly how the main KPI Dashboard's own drill-down
             screens behave (dashboard/drilldown.blade.php: Prev on page
             1 goes to the parent screen, never a disabled placeholder). --}}
        @php($state = trim((string) request('state', '')))
        @php($statusId = trim((string) request('status_id', '')))
        @if($state !== '')
            @php($parentUrl = route('customer-kpi.states', request()->except(['p','sort','state'])))
            @php($parentLabel = __('customer_kpi.all_states_label'))
        @elseif($statusId !== '')
            @php($parentUrl = route('customer-kpi.statuses', request()->except(['p','sort','status_id'])))
            @php($parentLabel = __('customer_kpi.all_statuses_label'))
        @else
            @php($parentUrl = route('customer-kpi.index', request()->except(['p','sort','state'])))
            @php($parentLabel = __('customer_kpi.customer_kpi_dashboard_label'))
        @endif
        <a href="{{ $parentUrl }}" style="color:#1565C0; text-decoration:none; font-size:9.5px; font-weight:600; display:inline-block; margin-bottom:4px;">&lsaquo; {{ $parentLabel }}</a>
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px;">
            <div>
                <h4 style="font-weight:700; margin:0; font-size:13px; color:#0f5c5a;">{{ __('customer_kpi.all_customers_heading') }}{{ isset($stateLabel) && $stateLabel ? ' — ' . $stateLabel : '' }} — {{ $sort === 'earning' ? __('customer_kpi.ranked_by_earning') : __('customer_kpi.ranked_by_sales') }}</h4>
                <div style="font-size:9px; color:#607d8b; margin-top:2px;">{{ $scopeLabel }} — {{ __('customer_kpi.customers_total_note', ['count' => $rows->total()]) }}</div>
            </div>
            <div style="display:flex; gap:4px;">
                <a href="{{ route('customer-kpi.list', array_merge(request()->except(['p','sort']), ['sort'=>'sales'])) }}" style="border:1px solid #0f9c96; background:{{ $sort==='sales' ? '#0f9c96' : '#fff' }}; color:{{ $sort==='sales' ? '#fff' : '#0f9c96' }}; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:600; text-decoration:none;">{{ __('customer_kpi.sales_pill') }}</a>
                <a href="{{ route('customer-kpi.list', array_merge(request()->except(['p','sort']), ['sort'=>'earning'])) }}" style="border:1px solid #0f9c96; background:{{ $sort==='earning' ? '#0f9c96' : '#fff' }}; color:{{ $sort==='earning' ? '#fff' : '#0f9c96' }}; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:600; text-decoration:none;">{{ __('customer_kpi.earning_pill') }}</a>
            </div>
        </div>
    </div>

    <div style="background:rgba(255,255,255,0.65); backdrop-filter:blur(18px); border:1px solid rgba(255,255,255,0.4); border-radius:12px; box-shadow:0 4px 16px rgba(120,180,190,0.15); padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:8.5px;">
                <thead>
                    <tr style="background:rgba(15,156,150,0.1);">
                        <th style="text-align:left; padding:3px 8px; font-size:7.5px; color:#0f5c5a; text-transform:uppercase;">#</th>
                        <th style="text-align:left; padding:3px 8px; font-size:7.5px; color:#0f5c5a; text-transform:uppercase;">{{ __('gl.col_customer') }}</th>
                        <th style="text-align:left; padding:3px 8px; font-size:7.5px; color:#0f5c5a; text-transform:uppercase;">{{ __('customer_kpi.col_owner') }}</th>
                        <th style="text-align:right; padding:3px 8px; font-size:7.5px; color:#0f5c5a; text-transform:uppercase;">{{ $sort === 'earning' ? __('gl.col_earning_income_rm') : __('customer_kpi.col_sales_rm') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $i => $row)
                    <tr style="border-bottom:1px solid rgba(15,156,150,0.1);">
                        <td style="padding:3px 8px; color:#94a3b8;">{{ $rows->firstItem() + $i }}</td>
                        <td style="padding:3px 8px;">
                            {{-- Name -> full Customer Profile (Profile/Sales
                                 History/Reminders/Activity Log/KPI Dashboard),
                                 per Chris: "when i point to the customer name
                                 you allow me drill down to see the entire
                                 customer profile." The scoped transaction
                                 list this screen builds is still one click
                                 away via "Transactions". --}}
                            <a href="{{ route($rolePrefix.'.customers.show', $row->customer_id) }}" style="color:#0f5c5a; font-weight:600; text-decoration:none;">{{ $row->full_name }}</a>
                            <a href="{{ route('customer-kpi.detail', array_merge(request()->except('p'), ['customerId'=>$row->customer_id])) }}" style="color:#94a3b8; font-size:7px; text-decoration:none; margin-left:6px;">{{ __('customer_kpi.transactions_link') }}</a>
                        </td>
                        <td style="padding:3px 8px; color:#607d8b;">{{ $row->owner_name }} ({{ $row->owner_code }})</td>
                        <td style="padding:3px 8px; text-align:right; font-weight:700; color:{{ $sort==='earning' ? '#F9A825' : '#0f9c96' }};">{{ number_format($row->total, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="padding:16px; text-align:center; color:#94a3b8;">{{ __('customer_kpi.no_customers_in_scope_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:6px;">
            {{-- Prev is ALWAYS a live link, never disabled — page 1 goes
                 up to $parentUrl (same target as the breadcrumb above),
                 matching the main KPI Dashboard's own Prev behavior. --}}
            <a href="{{ $rows->onFirstPage() ? $parentUrl : $rows->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.prev') }}</a>
            <span style="font-size:9px; color:#607d8b;">{{ __('customer_kpi.page_x_of_y', ['current' => $rows->currentPage(), 'last' => $rows->lastPage()]) }}</span>
            @if($rows->hasMorePages())
                <a href="{{ $rows->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
