@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_exec.box_vendor_marketplace_kpi'))

@section('content')

{{-- REDESIGNED 19 Sep 2026 -- per Chris: "follow the generallink kpi
     screen" -- exact same 3-section shape as
     admin/vendor-kpi/index.blade.php (Vendor KPI Dashboard): an
     overview tile row, a 3-column status-breakdown row, and a 2-column
     breakdown/top-N row. Same white-card style, same #1565C0 headers.
     Built from this domain's own real data (vendor link approval
     status / listing status / order payment status / listing source /
     top listings by sales) -- there's no document-verification or
     AI-risk step for CBE vendors and no "entity type" on a listing, so
     those specific cards were not force-copied 1:1. --}}

<div style="height:calc(100vh - 46px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px; box-sizing:border-box;">

    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:10px 16px; flex-shrink:0; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:13px; font-weight:700; color:#1565C0;">🛍️ {{ __('cbe_exec.box_vendor_marketplace_kpi') }}</div>
            <div style="font-size:10px; color:#6b7280;">{{ $scopeLabel }}</div>
        </div>
        <a href="{{ route('admin.cbe-kpi', $backParams) }}" style="display:inline-flex; align-items:center; gap:4px; background:var(--gl-blue); color:#fff; text-decoration:none; font-size:9.5px; font-weight:700; padding:6px 16px; border-radius:6px;">◂ {{ __('network.prev') }}</a>
    </div>


    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:8px 16px; flex-shrink:0; display:flex; align-items:center; gap:8px;">
        {{-- ADDED 19 Sep 2026 -- per Chris: "why you dont ask the selection criteria" -- lets you switch which CBE Group this screen shows, without going back to the Executive KPI Dashboard's own picker first. Drilling into one specific Branch/Temple still uses that fuller picker. --}}
        <i class="ti ti-building-community" style="color:#1565C0; font-size:13px;"></i>
        <select id="cbeGroupSelect-vendor-marketplace" style="font-size:9.5px; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; color:#263238; max-width:260px;">
            <option value="ALL" {{ ($selectedGroupId ?? 'ALL') === 'ALL' ? 'selected' : '' }}>All CBE Groups (Combined)</option>
            @foreach($groupOptions as $g)
            <option value="{{ $g->node_id }}" {{ ($selectedGroupId ?? '') === $g->group_label_id ? 'selected' : '' }}>{{ $g->group_name }}</option>
            @endforeach
        </select>
        <button type="button" onclick="var v=document.getElementById('cbeGroupSelect-vendor-marketplace').value; window.location.href = v==='ALL' ? '{{ route('admin.cbe-kpi.vendor-marketplace') }}?scope=all' : '{{ route('admin.cbe-kpi.vendor-marketplace') }}?node=' + encodeURIComponent(v);" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:9.5px; font-weight:700; cursor:pointer;">Go &rarr;</button>
    </div>

    <div style="flex:1; min-height:0; overflow-y:auto; display:flex; flex-direction:column; gap:8px;">

        {{-- Overview --}}
        <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 16px;">
            <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:8px;">{{ __('cbe_exec.box_vendor_marketplace_kpi') }}</div>
            <div style="display:grid; grid-template-columns:repeat(5,1fr); gap:8px;">
                <div style="background:#f0f9ff; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:18px; font-weight:700; color:#1565C0;">{{ number_format($vendorMarketplaceKpi['approved_vendors']) }}</div>
                    <div style="font-size:9.5px; color:#374151; margin-top:2px;">{{ __('cbe_exec.row_approved_vendors') }}</div>
                </div>
                <div style="background:#d1fae5; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:18px; font-weight:700; color:#065f46;">{{ number_format($vendorMarketplaceKpi['active_listings']) }}</div>
                    <div style="font-size:9.5px; color:#065f46; margin-top:2px;">{{ __('cbe_exec.row_active_listings') }}</div>
                </div>
                <div style="background:#fef3c7; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:18px; font-weight:700; color:#92400e;">{{ number_format($vendorMarketplaceKpi['orders_pending']) }}</div>
                    <div style="font-size:9.5px; color:#92400e; margin-top:2px;">{{ __('cbe_exec.row_orders_pending') }}</div>
                </div>
                <div style="background:#e0e7ff; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:18px; font-weight:700; color:#3730a3;">{{ number_format($vendorMarketplaceKpi['orders_paid_this_month']) }}</div>
                    <div style="font-size:9.5px; color:#3730a3; margin-top:2px;">{{ __('cbe_exec.row_orders_paid_this_month') }}</div>
                </div>
                <div style="background:#f5f3ff; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:16px; font-weight:700; color:#6D28D9;">RM {{ number_format($vendorMarketplaceKpi['revenue_paid_ytd'], 2) }}</div>
                    <div style="font-size:9.5px; color:#6D28D9; margin-top:2px;">{{ __('cbe_exec.row_revenue_paid_ytd') }}</div>
                </div>
            </div>
        </div>

        {{-- Vendor Link Status / Listing Status / Order Status --}}
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px;">
            <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 16px;">
                <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:8px;">📄 {{ __('cbe_exec.box_vendor_link_status') }}</div>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;"><span>⏳ {{ __('cbe_exec.row_vendor_link_pending') }}</span><span style="font-weight:700; color:#92400e;">{{ number_format($vendorMarketplaceKpi['vendor_link_status']['pending']) }}</span></div>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;"><span>✅ {{ __('cbe_exec.row_vendor_link_active') }}</span><span style="font-weight:700; color:#065f46;">{{ number_format($vendorMarketplaceKpi['vendor_link_status']['active']) }}</span></div>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;"><span>❌ {{ __('cbe_exec.row_vendor_link_rejected') }}</span><span style="font-weight:700; color:#991b1b;">{{ number_format($vendorMarketplaceKpi['vendor_link_status']['rejected']) }}</span></div>
                </div>
            </div>
            <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 16px;">
                <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:8px;">🛡️ {{ __('cbe_exec.box_listing_status') }}</div>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;"><span>{{ __('cbe_exec.row_listing_active') }}</span><span style="font-weight:700; color:#065f46;">{{ number_format($vendorMarketplaceKpi['listing_status']['active']) }}</span></div>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;"><span>{{ __('cbe_exec.row_listing_inactive') }}</span><span style="font-weight:700; color:#991b1b;">{{ number_format($vendorMarketplaceKpi['listing_status']['inactive']) }}</span></div>
                </div>
            </div>
            <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 16px;">
                <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:8px;">💸 {{ __('cbe_exec.box_order_status') }}</div>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;"><span>{{ __('cbe_exec.row_order_pending') }}</span><span style="font-weight:700; color:#92400e;">{{ number_format($vendorMarketplaceKpi['order_status']['pending']) }}</span></div>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;"><span>{{ __('cbe_exec.row_order_paid') }}</span><span style="font-weight:700; color:#065f46;">{{ number_format($vendorMarketplaceKpi['order_status']['paid']) }}</span></div>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;"><span>{{ __('cbe_exec.row_order_cancelled') }}</span><span style="font-weight:700; color:#991b1b;">{{ number_format($vendorMarketplaceKpi['order_status']['cancelled']) }}</span></div>
                </div>
            </div>
        </div>

        {{-- Listings by Source + Top 5 Listings by Sales --}}
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
            <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 16px;">
                <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:8px;">🏢 {{ __('cbe_exec.box_listing_source') }}</div>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;">
                        <span>{{ __('cbe_exec.row_listing_entity_own') }}</span>
                        <span style="font-weight:700; color:#374151;">{{ number_format($vendorMarketplaceKpi['listings_by_source']['entity_own']) }}</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;">
                        <span>{{ __('cbe_exec.row_listing_vendor_supplied') }}</span>
                        <span style="font-weight:700; color:#374151;">{{ number_format($vendorMarketplaceKpi['listings_by_source']['vendor_supplied']) }}</span>
                    </div>
                </div>
            </div>
            <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 16px;">
                <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:8px;">🏆 {{ __('cbe_exec.box_top5_listings') }}</div>
                @if($vendorMarketplaceKpi['top5_listings']->isEmpty())
                <div style="font-size:10.5px; color:#9ca3af;">{{ __('cbe_exec.no_data_yet') }}</div>
                @else
                <table style="width:100%; border-collapse:collapse; font-size:10.5px;">
                    <thead>
                        <tr style="border-bottom:1px solid #e0f2fe;">
                            <th style="text-align:left; padding:4px 6px; color:#6b7280; font-weight:600;">{{ __('cbe_exec.col_listing') }}</th>
                            <th style="text-align:center; padding:4px 6px; color:#6b7280; font-weight:600;">{{ __('cbe_exec.col_order_count') }}</th>
                            <th style="text-align:right; padding:4px 6px; color:#6b7280; font-weight:600;">{{ __('cbe_exec.col_total_sales') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vendorMarketplaceKpi['top5_listings'] as $i => $row)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:5px 6px; font-weight:600; word-break:break-word;">{{ $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : '')) }} {{ $row->title }}</td>
                            <td style="padding:5px 6px; text-align:center;">{{ number_format($row->order_count) }}</td>
                            <td style="padding:5px 6px; text-align:right; font-weight:700; color:#065f46;">RM {{ number_format($row->total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
