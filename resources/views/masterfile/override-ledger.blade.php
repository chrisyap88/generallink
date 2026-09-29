@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.affiliate_partner_ledger_title'))
@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:6px; font-size:10px;">

    <div style="display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
        <a href="{{ route('admin.masterfile.override-claims') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:600;">{{ __('masterfile.claim_submission_link') }}</a>
    </div>
    <div style="font-size:9.5px; color:#9ca3af; flex-shrink:0;">
        {{ __('masterfile.ledger_intro') }}
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.masterfile.override-ledger') }}" style="display:flex; align-items:flex-end; gap:6px; flex-shrink:0; flex-wrap:nowrap;">
        <div>
            <div style="font-size:8.5px; color:#718096; margin-bottom:1px;">{{ __('masterfile.status') }}</div>
            <select name="status" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; background:#fff; height:24px; width:130px;">
                <option value="">{{ __('masterfile.all_status_option') }}</option>
                @foreach(['CALCULATED','SUBMITTED','APPROVED','PAID','REJECTED'] as $s)
                <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ __('masterfile.status_'.strtolower($s)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <div style="font-size:8.5px; color:#718096; margin-bottom:1px;">{{ __('masterfile.col_vendor') }}</div>
            <select name="vendor_id" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; background:#fff; height:24px; width:130px;">
                <option value="">{{ __('masterfile.all_vendors_option') }}</option>
                @foreach($vendors as $v)
                <option value="{{ $v->vendor_id }}" {{ $vendorId === $v->vendor_id ? 'selected' : '' }}>{{ $v->vendor_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <div style="font-size:8.5px; color:#718096; margin-bottom:1px;">{{ __('masterfile.affiliate_partner_label') }}</div>
            <select name="member_id" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; background:#fff; height:24px; width:150px;">
                <option value="">{{ __('masterfile.all_members_option') }}</option>
                @foreach($members as $m)
                <option value="{{ $m->override_member_id }}" {{ $memberId === $m->override_member_id ? 'selected' : '' }}>{{ $m->full_name }} ({{ $m->override_member_code }})</option>
                @endforeach
            </select>
        </div>
        <button type="submit" style="background:#1B9AE4; color:#fff; border:none; border-radius:5px; padding:4px 14px; font-size:10px; font-weight:600; cursor:pointer; height:24px;">{{ __('masterfile.go_button') }}</button>
        <a href="{{ route('admin.masterfile.override-ledger') }}" style="background:#f3f4f6; color:#4A5568; text-decoration:none; border-radius:5px; padding:4px 14px; font-size:10px; font-weight:600; height:24px; display:inline-flex; align-items:center;">{{ __('masterfile.clear') }}</a>
        {{-- NEW 2 Aug 2026 — per Chris: after picking a member, open the
             clean boxed Debit/Credit statement instead of just filtering
             this list. formaction/formmethod submit this SAME form (so
             the selected member_id carries over) to the statement route
             instead of this page. --}}
        <button type="submit" formaction="{{ route('admin.masterfile.override-ledger.statement') }}" formmethod="GET" style="background:#38A169; color:#fff; border:none; border-radius:5px; padding:4px 14px; font-size:10px; font-weight:600; cursor:pointer; height:24px;" onclick="if(!this.form.member_id.value){event.preventDefault();alert({{ json_encode(__('masterfile.pick_affiliate_partner_alert')) }});}">{{ __('masterfile.view_statement_button') }}</button>
    </form>

    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; overflow:hidden; min-height:0;">
            <table style="width:100%; border-collapse:collapse; font-size:10px; table-layout:fixed;">
                <thead style="background:#F7FAFC;">
                    <tr>
                        <th style="width:30px;"></th>
                        <th style="width:160px; text-align:left; padding:5px 8px; color:#6b7280; font-weight:700; border-bottom:2px solid #E2E8F0;">{{ __('masterfile.col_member') }}</th>
                        <th style="width:100px; text-align:left; padding:5px 8px; color:#6b7280; font-weight:700; border-bottom:2px solid #E2E8F0;">{{ __('masterfile.col_vendor') }}</th>
                        <th style="width:110px; text-align:left; padding:5px 8px; color:#6b7280; font-weight:700; border-bottom:2px solid #E2E8F0;">{{ __('masterfile.col_period') }}</th>
                        <th style="width:90px; text-align:right; padding:5px 8px; color:#6b7280; font-weight:700; border-bottom:2px solid #E2E8F0;">{{ __('masterfile.col_amount_rm') }}</th>
                        <th style="width:80px; text-align:center; padding:5px 8px; color:#6b7280; font-weight:700; border-bottom:2px solid #E2E8F0;">{{ __('masterfile.status') }}</th>
                        <th style="width:130px; text-align:left; padding:5px 8px; color:#6b7280; font-weight:700; border-bottom:2px solid #E2E8F0;">{{ __('masterfile.col_last_action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($claims as $c)
                    @php
                        $statusColors = [
                            'CALCULATED' => ['#F3F4F6', '#374151'], 'SUBMITTED'  => ['#E3F2FD', '#1565C0'],
                            'APPROVED'   => ['#F3E5F5', '#4A148C'], 'PAID'       => ['#C8E6C9', '#1B5E20'],
                            'REJECTED'   => ['#FFCDD2', '#B71C1C'], 'SETTLED'    => ['#C8E6C9', '#1B5E20'],
                        ];
                        [$bg, $fg] = $statusColors[$c->status] ?? ['#F3F4F6', '#374151'];
                        $lastAction = match($c->status) {
                            'PAID', 'SETTLED' => $c->paid_by_name ? __('masterfile.paid_by_label', ['name' => $c->paid_by_name]) : __('masterfile.status_paid'),
                            'APPROVED' => $c->approved_by_name ? __('masterfile.approved_by_label', ['name' => $c->approved_by_name]) : __('masterfile.status_approved'),
                            'REJECTED' => $c->approved_by_name ? __('masterfile.rejected_by_label', ['name' => $c->approved_by_name]) : __('masterfile.status_rejected'),
                            'SUBMITTED' => $c->submitted_by_name ? __('masterfile.submitted_by_label', ['name' => $c->submitted_by_name]) : __('masterfile.status_submitted'),
                            default => __('masterfile.calculated_system_label'),
                        };
                    @endphp
                    <tr onclick="toggleLedgerRow('lr_{{ $c->claim_id }}')" style="border-top:1px solid #f1f5f9; cursor:pointer;" onmouseover="this.style.background='#F7FAFC'" onmouseout="this.style.background=''">
                        <td style="padding:5px 8px; color:#9ca3af; text-align:center;">▸</td>
                        <td style="padding:5px 8px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" onclick="event.stopPropagation();"><a href="{{ route('admin.masterfile.override-ledger.statement', ['member_id' => $c->override_member_id]) }}" style="color:#0D5A8E; font-weight:600; text-decoration:none;">{{ $c->member_name }}</a> <span style="color:#9ca3af; font-size:9px;">({{ $c->override_member_code }})</span></td>
                        <td style="padding:5px 8px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $c->vendor_name }}</td>
                        <td style="padding:5px 8px; font-size:9px; color:#6b7280;">{{ \Carbon\Carbon::parse($c->period_start)->format('d M y') }}–{{ \Carbon\Carbon::parse($c->period_end)->format('d M y') }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:700;">{{ number_format($c->calculated_amount, 2) }}</td>
                        <td style="padding:5px 8px; text-align:center;">
                            <span style="background:{{ $bg }}; color:{{ $fg }}; font-size:8.5px; font-weight:700; padding:2px 7px; border-radius:20px;">{{ __('masterfile.status_'.strtolower($c->status)) }}</span>
                        </td>
                        <td style="padding:5px 8px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#6b7280; font-size:9px;">{{ $lastAction }}</td>
                    </tr>
                    <tr id="lr_{{ $c->claim_id }}" style="display:none; background:#FAFBFC;">
                        <td colspan="7" style="padding:8px 20px; font-size:9.5px; color:#4A5568;">
                            <div style="display:flex; flex-wrap:wrap; gap:18px;">
                                <span><strong>{{ __('masterfile.sales_basis_label') }}</strong> RM {{ number_format($c->sales_basis_amount ?? 0, 2) }} ({{ $c->product_name ?? __('masterfile.any_product_fallback') }})</span>
                                <span><strong>{{ __('masterfile.calculated_label') }}</strong> {{ \Carbon\Carbon::parse($c->created_at)->format('d M Y, g:ia') }} ({{ __('masterfile.calculated_system_label') }})</span>
                                <span><strong>{{ __('masterfile.submitted_label') }}</strong> {{ $c->submitted_at ? \Carbon\Carbon::parse($c->submitted_at)->format('d M Y, g:ia') . __('masterfile.by_name_suffix', ['name' => $c->submitted_by_name]) : '—' }}</span>
                                <span><strong>{{ __('masterfile.approved_label') }}</strong> {{ ($c->approved_at && $c->status !== 'REJECTED') ? \Carbon\Carbon::parse($c->approved_at)->format('d M Y, g:ia') . __('masterfile.by_name_suffix', ['name' => $c->approved_by_name]) : '—' }}</span>
                                @if($c->status === 'REJECTED')
                                <span style="color:#B71C1C;"><strong>{{ __('masterfile.rejected_label') }}</strong> {{ $c->rejection_reason ?? __('masterfile.no_reason_given') }}</span>
                                @endif
                                <span><strong>{{ __('masterfile.paid_label') }}</strong> {{ $c->settled_at ? \Carbon\Carbon::parse($c->settled_at)->format('d M Y, g:ia') . __('masterfile.by_name_suffix', ['name' => $c->paid_by_name]) . ($c->payment_reference ? __('masterfile.ref_colon_label', ['ref' => $c->payment_reference]) : '') : '—' }}</span>
                                <span><strong>{{ __('masterfile.settlement_method_label') }}</strong> {{ $c->settlement_method === 'DEDUCT_FROM_CLAIM' ? __('masterfile.deduct_from_claim') : __('masterfile.claim_back_report') }}</span>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="text-align:center; padding:40px; color:#9ca3af;">{{ __('masterfile.no_override_claims_ledger') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="flex-shrink:0; padding:6px 12px; border-top:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
            <div style="font-size:10px; color:#9ca3af;">{{ __('masterfile.claims_count_page_label', ['count' => $claims->total(), 'current' => $claims->currentPage(), 'last' => max(1, $claims->lastPage())]) }}</div>
            <div style="display:flex; gap:6px;">
                @if($claims->onFirstPage())
                <span style="background:#93c5fd; color:#fff; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:600;">{{ __('masterfile.prev') }}</span>
                @else
                <a href="{{ $claims->appends(request()->query())->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:700;">{{ __('masterfile.prev') }}</a>
                @endif
                @if($claims->hasMorePages())
                <a href="{{ $claims->appends(request()->query())->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:700;">{{ __('masterfile.next') }}</a>
                @else
                <span style="background:#93c5fd; color:#fff; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:600;">{{ __('masterfile.next') }}</span>
                @endif
            </div>
        </div>
    </div>
</div>
<script>
function toggleLedgerRow(id){
    var r = document.getElementById(id);
    if (!r) return;
    r.style.display = (r.style.display === 'none') ? 'table-row' : 'none';
}
</script>
@endsection
