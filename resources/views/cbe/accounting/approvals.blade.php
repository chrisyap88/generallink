@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.approvals_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.approvals_page_title') }}</div>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="{{ route('cbe.accounting.approval-settings') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.approval_settings_link') }}</a>
            <a href="{{ route('cbe.accounting.index') }}" onclick="history.back(); return false;" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_accounting') }}</a>
        </div>
    </div>

    {{-- NEW 3 Sep 2026 (Task #373) — Pending / Approved / Rejected tabs,
         per Chris's AP spec. --}}
    <div style="flex-shrink:0; display:flex; gap:4px; margin-bottom:6px;">
        @foreach(['pending' => 'approval_tab_pending', 'approved' => 'approval_tab_approved', 'rejected' => 'approval_tab_rejected'] as $tabKey => $labelKey)
        <a href="{{ route('cbe.accounting.approvals', ['tab' => $tabKey]) }}" style="padding:5px 12px; border-radius:6px; font-size:10px; font-weight:600; text-decoration:none; {{ $tab === $tabKey ? 'background:var(--gl-blue); color:#fff;' : 'background:#f1f5f9; color:#546E7A;' }}">{{ __('cbe_accounting.'.$labelKey) }}</a>
        @endforeach
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_approval_type') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_approval_date') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_approval_description') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_approval_amount') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_approval_prepared_by') }}</th>
                        @if($tab === 'pending')
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_approval_actions') }}</th>
                        @else
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ $tab === 'approved' ? __('cbe_accounting.col_approval_approved_by') : __('cbe_accounting.col_approval_rejected_reason') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @php $typeLabels = ['BILL_PAYMENT' => __('cbe_accounting.approval_type_bill_payment'), 'BANK_TRANSFER' => __('cbe_accounting.approval_type_bank_transfer'), 'JOURNAL_VOUCHER' => __('cbe_accounting.approval_type_journal_voucher'), 'FIXED_ASSET_ACQUISITION' => __('cbe_accounting.approval_type_fa_acquisition'), 'FIXED_ASSET_DISPOSAL' => __('cbe_accounting.approval_type_fa_disposal'), 'FIXED_ASSET_TRANSFER' => __('cbe_accounting.approval_type_fa_transfer'), 'FIXED_ASSET_IMPROVEMENT' => __('cbe_accounting.approval_type_fa_improvement'), 'BANK_RECONCILIATION' => __('cbe_accounting.approval_type_bank_reconciliation')]; @endphp
                    @forelse($items as $item)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 6px; font-weight:600; color:#263238; white-space:nowrap;">{{ $typeLabels[$item->approval_type] ?? $item->approval_type }}</td>
                        <td style="padding:5px 6px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($item->entry_date)->format('d M Y') }}</td>
                        <td style="padding:5px 6px; color:#263238;">{{ $item->description }}</td>
                        <td style="padding:5px 6px; text-align:right; font-weight:700; color:#263238;">RM {{ number_format($item->amount, 2) }}</td>
                        <td style="padding:5px 6px; color:#6b7280;">{{ $item->prepared_by_name }}</td>
                        @if($tab === 'pending')
                        <td style="padding:5px 6px; text-align:right;">
                            <div style="display:flex; gap:4px; justify-content:flex-end;">
                                <form method="POST" action="{{ route('cbe.accounting.approvals.approve', ['type' => $item->approval_type, 'id' => $item->ref_id]) }}" onsubmit="return confirm('{{ __('cbe_accounting.approve_confirm_js') }}');">
                                    @csrf
                                    <button type="submit" style="background:#38A169; color:#fff; border:none; border-radius:5px; padding:4px 10px; font-size:8.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_accounting.approve_button') }}</button>
                                </form>
                                <form method="POST" action="{{ route('cbe.accounting.approvals.reject', ['type' => $item->approval_type, 'id' => $item->ref_id]) }}" onsubmit="var r = prompt('{{ __('cbe_accounting.reject_reason_prompt') }}'); if(r === null || r.trim() === '') { return false; } document.getElementById('reason_{{ $item->approval_type }}_{{ $item->ref_id }}').value = r; return true;">
                                    @csrf
                                    <input type="hidden" id="reason_{{ $item->approval_type }}_{{ $item->ref_id }}" name="reason" value="">
                                    <button type="submit" style="background:#c62828; color:#fff; border:none; border-radius:5px; padding:4px 10px; font-size:8.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_accounting.reject_button') }}</button>
                                </form>
                            </div>
                        </td>
                        @elseif($tab === 'approved')
                        <td style="padding:5px 6px; color:#38A169; font-weight:600;">{{ $item->approved_by_name }} <span style="color:#9ca3af; font-weight:400;">({{ $item->approved_at ? \Carbon\Carbon::parse($item->approved_at)->format('d M Y') : '' }})</span></td>
                        @else
                        <td style="padding:5px 6px; color:#c62828;">{{ $item->rejection_reason }}</td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:16px; text-align:center; color:#9ca3af;">{{ $tab === 'pending' ? __('cbe_accounting.no_pending_approvals_note') : __('cbe_accounting.no_approval_history_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
