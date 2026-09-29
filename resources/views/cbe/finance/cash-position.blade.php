@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.tile_cash_position'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.tile_cash_position') }} — {{ \Carbon\Carbon::parse($asOf)->format('d M Y') }}</div>
        <a href="{{ route('cbe.finance.bank-accounts') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_bank_accounts') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_bank') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_account_type') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_current_balance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $a)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; font-weight:600; color:#263238;">{{ $a->bank_name }}@if($a->account_name)<span style="color:#9ca3af;"> — {{ $a->account_name }}</span>@endif</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ __('cbe_records.acct_type_'.strtolower($a->account_type ?: 'bank_current')) }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:700; color:#263238;">RM {{ number_format($a->current_balance, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_records.no_bank_accounts_note') }}</td></tr>
                    @endforelse
                </tbody>
                @if($accounts->count())
                <tfoot>
                    <tr style="border-top:2px solid #263238;">
                        <td colspan="2" style="padding:6px 8px; font-weight:700; color:#263238;">{{ __('cbe_records.col_total') }}</td>
                        <td style="padding:6px 8px; text-align:right; font-weight:700; color:#263238;">RM {{ number_format($total, 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
