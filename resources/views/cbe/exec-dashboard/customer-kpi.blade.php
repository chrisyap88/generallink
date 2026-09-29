@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_exec.box_customer_kpi'))

@section('content')

{{-- REDESIGNED 19 Sep 2026 -- same as the admin-side twin
     (admin/cbe-kpi/customer-kpi.blade.php) -- see that file's header
     comment for the full rationale. --}}

<div style="height:calc(100vh - 46px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px; box-sizing:border-box;">

    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:10px 16px; flex-shrink:0; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#1565C0;">👥 {{ __('cbe_exec.box_customer_kpi') }}</div>
        <a href="{{ route('cbe.exec-dashboard') }}" style="display:inline-flex; align-items:center; gap:4px; background:var(--gl-blue); color:#fff; text-decoration:none; font-size:9.5px; font-weight:700; padding:6px 16px; border-radius:6px;">◂ {{ __('network.prev') }}</a>
    </div>

    <div style="flex:1; min-height:0; overflow-y:auto; display:flex; flex-direction:column; gap:8px;">

        <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 16px;">
            <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:8px;">{{ __('cbe_exec.box_customer_kpi') }}</div>
            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:8px;">
                <div style="background:#f0f9ff; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:18px; font-weight:700; color:#1565C0;">{{ number_format($customerKpi['total_customers']) }}</div>
                    <div style="font-size:9.5px; color:#374151; margin-top:2px;">{{ __('cbe_exec.row_total_customers') }}</div>
                </div>
                <div style="background:#f5f3ff; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:18px; font-weight:700; color:#6D28D9;">{{ number_format($customerKpi['total_donors']) }}</div>
                    <div style="font-size:9.5px; color:#6D28D9; margin-top:2px;">{{ __('cbe_exec.row_total_donors') }}</div>
                </div>
                <div style="background:#d1fae5; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:18px; font-weight:700; color:#065f46;">{{ number_format($customerKpi['linked_to_member']) }}</div>
                    <div style="font-size:9.5px; color:#065f46; margin-top:2px;">{{ __('cbe_exec.row_linked_to_member') }}</div>
                </div>
                <div style="background:#e0e7ff; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:18px; font-weight:700; color:#3730a3;">{{ number_format($customerKpi['new_this_month']) }}</div>
                    <div style="font-size:9.5px; color:#3730a3; margin-top:2px;">{{ __('cbe_exec.row_new_customers_this_month') }}</div>
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
            <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 16px;">
                <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:8px;">🏆 {{ __('cbe_exec.box_top3_customers') }}</div>
                @if($customerKpi['top3_by_sales']->isEmpty())
                <div style="font-size:10.5px; color:#9ca3af;">{{ __('cbe_exec.no_data_yet') }}</div>
                @else
                <table style="width:100%; border-collapse:collapse; font-size:10.5px;">
                    <thead>
                        <tr style="border-bottom:1px solid #e0f2fe;">
                            <th style="text-align:left; padding:4px 6px; color:#6b7280; font-weight:600;">{{ __('cbe_exec.col_customer') }}</th>
                            <th style="text-align:right; padding:4px 6px; color:#6b7280; font-weight:600;">{{ __('cbe_exec.col_sales_amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($customerKpi['top3_by_sales'] as $i => $row)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:5px 6px; font-weight:600; word-break:break-word;">{{ $i === 0 ? '🥇' : ($i === 1 ? '🥈' : '🥉') }} {{ $row->customer_name }}</td>
                            <td style="padding:5px 6px; text-align:right; font-weight:700; color:#065f46;">RM {{ number_format($row->total_sales, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
            <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 16px;">
                <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:8px;">🧾 {{ __('cbe_exec.box_invoice_status') }}</div>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;"><span>{{ __('cbe_exec.row_invoice_unpaid') }}</span><span style="font-weight:700; color:#991b1b;">{{ number_format($customerKpi['invoice_status']['unpaid']) }}</span></div>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;"><span>{{ __('cbe_exec.row_invoice_partial') }}</span><span style="font-weight:700; color:#92400e;">{{ number_format($customerKpi['invoice_status']['partially_paid']) }}</span></div>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;"><span>{{ __('cbe_exec.row_invoice_paid') }}</span><span style="font-weight:700; color:#065f46;">{{ number_format($customerKpi['invoice_status']['paid']) }}</span></div>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;"><span>{{ __('cbe_exec.row_invoice_cancelled') }}</span><span style="font-weight:700; color:#6b7280;">{{ number_format($customerKpi['invoice_status']['cancelled']) }}</span></div>
                </div>
            </div>
        </div>

        <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 16px;">
            <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:8px;">📊 {{ __('cbe_exec.box_performance_by_node') }}</div>
            @if($customerKpi['by_node']->isEmpty())
            <div style="font-size:10.5px; color:#9ca3af;">{{ __('cbe_exec.no_data_yet') }}</div>
            @else
            <table style="width:100%; border-collapse:collapse; font-size:10.5px;">
                <thead>
                    <tr style="border-bottom:1px solid #e0f2fe;">
                        <th style="text-align:left; padding:4px 6px; color:#6b7280; font-weight:600;">{{ __('cbe_exec.col_node') }}</th>
                        <th style="text-align:center; padding:4px 6px; color:#6b7280; font-weight:600;">{{ __('cbe_exec.col_customers') }}</th>
                        <th style="text-align:right; padding:4px 6px; color:#6b7280; font-weight:600;">{{ __('cbe_exec.col_sales_amount') }}</th>
                        <th style="text-align:right; padding:4px 6px; color:#6b7280; font-weight:600;">{{ __('cbe_exec.col_paid_amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customerKpi['by_node'] as $row)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 6px; font-weight:600; word-break:break-word;">{{ $row->node_name }}</td>
                        <td style="padding:5px 6px; text-align:center;">{{ number_format($row->customer_count) }}</td>
                        <td style="padding:5px 6px; text-align:right;">RM {{ number_format($row->total_sales, 2) }}</td>
                        <td style="padding:5px 6px; text-align:right; font-weight:700; color:#065f46;">RM {{ number_format($row->total_paid, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

    </div>
</div>
@endsection
