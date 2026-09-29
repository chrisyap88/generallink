@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.payment_voucher_page_title'))

@section('content')

{{-- NEW 3 Sep 2026 (Task #372) — one lump-sum payment split across
     several outstanding bills for one supplier. Each ticked bill creates
     its own cbe_bill_payments row via
     CbeAccountingController::storePaymentVoucher(), sharing one pv_no. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.payment_voucher_page_title') }} — {{ $supplier->supplier_name }}</div>
        <a href="{{ route('cbe.accounting.payment-voucher') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
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
        <form method="POST" action="{{ route('cbe.accounting.payment-voucher.store', $supplier->supplier_id) }}" style="height:100%; display:flex; flex-direction:column; gap:9px;">
            @csrf
            <div style="flex-shrink:0; display:flex; gap:8px; flex-wrap:wrap;">
                <div style="flex:1; min-width:130px;">
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_dn_date') }}</label>
                    <input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="flex:1; min-width:130px;">
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_payment_method_free') }}</label>
                    <input type="text" name="payment_method" value="{{ old('payment_method') }}" maxlength="60" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="flex:1; min-width:130px;">
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_bank_account') }}</label>
                    <select name="bank_account_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($bankAccounts as $b)
                        <option value="{{ $b->bank_account_id }}" {{ old('bank_account_id') == $b->bank_account_id ? 'selected' : '' }}>{{ $b->bank_name }}@if($b->account_name) — {{ $b->account_name }}@endif</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex:1; min-width:110px;">
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_reference_no') }}</label>
                    <input type="text" name="reference_no" value="{{ old('reference_no') }}" maxlength="60" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
            </div>

            <div style="flex:1; min-height:0; overflow:hidden; display:flex; flex-direction:column; border-top:1px solid #f3f4f6; padding-top:8px;">
                <div style="font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:5px;">{{ __('cbe_accounting.payment_voucher_table_title') }}</div>
                <div style="flex:1; min-height:0; overflow:hidden;">
                    <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                        <thead>
                            <tr style="background:var(--gl-light);">
                                <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_bill') }}</th>
                                <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_dn_date') }}</th>
                                <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_outstanding_amount') }}</th>
                                <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase; width:140px;">{{ __('cbe_accounting.payment_allocation_col_allocate') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($bills as $b)
                            <tr style="border-bottom:1px solid #f3f4f6;">
                                <td style="padding:5px 8px; font-weight:600; color:#263238;">{{ $b->bill_no ?: $b->doc_ref_no }}</td>
                                <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($b->bill_date)->format('d M Y') }}</td>
                                <td style="padding:5px 8px; text-align:right; color:#263238;">RM {{ number_format($b->outstanding, 2) }}</td>
                                <td style="padding:5px 8px; text-align:right;">
                                    <input type="number" step="0.01" min="0" max="{{ $b->outstanding }}" name="allocation[{{ $b->bill_id }}]" value="{{ old('allocation.'.$b->bill_id) }}" placeholder="0.00" style="width:110px; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10.5px; text-align:right; box-sizing:border-box;">
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="4" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.payment_voucher_no_outstanding_note') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="flex-shrink:0; padding-top:8px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.accounting.payment-voucher') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
