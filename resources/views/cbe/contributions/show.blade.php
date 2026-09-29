@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_events.contribution_detail_title'))

@section('content')

@php
    $statusColor = ['PLEDGED' => '#f9a825', 'PARTIALLY_PAID' => 'var(--gl-blue)', 'FULLY_PAID' => '#2e7d32', 'RECEIVED' => '#2e7d32', 'CANCELLED' => '#9ca3af'][$contribution->status] ?? '#6b7280';
    $outstanding = ($contribution->pledged_amount ?? 0) - $contribution->received_amount;
@endphp

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:13px; font-weight:700; color:#263238;">{{ $contribution->donor_name }}</div>
            <div style="font-size:9.5px; color:#6b7280;">{{ $contribution->event_name }} — {{ __('cbe_events.type_' . strtolower($contribution->contribution_type)) }}</div>
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
            <span style="background:{{ $statusColor }}22; color:{{ $statusColor }}; border-radius:10px; padding:3px 12px; font-size:10px; font-weight:700;">{{ __('cbe_events.status_' . strtolower($contribution->status)) }}</span>
            <a href="{{ route('cbe.contributions.index', $contribution->event_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="flex:1; min-height:0; display:flex; gap:10px;">
        <div style="flex:1; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; display:flex; flex-direction:column; gap:8px; overflow:hidden;">
            <div style="display:flex; gap:16px;">
                <div style="flex:1;">
                    <div style="font-size:8.5px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_events.col_pledged') }}</div>
                    <div style="font-size:14px; font-weight:700; color:#263238;">{{ $contribution->pledged_amount ? 'RM ' . number_format($contribution->pledged_amount, 2) : '—' }}</div>
                </div>
                <div style="flex:1;">
                    <div style="font-size:8.5px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_events.col_received') }}</div>
                    <div style="font-size:14px; font-weight:700; color:#2e7d32;">RM {{ number_format($contribution->received_amount, 2) }}</div>
                </div>
                <div style="flex:1;">
                    <div style="font-size:8.5px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_events.stat_outstanding') }}</div>
                    <div style="font-size:14px; font-weight:700; color:{{ $outstanding > 0 ? '#c62828' : '#2e7d32' }};">RM {{ number_format(max($outstanding, 0), 2) }}</div>
                </div>
            </div>
            @if($contribution->item_description)
            <div>
                <div style="font-size:8.5px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_events.field_item_description') }}</div>
                <div style="font-size:10.5px; color:#263238;">{{ $contribution->item_description }}</div>
            </div>
            @endif
            @if($contribution->notes)
            <div>
                <div style="font-size:8.5px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_events.field_notes') }}</div>
                <div style="font-size:10.5px; color:#263238;">{{ $contribution->notes }}</div>
            </div>
            @endif
            @if($contribution->receipt_attachment_path)
            <div><a href="{{ route('cbe.contributions.receipt', $contribution->contribution_id) }}" style="color:var(--gl-blue); font-weight:600; text-decoration:none; font-size:10.5px;">{{ __('cbe_records.view_attachment') }}</a></div>
            @endif

            <div style="border-top:1px solid #E2E8F0; padding-top:10px; margin-top:auto;">
                <div style="font-size:9.5px; font-weight:700; color:#263238; margin-bottom:6px;">{{ __('cbe_events.record_payment_title') }}</div>
                <form method="POST" action="{{ route('cbe.contributions.payments.store', $contribution->contribution_id) }}" style="display:flex; gap:6px; align-items:flex-end;">
                    @csrf
                    <div style="flex:1;">
                        <label style="display:block; font-size:8px; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_records.field_transaction_date') }}</label>
                        <input type="date" name="payment_date" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 7px; font-size:9.5px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:8px; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_records.field_amount') }}</label>
                        <input type="number" step="0.01" min="0.01" name="amount" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 7px; font-size:9.5px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:8px; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_events.field_payment_method') }}</label>
                        <input type="text" name="payment_method" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 7px; font-size:9.5px; box-sizing:border-box;">
                    </div>
                    <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 14px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
                </form>
            </div>
        </div>

        <div style="flex:1; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; display:flex; flex-direction:column; overflow:hidden;">
            <div style="font-size:9.5px; font-weight:700; color:#263238; margin-bottom:6px; flex-shrink:0;">{{ __('cbe_events.payment_history_title') }}</div>
            <div style="flex:1; min-height:0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                    <thead>
                        <tr style="background:var(--gl-light);">
                            <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_date') }}</th>
                            <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_amount') }}</th>
                            <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_events.field_payment_method') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $p)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:5px 8px; color:#6b7280;">{{ \Carbon\Carbon::parse($p->payment_date)->format('d M Y') }}</td>
                            <td style="padding:5px 8px; text-align:right; font-weight:600; color:#2e7d32;">RM {{ number_format($p->amount, 2) }}</td>
                            <td style="padding:5px 8px; color:#6b7280;">{{ $p->payment_method ?: '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_events.no_payments_note') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
