@extends('layouts.dashboard')

@section('page-title', __('customer_kpi.transactions_title'))

@section('content')

{{-- NEW 25 Jul 2026 — Customer KPI Dashboard (task #225). Deepest
     drill-down: one customer's actual transactions — Vendor, Type
     (Product), Date, Sales, Earning — per Chris: "drill down must show
     transaction by vendor, type date, sales and earning." Paginated
     Prev/Next, one screen, no scroll. --}}

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; font-family:'Poppins',sans-serif; background:linear-gradient(135deg,#DFF8F7 0%,#CFF4F1 100%);">

    <div style="flex-shrink:0; margin-bottom:6px;">
        {{-- Plain text link, NOT a blue pill — a blue pill with a left
             arrow reads as a second Prev button sitting at the top of
             the screen. Prev/Next belong at the bottom only (see the
             pagination footer below); this is just a "go back" link.
             FIXED 26 Jul 2026 — this used to always point straight at
             the Dashboard, skipping whichever customer list this
             screen was actually opened from (a plain "View All" list,
             or a state/status-filtered one). request()->except() here
             carries over the same state/status_id/sort query params the
             list screen was on, so Prev genuinely returns to that exact
             list, not just "the top". --}}
        @php($parentUrl = route('customer-kpi.list', request()->except(['p','customerId'])))
        <a href="{{ $parentUrl }}" style="color:#1565C0; text-decoration:none; font-size:9.5px; font-weight:600; display:inline-block; margin-bottom:4px;">&lsaquo; {{ __('customer_kpi.back_to_customer_list_link') }}</a>
        <h4 style="font-weight:700; margin:0; font-size:13px; color:#0f5c5a;">
            {{-- Name -> full Customer Profile — per Chris: "when i point
                 to the customer name you allow me drill down to see the
                 entire customer profile." --}}
            <a href="{{ route($rolePrefix.'.customers.show', $customer->customer_id) }}" style="color:#0f5c5a; text-decoration:none;">{{ $customer->full_name }}</a>
        </h4>
        <div style="font-size:9px; color:#607d8b; margin-top:2px;">{{ $scopeLabel }} — {{ __('customer_kpi.transactions_total_note', ['count' => $rows->total()]) }} {{ __('customer_kpi.owner_label') }} {{ $owner->full_name ?? __('customer_kpi.unknown_word') }}@if($owner) ({{ $owner->agent_code }})@endif</div>
    </div>

    <div style="background:rgba(255,255,255,0.65); backdrop-filter:blur(18px); border:1px solid rgba(255,255,255,0.4); border-radius:12px; box-shadow:0 4px 16px rgba(120,180,190,0.15); padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:8.5px;">
                <thead>
                    <tr style="background:rgba(15,156,150,0.1);">
                        <th style="text-align:left; padding:3px 8px; font-size:7.5px; color:#0f5c5a; text-transform:uppercase;">{{ __('network.col_date') }}</th>
                        <th style="text-align:left; padding:3px 8px; font-size:7.5px; color:#0f5c5a; text-transform:uppercase;">{{ __('network.col_vendor') }}</th>
                        <th style="text-align:left; padding:3px 8px; font-size:7.5px; color:#0f5c5a; text-transform:uppercase;">{{ __('customer_kpi.col_type') }}</th>
                        <th style="text-align:right; padding:3px 8px; font-size:7.5px; color:#0f5c5a; text-transform:uppercase;">{{ __('customer_kpi.col_sales_rm') }}</th>
                        <th style="text-align:right; padding:3px 8px; font-size:7.5px; color:#0f5c5a; text-transform:uppercase;">{{ __('customer_kpi.col_earning_rm') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                    <tr style="border-bottom:1px solid rgba(15,156,150,0.1);">
                        <td style="padding:3px 8px; color:#607d8b;">{{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d M Y') }}</td>
                        <td style="padding:3px 8px; color:#111827;">{{ $r->vendor_name }}</td>
                        <td style="padding:3px 8px; color:#111827;">{{ $r->product_name }}</td>
                        <td style="padding:3px 8px; text-align:right; font-weight:600; color:#0f9c96;">{{ number_format($r->premium_amount, 2) }}</td>
                        <td style="padding:3px 8px; text-align:right; font-weight:600; color:#F9A825;">{{ number_format($r->earning, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#94a3b8;">{{ __('customer_kpi.no_transactions_in_scope_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:6px;">
            {{-- Prev is ALWAYS a live link — page 1 goes up to $parentUrl
                 (the exact customer list this screen was opened from),
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
