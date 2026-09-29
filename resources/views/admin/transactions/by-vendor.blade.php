@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('title', __('admin_reports.tx_title_dash_name', ['name' => $vendor->vendor_name]))
@section('page-title', __('admin_reports.tx_page_title'))

@section('content')
{{-- Breadcrumb --}}
<div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;font-size:13px;">
    <a href="{{ route('admin.transactions') }}" style="color:#1B9AE4;text-decoration:none;">{{ __('admin_reports.tx_all_vendors') }}</a>
    <span style="color:#718096;">›</span>
    <span style="color:#0D5A8E;font-weight:700;">{{ $vendor->vendor_name }}</span>
</div>

{{-- Summary --}}
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px;">
    <div class="metric-card">
        <div class="metric-label">{{ __('admin_reports.tx_total_transactions_metric') }}</div>
        <div class="metric-value">{{ number_format($summary['total_transactions']) }}</div>
        <div class="metric-sub">{{ __('admin_reports.tx_vendor_this_month', ['name' => $vendor->vendor_name]) }}</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">{{ __('admin_reports.tx_total_amount_metric') }}</div>
        <div class="metric-value">RM {{ number_format($summary['total_amount'], 2) }}</div>
        <div class="metric-sub">{{ __('admin_reports.tx_all_products') }}</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">{{ __('admin_reports.tx_product_categories') }}</div>
        <div class="metric-value">{{ count($categories) }}</div>
        <div class="metric-sub">{{ __('admin_reports.tx_active_this_month') }}</div>
    </div>
</div>

{{-- Categories Table --}}
<div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <div class="card-title" style="margin-bottom:0;">
            <i class="ti ti-category" style="color:#0D5A8E"></i> {{ __('admin_reports.tx_product_categories_dash_name', ['name' => $vendor->vendor_name]) }}
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('admin.transactions') }}" style="background:#F7FAFC;border:1px solid #E2E8F0;color:#4A5568;border-radius:7px;padding:6px 14px;font-size:12px;text-decoration:none;">
                {{ __('network.back') }}
            </a>
            <button onclick="exportExcel()" style="background:#38A169;color:#fff;border:none;border-radius:7px;padding:6px 14px;font-size:12px;cursor:pointer;">
                <i class="ti ti-download"></i> {{ __('admin_reports.tx_export_excel') }}
            </button>
        </div>
    </div>

    @if(count($categories) === 0)
        <div style="text-align:center;color:#A0AEC0;padding:40px 0;font-size:13px;">{{ __('admin_reports.tx_no_transactions_found') }}</div>
    @else
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;" id="categories-table">
                <thead>
                    <tr style="background:#F7FAFC;">
                        <th style="padding:10px 12px;text-align:left;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('admin_reports.tx_col_product_category') }}</th>
                        <th style="padding:10px 12px;text-align:right;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('admin_reports.tx_col_transactions') }}</th>
                        <th style="padding:10px 12px;text-align:right;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('admin_reports.tx_col_total_amount_rm') }}</th>
                        <th style="padding:10px 12px;text-align:right;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('admin_reports.tx_col_pct_of_vendor') }}</th>
                        <th style="padding:10px 12px;text-align:center;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('admin_reports.tx_col_action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($categories as $cat)
                    <tr style="border-bottom:1px solid #F7FAFC;cursor:pointer;"
                        onclick="window.location='{{ route('admin.transactions.product', [$vendor->vendor_id, $cat->product_id]) }}'"
                        onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''">
                        <td style="padding:10px 12px;">
                            <div style="font-weight:600;color:#2D3748;">{{ $cat->product_name }}</div>
                            <div style="font-size:11px;color:#718096;">{{ $cat->category }}</div>
                        </td>
                        <td style="padding:10px 12px;text-align:right;font-weight:600;">{{ number_format($cat->total_transactions) }}</td>
                        <td style="padding:10px 12px;text-align:right;font-weight:700;color:#0D5A8E;">RM {{ number_format($cat->total_amount, 2) }}</td>
                        <td style="padding:10px 12px;text-align:right;color:#718096;">
                            {{ $summary['total_amount'] > 0 ? number_format(($cat->total_amount / $summary['total_amount']) * 100, 1) : 0 }}%
                        </td>
                        <td style="padding:10px 12px;text-align:center;">
                            <a href="{{ route('admin.transactions.product', [$vendor->vendor_id, $cat->product_id]) }}"
                               style="background:#1B9AE4;color:#fff;border-radius:6px;padding:4px 12px;font-size:11px;text-decoration:none;font-weight:600;">
                                {{ __('admin_reports.tx_view_arrow') }}
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background:#F0F9FF;font-weight:700;">
                        <td style="padding:10px 12px;color:#0D5A8E;">{{ __('admin_reports.tx_total_label') }}</td>
                        <td style="padding:10px 12px;text-align:right;color:#0D5A8E;">{{ number_format($summary['total_transactions']) }}</td>
                        <td style="padding:10px 12px;text-align:right;color:#0D5A8E;">RM {{ number_format($summary['total_amount'], 2) }}</td>
                        <td style="padding:10px 12px;text-align:right;color:#0D5A8E;">100%</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function exportExcel() {
    const table = document.getElementById('categories-table');
    const wb = XLSX.utils.table_to_book(table, {sheet: 'Categories'});
    XLSX.writeFile(wb, 'transactions_{{ Str::slug($vendor->vendor_name) }}_{{ now()->format("Ym") }}.xlsx');
}
</script>
@endpush
