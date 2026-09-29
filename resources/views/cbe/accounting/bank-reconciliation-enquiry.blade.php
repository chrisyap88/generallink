@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.bank_reconciliation_enquiry_page_title'))

@section('content')

{{-- NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module upgrade,
     Phase 4, spec section 4.3. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.bank_reconciliation_enquiry_page_title') }}</div>
        <a href="{{ route('cbe.accounting.bank-reconciliations') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.bank_reconciliations_page_title') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <form method="GET" action="{{ route('cbe.accounting.bank-reconciliation-enquiry') }}" style="flex-shrink:0; display:flex; gap:8px; align-items:flex-end; margin-bottom:8px;">
            <div style="flex:1;">
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_bank_account') }}</label>
                <select name="bank_account_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                    <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                    @foreach($bankAccounts as $b)
                    <option value="{{ $b->bank_account_id }}" {{ $bankAccountId == $b->bank_account_id ? 'selected' : '' }}>{{ $b->bank_name }}@if($b->account_name) — {{ $b->account_name }}@endif</option>
                    @endforeach
                </select>
            </div>
            <div style="flex:1;">
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_date_from') }}</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
            </div>
            <div style="flex:1;">
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_date_to') }}</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
            </div>
            <div style="flex:1;">
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.col_status') }}</label>
                <select name="status" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                    <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                    <option value="DRAFT" {{ $status === 'DRAFT' ? 'selected' : '' }}>{{ __('cbe_accounting.br_status_draft') }}</option>
                    <option value="PENDING_APPROVAL" {{ $status === 'PENDING_APPROVAL' ? 'selected' : '' }}>{{ __('cbe_accounting.br_status_pending_approval') }}</option>
                    <option value="COMPLETED" {{ $status === 'COMPLETED' ? 'selected' : '' }}>{{ __('cbe_accounting.br_status_completed') }}</option>
                </select>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" name="search" value="1" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.go_button') }}</button>
                <a href="{{ route('cbe.accounting.bank-reconciliation-enquiry') }}" style="background:#c4c9d0; color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_records.btn_modify_search') }}</a>
            </div>
        </form>

        @if(! $searched)
        <div style="flex:1; min-height:0; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:10.5px;">{{ __('cbe_accounting.enquiry_start_note') }}</div>
        @else
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_reconciliation_no') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_bank') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_statement_date') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_ending_balance') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reconciliations as $r)
                    <tr style="border-bottom:1px solid #f3f4f6; cursor:pointer;" onclick="window.location='{{ route('cbe.accounting.bank-reconciliations.show', $r->reconciliation_id) }}'">
                        <td style="padding:5px 8px; font-weight:600; color:var(--gl-blue);">{{ $r->reconciliation_no ?: '—' }}</td>
                        <td style="padding:5px 8px; color:#263238;">{{ $r->bank_name ?: __('cbe_accounting.field_bank_account_default') }}@if($r->account_name)<span style="color:#9ca3af;"> — {{ $r->account_name }}</span>@endif</td>
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($r->statement_date)->format('d/m/Y') }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:700; color:#263238;">RM {{ number_format($r->ending_balance, 2) }}</td>
                        @php $brEnquiryColors = ['COMPLETED' => ['#e8f5e9', '#1b5e20'], 'PENDING_APPROVAL' => ['#fff3e0', '#D97706'], 'DRAFT' => ['#fff8e1', '#8d6e00']]; @endphp
                        <td style="padding:5px 8px;"><span style="background:{{ $brEnquiryColors[$r->status][0] ?? '#fff8e1' }}; color:{{ $brEnquiryColors[$r->status][1] ?? '#8d6e00' }}; border-radius:10px; padding:2px 8px; font-size:8.5px; font-weight:600;">{{ __('cbe_accounting.br_status_'.strtolower($r->status)) }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.enquiry_no_results_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($reconciliations->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $reconciliations->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $reconciliations->currentPage(), 'last' => $reconciliations->lastPage(), 'total' => $reconciliations->total()]) }}</span>
            @if($reconciliations->hasMorePages())
                <a href="{{ $reconciliations->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection
