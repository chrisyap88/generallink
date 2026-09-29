@extends('layouts.dashboard')

@section('page-title')
{{ __('gl.my_earning_income_title') }} <span style="font-size:12px;color:#9ca3af;font-weight:400;margin-left:10px;">{{ $agent->full_name }} &middot; {{ $agent->agent_code }}</span>
@endsection


@push('styles')
<style>
.comm-wrap{padding:6px 8px;display:flex;flex-direction:column;gap:5px;height:calc(100vh - 66px);box-sizing:border-box;overflow:hidden;}
.page-section{display:none;flex:1;flex-direction:column;gap:6px;min-height:0;}
.page-section.active{display:flex;}
.card-sm{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:8px 12px;}
.dot{width:7px;height:7px;border-radius:50%;background:#d1d5db;cursor:pointer;}
.dot.on{background:#1565C0;}
</style>
@endpush

@section('content')
<div class="comm-wrap">

    {{-- Top bar --}}
    <div style="display:flex;justify-content:flex-end;align-items:center;">
        <div style="display:flex;align-items:center;gap:6px;">
            <div style="display:flex;gap:4px;">
                <div class="dot on" onclick="goPage(1)"></div>
                <div class="dot" onclick="goPage(2)"></div>
                <div class="dot" onclick="goPage(3)"></div>
            </div>
            <span id="pg-label" style="font-size:10px;color:#6b7280;">1 / 3</span>
        </div>
    </div>

    {{-- ═══ PAGE 1 — OVERVIEW ═══ --}}
    <div id="pg1" class="page-section active">

        {{-- 4 summary cards --}}
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;">
            <div class="card-sm" style="border-left:3px solid #1565C0;">
                <div style="font-size:8px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:3px;">💰 {{ __('gl.wallet_word') }}</div>
                <div style="font-size:15px;font-weight:800;color:#1565C0;">RM {{ number_format($walletBalance,2) }}</div>
                <div style="font-size:8px;color:#9ca3af;">{{ __('gl.available_balance_note') }}</div>
            </div>
            <div class="card-sm" style="border-left:3px solid #16a34a;">
                <div style="font-size:8px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:3px;">✅ {{ __('gl.earned_word') }}</div>
                <div style="font-size:15px;font-weight:800;color:#16a34a;">RM {{ number_format($totalEarned,2) }}</div>
                <div style="font-size:8px;color:#9ca3af;">{{ __('gl.confirmed_plus_paid_note') }}</div>
            </div>
            <div class="card-sm" style="border-left:3px solid #d97706;">
                <div style="font-size:8px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:3px;">⏳ {{ __('gl.pending_word') }}</div>
                <div style="font-size:15px;font-weight:800;color:#d97706;">RM {{ number_format($totalPending,2) }}</div>
                <div style="font-size:8px;color:#9ca3af;">{{ __('gl.awaiting_confirmation_note') }}</div>
            </div>
            <div class="card-sm" style="border-left:3px solid #dc2626;">
                <div style="font-size:8px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:3px;">🔒 {{ __('gl.on_hold_word') }}</div>
                <div style="font-size:15px;font-weight:800;color:#dc2626;">RM {{ number_format($totalHeld,2) }}</div>
                <div style="font-size:8px;color:#9ca3af;">{{ __('gl.claim_pending_note') }}</div>
            </div>
        </div>

        {{-- Chart + Group stats side by side --}}
        <div style="display:grid;grid-template-columns:1fr 180px;gap:5px;">

            {{-- Bar chart --}}
            <div class="card-sm" style="display:flex;flex-direction:column;overflow:hidden;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                    <div style="font-size:10px;font-weight:700;color:#374151;">{{ __('gl.monthly_earning_last6_heading') }}</div>
                    <div style="display:flex;gap:3px;">
                        <button onclick="switchChart('bar')" id="btn-bar" style="padding:2px 8px;font-size:9px;font-weight:600;border-radius:4px;border:1px solid #1565C0;background:#1565C0;color:#fff;cursor:pointer;">{{ __('dashboard.bar') }}</button>
                        <button onclick="switchChart('line')" id="btn-line" style="padding:2px 8px;font-size:9px;font-weight:600;border-radius:4px;border:1px solid #d1d5db;background:#fff;color:#374151;cursor:pointer;">{{ __('dashboard.line') }}</button>
                        <button onclick="switchChart('pie')" id="btn-pie" style="padding:2px 8px;font-size:9px;font-weight:600;border-radius:4px;border:1px solid #d1d5db;background:#fff;color:#374151;cursor:pointer;">{{ __('dashboard.pie') }}</button>
                    </div>
                </div>
                <div style="position:relative;height:100%;">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            {{-- Group stats --}}
            <div style="display:flex;flex-direction:column;gap:4px;overflow:hidden;">
                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:4px 8px;flex:1;">
                    <div style="font-size:8px;font-weight:700;color:#1e40af;text-transform:uppercase;margin-bottom:2px;">{{ __('gl.group_earned_label') }}</div>
                    <div style="font-size:12px;font-weight:800;color:#1e40af;">RM {{ number_format($groupTotalEarned,2) }}</div>
                    <div style="font-size:8px;color:#93c5fd;">{{ __('gl.all_roles_note') }}</div>
                </div>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:6px 10px;flex:1;">
                    <div style="font-size:8px;font-weight:700;color:#15803d;text-transform:uppercase;margin-bottom:2px;">{{ __('gl.my_share_label') }}</div>
                    <div style="font-size:12px;font-weight:800;color:#15803d;">RM {{ number_format($totalEarned,2) }}</div>
                    @php $pct = $groupTotalEarned > 0 ? ($totalEarned/$groupTotalEarned)*100 : 0; @endphp
                    <div style="font-size:8px;color:#4ade80;">{{ __('gl.pct_of_group', ['pct' => number_format($pct,1)]) }}</div>
                </div>
                <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:6px;padding:6px 10px;flex:1;">
                    <div style="font-size:8px;font-weight:700;color:#c2410c;text-transform:uppercase;margin-bottom:2px;">{{ __('gl.my_pending_label') }}</div>
                    <div style="font-size:12px;font-weight:800;color:#c2410c;">RM {{ number_format($totalPending,2) }}</div>
                    <div style="font-size:8px;color:#fb923c;">{{ __('gl.awaiting_release_note') }}</div>
                </div>
                <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:6px 10px;flex:1;">
                    <div style="font-size:8px;font-weight:700;color:#991b1b;text-transform:uppercase;margin-bottom:2px;">{{ __('gl.on_hold_word') }}</div>
                    <div style="font-size:12px;font-weight:800;color:#dc2626;">RM {{ number_format($totalHeld,2) }}</div>
                    <div style="font-size:8px;color:#f87171;">{{ __('gl.frozen_pending_claim_note') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ PAGE 2 — BREAKDOWN ═══ --}}
    <div id="pg2" class="page-section">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:40px;width:90%;max-width:1000px;margin:10px auto 0;height:calc(100vh - 140px);">
            <div class="card-sm" style="overflow-y:auto;">
                <div style="font-size:10px;font-weight:700;color:#374151;margin-bottom:8px;">{{ __('gl.by_product_type_heading') }}</div>
                @php
                $typeColors=['MOTOR'=>['bg'=>'#dbeafe','text'=>'#1e40af','bar'=>'#3b82f6'],'PERSONAL_ACCIDENT'=>['bg'=>'#dcfce7','text'=>'#166534','bar'=>'#22c55e'],'FIRE'=>['bg'=>'#fef3c7','text'=>'#92400e','bar'=>'#f59e0b'],'OTHER'=>['bg'=>'#f3f4f6','text'=>'#374151','bar'=>'#9ca3af']];
                $typeLabels=['MOTOR'=>__('gl.product_type_motor'),'PERSONAL_ACCIDENT'=>__('gl.product_type_personal_accident'),'FIRE'=>__('gl.product_type_fire'),'OTHER'=>__('gl.product_type_other')];
                $maxType=$byProductType->max('total_commission')?:1;
                @endphp
                @forelse($byProductType as $pt)
                @php $c=$typeColors[$pt->product_type]??$typeColors['OTHER']; @endphp
                <div style="margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid #f3f4f6;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;flex-wrap:nowrap;gap:8px;">
                        <span style="background:{{ $c['bg'] }};color:{{ $c['text'] }};font-size:9px;font-weight:700;padding:2px 7px;border-radius:20px;">{{ $typeLabels[$pt->product_type]??$pt->product_type }}</span>
                        <span style="font-size:11px;font-weight:700;color:#111827;">RM {{ number_format($pt->total_commission,2) }}</span>
                    </div>
                    <div style="background:#f3f4f6;border-radius:3px;height:5px;overflow:hidden;margin-bottom:3px;">
                        <div style="background:{{ $c['bar'] }};height:5px;width:{{ ($pt->total_commission/$maxType)*100 }}%;border-radius:3px;"></div>
                    </div>
                    <div style="display:flex;gap:10px;">
                        <span style="font-size:9px;color:#16a34a;">✓ RM {{ number_format($pt->earned,2) }}</span>
                        <span style="font-size:9px;color:#d97706;">⏳ RM {{ number_format($pt->pending,2) }}</span>
                        <span style="font-size:9px;color:#9ca3af;">{{ $pt->txn_count }} {{ __('gl.txn_suffix') }}</span>
                    </div>
                </div>
                @empty
                <div style="color:#9ca3af;font-size:11px;text-align:center;padding:20px 0;">{{ __('gl.no_data_dot') }}</div>
                @endforelse
            </div>

            <div class="card-sm" style="overflow-y:auto;">
                <div style="font-size:10px;font-weight:700;color:#374151;margin-bottom:8px;">{{ __('gl.by_role_heading') }}</div>
                @php
                $roleColors=['GROUP_LEADER'=>['bg'=>'#d1fae5','text'=>'#065f46','bar'=>'#10b981','label'=>\App\Services\RoleLabelService::label('GROUP_LEADER')],'TEAM_LEADER'=>['bg'=>'#dbeafe','text'=>'#1e40af','bar'=>'#3b82f6','label'=>\App\Services\RoleLabelService::label('TEAM_LEADER')],'INTRODUCER'=>['bg'=>'#ede9fe','text'=>'#5b21b6','bar'=>'#8b5cf6','label'=>\App\Services\RoleLabelService::label('INTRODUCER')]];
                $maxRole=$byRole->max('total_commission')?:1;
                $groupTotal=$byRole->sum('total_commission');
                @endphp
                <div style="font-size:9px;color:#6b7280;margin-bottom:8px;">{{ __('gl.group_total_colon') }} <strong style="color:#111827;">RM {{ number_format($groupTotal,2) }}</strong></div>
                @forelse($byRole as $role)
                @php $rc=$roleColors[$role->role_at_transaction]??['bg'=>'#f3f4f6','text'=>'#374151','bar'=>'#9ca3af','label'=>$role->role_at_transaction]; @endphp
                <div style="margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid #f3f4f6;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;flex-wrap:nowrap;gap:8px;">
                        <span style="background:{{ $rc['bg'] }};color:{{ $rc['text'] }};font-size:9px;font-weight:700;padding:2px 7px;border-radius:20px;">{{ $rc['label'] }}</span>
                        <span style="font-size:11px;font-weight:700;color:#111827;">RM {{ number_format($role->total_commission,2) }}</span>
                    </div>
                    <div style="background:#f3f4f6;border-radius:3px;height:5px;overflow:hidden;margin-bottom:3px;">
                        <div style="background:{{ $rc['bar'] }};height:5px;width:{{ ($role->total_commission/$maxRole)*100 }}%;border-radius:3px;"></div>
                    </div>
                    <div style="display:flex;gap:10px;">
                        <span style="font-size:9px;color:#16a34a;">✓ RM {{ number_format($role->earned,2) }}</span>
                        <span style="font-size:9px;color:#d97706;">⏳ RM {{ number_format($role->pending,2) }}</span>
                        <span style="font-size:9px;color:#9ca3af;">{{ $role->txn_count }} {{ __('gl.txn_suffix') }}</span>
                    </div>
                </div>
                @empty
                <div style="color:#9ca3af;font-size:11px;text-align:center;padding:20px 0;">{{ __('gl.no_data_dot') }}</div>
                @endforelse
            </div>
        </div>

        @if(count($heldItems) > 0)
        <div class="card-sm">
            <div style="font-size:10px;font-weight:700;color:#dc2626;margin-bottom:6px;">{{ __('gl.held_count_items_heading', ['count' => count($heldItems)]) }}</div>
            <table style="width:100%;border-collapse:collapse;font-size:10px;">
                <thead><tr style="background:#fef2f2;">
                    <th style="text-align:left;padding:5px 8px;color:#374151;">{{ __('gl.col_policy') }}</th>
                    <th style="text-align:left;padding:5px 8px;color:#374151;">{{ __('gl.col_customer') }}</th>
                    <th style="text-align:left;padding:5px 8px;color:#374151;">{{ __('gl.col_reason') }}</th>
                    <th style="text-align:right;padding:5px 8px;color:#374151;">{{ __('gl.col_held_rm') }}</th>
                    <th style="text-align:right;padding:5px 8px;color:#374151;">{{ __('gl.col_days') }}</th>
                </tr></thead>
                <tbody>
                @foreach($heldItems as $item)
                <tr style="border-bottom:1px solid #f9fafb;">
                    <td style="padding:5px 8px;color:#2563eb;font-weight:600;">{{ $item->policy_number }}</td>
                    <td style="padding:5px 8px;">{{ $item->customer_name }}</td>
                    <td style="padding:5px 8px;color:#d97706;">{{ str_replace('_',' ',$item->hold_reason) }}</td>
                    <td style="padding:5px 8px;text-align:right;font-weight:700;color:#dc2626;">{{ number_format($item->held_amount,2) }}</td>
                    <td style="padding:5px 8px;text-align:right;color:{{ ($item->days_held??0)>=7?'#dc2626':'#374151' }};">{{ $item->days_held??0 }}d</td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- ═══ PAGE 3 — HISTORY ═══ --}}
    <div id="pg3" class="page-section">
        <form method="GET" action="{{ route('gl.commissions.index') }}" style="display:flex;gap:6px;align-items:center;">
            <input type="hidden" name="page_tab" value="3">
            <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('gl.search_customer_or_policy_placeholder') }}" style="border:1px solid #d1d5db;border-radius:5px;padding:4px 9px;font-size:11px;outline:none;flex:1;">
            <select name="filter" style="border:1px solid #d1d5db;border-radius:5px;padding:4px 8px;font-size:11px;background:#fff;outline:none;">
                <option value="all" {{ $filter==='all'?'selected':'' }}>{{ __('gl.all_option') }}</option>
                <option value="earned" {{ $filter==='earned'?'selected':'' }}>{{ __('gl.earned_option') }}</option>
                <option value="pending" {{ $filter==='pending'?'selected':'' }}>{{ __('gl.pending_word') }}</option>
            </select>
            <button type="submit" style="background:#1565C0;color:#fff;border:none;border-radius:5px;padding:4px 12px;font-size:11px;cursor:pointer;">{{ __('gl.filter_button') }}</button>
            <a href="{{ route('gl.commissions.index') }}" style="background:#f3f4f6;color:#374151;border-radius:5px;padding:4px 10px;font-size:11px;text-decoration:none;">{{ __('gl.reset_link') }}</a>
            <span style="font-size:10px;color:#6b7280;white-space:nowrap;">{{ __('gl.records_count', ['count' => $commissions->total()]) }}</span>
        </form>

        <div class="card-sm" style="flex:1;overflow:hidden;display:flex;flex-direction:column;padding:0;">
            <div style="overflow-y:auto;flex:1;">
                <table style="width:100%;border-collapse:collapse;font-size:11px;">
                    <thead>
                        <tr style="background:#f9fafb;">
                            <th style="text-align:left;padding:7px 10px;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;">{{ __('gl.col_policy_no') }}</th>
                            <th style="text-align:left;padding:7px 10px;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;">{{ __('gl.col_customer') }}</th>
                            <th style="text-align:left;padding:7px 10px;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;">{{ __('gl.col_product') }}</th>
                            <th style="text-align:left;padding:7px 10px;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;">{{ __('gl.col_vendor') }}</th>
                            <th style="text-align:right;padding:7px 10px;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;">{{ __('gl.col_sales_amount') }}</th>
                            <th style="text-align:right;padding:7px 10px;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;">%</th>
                            <th style="text-align:right;padding:7px 10px;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;">{{ __('gl.col_earning_income') }}</th>
                            <th style="text-align:center;padding:7px 10px;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;">{{ __('gl.col_status') }}</th>
                            <th style="text-align:left;padding:7px 10px;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;">{{ __('gl.col_date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @php
                    $ss2=['CONFIRMED'=>['bg'=>'#d1fae5','text'=>'#065f46','label'=>__('gl.status_confirmed')],'PAID'=>['bg'=>'#d1fae5','text'=>'#065f46','label'=>__('gl.status_paid')],'PENDING'=>['bg'=>'#fef3c7','text'=>'#92400e','label'=>__('gl.pending_word')],'HELD'=>['bg'=>'#fee2e2','text'=>'#991b1b','label'=>__('gl.status_held')],'REVERSED'=>['bg'=>'#f3f4f6','text'=>'#6b7280','label'=>__('gl.status_reversed')]];
                    $tc2=['MOTOR'=>['bg'=>'#dbeafe','text'=>'#1e40af'],'PERSONAL_ACCIDENT'=>['bg'=>'#dcfce7','text'=>'#166534'],'FIRE'=>['bg'=>'#fef3c7','text'=>'#92400e'],'OTHER'=>['bg'=>'#f3f4f6','text'=>'#374151']];
                    $tl2=['MOTOR'=>__('gl.product_type_motor'),'PERSONAL_ACCIDENT'=>__('gl.product_type_pa_short'),'FIRE'=>__('gl.product_type_fire'),'OTHER'=>__('gl.product_type_other')];
                    @endphp
                    @forelse($commissions as $c)
                    @php $s=$ss2[$c->status]??['bg'=>'#f3f4f6','text'=>'#6b7280','label'=>$c->status]; $t=$tc2[$c->product_type??'OTHER']??$tc2['OTHER']; @endphp
                    <tr style="border-bottom:1px solid #f3f4f6;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background=''">
                        <td style="padding:5px 10px;color:#2563eb;font-weight:600;white-space:nowrap;">{{ $c->policy_number }}</td>
                        <td style="padding:5px 10px;color:#374151;white-space:nowrap;">{{ $c->customer_name }}</td>
                        <td style="padding:5px 10px;"><span style="background:{{ $t['bg'] }};color:{{ $t['text'] }};font-size:9px;padding:1px 6px;border-radius:10px;">{{ $tl2[$c->product_type??'OTHER']??$c->product_type }}</span></td>
                        <td style="padding:5px 10px;color:#6b7280;white-space:nowrap;">{{ $c->vendor_name }}</td>
                        <td style="padding:5px 10px;text-align:right;color:#374151;white-space:nowrap;">{{ number_format($c->premium_amount,2) }}</td>
                        <td style="padding:5px 10px;text-align:right;color:#6b7280;">{{ number_format($c->entitlement_pct,1) }}%</td>
                        <td style="padding:5px 10px;text-align:right;font-weight:700;color:#111827;white-space:nowrap;">{{ number_format($c->commission_amount,2) }}</td>
                        <td style="padding:5px 10px;text-align:center;"><span style="background:{{ $s['bg'] }};color:{{ $s['text'] }};font-size:9px;font-weight:600;padding:2px 7px;border-radius:20px;">{{ $s['label'] }}</span></td>
                        <td style="padding:5px 10px;color:#6b7280;white-space:nowrap;">{{ \Carbon\Carbon::parse($c->created_at)->format('d M Y') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="9" style="text-align:center;padding:30px;color:#9ca3af;">{{ __('gl.no_records_found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 10px;border-top:1px solid #f3f4f6;background:#fafafa;">
                <span style="font-size:10px;color:#6b7280;">{{ __('gl.showing_x_to_y_of_total', ['first' => $commissions->firstItem()??0, 'last' => $commissions->lastItem()??0, 'total' => $commissions->total()]) }}</span>
                <div style="display:flex;gap:4px;">
                    @if($commissions->onFirstPage())
                        <span style="padding:3px 9px;border:1px solid #e5e7eb;border-radius:4px;font-size:10px;color:#d1d5db;">{{ __('network.prev') }}</span>
                    @else
                        <a href="{{ $commissions->previousPageUrl() }}&page_tab=3" style="padding:3px 9px;border:1px solid #e5e7eb;border-radius:4px;font-size:10px;color:#374151;text-decoration:none;">{{ __('network.prev') }}</a>
                    @endif
                    @if($commissions->hasMorePages())
                        <a href="{{ $commissions->nextPageUrl() }}&page_tab=3" style="padding:3px 9px;border:1px solid #e5e7eb;border-radius:4px;font-size:10px;color:#374151;text-decoration:none;">{{ __('network.next') }}</a>
                    @else
                        <span style="padding:3px 9px;border:1px solid #e5e7eb;border-radius:4px;font-size:10px;color:#d1d5db;">{{ __('network.next') }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Prev / Next --}}
    <div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0 0 0;">
        <button id="btn-prev" style="display:none;position:fixed;bottom:16px;left:276px;background:#1565C0;color:#fff;border:none;border-radius:5px;padding:6px 18px;font-size:11px;font-weight:700;cursor:pointer;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.2);line-height:1.4;">{{ __('network.prev') }}</button>
        <div style="flex:1;"></div>
        <button id="btn-next" onclick="changePage(1)" style="position:fixed;bottom:16px;right:16px;background:#1565C0;color:#fff;border:none;border-radius:5px;padding:6px 18px;font-size:11px;font-weight:700;cursor:pointer;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.2);line-height:1.4;">{{ __('network.next') }}</button>
    </div>

</div>
@endsection

@push('scripts')
<script>
var cur = {{ request('page_tab', 1) }};
function goPage(n) {
    document.querySelectorAll('.page-section').forEach(function(el){ el.classList.remove('active'); el.style.display='none'; });
    var t = document.getElementById('pg'+n);
    t.classList.add('active'); t.style.display='flex';
    document.querySelectorAll('.dot').forEach(function(d,i){ d.classList.toggle('on',i===n-1); });
    document.getElementById('pg-label').textContent = n+' / 3';
    var prevBtn = document.getElementById('btn-prev');
    prevBtn.style.display = 'inline-block';
    if (n === 1) {
        prevBtn.onclick = function(){ window.location = '{{ route('gl.dashboard') }}'; };
    } else {
        prevBtn.onclick = function(){ changePage(-1); };
    }
    document.getElementById('btn-next').style.display = n<3?'inline-block':'none';
    cur=n;
}
function changePage(dir){ var n=cur+dir; if(n>=1&&n<=3) goPage(n); }
goPage(cur);

var trendLabels = {!! $monthlyTrend->pluck('month_label')->toJson() !!};
var trendData   = {!! $monthlyTrend->pluck('total')->toJson() !!};
var trendChart  = null;

function switchChart(type) {
    // Update buttons
    ['bar','line','pie'].forEach(function(t){
        var b = document.getElementById('btn-'+t);
        if(t===type){ b.style.background='#1565C0'; b.style.color='#fff'; b.style.borderColor='#1565C0'; }
        else { b.style.background='#fff'; b.style.color='#374151'; b.style.borderColor='#d1d5db'; }
    });

    if(trendChart) trendChart.destroy();

    var isPie = type==='pie';
    var colors = trendData.map(function(_,i){
        var palette=['#1565C0','#16a34a','#d97706','#7c3aed','#0891b2','#dc2626'];
        return palette[i % palette.length];
    });

    trendChart = new Chart(document.getElementById('trendChart'), {
        type: type,
        data: {
            labels: trendLabels,
            datasets:[{
                data: trendData,
                backgroundColor: isPie ? colors : '#1565C0',
                borderColor: type==='line' ? '#1565C0' : colors,
                borderWidth: type==='line' ? 2 : 1,
                borderRadius: type==='bar' ? 3 : 0,
                barPercentage: 0.5,
                fill: false,
                pointRadius: type==='line' ? 4 : 0,
                tension: 0.3,
            }]
        },
        options:{
            responsive:true,
            maintainAspectRatio:false,
            plugins:{
                legend:{ display: isPie, position:'bottom', labels:{ font:{size:9}, boxWidth:10 } },
                tooltip:{ callbacks:{ label: ctx => 'RM '+ctx.parsed.toFixed(2) } }
            },
            scales: isPie ? {} : {
                y:{ticks:{font:{size:9},callback:v=>'RM '+v.toLocaleString()},grid:{color:'#f3f4f6'}},
                x:{ticks:{font:{size:9}},grid:{display:false}}
            }
        }
    });
}

switchChart('bar');
</script>
@endpush
