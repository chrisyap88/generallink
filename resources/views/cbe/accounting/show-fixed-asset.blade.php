@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.fixed_asset_detail_page_title'))

@section('content')

{{-- REDESIGNED 4 Sep 2026 (Task #395) — Fixed Asset Module upgrade. The
     Asset Master now carries far more fields (Category/Location/
     Department/Fund/Supplier/Invoice No./Capitalisation Date/
     Depreciation Method/Depreciation Start Date/Original-Additional-
     Revised Cost) than fits one no-scroll screen alongside Depreciation,
     Disposal and History — this is now a 3-page Prev/Next flow (no jump
     screens, per standing UI rule): page 1 = Asset Master Info,
     page 2 = Depreciation & Disposal, page 3 = Asset History
     (Improvements + Transfers, spec section 16). --}}

@php $faStatusColors = ['ACTIVE' => '#2e7d32', 'FULLY_DEPRECIATED' => '#D97706', 'DISPOSED' => '#9ca3af']; @endphp

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ $asset->asset_name }}</div>
        <div style="display:flex; gap:12px; align-items:center;">
            @if($asset->status !== 'DISPOSED')
            <a href="{{ route('cbe.accounting.fixed-assets.improve', $asset->asset_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.add_asset_improvement_button') }}</a>
            <a href="{{ route('cbe.accounting.fixed-assets.transfer', $asset->asset_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.add_asset_transfer_button') }}</a>
            @endif
            <a href="{{ route('cbe.accounting.fixed-assets.edit', $asset->asset_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.edit_fixed_asset_button') }}</a>
            <a href="{{ route('cbe.accounting.fixed-assets') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
        </div>
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

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">

        @if($page < 2)
        {{-- PAGE 1 — Asset Master Info --}}
        <div style="flex:1; min-height:0; overflow:hidden; display:flex; flex-direction:column;">
            <div style="flex-shrink:0; font-size:10px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:8px;">{{ __('cbe_accounting.fa_page_asset_info') }}</div>
            <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:10px; font-size:10px;">
                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_asset_tag') }}</span><br>{{ $asset->asset_tag ?: '—' }}</div>
                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_asset_category') }}</span><br>{{ $asset->category_name ?: ($asset->asset_class ?: '—') }}</div>
                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_asset_location') }}</span><br>{{ $asset->location_name ?: ($asset->location ?: '—') }}</div>
                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_pr_cost_centre') }}</span><br>{{ $asset->centre_name ?: '—' }}</div>

                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_pr_fund') }}</span><br>{{ $asset->fund_name ?: '—' }}</div>
                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_supplier') }}</span><br>{{ $asset->supplier_name ?: '—' }}</div>
                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_invoice_no') }}</span><br>{{ $asset->invoice_no ?: '—' }}</div>
                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_asset_funding_source') }}</span><br>{{ $asset->funding_source ?: '—' }}</div>

                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_acquired_date') }}</span><br>{{ \Carbon\Carbon::parse($asset->acquired_date)->format('d M Y') }}</div>
                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_capitalisation_date') }}</span><br>{{ $asset->capitalisation_date ? \Carbon\Carbon::parse($asset->capitalisation_date)->format('d M Y') : '—' }}</div>
                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_depreciation_start_date') }}</span><br>{{ $asset->depreciation_start_date ? \Carbon\Carbon::parse($asset->depreciation_start_date)->format('d M Y') : '—' }}</div>
                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_depreciation_method') }}</span><br>{{ __('cbe_accounting.depr_method_'.strtolower($asset->depreciation_method)) }}</div>

                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_original_cost') }}</span><br>RM {{ number_format($asset->acquisition_cost, 2) }}</div>
                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_additional_cost_total') }}</span><br>RM {{ number_format($revisedCost - $asset->acquisition_cost, 2) }}</div>
                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_revised_cost') }}</span><br><span style="font-weight:700;">RM {{ number_format($revisedCost, 2) }}</span></div>
                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_salvage_value') }}</span><br>RM {{ number_format($asset->salvage_value, 2) }}</div>

                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_useful_life_months') }}</span><br>{{ $asset->useful_life_months }}</div>
                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.col_status') }}</span><br><span style="color:{{ $faStatusColors[$asset->status] }}; font-weight:700;">{{ __('cbe_accounting.fa_status_'.strtolower($asset->status)) }}</span></div>
                <div>
                    <span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.col_gl_status') }}</span><br>
                    @if($asset->journal_id)
                    <a href="{{ route('cbe.accounting.journal-vouchers.show', $asset->journal_id) }}" style="text-decoration:none; font-weight:700; color:{{ $asset->gl_posting_status === 'POSTED' ? '#2e7d32' : '#9e9e9e' }};">{{ __('cbe_accounting.gl_status_'.strtolower($asset->gl_posting_status ?: 'not_posted')) }}</a>
                    @else
                    <span style="color:#9e9e9e; font-weight:600;">{{ __('cbe_accounting.gl_status_not_posted') }}</span>
                    @endif
                </div>
                @if($asset->bill_id)
                <div>
                    <span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.col_purchase_bill') }}</span><br>
                    <a href="{{ route('cbe.accounting.bill-enquiry.show', $asset->bill_id) }}" style="text-decoration:none; font-weight:700; color:var(--gl-blue);">{{ __('cbe_accounting.view_bill_link') }}</a>
                </div>
                @endif
            </div>
        </div>
        <div style="flex-shrink:0; display:flex; justify-content:flex-end; align-items:center; padding-top:8px;">
            <a href="{{ route('cbe.accounting.fixed-assets.show', ['asset' => $asset->asset_id, 'page' => 2]) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
        </div>

        @elseif($page == 2)
        {{-- PAGE 2 — Depreciation & Disposal --}}
        <div style="flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
            <div style="flex-shrink:0; display:flex; align-items:center; gap:16px; background:var(--gl-light); border-radius:6px; padding:8px 12px; margin-bottom:8px; font-size:10.5px;">
                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.col_status') }}</span><br><span style="color:{{ $faStatusColors[$asset->status] }}; font-weight:700;">{{ __('cbe_accounting.fa_status_'.strtolower($asset->status)) }}</span></div>
                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.col_accum_depreciation') }}</span><br>RM {{ number_format($accumDepr, 2) }}</div>
                <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.col_net_book_value') }}</span><br><span style="color:#2e7d32; font-weight:700;">RM {{ number_format($netBookValue, 2) }}</span></div>
                <div style="flex:1; text-align:right;">
                    @if($asset->status === 'ACTIVE')
                        @if($alreadyPostedThisMonth)
                            <span style="color:#9ca3af; font-size:9.5px;">{{ __('cbe_accounting.depreciation_already_posted_note', ['period' => $currentPeriod]) }}</span>
                        @elseif($nextAmount <= 0)
                            <span style="color:#9ca3af; font-size:9.5px;">{{ __('cbe_accounting.depreciation_nothing_left_note') }}</span>
                        @else
                            <form method="POST" action="{{ route('cbe.accounting.fixed-assets.depreciate', $asset->asset_id) }}" onsubmit="return confirm('{{ __('cbe_accounting.post_depreciation_confirm_js') }}');">
                                @csrf
                                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 14px; font-size:10px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.post_depreciation_button', ['amount' => number_format($nextAmount, 2)]) }}</button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>

            <div style="flex:1; min-height:0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                    <thead>
                        <tr style="background:var(--gl-light);">
                            <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_depr_period') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_depr_amount') }}</th>
                            <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_depr_posted_on') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $e)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:4px 6px;">
                                @if($e->journal_id)
                                <a href="{{ route('cbe.accounting.journal-vouchers.show', $e->journal_id) }}" style="color:var(--gl-blue); text-decoration:none;">{{ $e->period_month }}</a>
                                @else
                                <span style="color:#263238;">{{ $e->period_month }}</span>
                                @endif
                            </td>
                            <td style="padding:4px 6px; text-align:right; color:#263238;">RM {{ number_format($e->amount, 2) }}</td>
                            <td style="padding:4px 6px; color:#6b7280;">{{ \Carbon\Carbon::parse($e->created_at)->format('d M Y') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" style="padding:12px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_depreciation_entries_note') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="flex-shrink:0; margin-top:8px; padding-top:8px; border-top:1px solid #f3f4f6;">
                @if($asset->status === 'DISPOSED')
                <div style="font-size:10px; color:#6b7280;">
                    {{ __('cbe_accounting.asset_disposed_note', ['date' => \Carbon\Carbon::parse($asset->disposed_date)->format('d M Y'), 'proceeds' => number_format($asset->disposal_proceeds, 2)]) }}
                    ({{ __('cbe_accounting.disposal_type_'.strtolower($asset->disposal_type ?: 'other')) }}@if($asset->asset_condition), {{ __('cbe_accounting.condition_'.strtolower($asset->asset_condition)) }}@endif)
                    @if($asset->disposal_reason) — {{ $asset->disposal_reason }} @endif
                    @if($asset->disposal_attachment_path) — <span style="color:var(--gl-blue);">{{ $asset->disposal_attachment_original_name }}</span> @endif
                    @if($disposalJournalId) — <a href="{{ route('cbe.accounting.journal-vouchers.show', $disposalJournalId) }}" style="color:var(--gl-blue); text-decoration:none;">{{ __('cbe_accounting.view_journal_link') }}</a> @endif
                </div>
                @else
                <form method="POST" action="{{ route('cbe.accounting.fixed-assets.dispose', $asset->asset_id) }}" enctype="multipart/form-data" onsubmit="return confirm('{{ __('cbe_accounting.dispose_confirm_js') }}');" style="display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end;">
                    @csrf
                    <div style="width:110px;">
                        <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_disposal_type') }}</label>
                        <select name="disposal_type" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; box-sizing:border-box;">
                            <option value="SALE">{{ __('cbe_accounting.disposal_type_sale') }}</option>
                            <option value="DONATION">{{ __('cbe_accounting.disposal_type_donation') }}</option>
                            <option value="SCRAP">{{ __('cbe_accounting.disposal_type_scrap') }}</option>
                            <option value="WRITE_OFF">{{ __('cbe_accounting.disposal_type_write_off') }}</option>
                            <option value="LOSS">{{ __('cbe_accounting.disposal_type_loss') }}</option>
                            <option value="OTHER">{{ __('cbe_accounting.disposal_type_other') }}</option>
                        </select>
                    </div>
                    <div style="width:110px;">
                        <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_asset_condition') }}</label>
                        <select name="asset_condition" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; box-sizing:border-box;">
                            <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                            <option value="DAMAGED">{{ __('cbe_accounting.condition_damaged') }}</option>
                            <option value="LOST">{{ __('cbe_accounting.condition_lost') }}</option>
                            <option value="OBSOLETE">{{ __('cbe_accounting.condition_obsolete') }}</option>
                            <option value="UNUSABLE">{{ __('cbe_accounting.condition_unusable') }}</option>
                        </select>
                    </div>
                    <div style="width:110px;">
                        <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_disposed_date') }}</label>
                        <input type="date" name="disposed_date" value="{{ now()->toDateString() }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; box-sizing:border-box;">
                    </div>
                    <div style="width:100px;">
                        <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_disposal_proceeds') }}</label>
                        <input type="number" step="0.01" min="0" name="disposal_proceeds" value="0" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1; min-width:140px;">
                        <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_disposal_reason') }}</label>
                        <input type="text" name="disposal_reason" maxlength="255" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; box-sizing:border-box;">
                    </div>
                    <div style="width:150px;">
                        <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_supporting_document') }}</label>
                        <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf" style="width:100%; font-size:9px;">
                    </div>
                    <button type="submit" style="background:#c62828; color:#fff; border:none; border-radius:6px; padding:6px 14px; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_accounting.dispose_button') }}</button>
                </form>
                @endif
            </div>
        </div>
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            <a href="{{ route('cbe.accounting.fixed-assets.show', ['asset' => $asset->asset_id, 'page' => 1]) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            <a href="{{ route('cbe.accounting.fixed-assets.show', ['asset' => $asset->asset_id, 'page' => 3]) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
        </div>

        @else
        {{-- PAGE 3 — Asset History (Improvements + Transfers, spec section 16) --}}
        @php
            $history = collect();
            foreach ($improvements as $imp) {
                $history->push((object) ['date' => $imp->transaction_date, 'type' => 'improvement', 'row' => $imp]);
            }
            foreach ($transfers as $tr) {
                $history->push((object) ['date' => $tr->transfer_date, 'type' => 'transfer', 'row' => $tr]);
            }
            $history = $history->sortByDesc('date')->values();
        @endphp
        <div style="flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
            <div style="flex-shrink:0; font-size:10px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:8px;">{{ __('cbe_accounting.fa_page_asset_history') }}</div>
            <div style="flex:1; min-height:0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                    <thead>
                        <tr style="background:var(--gl-light);">
                            <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_dn_date') }}</th>
                            <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_history_type') }}</th>
                            <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_history_details') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_bill_amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($history as $h)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:4px 6px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($h->date)->format('d M Y') }}</td>
                            @if($h->type === 'improvement')
                            <td style="padding:4px 6px; font-weight:600; color:#263238;">
                                @if($h->row->journal_id)
                                <a href="{{ route('cbe.accounting.journal-vouchers.show', $h->row->journal_id) }}" style="color:var(--gl-blue); text-decoration:none;">{{ __('cbe_accounting.fa_history_improvement') }}</a>
                                @else
                                {{ __('cbe_accounting.fa_history_improvement') }}
                                @endif
                            </td>
                            <td style="padding:4px 6px; color:#263238;">{{ $h->row->description }}@if($h->row->source_reference) ({{ $h->row->source_reference }})@endif</td>
                            <td style="padding:4px 6px; text-align:right; color:#263238;">RM {{ number_format($h->row->additional_cost, 2) }}</td>
                            @else
                            <td style="padding:4px 6px; font-weight:600; color:#263238;">{{ __('cbe_accounting.fa_history_transfer') }}</td>
                            <td style="padding:4px 6px; color:#263238;">
                                @if($h->row->from_location_name != $h->row->to_location_name && $h->row->to_location_name)
                                    {{ __('cbe_accounting.field_asset_location') }}: {{ $h->row->from_location_name ?: '—' }} → {{ $h->row->to_location_name }}
                                @elseif($h->row->to_centre_name)
                                    {{ __('cbe_accounting.field_pr_cost_centre') }}: {{ $h->row->from_centre_name ?: '—' }} → {{ $h->row->to_centre_name }}
                                @elseif($h->row->to_fund_name)
                                    {{ __('cbe_accounting.field_pr_fund') }}: {{ $h->row->from_fund_name ?: '—' }} → {{ $h->row->to_fund_name }}
                                @else
                                    {{ $h->row->reason ?: '—' }}
                                @endif
                            </td>
                            <td style="padding:4px 6px; text-align:right; color:#9ca3af;">—</td>
                            @endif
                        </tr>
                        @empty
                        <tr><td colspan="4" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_asset_history_note') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div style="flex-shrink:0; display:flex; justify-content:flex-start; align-items:center; padding-top:8px;">
            <a href="{{ route('cbe.accounting.fixed-assets.show', ['asset' => $asset->asset_id, 'page' => 2]) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
        </div>
        @endif
    </div>
</div>
@endsection
