@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.petty_cash_page_title'))

@section('content')

{{-- NEW 2 Sep 2026 (Task #337) — Petty Cash (imprest system). Each fund
     has its own GL sub-account (code 1150-1199), same pattern as Bank
     Accounts (Task #333) — see the migration's header comment. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.petty_cash_page_title') }}</div>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="{{ route('cbe.accounting.petty-cash-funds.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.add_petty_cash_fund_button') }}</a>
            <a href="{{ route('cbe.accounting.index') }}" onclick="history.back(); return false;" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_accounting') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="flex-shrink:0; background:#eef4fb; border-left:3px solid var(--gl-blue); color:#263238; border-radius:6px; padding:6px 10px; font-size:9.5px; margin-bottom:6px;">{{ __('cbe_accounting.petty_cash_helper_note') }}</div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_fund_name') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_custodian_name') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_float_amount') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.petty_cash_current_balance') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($funds as $f)
                    <tr style="border-bottom:1px solid #f3f4f6; cursor:pointer;" onclick="window.location='{{ route('cbe.accounting.petty-cash-funds.show', $f->fund_id) }}';">
                        <td style="padding:5px 8px; font-weight:600; color:var(--gl-blue);">{{ $f->fund_name }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $f->custodian_name }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#6b7280;">RM {{ number_format($f->float_amount, 2) }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:600; color:#263238;">RM {{ number_format($f->balance, 2) }}</td>
                        <td style="padding:5px 8px;">
                            <span style="color:{{ $f->is_active ? '#2e7d32' : '#9ca3af' }}; font-weight:600;">{{ $f->is_active ? __('cbe_records.active_label') : __('cbe_records.inactive_label') }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_petty_cash_funds_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($funds->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $funds->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $funds->currentPage(), 'last' => $funds->lastPage(), 'total' => $funds->total()]) }}</span>
            @if($funds->hasMorePages())
                <a href="{{ $funds->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
