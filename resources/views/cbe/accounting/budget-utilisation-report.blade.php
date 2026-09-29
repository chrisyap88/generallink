@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.budget_utilisation_report_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.budget_utilisation_report_title') }}</div>
        <a href="{{ route('cbe.accounting.index') }}" onclick="history.back(); return false;" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_accounting') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">

        <div style="flex-shrink:0; display:flex; justify-content:center; align-items:center; gap:16px; margin-bottom:8px;">
            <a href="{{ route('cbe.accounting.reports.budget-utilisation', ['year' => $year - 1]) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            <span style="font-size:12.5px; font-weight:700; color:#263238;">{{ $year }}</span>
            <a href="{{ route('cbe.accounting.reports.budget-utilisation', ['year' => $year + 1]) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
        </div>

        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:8.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_budget_name') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_pr_cost_centre') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_pr_fund') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_budget_amount') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_budget_commitment') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_budget_actual_invoice') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_budget_actual_payment') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_budget_remaining') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_budget_utilisation') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 6px; font-weight:600; color:#263238;">{{ $r->budget_name ?: '—' }}</td>
                        <td style="padding:5px 6px; color:#6b7280;">{{ $r->centre_name ?: __('cbe_records.none_option') }}</td>
                        <td style="padding:5px 6px; color:#6b7280;">{{ $r->fund_name ?: __('cbe_records.none_option') }}</td>
                        <td style="padding:5px 6px; text-align:right; color:#263238;">{{ number_format($r->budget_amount, 2) }}</td>
                        <td style="padding:5px 6px; text-align:right; color:#D97706;">{{ number_format($r->commitment, 2) }}</td>
                        <td style="padding:5px 6px; text-align:right; color:#1565C0;">{{ number_format($r->actual_invoice, 2) }}</td>
                        <td style="padding:5px 6px; text-align:right; color:#6b7280;">{{ number_format($r->actual_payment, 2) }}</td>
                        <td style="padding:5px 6px; text-align:right; font-weight:700; color:{{ $r->remaining < 0 ? '#c62828' : '#2e7d32' }};">{{ number_format($r->remaining, 2) }}</td>
                        <td style="padding:5px 6px; text-align:right; font-weight:600; color:{{ $r->utilisation > 100 ? '#c62828' : '#263238' }};">{{ number_format($r->utilisation, 1) }}%</td>
                    </tr>
                    @empty
                    <tr><td colspan="9" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_budgets_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
