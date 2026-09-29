@extends('layouts.dashboard')

@section('title', __('tl.team_transactions_title'))

@section('page-title')
{{ __('tl.team_transactions_title') }} <span style="font-size:12px;color:#9ca3af;font-weight:400;margin-left:10px;">{{ __('tl.all_sales_under_team_note') }}</span>
@endsection

@push('styles')
<style>
.txl-wrap{padding:6px 8px;display:flex;flex-direction:column;gap:5px;height:calc(100vh - 66px);box-sizing:border-box;overflow:hidden;}
.txl-page{display:none;flex:1;flex-direction:column;gap:6px;min-height:0;}
.txl-page.active{display:flex;}
.txl-card{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:8px 12px;}
.txl-dot{width:7px;height:7px;border-radius:50%;background:#d1d5db;cursor:pointer;}
.txl-dot.on{background:#1565C0;}

.txl-filter-bar{padding:4px 10px;flex-shrink:0;}
.txl-filter-bar input,
.txl-filter-bar select{padding:2px 6px;font-size:10px;border:1px solid #E2E8F0;border-radius:4px;height:24px;box-sizing:border-box;}
.txl-filter-bar button,
.txl-filter-bar a{padding:2px 10px;font-size:10px;height:24px;display:flex;align-items:center;border-radius:4px;}

.txl-table-card{flex:1;overflow:hidden;padding:4px 10px 8px 10px;min-height:0;display:flex;flex-direction:column;}
.txl-table-scroll{flex:1;overflow-y:scroll;overflow-x:hidden;min-height:0;scrollbar-gutter:stable;}
.txl-totals{flex-shrink:0;border-top:2px solid #E2E8F0;padding-top:4px;margin-top:4px;}
.txl-table{width:100%;border-collapse:collapse;font-size:10px;table-layout:fixed;}
.txl-table th{padding:3px 6px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;background:#F7FAFC;}
.txl-table td{padding:3px 6px;line-height:1.25;border-bottom:1px solid #F7FAFC;}
.txl-table .sub{font-size:8px;color:#9ca3af;}
.txl-badge{padding:1px 6px;border-radius:20px;font-size:9px;font-weight:600;}
</style>
@endpush

@section('content')
<div class="txl-wrap">

    {{-- ═══════════════ PAGE 1 — SUMMARY ═══════════════ --}}
    <div id="txl-pg1" class="txl-page active">

        <div style="display:flex;justify-content:flex-end;align-items:center;margin-bottom:-2px;">
            <div style="display:flex;align-items:center;gap:6px;">
                <div style="display:flex;gap:4px;">
                    <div class="txl-dot on" onclick="goTxlPage(1)"></div>
                    <div class="txl-dot" onclick="goTxlPage(2)"></div>
                </div>
                <span id="txl-pg-label" style="font-size:10px;color:#6b7280;">1 / 2</span>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;">
            <div class="txl-card" style="border-left:3px solid #0D5A8E;display:flex;flex-direction:column;justify-content:center;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">{{ __('gl.total_transactions_label') }}</div>
                <div style="font-size:22px;font-weight:800;color:#0D5A8E;">{{ number_format($summary->total) }}</div>
            </div>
            <a href="{{ route('tl.transactions', ['status'=>'ACTIVE']) }}" class="txl-card" style="border-left:3px solid #38A169;display:flex;flex-direction:column;justify-content:center;text-decoration:none;color:inherit;cursor:pointer;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">{{ __('network.active') }}</div>
                <div style="font-size:22px;font-weight:800;color:#38A169;">{{ number_format($summary->active) }}</div>
                <div style="font-size:10px;color:#9ca3af;">{{ __('gl.view_transactions_link') }}</div>
            </a>
            <a href="{{ route('tl.transactions', ['status'=>'PENDING_RENEWAL']) }}" class="txl-card" style="border-left:3px solid #D97706;display:flex;flex-direction:column;justify-content:center;text-decoration:none;color:inherit;cursor:pointer;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">{{ __('network.pending_renewal') }}</div>
                <div style="font-size:22px;font-weight:800;color:#D97706;">{{ number_format($summary->pending_renewal) }}</div>
                <div style="font-size:10px;color:#9ca3af;">{{ __('gl.view_transactions_link') }}</div>
            </a>
            <div class="txl-card" style="border-left:3px solid #1B9AE4;display:flex;flex-direction:column;justify-content:center;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">{{ __('gl.total_sales_rm_label') }}</div>
                <div style="font-size:22px;font-weight:800;color:#1B9AE4;">{{ number_format($summary->total_premium, 2) }}</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr;gap:8px;flex:1;min-height:0;">
            <div class="txl-card" style="display:flex;flex-direction:column;overflow:hidden;">
                <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:6px;">{{ __('gl.sales_by_product_type_heading') }}</div>
                <div style="overflow:auto;flex:1;min-height:0;">
                    <table class="txl-table">
                        <thead><tr><th>{{ __('tl.col_product_type') }}</th><th style="text-align:center;">{{ __('tl.col_count') }}</th><th style="text-align:right;">{{ __('network.col_sales_amount_rm') }}</th></tr></thead>
                        <tbody>
                        @foreach($byProductType as $pt)
                            <tr style="cursor:pointer;" onclick="window.location='{{ route('tl.transactions', ['product_type'=>$pt->product_type]) }}'" onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''">
                                <td style="font-weight:600;color:#1565C0;">{{ $pt->product_type }} &#8594;</td>
                                <td style="text-align:center;">{{ $pt->total }}</td>
                                <td style="text-align:right;font-weight:700;">{{ number_format($pt->total_premium,2) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════ PAGE 2 — FILTERS + TABLE ═══════════════ --}}
    <div id="txl-pg2" class="txl-page">

        <div class="txl-card txl-filter-bar" style="display:flex;align-items:center;gap:8px;">
            <form method="GET" action="{{ route('tl.transactions') }}" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;flex:1;">
                @if(request('product_type'))
                <input type="hidden" name="product_type" value="{{ request('product_type') }}">
                <span style="background:#DBEAFE;color:#1D4ED8;font-size:9px;font-weight:700;padding:3px 8px;border-radius:4px;">{{ request('product_type') }} &times;
                    <a href="{{ route('tl.transactions', request()->except(['product_type','page'])) }}" style="color:#1D4ED8;text-decoration:none;margin-left:2px;">&#10005;</a>
                </span>
                @endif
                @php
                    $statusLabels = [
                        'DRAFT'=>__('gl.status_draft'),'SUBMITTED'=>__('network.submitted'),'ACTIVE'=>__('network.active'),
                        'PENDING_RENEWAL'=>__('network.pending_renewal'),'RENEWED'=>__('gl.status_renewed'),
                        'LAPSED'=>__('network.lapsed'),'CANCELLED'=>__('gl.status_cancelled'),
                    ];
                @endphp
                <div style="flex:2;min-width:160px;">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('tl.search_transaction_or_customer_placeholder') }}"
                           style="width:100%;box-sizing:border-box;">
                </div>
                <div style="flex:1;min-width:110px;">
                    <select name="status" style="width:100%;">
                        <option value="">{{ __('network.all_status_option') }}</option>
                        @foreach(['DRAFT','SUBMITTED','ACTIVE','PENDING_RENEWAL','RENEWED','LAPSED','CANCELLED'] as $s)
                            <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ $statusLabels[$s] }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex:1;min-width:110px;">
                    <select name="vendor" style="width:100%;">
                        <option value="">{{ __('gl.all_vendors_option') }}</option>
                        @foreach($vendors as $v)
                            <option value="{{ $v }}" {{ request('vendor') == $v ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" style="background:#1565C0;color:#fff;border:none;font-weight:600;cursor:pointer;">{{ __('gl.filter_button') }}</button>
                <a href="{{ route('tl.transactions') }}" style="background:#F7FAFC;border:1px solid #E2E8F0;color:#4A5568;text-decoration:none;">{{ __('gl.reset_link') }}</a>
            </form>
            <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
                <div style="display:flex;gap:4px;">
                    <div class="txl-dot" onclick="goTxlPage(1)"></div>
                    <div class="txl-dot on" onclick="goTxlPage(2)"></div>
                </div>
                <span style="font-size:10px;color:#6b7280;">2 / 2</span>
            </div>
        </div>

        <div class="txl-card txl-table-card">
            <div class="txl-table-scroll">
            <table class="txl-table">
                <colgroup>
                    <col style="width:11%;">
                    <col style="width:14%;">
                    <col style="width:15%;">
                    <col style="width:13%;">
                    <col style="width:12%;">
                    <col style="width:8%;">
                    <col style="width:12%;">
                    <col style="width:15%;">
                </colgroup>
                <thead>
                    <tr style="position:sticky;top:0;">
                        <th style="white-space:nowrap;">{{ __('gl.col_transaction_no') }}</th>
                        <th>{{ __('gl.col_customer') }}</th>
                        <th>{{ __('gl.col_product') }}</th>
                        <th>{{ __('gl.col_agent') }}</th>
                        <th style="text-align:right;white-space:nowrap;">{{ __('network.col_sales_amount_rm') }}</th>
                        <th style="text-align:right;white-space:nowrap;">{{ __('gl.col_pct') }}</th>
                        <th style="text-align:right;white-space:nowrap;">{{ __('gl.col_earning_income_rm') }}</th>
                        <th style="text-align:center;">{{ __('gl.col_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $txn)
                    <tr style="cursor:pointer;" onclick="window.location='{{ route('tl.transactions.show', $txn->policy_id) }}'"
                        onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''">
                        <td style="font-weight:600;color:#1565C0;">{{ $txn->policy_number }}</td>
                        <td>{{ $txn->customer_name }}</td>
                        <td>
                            {{ $txn->product_name }}<br>
                            <span class="sub">{{ $txn->vendor_name }}</span>
                        </td>
                        <td>
                            {{ $txn->agent_name }}<br>
                            <span class="sub">{{ $txn->agent_code }}</span>
                        </td>
                        <td style="text-align:right;font-weight:700;">{{ number_format($txn->premium_amount, 2) }}</td>
                        <td style="text-align:right;color:#9ca3af;">{{ number_format($txn->my_pct, 1) }}%</td>
                        <td style="text-align:right;font-weight:700;color:#38A169;">{{ number_format($txn->my_commission, 2) }}</td>
                        <td style="text-align:center;white-space:nowrap;">
                            @php
                                $badges = [
                                    'ACTIVE'=>['#d1fae5','#065f46'],'SUBMITTED'=>['#dbeafe','#1e40af'],'DRAFT'=>['#f3f4f6','#374151'],
                                    'PENDING_RENEWAL'=>['#fef3c7','#92400e'],'RENEWED'=>['#e0f2fe','#075985'],'LAPSED'=>['#fee2e2','#991b1b'],'CANCELLED'=>['#f3f4f6','#6b7280'],
                                ];
                                $badge = $badges[$txn->status] ?? ['#f3f4f6','#374151'];
                                $label = $statusLabels[$txn->status] ?? $txn->status;
                            @endphp
                            <span class="txl-badge" style="background:{{ $badge[0] }};color:{{ $badge[1] }};">{{ $label }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" style="text-align:center;padding:30px;color:#A0AEC0;">{{ __('gl.no_transactions_found_dot') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>

            {{-- Pinned Totals --}}
            @if($transactions->isNotEmpty())
            <div class="txl-totals">
                <table class="txl-table" style="width:100%;">
                    <colgroup>
                        <col style="width:11%;">
                        <col style="width:14%;">
                        <col style="width:15%;">
                        <col style="width:13%;">
                        <col style="width:12%;">
                        <col style="width:8%;">
                        <col style="width:12%;">
                        <col style="width:15%;">
                    </colgroup>
                    <tr style="background:#F7FAFC;">
                        <td colspan="4" style="text-align:right;font-weight:700;color:#374151;padding:4px 6px;">{{ __('gl.page_total_label') }}</td>
                        <td style="text-align:right;font-weight:800;color:#0D5A8E;padding:4px 6px;">{{ number_format($transactions->sum('premium_amount'),2) }}</td>
                        <td></td>
                        <td style="text-align:right;font-weight:800;color:#38A169;padding:4px 6px;">{{ number_format($transactions->sum('my_commission'),2) }}</td>
                        <td></td>
                    </tr>
                    <tr style="background:#EBF5FB;">
                        <td colspan="4" style="text-align:right;font-weight:700;color:#374151;padding:4px 6px;">{{ __('gl.grand_total_records_label', ['count' => $transactions->total()]) }}</td>
                        <td style="text-align:right;font-weight:800;color:#1D4ED8;padding:4px 6px;">{{ number_format($grandTotal,2) }}</td>
                        <td></td>
                        <td style="text-align:right;font-weight:800;color:#1D4ED8;padding:4px 6px;">{{ number_format($commissionGrandTotal,2) }}</td>
                        <td></td>
                    </tr>
                </table>
                @if($transactions->hasPages())
                <div style="margin-top:4px;display:flex;align-items:center;justify-content:space-between;font-size:10px;">
                    <span style="color:#9ca3af;">{{ __('gl.showing_x_to_y_of_z_records', ['first' => $transactions->firstItem(), 'last' => $transactions->lastItem(), 'total' => $transactions->total()]) }}</span>
                    <div style="font-size:10px;">{{ $transactions->appends(request()->query())->links() }}</div>
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>

    {{-- Prev / Next --}}
    <button id="txl-btn-prev" style="display:none;position:fixed;bottom:16px;left:276px;background:#1565C0;color:#fff;border:none;border-radius:5px;padding:6px 18px;font-size:11px;font-weight:700;cursor:pointer;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.2);line-height:1.4;">{{ __('network.prev') }}</button>
    <button id="txl-btn-next" onclick="changeTxlPage(1)" style="position:fixed;bottom:16px;right:16px;background:#1565C0;color:#fff;border:none;border-radius:5px;padding:6px 18px;font-size:11px;font-weight:700;cursor:pointer;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.2);line-height:1.4;">{{ __('network.next') }}</button>

</div>

@push('scripts')
<script>
var txlCur = 1;
function goTxlPage(n) {
    document.querySelectorAll('.txl-page').forEach(el => { el.classList.remove('active'); el.style.display='none'; });
    var t = document.getElementById('txl-pg'+n);
    t.classList.add('active'); t.style.display='flex';
    document.querySelectorAll('.txl-dot').forEach((d,i) => d.classList.toggle('on', (i%2)===n-1));
    var label = document.getElementById('txl-pg-label');
    if (label) label.textContent = n+' / 2';
    var prevBtn = document.getElementById('txl-btn-prev');
    prevBtn.style.display = 'inline-block';
    if (n === 1) {
        prevBtn.onclick = function(){ window.location = '{{ route('tl.dashboard') }}'; };
    } else {
        prevBtn.onclick = function(){ changeTxlPage(-1); };
    }
    document.getElementById('txl-btn-next').style.display = n<2?'inline-block':'none';
    txlCur = n;
}
function changeTxlPage(dir) { var n = txlCur + dir; if(n>=1 && n<=2) goTxlPage(n); }

var txlInitPage = (window.location.search && window.location.search.length > 1) ? 2 : 1;
goTxlPage(txlInitPage);
</script>
@endpush
@endsection
