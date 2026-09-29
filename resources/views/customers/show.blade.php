@extends('layouts.dashboard')

@section('title', __('customers.customer_dash_name_title', ['name' => $customer->full_name]))
@section('page-title', __('gl.customer_detail_title'))

{{-- REBUILT 19 Jul 2026 — per Chris: replaced the old single-page
     stacked layout (Personal Info / Owned By cards + a row of 5 stat
     boxes that "mean nothing" once a customer only has 1 transaction)
     with a proper tabbed profile: Profile, Sales History, Reminders,
     Activity Log History, and a Sales KPI Dashboard tab with numbers
     that stay meaningful at any transaction count (lifetime premium,
     average premium/policy, tenure, next renewal due) plus 2 donut
     charts (premium mix by product, policy status mix) that render
     sensibly even with just 1 policy and get more useful as more are
     added. --}}

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:0 16px; box-sizing:border-box;">

<div style="flex-shrink:0;">
    {{-- TIGHTENED 19 Jul 2026 per Chris: too much empty gap between the
         name/buttons row and the tab bar below it. --}}
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
        <div>
            <a href="{{ route($rolePrefix . '.customers.index') }}" style="font-size:9.5px; color:#546E7A; text-decoration:none;">{{ __('gl.back_to_customers_link') }}</a>
            {{-- SHRUNK 19 Jul 2026 per Chris: header + tab row were taking
                 too much vertical space to fit everything in one screen. --}}
            <h4 style="font-weight:700; margin:2px 0 0; font-size:12px; color:#1565C0;">
                {{ $customer->full_name }}
                @if(($customer->status_code ?? 'ACTIVE') !== 'ACTIVE')
                <span style="background:#F3E8FF;color:#6B21A8;padding:1px 7px;border-radius:20px;font-size:8.5px;font-weight:700;vertical-align:middle;">{{ strtoupper($customer->status_description ?? $customer->status_code) }}{{ ($customer->status_code ?? '') === 'PROSPECT' ? __('customers.not_yet_paying_customer_suffix') : '' }}</span>
                @endif
                @if(!empty($customer->customer_type_description))
                <span style="background:#DBEAFE;color:#1e40af;padding:1px 7px;border-radius:20px;font-size:8.5px;font-weight:700;vertical-align:middle;">{{ $customer->customer_type_description }}</span>
                @endif
            </h4>
        </div>
        <div style="display:flex; gap:8px;">
            {{-- NEW 19 Jul 2026 — per Chris: no role can ever delete a
                 customer/prospect record. This is the delete-replacement
                 action — sets status to Inactive, available to whoever
                 can see this record (owning agent, or Admin). Admin later
                 cleans up Inactive records via the separate Housekeeping
                 screen. --}}
            @if(($customer->status_code ?? '') !== 'INACTIVE')
            <form method="POST" action="{{ route($rolePrefix . '.customers.deactivate', $customer->customer_id) }}" onsubmit="return confirm({{ Js::from(__('customers.set_inactive_confirm')) }});">
                @csrf
                <button type="submit" style="background:#fff; color:#B91C1C; border:1px solid #FCA5A5; text-decoration:none; border-radius:6px; padding:5px 12px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('customers.set_inactive_button') }}</button>
            </form>
            @endif
            <a href="{{ route($rolePrefix . '.customers.edit', $customer->customer_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('gl.edit_customer_button') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:4px;">{{ session('success') }}</div>
    @endif

    <div style="display:flex; align-items:center; gap:3px; margin:0 2px;">
        <button type="button" class="cuTabBtn" data-tab="cuProfile" style="background:#fff; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9px; font-weight:700; color:#1565C0; cursor:pointer; position:relative; top:1px;">{{ __('customers.tab_profile') }}</button>
        <button type="button" class="cuTabBtn" data-tab="cuSales" style="background:#F7FAFC; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9px; font-weight:700; color:#6b7280; cursor:pointer; position:relative; top:1px;">{{ __('customers.tab_sales_history') }} <span style="font-weight:400;">({{ $transactions->total() }})</span></button>
        <button type="button" class="cuTabBtn" data-tab="cuRemRenewal" style="background:#F7FAFC; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9px; font-weight:700; color:#6b7280; cursor:pointer; position:relative; top:1px;">{{ __('customers.tab_renewal_reminder') }} <span style="font-weight:400;">({{ $renewalReminders->count() }})</span></button>
        <button type="button" class="cuTabBtn" data-tab="cuRemFollowUp" style="background:#F7FAFC; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9px; font-weight:700; color:#6b7280; cursor:pointer; position:relative; top:1px;">{{ __('customers.tab_follow_up_reminder') }} <span style="font-weight:400;">({{ $reminders->where('status','PENDING')->count() }})</span></button>
        {{-- NEW 29 Jul 2026 — Support Tickets tab (task #259). Per Chris:
             "make full use of EspoCRM" — Help Desk/Case Management. --}}
        <button type="button" class="cuTabBtn" data-tab="cuTickets" style="background:#F7FAFC; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9px; font-weight:700; color:#6b7280; cursor:pointer; position:relative; top:1px;">{{ __('customers.tab_support_tickets') }} <span style="font-weight:400;">({{ $tickets->whereNotIn('status', ['RESOLVED','CLOSED'])->count() }})</span></button>
        <button type="button" class="cuTabBtn" data-tab="cuActivity" style="background:#F7FAFC; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9px; font-weight:700; color:#6b7280; cursor:pointer; position:relative; top:1px;">{{ __('customers.tab_activity_log') }}</button>
    </div>
</div>

<div style="flex:1 1 auto; min-height:0; overflow-y:auto; padding-bottom:10px;">

    {{-- PROFILE — REBOXED 19 Jul 2026 per Chris's sketch: 2 columns.
         Left column stacks Contact & Created By on top of Address.
         Right column holds Classification, matching the taller combined
         height of the two left boxes. "Owned By" renamed to "Created By"
         (same underlying agent — no reassignment feature exists, so
         these are always the same person today). Address row order per
         Chris's mockup: Address, City, State, Postcode, Customer Since. --}}
    {{-- FIXED 19 Jul 2026 — per Chris (screenshot showed everything
         stacked full-width, not 2 columns): the tab-switching script at
         the bottom of this file sets p.style.display = 'block' on
         whichever panel is active, which was overwriting this panel's
         OWN display:grid the instant the Profile tab opened, collapsing
         it to a single stacked column. Moved the grid onto an inner
         wrapper div instead — the outer #cuProfile panel now only ever
         toggles between block/none (safe), and the actual 2-column
         grid lives one level deeper where the script never touches it. --}}
    {{-- RE-LAID-OUT 19 Jul 2026 per Chris: Address values were getting
         truncated in an equal-width column, and Column 2 (Classification
         alone) left a lot of empty space below it. Moved "Created By"
         out of the Contact box into its own box in Column 2 (below
         Classification, header styled the same as Address), which frees
         Column 1 to hold just Contact + Address, and widened Column 1 /
         narrowed Column 2 (1.4fr / 1fr) since Address needs the most
         horizontal room while Classification/Created By are all short
         values. Address row order: Address, Postcode, City, State,
         Customer Since. --}}
    <div id="cuProfile" class="cuTabPanel" style="background:#F7FAFC; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:8px;">
    <div style="display:grid; grid-template-columns:1.4fr 1fr; gap:6px;">
        <div style="display:flex; flex-direction:column; gap:6px;">
            <div style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:8px 10px;">
                <p style="font-size:8.5px; font-weight:700; color:#1565C0; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">{{ __('gl.col_contact') }}</p>
                {{-- FIXED 19 Jul 2026 per Chris: label width here must
                     match the Address box below it (Box 2) so both
                     boxes' value columns line up vertically — was 56px
                     here vs 80px in Address, so "VASUDAVAN..." and
                     "NO. 31 JALAN..." started at different x positions. --}}
                @foreach([[__('gl.field_full_name'),$customer->full_name],[__('customers.field_hp'),$customer->phone],[__('gl.field_email'),$customer->email??'—']] as $row)
                <div style="display:flex; gap:8px; padding:2px 0; border-bottom:1px solid #f0f4f8; font-size:9.5px;">
                    <span style="color:#546E7A; width:80px; flex-shrink:0;">{{ $row[0] }}</span>
                    <span style="font-weight:600; text-align:left;">{{ $row[1] }}</span>
                </div>
                @endforeach
            </div>
            {{-- FIXED 19 Jul 2026 per Chris: the raw $customer->address
                 column already has postcode/city/state baked into the
                 free-text string (how it was originally captured), which
                 duplicated those same values shown in the rows below.
                 Strip the known postcode/city/state substrings out of
                 the Address line for display only — the underlying
                 stored value is untouched. --}}
            @php
                $addressLine = trim(preg_replace('/\s+/', ' ', str_ireplace(array_filter([$customer->postcode, $customer->city, $customer->state]), '', $customer->address ?? '')));
                $addressLine = trim($addressLine, " ,\t\n\r\0\x0B");
                if ($addressLine === '') { $addressLine = '—'; }
            @endphp
            <div style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:8px 10px;">
                <p style="font-size:8.5px; font-weight:700; color:#1565C0; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">{{ __('gl.field_address') }}</p>
                @foreach([[__('gl.field_address'),$addressLine],[__('gl.field_postcode'),$customer->postcode??'—'],[__('gl.field_city'),$customer->city??'—'],[__('gl.field_state'),$customer->state??'—'],[__('customers.customer_since_label'), \Illuminate\Support\Carbon::parse($customer->created_at)->format('d M Y')]] as $row)
                <div style="display:flex; gap:8px; padding:2px 0; border-bottom:1px solid #f0f4f8; font-size:9.5px;">
                    <span style="color:#546E7A; width:80px; flex-shrink:0;">{{ $row[0] }}</span>
                    <span style="font-weight:600; text-align:left;">{{ $row[1] }}</span>
                </div>
                @endforeach
            </div>
        </div>
        <div style="display:flex; flex-direction:column; gap:6px;">
            <div style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:8px 10px;">
                <p style="font-size:8.5px; font-weight:700; color:#1565C0; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">{{ __('customers.classification_heading') }}</p>
                @foreach([[__('gl.col_status'),$customer->status_description??'—'],[__('customers.field_type'),$customer->customer_type_description??'—'],[__('customers.field_category'),$customer->category_description??'—'],[__('customers.field_occupation'),$customer->occupation_group_description??'—'],[__('customers.field_source'),$customer->source_description??'—']] as $row)
                <div style="display:flex; gap:8px; padding:2px 0; border-bottom:1px solid #f0f4f8; font-size:9.5px;">
                    <span style="color:#546E7A; width:70px; flex-shrink:0;">{{ $row[0] }}</span>
                    <span style="font-weight:600; text-align:left;">{{ $row[1] }}</span>
                </div>
                @endforeach
            </div>
            <div style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:8px 10px;">
                <p style="font-size:8.5px; font-weight:700; color:#1565C0; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">{{ __('customers.created_by_heading') }}</p>
                <p style="font-weight:700; font-size:10.5px; margin:2px 0 1px;">{{ $customer->agent_name }}</p>
                <p style="color:#546E7A; font-size:9px; margin-bottom:3px;">{{ $customer->agent_code }}</p>
                @php $rc=['GROUP_LEADER'=>['#E1F5EE','#0F6E56'],'TEAM_LEADER'=>['#E6F1FB','#0C447C'],'INTRODUCER'=>['#F3E8FF','#6B21A8']][$customer->agent_role]??['#f3f4f6','#374151']; @endphp
                <span style="font-size:8.5px; padding:1px 8px; border-radius:20px; background:{{ $rc[0] }}; color:{{ $rc[1] }};">
                    {{ \App\Services\RoleLabelService::label($customer->agent_role) }}
                </span>
            </div>
        </div>
    </div>
    </div>

    {{-- SALES HISTORY — REBUILT 26 Jul 2026, per Chris: replaced the tall
         "Policy / Sale Details" cards with a compact row/table view —
         No, Sales Date, Vendor, Product Type, Amount, plus a View link
         that opens the full Sales Transaction form (Document Ref#,
         Status, Sum Insured, Coverage dates/type, NCD%, Excess, Add-ons
         etc. all still live on that dedicated screen). 10 records per
         page, footer Prev/Next styled exactly like the main KPI
         Dashboard's paginated screens (solid blue pill, bottom-left/
         right, grayed only at the real start/end of the list) instead
         of Laravel's default numbered page links — sized to fit all 10
         rows with no scrolling. --}}
    <div id="cuSales" class="cuTabPanel" style="display:none; background:#F7FAFC; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:8px 10px;">
        <div style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px; table-layout:fixed;">
                <thead>
                    <tr style="background:#f0f7ff;">
                        <th style="width:34px; padding:6px 8px; text-align:left; font-size:8.5px; color:#546E7A; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #B2EBF2;">{{ __('customers.col_no') }}</th>
                        <th style="width:90px; padding:6px 8px; text-align:left; font-size:8.5px; color:#546E7A; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #B2EBF2;">{{ __('customers.col_sales_date') }}</th>
                        <th style="padding:6px 8px; text-align:left; font-size:8.5px; color:#546E7A; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #B2EBF2;">{{ __('gl.col_vendor') }}</th>
                        <th style="padding:6px 8px; text-align:left; font-size:8.5px; color:#546E7A; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #B2EBF2;">{{ __('tl.col_product_type') }}</th>
                        <th style="width:95px; padding:6px 8px; text-align:right; font-size:8.5px; color:#546E7A; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #B2EBF2;">{{ __('network.col_sales_amount_rm') }}</th>
                        <th style="width:95px; padding:6px 8px; text-align:right; font-size:8.5px; color:#546E7A; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #B2EBF2;">{{ __('gl.col_earning_income_rm') }}</th>
                        <th style="width:60px; padding:6px 8px; border-bottom:1px solid #B2EBF2;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $i => $txn)
                    <tr style="border-bottom:1px solid #f0f4f8;">
                        <td style="padding:6px 8px; color:#94a3b8;">{{ $transactions->firstItem() + $i }}</td>
                        <td style="padding:6px 8px; color:#546E7A;">{{ \Carbon\Carbon::parse($txn->created_at)->format('d M Y') }}</td>
                        <td style="padding:6px 8px; color:#111827; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $txn->vendor_name }}</td>
                        <td style="padding:6px 8px; color:#374151; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ ucwords(strtolower(str_replace('_',' ',$txn->product_type))) }}</td>
                        <td style="padding:6px 8px; text-align:right; font-weight:700; color:#0f9c96;">{{ number_format($txn->premium_amount,2) }}</td>
                        <td style="padding:6px 8px; text-align:right; font-weight:700; color:#F9A825;">{{ $txn->earning_income ? number_format($txn->earning_income,2) : '—' }}</td>
                        <td style="padding:6px 8px; text-align:right;"><a href="{{ route($rolePrefix . '.sales-transactions.show', $txn->policy_id) }}" style="color:#1565C0; font-weight:600; text-decoration:none; font-size:9px;">{{ __('customers.view_link_arrow') }}</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="padding:24px; text-align:center; color:#999; font-size:10.5px;">{{ __('gl.no_transactions_found_dot') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 2px 0;">
            @if($transactions->onFirstPage())
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
            <a href="{{ $transactions->previousPageUrl() }}#cuSales" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9px; color:#607d8b;">{{ __('customers.page_x_of_y_total', ['current' => $transactions->currentPage(), 'last' => $transactions->lastPage(), 'total' => $transactions->total()]) }}</span>
            @if($transactions->hasMorePages())
            <a href="{{ $transactions->nextPageUrl() }}#cuSales" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.next') }}</a>
            @else
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>

    {{-- REMINDERS — UN-NESTED 19 Jul 2026 per Chris: was a "Reminders"
         tab with a Renewal Reminder / Follow Up Reminder sub-tab drill-
         down underneath it; that extra click-through is now removed —
         Renewal Reminder and Follow Up Reminder are their own top-level
         tabs, in the same row as Profile / Sales History / Activity Log
         / BI Dashboard. --}}

    {{-- RENEWAL REMINDER — read-only, system-generated per policy --}}
    <div id="cuRemRenewal" class="cuTabPanel" style="display:none; background:#F7FAFC; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:4px 10px 10px;">
        <div style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:10px 12px;">
            @forelse($renewalReminders as $rr)
            <div style="border-bottom:1px solid #f7fafc; padding:8px 0;">
                <div style="display:flex; align-items:baseline; gap:6px; margin-bottom:2px; flex-wrap:wrap;">
                    <span style="font-size:10px; font-weight:700; color:#374151;">{{ $rr->product_name }}</span>
                    <span style="font-size:9px; color:#9ca3af;">{{ $rr->vendor_name }} &middot; {{ __('customers.ref_hash_label') }} {{ $rr->document_reference_number }}</span>
                    @if($rr->reminder_sent_at)
                    <span style="background:#d1fae5; color:#065f46; padding:1px 7px; border-radius:20px; font-size:8.5px; font-weight:600;">{{ __('customers.sent_date_label', ['date' => \Carbon\Carbon::parse($rr->reminder_sent_at)->format('d M Y')]) }}</span>
                    @else
                    <span style="background:#fef3c7; color:#92400e; padding:1px 7px; border-radius:20px; font-size:8.5px; font-weight:600;">{{ __('customers.scheduled_date_label', ['date' => \Carbon\Carbon::parse($rr->reminder_scheduled_date)->format('d M Y')]) }}</span>
                    @endif
                </div>
                {{-- TIGHTENED 19 Jul 2026 per Chris: the stored message
                     has blank-line paragraph breaks (Dear .../ blank /
                     Your policy is due.../ blank / item list / blank /
                     Please review.../ blank / {{RENEWAL_LINK}}/ blank /
                     Your agent.../ blank / GeneralLink). Rendering it
                     as one white-space:pre-line block gave every blank
                     line a full text-line-height's worth of empty
                     space. Split into individual lines instead so each
                     blank line can be shrunk to a small fixed gap
                     independently of the font's line-height. --}}
                <div style="background:#F7FAFC; border:1px solid #E2E8F0; border-radius:6px; padding:5px 8px; font-size:9.5px; color:#374151;">
                    @php $rrLines = preg_split('/\r\n|\r|\n/', $rr->reminder_message ?? ''); @endphp
                    @foreach($rrLines as $rrLine)
                        @if(trim($rrLine) === '')
                        <div style="height:4px;"></div>
                        @else
                        <div style="line-height:1.25;">{{ $rrLine }}</div>
                        @endif
                    @endforeach
                </div>
            </div>
            @empty
            <div style="font-size:10.5px; color:#9ca3af; padding:6px 0;">{{ __('customers.no_renewal_reminder_note') }}</div>
            @endforelse
        </div>
    </div>

    {{-- FOLLOW UP REMINDER — agent's own personal reminders --}}
    <div id="cuRemFollowUp" class="cuTabPanel" style="display:none; background:#F7FAFC; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:4px 10px 10px;">
        <div style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:10px 12px;">
            @forelse($reminders as $r)
            <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; padding:6px 0; border-bottom:1px solid #f7fafc;">
                <div style="min-width:0;">
                    @php $rt=['CALL_FOLLOW_UP'=>['#DBEAFE','#1e40af',__('customers.reminder_type_call_followup')],'RENEWAL'=>['#EDE9FE','#5b21b6',__('customers.reminder_type_renewal')],'OTHER'=>['#f3f4f6','#374151',__('customers.reminder_type_other')]][$r->reminder_type]??['#f3f4f6','#374151',$r->reminder_type]; @endphp
                    <span style="background:{{ $rt[0] }}; color:{{ $rt[1] }}; padding:1px 8px; border-radius:20px; font-size:8.5px; font-weight:700;">{{ $rt[2] }}</span>
                    <span style="font-size:10.5px; font-weight:600; color:#374151; margin-left:4px;">{{ \Carbon\Carbon::parse($r->reminder_date)->format('d M Y') }}</span>
                    @if($r->status !== 'PENDING')
                    <span style="font-size:9px; color:#9ca3af;">({{ ucfirst(strtolower($r->status)) }})</span>
                    @endif
                    @if($r->escalated)
                    <span style="background:#DBEAFE; color:#1e40af; padding:1px 7px; border-radius:20px; font-size:8.5px; font-weight:700;" title="{{ __('customers.escalated_title_note') }}">{{ __('customers.escalated_sent_badge') }}</span>
                    @endif
                    @if($r->note)
                    <div style="font-size:9.5px; color:#6b7280; margin-top:2px;">{{ $r->note }}</div>
                    @endif
                </div>
                @if($r->status === 'PENDING')
                <div style="display:flex; gap:6px; flex-shrink:0;">
                    <form method="POST" action="{{ route($rolePrefix . '.reminders.done', $r->reminder_id) }}" style="margin:0;">
                        @csrf
                        <button type="submit" style="background:#e8f5e9; color:#1b5e20; border:none; border-radius:5px; padding:3px 9px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('customers.done_button') }}</button>
                    </form>
                    <form method="POST" action="{{ route($rolePrefix . '.reminders.destroy', $r->reminder_id) }}" onsubmit="return confirm({{ Js::from(__('customers.remove_reminder_confirm')) }});" style="margin:0;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" style="background:#fef2f2; color:#991b1b; border:none; border-radius:5px; padding:3px 9px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('customers.remove_button') }}</button>
                    </form>
                </div>
                @endif
            </div>
            @empty
            <div style="font-size:10.5px; color:#9ca3af; padding:6px 0;">{{ __('customers.no_personal_reminders_note', ['type' => ($customer->status_code ?? '') === 'PROSPECT' ? __('customers.prospect_word') : __('customers.customer_word')]) }}</div>
            @endforelse

            {{-- NEW 19 Jul 2026 — per Chris: Save keeps it private (as
                 before). Save and Send also notifies whichever side
                 isn't you — your own upline chain (TL/GL/Admin) if you
                 own this customer, or the owning agent if you're Admin
                 acting on someone else's — via the notification bell. --}}
            <form method="POST" action="{{ route($rolePrefix . '.customers.reminders.store', $customer->customer_id) }}" style="display:flex; gap:6px; align-items:flex-end; flex-wrap:wrap; margin-top:8px; padding-top:8px; border-top:1px solid #f7fafc;">
                @csrf
                <div style="flex:1 1 150px; min-width:130px;">
                    <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('gl.col_type') }}</label>
                    <select name="reminder_type" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                        <option value="CALL_FOLLOW_UP">{{ __('customers.reminder_type_call_followup') }}</option>
                        <option value="RENEWAL">{{ __('customers.reminder_type_renewal') }}</option>
                        <option value="OTHER">{{ __('customers.reminder_type_other') }}</option>
                    </select>
                </div>
                <div style="flex:1 1 130px; min-width:120px;">
                    <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('network.col_date') }}</label>
                    <input type="date" name="reminder_date" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="flex:2 1 200px; min-width:160px;">
                    <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('customers.note_label') }}</label>
                    <input type="text" name="note" placeholder="{{ __('customers.follow_up_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <button type="submit" name="submit_action" value="save" style="background:#f3f4f6; color:#374151; border:1px solid #d1d5db; border-radius:5px; padding:6px 14px; font-size:10.5px; font-weight:700; cursor:pointer; white-space:nowrap;">{{ __('customers.save_button') }}</button>
                <button type="submit" name="submit_action" value="save_send" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:6px 14px; font-size:10.5px; font-weight:700; cursor:pointer; white-space:nowrap;" title="{{ __('customers.save_send_title_note') }}">{{ __('customers.save_and_send_button') }}</button>
            </form>
        </div>
    </div>

    {{-- SUPPORT TICKETS — NEW 29 Jul 2026 (task #259). Per Chris: "make
         full use of EspoCRM" — Help Desk / Case Management / Complaint
         Management / Service Request. Each ticket here is also mirrored
         as a Case in EspoCRM (free, core feature) via
         EspoCrmService::createCase — GeneralLink stays the only screen
         anyone actually uses. Shared visibility (not private like
         Follow Up Reminder) since any agent handling this customer
         should see open complaints/requests against them. --}}
    <div id="cuTickets" class="cuTabPanel" style="display:none; background:#F7FAFC; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:4px 10px 10px;">
        <div style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:10px 12px;">
            @forelse($tickets as $t)
            @php
                $pc = ['HIGH'=>['#fee2e2','#991b1b'],'MEDIUM'=>['#fef3c7','#92400e'],'LOW'=>['#e0f2fe','#075985']][$t->priority] ?? ['#f3f4f6','#374151'];
                $sc = ['OPEN'=>['#dbeafe','#1e40af'],'IN_PROGRESS'=>['#fef3c7','#92400e'],'RESOLVED'=>['#d1fae5','#065f46'],'CLOSED'=>['#f3f4f6','#374151']][$t->status] ?? ['#f3f4f6','#374151'];
                $overdue = $t->due_at && \Carbon\Carbon::parse($t->due_at)->isPast() && !in_array($t->status, ['RESOLVED','CLOSED']);
                $priorityLabels = ['HIGH'=>__('customers.priority_high'),'MEDIUM'=>__('customers.priority_medium'),'LOW'=>__('customers.priority_low')];
                $ticketTypeLabels = ['COMPLAINT'=>__('customers.ticket_type_complaint'),'SERVICE_REQUEST'=>__('customers.ticket_type_service_request'),'QUESTION'=>__('customers.ticket_type_question'),'OTHER'=>__('customers.ticket_type_other')];
            @endphp
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:8px; padding:6px 0; border-bottom:1px solid #f7fafc;">
                <div style="min-width:0;">
                    <span style="background:{{ $pc[0] }}; color:{{ $pc[1] }}; padding:1px 8px; border-radius:20px; font-size:8.5px; font-weight:700;">{{ $priorityLabels[$t->priority] ?? ucfirst(strtolower($t->priority)) }}</span>
                    <span style="background:#f3f4f6; color:#374151; padding:1px 8px; border-radius:20px; font-size:8.5px; font-weight:700; margin-left:4px;">{{ $ticketTypeLabels[$t->ticket_type] ?? ucwords(strtolower(str_replace('_',' ',$t->ticket_type))) }}</span>
                    <span style="font-size:10.5px; font-weight:600; color:#374151; margin-left:4px;">{{ $t->subject }}</span>
                    @if($overdue)
                    <span style="background:#fee2e2; color:#991b1b; padding:1px 7px; border-radius:20px; font-size:8.5px; font-weight:700; margin-left:4px;">{{ __('customers.overdue_badge') }}</span>
                    @endif
                    @if($t->description)
                    <div style="font-size:9.5px; color:#6b7280; margin-top:2px;">{{ $t->description }}</div>
                    @endif
                    <div style="font-size:8.5px; color:#9ca3af; margin-top:2px;">{{ __('customers.logged_date_label', ['date' => \Carbon\Carbon::parse($t->created_at)->format('d M Y')]) }} @if($t->due_at) &middot; {{ __('customers.due_date_label', ['date' => \Carbon\Carbon::parse($t->due_at)->format('d M Y, g:ia')]) }} @endif</div>
                </div>
                <form method="POST" action="{{ route('support-tickets.update-status', $t->ticket_id) }}" style="margin:0; flex-shrink:0;">
                    @csrf
                    <select name="status" onchange="this.form.submit()" style="background:{{ $sc[0] }}; color:{{ $sc[1] }}; border:none; border-radius:20px; padding:3px 8px; font-size:8.5px; font-weight:700;">
                        <option value="OPEN" {{ $t->status==='OPEN'?'selected':'' }}>{{ __('customers.ticket_status_open') }}</option>
                        <option value="IN_PROGRESS" {{ $t->status==='IN_PROGRESS'?'selected':'' }}>{{ __('customers.ticket_status_in_progress') }}</option>
                        <option value="RESOLVED" {{ $t->status==='RESOLVED'?'selected':'' }}>{{ __('customers.ticket_status_resolved') }}</option>
                        <option value="CLOSED" {{ $t->status==='CLOSED'?'selected':'' }}>{{ __('customers.ticket_status_closed') }}</option>
                    </select>
                </form>
            </div>
            @empty
            <div style="font-size:10.5px; color:#9ca3af; padding:6px 0;">{{ __('customers.no_support_tickets_note', ['type' => ($customer->status_code ?? '') === 'PROSPECT' ? __('customers.prospect_word') : __('customers.customer_word')]) }}</div>
            @endforelse

            <form method="POST" action="{{ route('support-tickets.store', $customer->customer_id) }}" style="display:flex; gap:6px; align-items:flex-end; flex-wrap:wrap; margin-top:8px; padding-top:8px; border-top:1px solid #f7fafc;">
                @csrf
                <div style="flex:2 1 200px; min-width:160px;">
                    <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('customers.subject_label') }}</label>
                    <input type="text" name="subject" required maxlength="200" placeholder="{{ __('customers.brief_summary_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="flex:1 1 130px; min-width:120px;">
                    <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('gl.col_type') }}</label>
                    <select name="ticket_type" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                        <option value="COMPLAINT">{{ __('customers.ticket_type_complaint') }}</option>
                        <option value="SERVICE_REQUEST">{{ __('customers.ticket_type_service_request') }}</option>
                        <option value="QUESTION" selected>{{ __('customers.ticket_type_question') }}</option>
                        <option value="OTHER">{{ __('customers.ticket_type_other') }}</option>
                    </select>
                </div>
                <div style="flex:1 1 110px; min-width:100px;">
                    <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('customers.priority_label') }}</label>
                    <select name="priority" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                        <option value="LOW">{{ __('customers.priority_low') }}</option>
                        <option value="MEDIUM" selected>{{ __('customers.priority_medium') }}</option>
                        <option value="HIGH">{{ __('customers.priority_high') }}</option>
                    </select>
                </div>
                <div style="flex:2 1 200px; min-width:160px;">
                    <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('customers.details_label') }}</label>
                    <input type="text" name="description" maxlength="2000" placeholder="{{ __('customers.optional_details_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:6px 14px; font-size:10.5px; font-weight:700; cursor:pointer; white-space:nowrap;">{{ __('customers.log_ticket_button') }}</button>
            </form>
        </div>
    </div>

    {{-- ACTIVITY LOG HISTORY --}}
    <div id="cuActivity" class="cuTabPanel" style="display:none; background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:0;">
        <div style="font-size:9.5px; color:#9ca3af; padding:8px 12px 0;">{{ __('customers.activity_log_note') }}</div>
        <table style="width:100%; border-collapse:collapse; font-size:10.5px; margin-top:6px;">
            <thead style="background:#f0f7ff;">
                <tr>
                    <th style="padding:6px 10px; text-align:left; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #B2EBF2;">{{ __('network.col_date') }}</th>
                    <th style="padding:6px 6px; text-align:left; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #B2EBF2;">{{ __('gl.col_action') }}</th>
                    <th style="padding:6px 6px; text-align:left; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #B2EBF2;">{{ __('customers.col_by') }}</th>
                    <th style="padding:6px 6px; text-align:left; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #B2EBF2;">{{ __('customers.details_label') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($activityLog as $log)
                <tr style="border-bottom:1px solid #f0f4f8;">
                    <td style="padding:7px 10px; white-space:nowrap; color:#546E7A;">{{ \Illuminate\Support\Carbon::parse($log->created_at)->format('d M Y, g:ia') }}</td>
                    <td style="padding:7px 6px;">
                        @php $ab=['CREATE'=>['#d1fae5','#065f46',__('customers.action_create')],'UPDATE'=>['#dbeafe','#1e40af',__('customers.action_update')],'DELETE'=>['#fee2e2','#991b1b',__('customers.action_delete')]][$log->action]??['#f3f4f6','#374151',$log->action]; @endphp
                        <span style="background:{{ $ab[0] }}; color:{{ $ab[1] }}; padding:1px 8px; border-radius:20px; font-size:9px; font-weight:600;">{{ $ab[2] }}</span>
                    </td>
                    <td style="padding:7px 6px;">{{ $log->changed_by ?? __('customers.system_word') }}</td>
                    <td style="padding:7px 6px; color:#4b5563; font-size:9.5px;">
                        @php $after = $log->after_value ? json_decode($log->after_value, true) : []; @endphp
                        @if(!empty($after))
                            {{ collect($after)->map(fn($v,$k) => ucwords(str_replace('_',' ',$k)) . ': ' . $v)->implode(' · ') }}
                        @else
                            &mdash;
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" style="padding:24px; text-align:center; color:#999; font-size:10.5px;">{{ __('gl.no_records_found') }}</td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- EspoCRM TIMELINE — NEW 29 Jul 2026 (task #263). Per Chris:
             "have you incorporate ALL EspoCRM feature" — this was the
             one genuine reuse item from the Feature Reuse Review that
             hadn't actually been wired up yet: EspoCRM's own Stream
             (activity feed) for this customer's Contact record, shown
             read-only alongside GeneralLink's own log above — kept as
             its own section rather than merged, since it's a different
             system's record of a different set of events. --}}
        <div style="font-size:9.5px; color:#9ca3af; padding:14px 12px 0; border-top:1px solid #f0f4f8; margin-top:10px;">{{ __('customers.espocrm_timeline_heading') }} <span style="font-weight:400;">{{ __('customers.espocrm_timeline_subtitle') }}</span></div>
        <div style="padding:6px 12px 12px;">
            @forelse($espoTimeline as $item)
            <div style="display:flex; gap:8px; padding:5px 0; border-bottom:1px solid #f7fafc; font-size:9.5px;">
                <span style="color:#9ca3af; white-space:nowrap;">{{ !empty($item['createdAt']) ? \Illuminate\Support\Carbon::parse($item['createdAt'])->format('d M Y, g:ia') : '—' }}</span>
                <span style="color:#374151;">
                    <span style="font-weight:600;">{{ $item['createdByName'] ?? 'EspoCRM' }}</span>
                    @if(!empty($item['post']))
                        &mdash; {{ $item['post'] }}
                    @else
                        &mdash; {{ ucfirst(strtolower($item['type'] ?? 'activity')) }}
                    @endif
                </span>
            </div>
            @empty
            <div style="font-size:10px; color:#9ca3af; padding:4px 0;">{{ !empty($customer->espocrm_contact_id) ? __('customers.no_espocrm_activity_note') : __('customers.not_mirrored_espocrm_note') }}</div>
            @endforelse
        </div>
    </div>

</div>

{{-- NEW 25 Jul 2026 — per Chris: EVERY screen needs a bottom Prev, filled
     blue, bottom-left, no exceptions — this profile screen had none at
     all. This screen is reached from many different places (Customer
     list, Customer KPI dashboard/list/state drill-downs, Renewal
     Forecast, Survey Responses, etc.), so rather than hardcode one
     fixed destination, Prev returns to whichever screen actually linked
     here (browser history) — the one navigation button that is always
     correct no matter the entry point. No Next applies here since this
     is a single customer record, not a paginated list. --}}
<div style="flex-shrink:0; padding:6px 0;">
    <a href="javascript:void(0)" onclick="history.back()" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700; display:inline-block;">{{ __('network.prev') }}</a>
</div>

</div>

<script>
(function() {
    var tabBtns = document.querySelectorAll('.cuTabBtn');
    var tabPanels = document.querySelectorAll('.cuTabPanel');

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

    // Sales History Prev/Next links reload the page with #cuSales — honor
    // that hash so paging doesn't kick you back to the Profile tab.
    var initialTab = 'cuProfile';
    var hashTab = window.location.hash.replace('#', '');
    if (hashTab && document.getElementById(hashTab)) { initialTab = hashTab; }
    activateTab(initialTab);
})();
</script>

@endsection
