@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.petty_cash_detail_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ $fund->fund_name }}</div>
        <a href="{{ route('cbe.accounting.petty-cash-funds') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 12px; flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">

        {{-- Summary strip --}}
        <div style="flex-shrink:0; display:flex; align-items:center; gap:16px; background:var(--gl-light); border-radius:6px; padding:7px 10px; margin-bottom:7px; font-size:10px;">
            <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_custodian_name') }}</span><br>{{ $fund->custodian_name }}</div>
            <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_float_amount') }}</span><br>RM {{ number_format($fund->float_amount, 2) }}</div>
            <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.petty_cash_current_balance') }}</span><br><span style="color:#2e7d32; font-weight:700;">RM {{ number_format($balance, 2) }}</span></div>
            <div style="flex:1;"></div>
        </div>

        {{-- Top-Up (establish/replenish) --}}
        <form method="POST" action="{{ route('cbe.accounting.petty-cash-funds.topup', $fund->fund_id) }}" style="flex-shrink:0; display:flex; gap:6px; align-items:flex-end; margin-bottom:8px; padding-bottom:8px; border-bottom:1px solid #f3f4f6;">
            @csrf
            <div style="width:110px;">
                <label style="display:block; font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_topup_date') }}</label>
                <input type="date" name="topup_date" value="{{ now()->toDateString() }}" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:9.5px; box-sizing:border-box;">
            </div>
            <div style="width:110px;">
                <label style="display:block; font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_amount') }}</label>
                <input type="number" step="0.01" min="0.01" name="amount" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:9.5px; box-sizing:border-box;">
            </div>
            <div style="width:170px;">
                <label style="display:block; font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.petty_cash_topup_source') }}</label>
                <select name="bank_account_id" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:9.5px; box-sizing:border-box;">
                    <option value="">{{ __('cbe_accounting.field_bank_account_default') }}</option>
                    @foreach($bankAccounts as $b)
                    <option value="{{ $b->bank_account_id }}">{{ $b->bank_name }}@if($b->account_name) — {{ $b->account_name }}@endif</option>
                    @endforeach
                </select>
            </div>
            <div style="flex:1;">
                <label style="display:block; font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_notes') }}</label>
                <input type="text" name="notes" maxlength="255" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:9.5px; box-sizing:border-box;">
            </div>
            <button type="submit" style="background:#38A169; color:#fff; border:none; border-radius:14px; padding:5px 14px; font-size:9.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_accounting.petty_cash_topup_button') }}</button>
        </form>

        {{-- Voucher entry --}}
        <form method="POST" action="{{ route('cbe.accounting.petty-cash-funds.vouchers.store', $fund->fund_id) }}" style="flex-shrink:0; margin-bottom:8px;">
            @csrf
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                <label style="font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_voucher_date') }}</label>
                <input type="date" name="voucher_date" value="{{ now()->toDateString() }}" required style="border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:9.5px; width:120px;">
                <div style="font-size:8px; color:#9ca3af;">{{ __('cbe_accounting.petty_cash_voucher_helper_note') }}</div>
            </div>
            <table style="width:100%; border-collapse:collapse; font-size:9px;">
                <thead>
                    <tr>
                        <th style="text-align:left; padding:2px 4px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_payee') }}</th>
                        <th style="text-align:left; padding:2px 4px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_line_description') }}</th>
                        <th style="text-align:left; padding:2px 4px; font-size:8px; color:#546E7A; text-transform:uppercase; width:170px;">{{ __('cbe_accounting.field_category') }}</th>
                        <th style="text-align:right; padding:2px 4px; font-size:8px; color:#546E7A; text-transform:uppercase; width:100px;">{{ __('cbe_accounting.field_amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @for($i = 0; $i < 3; $i++)
                    <tr>
                        <td style="padding:2px 4px;"><input type="text" name="line_payee[]" maxlength="150" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9px; box-sizing:border-box;"></td>
                        <td style="padding:2px 4px;"><input type="text" name="line_description[]" maxlength="255" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9px; box-sizing:border-box;"></td>
                        <td style="padding:2px 4px;">
                            <select name="line_category[]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9px; box-sizing:border-box;">
                                <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                                @foreach($categories as $c)
                                <option value="{{ $c->category_id }}">{{ $c->category_name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td style="padding:2px 4px;"><input type="number" step="0.01" name="line_amount[]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9px; box-sizing:border-box; text-align:right;"></td>
                    </tr>
                    @endfor
                </tbody>
            </table>
            <div style="display:flex; justify-content:flex-end; margin-top:3px;">
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:14px; padding:4px 14px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.petty_cash_add_vouchers_button') }}</button>
            </div>
        </form>

        {{-- Voucher history --}}
        <div style="flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden; border-top:1px solid #f3f4f6; padding-top:6px;">
            <div style="flex:1; min-height:0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:9px;">
                    <thead>
                        <tr style="background:var(--gl-light);">
                            <th style="text-align:left; padding:3px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_voucher_date') }}</th>
                            <th style="text-align:left; padding:3px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_payee') }}</th>
                            <th style="text-align:left; padding:3px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_category') }}</th>
                            <th style="text-align:right; padding:3px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_amount') }}</th>
                            <th style="text-align:left; padding:3px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_doc_ref_no') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vouchers as $v)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:3px 6px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($v->voucher_date)->format('d M Y') }}</td>
                            <td style="padding:3px 6px; color:#263238;">{{ $v->payee }}</td>
                            <td style="padding:3px 6px; color:#6b7280;">{{ $v->category_name ?: '—' }}</td>
                            <td style="padding:3px 6px; text-align:right; color:#263238;">RM {{ number_format($v->amount, 2) }}</td>
                            <td style="padding:3px 6px; color:#9ca3af;">{{ $v->doc_ref_no }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="padding:12px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_petty_cash_vouchers_note') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:5px;">
                @if($vouchers->onFirstPage())
                    <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:700;">{{ __('network.prev') }}</span>
                @else
                    <a href="{{ $vouchers->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:600;">{{ __('network.prev') }}</a>
                @endif
                <span style="font-size:8.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $vouchers->currentPage(), 'last' => $vouchers->lastPage(), 'total' => $vouchers->total()]) }}</span>
                @if($vouchers->hasMorePages())
                    <a href="{{ $vouchers->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:600;">{{ __('network.next') }}</a>
                @else
                    <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:700;">{{ __('network.next') }}</span>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
