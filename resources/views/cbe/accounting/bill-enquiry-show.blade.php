@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.bill_enquiry_page_title'))

@section('content')

{{-- NEW 3 Sep 2026 (Task #374) — Supplier Invoice (Bill) Enquiry detail:
     header, line items, payments made, linked credit/debit notes, GL
     status. Mirrors invoice-enquiry-show.blade.php on the AR side. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ $b->bill_no ?: $b->doc_ref_no }} — <a href="{{ route('cbe.accounting.supplier-enquiry', $b->supplier_id) }}" style="color:var(--gl-blue); text-decoration:none;">{{ $b->supplier_name }}</a></div>
        <a href="{{ route('cbe.accounting.bill-enquiry') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    <div style="flex-shrink:0; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:8px 10px; margin-bottom:8px; display:flex; gap:20px; align-items:center; flex-wrap:wrap;">
        <div>
            <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_dn_date') }}</div>
            <div style="font-size:10.5px; color:#263238; font-weight:600;">{{ \Carbon\Carbon::parse($b->bill_date)->format('d M Y') }}</div>
        </div>
        <div>
            <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_status') }}</div>
            <div style="font-size:10.5px; color:#263238; font-weight:600;">{{ __('cbe_accounting.status_'.strtolower($b->status)) }}</div>
        </div>
        <div>
            <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_bill_amount') }}</div>
            <div style="font-size:10.5px; color:#263238; font-weight:600;">RM {{ number_format($b->amount, 2) }}</div>
        </div>
        <div>
            <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.field_paid_amount') }}</div>
            <div style="font-size:10.5px; color:#263238; font-weight:600;">RM {{ number_format($b->paid_amount, 2) }}</div>
        </div>
        <div>
            <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_gl_status') }}</div>
            @if($b->journal_id)
            <a href="{{ route('cbe.accounting.journal-vouchers.show', $b->journal_id) }}" style="font-size:10.5px; font-weight:700; text-decoration:none; color:{{ $b->gl_posting_status === 'POSTED' ? '#2e7d32' : ($b->gl_posting_status === 'REVERSED' ? '#c62828' : '#9e9e9e') }};">{{ __('cbe_accounting.gl_status_'.strtolower($b->gl_posting_status ?: 'not_posted')) }}</a>
            @else
            <div style="font-size:10.5px; color:#9e9e9e; font-weight:600;">{{ __('cbe_accounting.gl_status_not_posted') }}</div>
            @endif
        </div>
        @if(!empty($b->match_status) && $b->match_status !== 'NOT_APPLICABLE')
        <div>
            <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_match_status') }}</div>
            @php $matchColors = ['MATCHED' => '#2e7d32', 'PRICE_VARIANCE' => '#c62828', 'QUANTITY_VARIANCE' => '#D97706', 'NO_PO' => '#9e9e9e', 'NO_GRN' => '#9e9e9e']; @endphp
            <span style="background:{{ $matchColors[$b->match_status] ?? '#9e9e9e' }}; color:#fff; border-radius:10px; padding:2px 8px; font-size:9px; font-weight:600;">{{ __('cbe_accounting.match_status_'.strtolower($b->match_status)) }}</span>
        </div>
        @endif
        @if(!empty($b->po_doc_ref_no))
        <div>
            <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_purchase_order') }}</div>
            <a href="{{ route('cbe.accounting.purchase-orders.show', $b->po_id) }}" style="font-size:10.5px; font-weight:700; text-decoration:none; color:var(--gl-blue);">{{ $b->po_doc_ref_no }}</a>
        </div>
        @endif
        @if(!empty($b->grn_doc_ref_no))
        <div>
            <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_goods_receipt') }}</div>
            <a href="{{ route('cbe.accounting.goods-receipts.show', $b->grn_id) }}" style="font-size:10.5px; font-weight:700; text-decoration:none; color:var(--gl-blue);">{{ $b->grn_doc_ref_no }}</a>
        </div>
        @endif
        <div>
            <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_fixed_asset') }}</div>
            @if($linkedAsset)
            <a href="{{ route('cbe.accounting.fixed-assets.show', $linkedAsset->asset_id) }}" style="font-size:10.5px; font-weight:700; text-decoration:none; color:var(--gl-blue);">{{ $linkedAsset->asset_name }}</a>
            @elseif($b->status !== 'CANCELLED')
            <a href="{{ route('cbe.accounting.bill-enquiry.create-fixed-asset', $b->bill_id) }}" style="font-size:9.5px; font-weight:600; text-decoration:none; color:var(--gl-blue); border:1px solid var(--gl-blue); border-radius:14px; padding:2px 10px;">{{ __('cbe_accounting.create_fixed_asset_button') }}</a>
            @else
            <div style="font-size:10.5px; color:#9ca3af;">—</div>
            @endif
        </div>
        <div style="margin-left:auto; text-align:right;">
            <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.field_outstanding_amount') }}</div>
            <div style="font-size:15px; font-weight:700; color:{{ ($b->amount - $b->paid_amount) > 0.004 ? '#c62828' : '#2e7d32' }};">RM {{ number_format($b->amount - $b->paid_amount, 2) }}</div>
        </div>
    </div>

    <div style="flex:1; min-height:0; display:flex; gap:8px; overflow:hidden;">
        <div style="flex:1; min-width:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; display:flex; flex-direction:column; overflow:hidden;">
            <div style="font-size:10px; font-weight:700; color:#263238; margin-bottom:5px; flex-shrink:0;">{{ __('cbe_accounting.bill_enquiry_lines_title') }}</div>
            <div style="flex:1; min-height:0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:9px;">
                    <thead>
                        <tr style="background:var(--gl-light);">
                            <th style="text-align:left; padding:3px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_dn_reason') }}</th>
                            <th style="text-align:right; padding:3px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_bill_amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lines as $l)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:4px 6px; color:#263238;">{{ $l->description ?: $l->category_name ?: '—' }}</td>
                            <td style="padding:4px 6px; text-align:right; color:#263238;">RM {{ number_format($l->line_total, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="2" style="padding:10px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.bill_enquiry_no_lines_note') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div style="flex:1; min-width:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; display:flex; flex-direction:column; overflow:hidden;">
            <div style="font-size:10px; font-weight:700; color:#263238; margin-bottom:5px; flex-shrink:0;">{{ __('cbe_accounting.bill_enquiry_payments_title') }}</div>
            <div style="flex:1; min-height:0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:9px;">
                    <thead>
                        <tr style="background:var(--gl-light);">
                            <th style="text-align:left; padding:3px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_dn_date') }}</th>
                            <th style="text-align:left; padding:3px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_bank_account') }}</th>
                            <th style="text-align:right; padding:3px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_bill_amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $p)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:4px 6px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($p->payment_date)->format('d M Y') }}</td>
                            <td style="padding:4px 6px; color:#263238;">{{ $p->bank_name ?: '—' }}</td>
                            <td style="padding:4px 6px; text-align:right; color:#2e7d32; font-weight:700;">RM {{ number_format($p->amount, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" style="padding:10px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.bill_enquiry_no_payments_note') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($debitNotes->isNotEmpty() || $creditNotes->isNotEmpty())
            <div style="font-size:10px; font-weight:700; color:#263238; margin:8px 0 4px; flex-shrink:0;">{{ __('cbe_accounting.bill_enquiry_notes_title') }}</div>
            <div style="flex-shrink:0; max-height:70px; overflow:hidden; font-size:9px;">
                @foreach($debitNotes as $dn)
                <div style="display:flex; justify-content:space-between; padding:2px 6px; color:#c62828;"><span>{{ __('cbe_accounting.ap_debit_notes_page_title') }} — {{ \Carbon\Carbon::parse($dn->note_date)->format('d M Y') }}</span><span>+ RM {{ number_format($dn->amount, 2) }}</span></div>
                @endforeach
                @foreach($creditNotes as $cn)
                <div style="display:flex; justify-content:space-between; padding:2px 6px; color:#2e7d32;"><span>{{ __('cbe_accounting.ap_credit_notes_page_title') }} — {{ \Carbon\Carbon::parse($cn->note_date)->format('d M Y') }}</span><span>- RM {{ number_format($cn->amount, 2) }}</span></div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
