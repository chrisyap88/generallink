@extends('layouts.dashboard')

@section('page-title', __('sales_transactions.detail_title'))

{{-- REBUILT 19 Jul 2026 — per Chris: this View screen should visually
     mirror the Submit Sales Transaction screen's 3 folder tabs (Sales
     Transaction/Policy, Customer, Renewal Reminder), but fully
     READ-ONLY — no inputs, no Save/Submit/Cancel. Only a single "Prev"
     button at the bottom that goes back to the list. Proof Documents
     and Earning Income Breakdown (with Admin's Confirm/Clear Flag
     actions) stay below the tabs, unchanged, since Chris didn't ask
     for those to be removed. --}}

@section('content')
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:6px 16px 0; box-sizing:border-box;">

    <div style="flex-shrink:0;">
        @if(session('success'))
        <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:6px 10px; font-size:11px; margin-bottom:6px;">{{ session('success') }}</div>
        @endif

        @if($txn->flagged_for_review)
        <div style="background:#fef2f2; border-left:3px solid #e53935; border-radius:6px; padding:8px 10px; font-size:11px; color:#991b1b; margin-bottom:6px; display:flex; align-items:center; justify-content:space-between; gap:10px;">
            <span>&#9888; <strong>{{ __('sales_transactions.flagged_for_review_label') }}</strong> {{ $txn->flag_reason }}</span>
            @if($isAdmin)
            <form method="POST" action="{{ route('admin.sales-transactions.clear-flag', $txn->policy_id) }}" onsubmit="return confirm('{{ __('sales_transactions.clear_flag_confirm_js') }}');" style="margin:0;">
                @csrf
                <button type="submit" style="background:#fff; border:1px solid #e53935; color:#991b1b; border-radius:6px; padding:5px 12px; font-size:10.5px; font-weight:700; cursor:pointer; white-space:nowrap;">{{ __('sales_transactions.clear_flag_button') }}</button>
            </form>
            @endif
        </div>
        @endif

        {{-- TABS — same visual style as create.blade.php's tab bar. Doc
             reference # floated right of the tabs instead of its own row. --}}
        <div style="display:flex; align-items:center; gap:3px; margin:0 2px;">
            <button type="button" class="vTabBtn" data-tab="vPolicyCard" id="vTabBtnPolicy"
                    style="background:#fff; border:1px solid #E2E8F0; border-bottom:none; border-radius:8px 8px 0 0; padding:7px 16px; font-size:10.5px; font-weight:700; color:#1565C0; cursor:pointer; position:relative; top:1px;">
                {{ __('sales_transactions.tab_policy') }}
            </button>
            <button type="button" class="vTabBtn" data-tab="vCustomerCard" id="vTabBtnCustomer"
                    style="background:#F7FAFC; border:1px solid #E2E8F0; border-bottom:none; border-radius:8px 8px 0 0; padding:7px 16px; font-size:10.5px; font-weight:700; color:#6b7280; cursor:pointer; position:relative; top:1px;">
                {{ __('sales_transactions.tab_customer') }}
            </button>
            <button type="button" class="vTabBtn" data-tab="vRenewalCard" id="vTabBtnRenewal"
                    style="background:#F7FAFC; border:1px solid #E2E8F0; border-bottom:none; border-radius:8px 8px 0 0; padding:7px 16px; font-size:10.5px; font-weight:700; color:#6b7280; cursor:pointer; position:relative; top:1px;">
                {{ __('sales_transactions.tab_renewal') }}
            </button>
            <span style="margin-left:auto; font-size:10.5px; color:#9ca3af; white-space:nowrap;">{{ __('sales_transactions.ref_hash_colon_label') }} <strong style="color:#374151;">{{ $txn->document_reference_number }}</strong></span>
        </div>
    </div>

    <div style="flex:1 1 auto; min-height:0; overflow-y:auto; padding-bottom:8px;">

        {{-- POLICY --}}
        <div id="vPolicyCard" class="vTabPanel" style="background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:8px 12px; margin-bottom:8px;">
            <div style="font-size:11px; font-weight:700; color:#374151; margin-bottom:6px;">{{ __('sales_transactions.policy_sale_details_heading') }}</div>

            @php
                $statusLabels = ['DRAFT'=>__('gl.status_draft'),'SUBMITTED'=>__('network.submitted'),'ACTIVE'=>__('network.active'),'PENDING_RENEWAL'=>__('network.pending_renewal'),'RENEWED'=>__('gl.status_renewed'),'LAPSED'=>__('network.lapsed'),'CANCELLED'=>__('gl.status_cancelled')];
            @endphp
            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:8px; font-size:10.5px; margin-bottom:8px;">
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_document_reference_hash_label') }}</span><br><strong>{{ $txn->document_reference_number }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('gl.col_status') }}</span><br>
                    @php
                        $badges = ['ACTIVE'=>['#d1fae5','#065f46'],'SUBMITTED'=>['#dbeafe','#1e40af'],'DRAFT'=>['#f3f4f6','#374151'],'PENDING_RENEWAL'=>['#fef3c7','#92400e'],'RENEWED'=>['#e0f2fe','#075985'],'LAPSED'=>['#fee2e2','#991b1b'],'CANCELLED'=>['#f3f4f6','#6b7280']];
                        $badge = $badges[$txn->status] ?? ['#f3f4f6','#374151'];
                    @endphp
                    <span style="background:{{ $badge[0] }};color:{{ $badge[1] }};padding:1px 7px;border-radius:20px;font-size:9px;font-weight:600;">{{ $statusLabels[$txn->status] ?? $txn->status }}</span>
                </div>
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_vendor_label') }}</span><br><strong>{{ $txn->vendor_name }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_product_label') }}</span><br><strong>{{ $txn->product_name }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_sales_amount_premium_label') }}</span><br><strong>{{ number_format($txn->premium_amount,2) }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('gl.sum_insured_rm_label') }}</span><br><strong>{{ $txn->sum_insured ? number_format($txn->sum_insured,2) : '—' }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_submitted_by_label') }}</span><br><strong>{{ $txn->agent_name }}</strong> ({{ $txn->agent_code }})</div>
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_submitted_on_label') }}</span><br><strong>{{ \Illuminate\Support\Carbon::parse($txn->created_at)->format('d M Y, g:ia') }}</strong></div>
            </div>

            @if($renewal)
            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:8px; font-size:10.5px; margin-bottom:8px; padding-top:8px; border-top:1px solid #f3f4f6;">
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_coverage_start_label') }}</span><br><strong>{{ \Illuminate\Support\Carbon::parse($renewal->coverage_start)->format('d M Y') }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_coverage_end_label') }}</span><br><strong>{{ \Illuminate\Support\Carbon::parse($renewal->coverage_end)->format('d M Y') }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_coverage_type_show_label') }}</span><br><strong>{{ $attributes['COVERAGE_TYPE'] ?? '—' }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_vehicle_number_label') }}</span><br><strong>{{ $renewal->vehicle_number ?? '—' }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_ncd_pct_label') }}</span><br><strong>{{ $attributes['NCD_PERCENTAGE'] ?? '—' }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_excess_rm_label') }}</span><br><strong>{{ $attributes['EXCESS_AMOUNT'] ?? '—' }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_total_payable_incl_tax_label') }}</span><br><strong>{{ $attributes['TOTAL_AMOUNT_PAYABLE_INCL_TAX'] ?? '—' }}</strong></div>
            </div>

            @if(!empty($attributes['ADD_ONS']))
            @php
                $addonLines = array_values(array_filter(preg_split('/\r\n|\r|\n/', $attributes['ADD_ONS']), fn($l) => trim($l) !== ''));
                $addonBox1 = implode("\n", array_slice($addonLines, 0, 3));
                $addonBox2 = implode("\n", array_slice($addonLines, 3));
            @endphp
            <div style="font-size:9.5px; color:#9ca3af; margin:4px 0 4px;">{{ __('sales_transactions.addons_extensions_heading') }}</div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px;">
                <div style="background:#F7FAFC; border:1px solid #E2E8F0; border-radius:5px; padding:6px 8px; font-size:10.5px; color:#111827; white-space:pre-line; min-height:44px;">{{ $addonBox1 ?: '—' }}</div>
                <div style="background:#F7FAFC; border:1px solid #E2E8F0; border-radius:5px; padding:6px 8px; font-size:10.5px; color:#111827; white-space:pre-line; min-height:44px;">{{ $addonBox2 ?: '—' }}</div>
            </div>
            @endif

            @if($renewal->engine_number || $renewal->chassis_number || $renewal->vehicle_make_model)
            <div style="font-size:9.5px; color:#9ca3af; margin:4px 0 6px; padding-top:6px; border-top:1px solid #f3f4f6;">{{ __('sales_transactions.vehicle_details_heading') }}</div>
            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:8px; font-size:10.5px;">
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_make_type_body_label') }}</span><br><strong>{{ $renewal->vehicle_make_model ?? '—' }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_cubic_capacity_label') }}</span><br><strong>{{ $renewal->cubic_capacity ?? '—' }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_year_manufacture_label') }}</span><br><strong>{{ $renewal->year_of_manufacture ?? '—' }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_seating_capacity_label') }}</span><br><strong>{{ $renewal->seating_capacity ?? '—' }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_engine_no_label') }}</span><br><strong>{{ $renewal->engine_number ?? '—' }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_chassis_no_label') }}</span><br><strong>{{ $renewal->chassis_number ?? '—' }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_trailer_chassis_no_label') }}</span><br><strong>{{ $renewal->trailer_chassis_number ?? '—' }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_named_drivers_show_label') }}</span><br><strong>{{ $renewal->named_drivers ?? '—' }}</strong></div>
            </div>
            @endif
            @else
            <div style="font-size:10.5px; color:#9ca3af; padding-top:6px; border-top:1px solid #f3f4f6;">{{ __('sales_transactions.not_applicable_non_insurance_note') }}</div>
            @endif
        </div>

        {{-- CUSTOMER --}}
        <div id="vCustomerCard" class="vTabPanel" style="display:none; background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:8px 12px; margin-bottom:8px;">
            <div style="font-size:11px; font-weight:700; color:#374151; margin-bottom:6px;">{{ __('sales_transactions.customer_heading') }}</div>
            @php
                $nricMasked = '—';
                if (!empty($txn->customer_nric_encrypted)) {
                    try { $nricMasked = '****' . substr(decrypt($txn->customer_nric_encrypted), -4); } catch (\Exception $e) { $nricMasked = '—'; }
                }
            @endphp
            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:8px; font-size:10.5px; margin-bottom:8px;">
                <div><span style="color:#9ca3af;">{{ __('sales_transactions.field_full_name_show_label') }}</span><br><strong>{{ $txn->customer_name }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('gl.field_nric') }}</span><br><strong>{{ $nricMasked }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('gl.field_phone') }}</span><br><strong>{{ $txn->customer_phone }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('gl.field_email') }}</span><br><strong>{{ $txn->customer_email ?? '—' }}</strong></div>
            </div>
            <div style="display:grid; grid-template-columns:2fr 0.7fr 1fr 1fr; gap:8px; font-size:10.5px;">
                <div><span style="color:#9ca3af;">{{ __('gl.field_address') }}</span><br><strong>{{ $txn->customer_address ?? '—' }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('gl.field_postcode') }}</span><br><strong>{{ $txn->customer_postcode ?? '—' }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('gl.field_city') }}</span><br><strong>{{ $txn->customer_city ?? '—' }}</strong></div>
                <div><span style="color:#9ca3af;">{{ __('gl.field_state') }}</span><br><strong>{{ $txn->customer_state ?? '—' }}</strong></div>
            </div>
        </div>

        {{-- RENEWAL REMINDER — the actual reminder stored at submission
             time (task #94), not a live preview like create.blade.php. --}}
        <div id="vRenewalCard" class="vTabPanel" style="display:none; background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:5px 8px; margin-bottom:5px;">
            @if($renewal && $renewal->reminder_scheduled_date)
            {{-- COMPACTED 19 Jul 2026 — per Chris: title + the 3 stat fields
                 folded into a single row instead of two, to leave more room
                 for the message so it fits without scrolling. --}}
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:3px;">
                <span style="font-size:9.5px; font-weight:700; color:#374151; white-space:nowrap;">{{ __('sales_transactions.renewal_reminder_heading') }}</span>
                <span style="background:#DBEAFE; color:#1e40af; border-radius:14px; padding:2px 9px; font-size:8.5px; font-weight:700; white-space:nowrap;">{{ __('sales_transactions.scheduled_label') }} {{ \Illuminate\Support\Carbon::parse($renewal->reminder_scheduled_date)->format('d M Y') }}</span>
                <span style="background:#EDE9FE; color:#5b21b6; border-radius:14px; padding:2px 9px; font-size:8.5px; font-weight:700; white-space:nowrap;">{{ __('sales_transactions.status_label') }} {{ ucwords(strtolower($renewal->status)) }}</span>
                <span style="background:{{ $renewal->reminder_sent_at ? '#d1fae5' : '#FEF3C7' }}; color:{{ $renewal->reminder_sent_at ? '#065f46' : '#92400e' }}; border-radius:14px; padding:2px 9px; font-size:8.5px; font-weight:700; white-space:nowrap;">{{ __('sales_transactions.sent_label') }} {{ $renewal->reminder_sent_at ? \Illuminate\Support\Carbon::parse($renewal->reminder_sent_at)->format('d M Y, g:ia') : __('sales_transactions.not_sent_yet') }}</span>
            </div>
            <div style="background:#F7FAFC; border:1px solid #E2E8F0; border-radius:5px; padding:4px 7px; font-size:8px; line-height:1.25; color:#111827; white-space:pre-line;">{{ $renewal->reminder_message ?? '—' }}</div>
            @else
            <div style="font-size:9.5px; font-weight:700; color:#374151; margin-bottom:4px;">{{ __('sales_transactions.renewal_reminder_heading') }}</div>
            <div style="font-size:10.5px; color:#9ca3af;">{{ __('sales_transactions.no_renewal_reminder_scheduled_note') }}</div>
            @endif
        </div>

        {{-- PROOF DOCUMENTS --}}
        <div style="background:#fff; border-radius:8px; padding:10px 14px; margin-bottom:8px;">
            <div style="font-size:11px; font-weight:700; color:#374151; margin-bottom:8px;">{{ __('sales_transactions.proof_documents_heading') }}</div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                @php
                    $docTypeLabels = ['RECEIPT'=>__('sales_transactions.doc_type_receipt'),'SALES_INVOICE'=>__('sales_transactions.doc_type_sales_invoice'),'POLICY_DOCUMENT'=>__('sales_transactions.doc_type_policy_document'),'OTHER'=>__('sales_transactions.doc_type_other')];
                @endphp
                @forelse($documents as $doc)
                <a href="{{ route($rolePrefix . '.sales-transactions.document', $doc->document_id) }}" target="_blank" style="display:block; width:110px; text-decoration:none; color:inherit; border:1px solid #E2E8F0; border-radius:6px; padding:6px; text-align:center;">
                    @if(in_array(strtolower(pathinfo($doc->file_name, PATHINFO_EXTENSION)), ['jpg','jpeg','png']))
                        <div style="width:100%; height:70px; background:#E0F7FA; border-radius:4px; display:flex; align-items:center; justify-content:center; margin-bottom:4px;"><i class="ti ti-photo" style="font-size:26px; color:#1565C0;"></i></div>
                    @else
                        <div style="width:100%; height:70px; background:#F7FAFC; border-radius:4px; display:flex; align-items:center; justify-content:center; margin-bottom:4px;"><i class="ti ti-file-type-pdf" style="font-size:26px; color:#e53935;"></i></div>
                    @endif
                    <div style="font-size:9px; color:#1565C0; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $doc->file_name }}</div>
                    <div style="font-size:8px; color:#9ca3af;">{{ $docTypeLabels[$doc->document_type] ?? ucwords(strtolower(str_replace('_',' ',$doc->document_type))) }}</div>
                </a>
                @empty
                <div style="color:#9ca3af; font-size:10.5px;">{{ __('sales_transactions.no_documents_attached_note') }}</div>
                @endforelse
            </div>
        </div>

        {{-- COMMISSION --}}
        <div style="background:#fff; border-radius:8px; padding:10px 14px; margin-bottom:8px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                <div style="font-size:11px; font-weight:700; color:#374151;">{{ __('sales_transactions.earning_income_breakdown_heading') }}</div>
                @if($isAdmin && $commissions->where('status','PENDING')->count() > 0)
                <form method="POST" action="{{ route('admin.sales-transactions.confirm', $txn->policy_id) }}" onsubmit="return confirm('{{ __('sales_transactions.confirm_release_confirm_js') }}');" style="margin:0;">
                    @csrf
                    <button type="submit" style="background:#38A169; color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:11px; font-weight:700; cursor:pointer;">{{ __('sales_transactions.confirm_release_button') }}</button>
                </form>
                @endif
            </div>
            @php
                $cStatusLabels = ['PENDING'=>__('sales_transactions.pending_word'),'CONFIRMED'=>__('gl.status_confirmed'),'REVERSED'=>__('gl.status_reversed')];
            @endphp
            <table style="width:100%; border-collapse:collapse; font-size:10.5px;">
                <thead>
                    <tr style="background:#F7FAFC;">
                        <th style="text-align:left; padding:5px 8px; color:#0D5A8E;">{{ __('sales_transactions.col_role') }}</th>
                        <th style="text-align:left; padding:5px 8px; color:#0D5A8E;">{{ __('gl.col_agent') }}</th>
                        <th style="text-align:right; padding:5px 8px; color:#0D5A8E;">{{ __('sales_transactions.col_pct') }}</th>
                        <th style="text-align:right; padding:5px 8px; color:#0D5A8E;">{{ __('sales_transactions.col_amount_rm') }}</th>
                        <th style="text-align:center; padding:5px 8px; color:#0D5A8E;">{{ __('gl.col_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($commissions as $c)
                    <tr style="border-bottom:1px solid #F7FAFC;">
                        <td style="padding:5px 8px;">{{ ucwords(strtolower(str_replace('_',' ',$c->role_at_transaction))) }}</td>
                        <td style="padding:5px 8px;">{{ $c->is_breakage ? __('sales_transactions.system_no_active_upline') : $c->agent_name . ' (' . $c->agent_code . ')' }}</td>
                        <td style="text-align:right; padding:5px 8px;">{{ number_format($c->entitlement_pct,1) }}%</td>
                        <td style="text-align:right; padding:5px 8px; font-weight:700;">{{ number_format($c->commission_amount,2) }}</td>
                        <td style="text-align:center; padding:5px 8px;">
                            @php
                                $cbadge = ['PENDING'=>['#fef3c7','#92400e'],'CONFIRMED'=>['#d1fae5','#065f46'],'REVERSED'=>['#fee2e2','#991b1b']][$c->status] ?? ['#f3f4f6','#374151'];
                            @endphp
                            <span style="background:{{ $cbadge[0] }};color:{{ $cbadge[1] }};padding:1px 7px;border-radius:20px;font-size:9px;font-weight:600;">{{ $cStatusLabels[$c->status] ?? $c->status }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="text-align:center; padding:16px; color:#9ca3af;">{{ __('sales_transactions.no_earning_income_structure_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($commissions->where('status','PENDING')->count() > 0)
            <div style="font-size:9.5px; color:#92400e; margin-top:8px;">{!! __('sales_transactions.earning_income_pending_note', ['pending' => '<strong>' . __('sales_transactions.pending_word') . '</strong>']) !!}</div>
            @endif
        </div>

    </div>

    {{-- FOOTER — single Prev button back to the list. No Save/Submit/Cancel
         on this read-only screen, per Chris. --}}
    <div style="flex-shrink:0; padding:4px 0 6px; background:#fff; border-top:1px solid #f3f4f6;">
        <a href="{{ route($rolePrefix . '.sales-transactions.index') }}" style="display:inline-block; background:#1565C0; color:#fff; text-decoration:none; border-radius:5px; padding:6px 18px; font-size:11px; font-weight:700;">{{ __('network.prev') }}</a>
    </div>
</div>

<script>
(function() {
    var tabBtns = document.querySelectorAll('.vTabBtn');
    var tabPanels = document.querySelectorAll('.vTabPanel');
    function activateTab(tabId) {
        tabPanels.forEach(function(p) { p.style.display = (p.id === tabId) ? 'block' : 'none'; });
        tabBtns.forEach(function(b) {
            var active = b.dataset.tab === tabId;
            b.style.background = active ? '#fff' : '#F7FAFC';
            b.style.color = active ? '#1565C0' : '#6b7280';
        });
    }
    tabBtns.forEach(function(b) {
        b.addEventListener('click', function() { activateTab(b.dataset.tab); });
    });
})();
</script>
@endsection
