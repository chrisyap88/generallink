@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('title', __('admin_reports.tx_title_dash_name', ['name' => $product->product_name]))
@section('page-title', __('admin_reports.tx_page_title'))

@section('content')
{{-- Breadcrumb --}}
<div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;font-size:13px;">
    <a href="{{ route('admin.transactions') }}" style="color:#1B9AE4;text-decoration:none;">{{ __('admin_reports.tx_all_vendors') }}</a>
    <span style="color:#718096;">›</span>
    <a href="{{ route('admin.transactions.vendor', $vendor->vendor_id) }}" style="color:#1B9AE4;text-decoration:none;">{{ $vendor->vendor_name }}</a>
    <span style="color:#718096;">›</span>
    <span style="color:#0D5A8E;font-weight:700;">{{ $product->product_name }}</span>
</div>

{{-- Summary --}}
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px;">
    <div class="metric-card">
        <div class="metric-label">{{ __('admin_reports.tx_total_transactions_metric') }}</div>
        <div class="metric-value">{{ number_format($summary['total_transactions']) }}</div>
        <div class="metric-sub">{{ $product->product_name }}</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">{{ __('admin_reports.tx_total_amount_metric') }}</div>
        <div class="metric-value">RM {{ number_format($summary['total_amount'], 2) }}</div>
        <div class="metric-sub">{{ __('admin_reports.tx_this_month') }}</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">{{ __('admin_reports.tx_average_per_transaction') }}</div>
        <div class="metric-value">RM {{ $summary['total_transactions'] > 0 ? number_format($summary['total_amount'] / $summary['total_transactions'], 2) : '0.00' }}</div>
        <div class="metric-sub">{{ __('admin_reports.tx_average_amount') }}</div>
    </div>
</div>

{{-- Transactions Table --}}
<div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <div class="card-title" style="margin-bottom:0;">
            <i class="ti ti-file-invoice" style="color:#0D5A8E"></i> {{ __('admin_reports.tx_individual_transactions_suffix', ['name' => $product->product_name]) }}
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('admin.transactions.vendor', $vendor->vendor_id) }}"
               style="background:#F7FAFC;border:1px solid #E2E8F0;color:#4A5568;border-radius:7px;padding:6px 14px;font-size:12px;text-decoration:none;">
                {{ __('network.back') }}
            </a>
            <button onclick="exportExcel()" style="background:#38A169;color:#fff;border:none;border-radius:7px;padding:6px 14px;font-size:12px;cursor:pointer;">
                <i class="ti ti-download"></i> {{ __('admin_reports.tx_export_excel') }}
            </button>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;" id="transactions-table">
            <thead>
                <tr style="background:#F7FAFC;">
                    <th style="padding:10px 12px;text-align:left;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('admin_reports.tx_col_transaction_no') }}</th>
                    <th style="padding:10px 12px;text-align:left;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('admin_reports.tx_col_customer') }}</th>
                    <th style="padding:10px 12px;text-align:left;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('growth.agent') }}</th>
                    <th style="padding:10px 12px;text-align:left;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('admin_reports.tx_col_agent_code') }}</th>
                    <th style="padding:10px 12px;text-align:right;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('admin_reports.tx_col_amount_rm') }}</th>
                    <th style="padding:10px 12px;text-align:center;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('network.col_date') }}</th>
                    <th style="padding:10px 12px;text-align:center;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('network.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $tx)
                <tr style="border-bottom:1px solid #F7FAFC;" onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''">
                    <td style="padding:10px 12px;font-weight:600;color:#0D5A8E;">{{ $tx->policy_number }}</td>
                    <td style="padding:10px 12px;">{{ $tx->customer_name ?? '—' }}</td>
                    <td style="padding:10px 12px;">{{ $tx->agent_name }}</td>
                    <td style="padding:10px 12px;font-size:11px;color:#718096;">{{ $tx->agent_code }}</td>
                    <td style="padding:10px 12px;text-align:right;font-weight:700;">RM {{ number_format($tx->premium_amount, 2) }}</td>
                    <td style="padding:10px 12px;text-align:center;color:#718096;">{{ \Carbon\Carbon::parse($tx->created_at)->format('d M Y') }}</td>
                    <td style="padding:10px 12px;text-align:center;">
                        <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;
                            background:{{ $tx->status === 'ACTIVE' ? '#E8F5E9' : ($tx->status === 'PENDING' ? '#FFF8E1' : '#FDE8E8') }};
                            color:{{ $tx->status === 'ACTIVE' ? '#38A169' : ($tx->status === 'PENDING' ? '#D97706' : '#E53E3E') }};">
                            {{ $tx->status }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="padding:40px;text-align:center;color:#A0AEC0;">{{ __('admin_reports.tx_no_transactions_found') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($transactions->hasPages())
    <div style="margin-top:16px;">
        {{ $transactions->links() }}
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function exportExcel() {
    const table = document.getElementById('transactions-table');
    const wb = XLSX.utils.table_to_book(table, {sheet: 'Transactions'});
    XLSX.writeFile(wb, 'transactions_{{ Str::slug($product->product_name) }}_{{ now()->format("Ym") }}.xlsx');
}
</script>
@endpush
