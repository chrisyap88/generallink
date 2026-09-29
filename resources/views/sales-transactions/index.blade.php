@extends('layouts.dashboard')

@section('page-title', __('sales_transactions.title'))

@push('styles')
<style>
.stx-wrap{padding:6px 8px;display:flex;flex-direction:column;gap:6px;height:calc(100vh - 66px);box-sizing:border-box;overflow:hidden;}
.stx-card{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:8px 12px;}
.stx-filter-bar{padding:6px 10px;flex-shrink:0;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.stx-filter-bar input,.stx-filter-bar select{padding:4px 7px;font-size:10.5px;border:1px solid #E2E8F0;border-radius:4px;height:26px;box-sizing:border-box;}
.stx-table-card{flex:1;overflow:hidden;padding:4px 10px 8px 10px;min-height:0;display:flex;flex-direction:column;}
.stx-table-scroll{flex:1;overflow-y:auto;overflow-x:hidden;min-height:0;scrollbar-gutter:stable;}
.stx-table{width:100%;border-collapse:collapse;font-size:10.5px;table-layout:fixed;}
.stx-table th{padding:4px 6px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;background:#F7FAFC;position:sticky;top:0;}
.stx-table td{padding:4px 6px;line-height:1.3;border-bottom:1px solid #F7FAFC;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.stx-badge{padding:1px 7px;border-radius:20px;font-size:9px;font-weight:600;white-space:nowrap;}
.stx-pager{flex-shrink:0;border-top:1px solid #E2E8F0;padding-top:6px;margin-top:4px;display:flex;align-items:center;justify-content:space-between;font-size:10.5px;}
.stx-pager nav ul{display:flex;gap:4px;list-style:none;margin:0;padding:0;}
.stx-pager nav a,.stx-pager nav span{display:inline-block;padding:4px 9px;border-radius:5px;font-size:10.5px;text-decoration:none;color:#1565C0;border:1px solid #E2E8F0;}
.stx-pager nav .active span{background:#1565C0;color:#fff;border-color:#1565C0;}
.stx-pager nav .disabled span{color:#cbd5e1;}
</style>
@endpush

@section('content')
<div class="stx-wrap">

    <div style="flex-shrink:0;">
        <a href="{{ route($rolePrefix . '.dashboard') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('tl.back_to_dashboard_link') }}</a>
    </div>

    <div class="stx-card" style="flex-shrink:0;display:flex;align-items:center;justify-content:space-between;">
        <div style="font-size:11px;color:#374151;">{{ __('sales_transactions.intro_note') }}</div>
        <a href="{{ route($rolePrefix . '.sales-transactions.create') }}" style="background:#1565C0;color:#fff;text-decoration:none;border-radius:6px;padding:7px 16px;font-size:11px;font-weight:700;white-space:nowrap;">{{ __('sales_transactions.submit_button') }}</a>
    </div>

    @if(session('success'))
    <div class="stx-card" style="border-left:3px solid #38A169;color:#1b5e20;flex-shrink:0;">{{ session('success') }}</div>
    @endif

    <div class="stx-card stx-filter-bar">
        <form method="GET" action="{{ route($rolePrefix . '.sales-transactions.index') }}" id="stxFilterForm" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;flex:1;">
            <div style="flex:2;min-width:200px;">
                <input type="text" name="search" id="stxSearch" value="{{ request('search') }}" placeholder="{{ __('sales_transactions.search_placeholder') }}" style="width:100%;box-sizing:border-box;" autocomplete="off">
            </div>
            @php
                $statusLabels = ['DRAFT'=>__('gl.status_draft'),'SUBMITTED'=>__('network.submitted'),'ACTIVE'=>__('network.active'),'PENDING_RENEWAL'=>__('network.pending_renewal'),'RENEWED'=>__('gl.status_renewed'),'LAPSED'=>__('network.lapsed'),'CANCELLED'=>__('gl.status_cancelled')];
            @endphp
            <select name="status" style="min-width:130px;">
                <option value="">{{ __('network.all_status_option') }}</option>
                @foreach(['DRAFT','SUBMITTED','ACTIVE','PENDING_RENEWAL','RENEWED','LAPSED','CANCELLED'] as $s)
                    <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ $statusLabels[$s] }}</option>
                @endforeach
            </select>
            <label style="display:flex;align-items:center;gap:4px;font-size:10.5px;color:#92400e;white-space:nowrap;">
                <input type="checkbox" name="flagged" value="1" {{ request('flagged') ? 'checked' : '' }} onchange="document.getElementById('stxFilterForm').submit()"> {{ __('sales_transactions.flagged_only_label') }}
            </label>
            <button type="submit" style="background:#1565C0;color:#fff;border:none;border-radius:4px;padding:4px 14px;height:26px;font-size:10.5px;font-weight:600;cursor:pointer;">{{ __('gl.filter_button') }}</button>
            <a href="{{ route($rolePrefix . '.sales-transactions.index') }}" style="background:#F7FAFC;border:1px solid #E2E8F0;color:#4A5568;text-decoration:none;border-radius:4px;padding:4px 12px;height:26px;display:flex;align-items:center;font-size:10.5px;">{{ __('gl.reset_link') }}</a>
        </form>
    </div>

    <div class="stx-card stx-table-card">
        <div class="stx-table-scroll">
        <table class="stx-table">
            <colgroup>
                <col style="width:14%;"><col style="width:16%;"><col style="width:16%;">
                <col style="width:16%;"><col style="width:12%;">
                <col style="width:9%;"><col style="width:8%;"><col style="width:9%;">
            </colgroup>
            <thead>
                <tr>
                    <th>{{ __('sales_transactions.col_reference_no') }}</th><th>{{ __('gl.col_customer') }}</th><th>{{ __('gl.col_agent') }}</th>
                    <th>{{ __('sales_transactions.col_product_vendor') }}</th><th style="text-align:right;">{{ __('network.col_sales_amount_rm') }}</th>
                    <th style="text-align:center;">{{ __('gl.col_status') }}</th><th style="text-align:center;">{{ __('sales_transactions.col_flag') }}</th>
                    <th style="text-align:center;">{{ __('gl.view_link') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $txn)
                <tr style="cursor:pointer;" onclick="window.location='{{ route($rolePrefix . '.sales-transactions.show', $txn->policy_id) }}'"
                    onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''">
                    <td style="font-weight:600;color:#1565C0;">{{ $txn->document_reference_number }}</td>
                    <td>{{ $txn->customer_name }}</td>
                    <td>{{ $txn->agent_name }}<br><span style="font-size:8.5px;color:#9ca3af;">{{ $txn->agent_code }}</span></td>
                    <td>{{ $txn->product_name }}<br><span style="font-size:8.5px;color:#9ca3af;">{{ $txn->vendor_name }}</span></td>
                    <td style="text-align:right;font-weight:700;">{{ number_format($txn->premium_amount,2) }}</td>
                    <td style="text-align:center;">
                        @php
                            $badges = ['ACTIVE'=>['#d1fae5','#065f46'],'SUBMITTED'=>['#dbeafe','#1e40af'],'DRAFT'=>['#f3f4f6','#374151'],'PENDING_RENEWAL'=>['#fef3c7','#92400e'],'RENEWED'=>['#e0f2fe','#075985'],'LAPSED'=>['#fee2e2','#991b1b'],'CANCELLED'=>['#f3f4f6','#6b7280']];
                            $badge = $badges[$txn->status] ?? ['#f3f4f6','#374151'];
                        @endphp
                        <span class="stx-badge" style="background:{{ $badge[0] }};color:{{ $badge[1] }};">{{ $statusLabels[$txn->status] ?? $txn->status }}</span>
                    </td>
                    <td style="text-align:center;">
                        @if($txn->flagged_for_review)
                        <span class="stx-badge" style="background:#fee2e2;color:#991b1b;">&#9888; {{ __('sales_transactions.flagged_badge') }}</span>
                        @endif
                    </td>
                    <td style="text-align:center;" onclick="event.stopPropagation();">
                        <a href="{{ route($rolePrefix . '.sales-transactions.show', $txn->policy_id) }}" style="background:#1565C0;color:#fff;text-decoration:none;border-radius:4px;padding:3px 10px;font-size:9.5px;font-weight:600;white-space:nowrap;">{{ __('gl.view_link') }}</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;padding:30px;color:#A0AEC0;">{{ __('sales_transactions.no_records_found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>

        <div class="stx-pager">
            <span style="color:#9ca3af;">
                @if($transactions->total() > 0)
                {{ __('gl.showing_x_to_y_of_z_records', ['first' => $transactions->firstItem(), 'last' => $transactions->lastItem(), 'total' => $transactions->total()]) }}
                @else
                {{ __('sales_transactions.no_records_short') }}
                @endif
            </span>
            <div>{{ $transactions->onEachSide(1)->links() }}</div>
        </div>
    </div>
</div>

<script>
// Live type-ahead filtering — auto-submits the search after a short pause,
// so results update as you type without needing to click Filter.
(function() {
    var input = document.getElementById('stxSearch');
    var form = document.getElementById('stxFilterForm');
    var timer = null;
    input.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(function() { form.submit(); }, 500);
    });
})();
</script>
@endsection
