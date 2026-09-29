@extends('layouts.dashboard')

@section('page-title', __('customer_kpi.all_states_title'))

@section('content')

{{-- NEW 25 Jul 2026 — Customer KPI Dashboard, Box 3 "By Location"
     full drill-down. Per Chris: "the next screen you must show state in
     every row and the customer count and total amount then view at the
     right column ... click drill down to see all customer and the owner
     and amount." Sits between the Dashboard (Box 3's Top-3 states) and
     the per-state customer list — Prev here goes back to the Dashboard;
     Prev on the customer list (customer-kpi.list?state=...) comes back
     here instead of straight to the Dashboard. --}}

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; font-family:'Poppins',sans-serif; background:linear-gradient(135deg,#DFF8F7 0%,#CFF4F1 100%);">

    @php($parentUrl = route('customer-kpi.index', request()->except(['p','sort'])))
    <div style="flex-shrink:0; margin-bottom:6px;">
        <a href="{{ $parentUrl }}" style="color:#1565C0; text-decoration:none; font-size:9.5px; font-weight:600; display:inline-block; margin-bottom:4px;">&lsaquo; {{ __('customer_kpi.customer_kpi_dashboard_label') }}</a>
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px;">
            <div>
                <h4 style="font-weight:700; margin:0; font-size:13px; color:#0f5c5a;">{{ __('customer_kpi.all_states_heading') }} — {{ $sort === 'earning' ? __('customer_kpi.ranked_by_earning') : __('customer_kpi.ranked_by_sales') }}</h4>
                <div style="font-size:9px; color:#607d8b; margin-top:2px;">{{ $scopeLabel }} — {{ __('customer_kpi.states_total_note', ['count' => $rows->total()]) }}</div>
            </div>
            <div style="display:flex; gap:4px;">
                <a href="{{ route('customer-kpi.states', array_merge(request()->except(['p','sort']), ['sort'=>'sales'])) }}" style="border:1px solid #0f9c96; background:{{ $sort==='sales' ? '#0f9c96' : '#fff' }}; color:{{ $sort==='sales' ? '#fff' : '#0f9c96' }}; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:600; text-decoration:none;">{{ __('customer_kpi.sales_pill') }}</a>
                <a href="{{ route('customer-kpi.states', array_merge(request()->except(['p','sort']), ['sort'=>'earning'])) }}" style="border:1px solid #0f9c96; background:{{ $sort==='earning' ? '#0f9c96' : '#fff' }}; color:{{ $sort==='earning' ? '#fff' : '#0f9c96' }}; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:600; text-decoration:none;">{{ __('customer_kpi.earning_pill') }}</a>
            </div>
        </div>
    </div>

    <div style="background:rgba(255,255,255,0.65); backdrop-filter:blur(18px); border:1px solid rgba(255,255,255,0.4); border-radius:12px; box-shadow:0 4px 16px rgba(120,180,190,0.15); padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:8.5px;">
                <thead>
                    <tr style="background:rgba(15,156,150,0.1);">
                        <th style="text-align:left; padding:3px 8px; font-size:7.5px; color:#0f5c5a; text-transform:uppercase;">#</th>
                        <th style="text-align:left; padding:3px 8px; font-size:7.5px; color:#0f5c5a; text-transform:uppercase;">{{ __('customer_kpi.col_state') }}</th>
                        <th style="text-align:right; padding:3px 8px; font-size:7.5px; color:#0f5c5a; text-transform:uppercase;">{{ __('customer_kpi.col_customers') }}</th>
                        <th style="text-align:right; padding:3px 8px; font-size:7.5px; color:#0f5c5a; text-transform:uppercase;">{{ $sort === 'earning' ? __('gl.col_earning_income_rm') : __('customer_kpi.col_sales_rm') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $i => $row)
                    <tr style="border-bottom:1px solid rgba(15,156,150,0.1);">
                        <td style="padding:3px 8px; color:#94a3b8;">{{ $rows->firstItem() + $i }}</td>
                        <td style="padding:3px 8px; color:#111827; font-weight:600;">{{ $row->state }}</td>
                        <td style="padding:3px 8px; text-align:right; color:#607d8b;">{{ number_format($row->customers) }}</td>
                        <td style="padding:3px 8px; text-align:right; font-weight:700; color:{{ $sort==='earning' ? '#F9A825' : '#0f9c96' }};">{{ number_format($row->total, 2) }}</td>
                        <td style="padding:3px 8px; text-align:right;">
                            @php($stateParam = $row->state === 'Unspecified' ? 'UNSPECIFIED' : $row->state)
                            <a href="{{ route('customer-kpi.list', array_merge(request()->except(['p','state']), ['state'=>$stateParam])) }}" style="color:#1565C0; font-weight:600; text-decoration:none; font-size:8px;">{{ __('customers.view_link_arrow') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#94a3b8;">{{ __('customer_kpi.no_location_data_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:6px;">
            {{-- Prev is ALWAYS a live link — page 1 goes up to $parentUrl,
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
