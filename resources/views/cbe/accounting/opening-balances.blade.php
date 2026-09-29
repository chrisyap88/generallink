@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.tile_opening_balances'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_opening_balances') }}</div>
        <a href="{{ route('cbe.accounting.index') }}" onclick="history.back(); return false;" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.accounting.opening-balances.store') }}" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            <div style="flex-shrink:0; display:flex; gap:8px; align-items:flex-end; margin-bottom:6px;">
                <div style="width:170px;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_ob_as_of_date') }}</label>
                    <input type="date" name="as_of_date" value="{{ old('as_of_date', now()->toDateString()) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="flex:1; font-size:8px; color:#9ca3af;">{{ __('cbe_accounting.ob_helper_note') }}</div>
            </div>

            {{-- Chart of Accounts can grow past what fits on screen (each
                 bank account/petty cash fund adds its own row), and every
                 one must be included so the debit/credit totals below are
                 always the true total — so unlike other screens, this
                 list scrolls internally rather than risk silently
                 dropping an account's opening balance off-screen. --}}
            <div style="flex:1; min-height:0; overflow-y:auto; border:1px solid #f3f4f6; border-radius:6px;">
                <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                    <thead>
                        <tr style="background:var(--gl-light); position:sticky; top:0;">
                            <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_code') }}</th>
                            <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_name') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase; width:110px;">{{ __('cbe_accounting.col_jv_debit') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase; width:110px;">{{ __('cbe_accounting.col_jv_credit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($accounts as $a)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:3px 6px; color:#6b7280;">{{ $a->account_code }}</td>
                            <td style="padding:3px 6px; color:#263238;">
                                {{ $a->account_name }}
                                <input type="hidden" name="line_account[]" value="{{ $a->account_id }}">
                            </td>
                            <td style="padding:3px 6px;">
                                <input type="number" step="0.01" min="0" name="line_debit[]" value="{{ old('line_debit.'.$loop->index) }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:9.5px; box-sizing:border-box; text-align:right;">
                            </td>
                            <td style="padding:3px 6px;">
                                <input type="number" step="0.01" min="0" name="line_credit[]" value="{{ old('line_credit.'.$loop->index) }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:9.5px; box-sizing:border-box; text-align:right;">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.accounting.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
