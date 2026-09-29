@extends('layouts.dashboard')

@section('page-title', 'My Commission')

@section('content')
<div style="padding: 24px;">

    {{-- ── Page Header ── --}}
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
            <h1 style="font-size:22px; font-weight:700; color:#111827; margin:0;">My Commission</h1>
            <p style="font-size:13px; color:#6b7280; margin:4px 0 0;">Commission earned, pending and held for {{ $agent->full_name }} ({{ $agent->agent_code }})</p>
        </div>
    </div>

    {{-- ── Wallet Summary Cards ── --}}
    <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-bottom:24px;">

        {{-- Wallet Balance --}}
        <div style="background:#fff; border-radius:10px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,.08); border-left:4px solid #2563eb;">
            <div style="font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin-bottom:8px;">💰 Commission Wallet</div>
            <div style="font-size:24px; font-weight:700; color:#2563eb;">RM {{ number_format($walletBalance, 2) }}</div>
            <div style="font-size:12px; color:#6b7280; margin-top:4px;">Available balance</div>
        </div>

        {{-- Total Earned --}}
        <div style="background:#fff; border-radius:10px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,.08); border-left:4px solid #16a34a;">
            <div style="font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin-bottom:8px;">✅ Total Earned</div>
            <div style="font-size:24px; font-weight:700; color:#16a34a;">RM {{ number_format($totalEarned, 2) }}</div>
            <div style="font-size:12px; color:#6b7280; margin-top:4px;">Confirmed + paid out</div>
        </div>

        {{-- Pending --}}
        <div style="background:#fff; border-radius:10px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,.08); border-left:4px solid #d97706;">
            <div style="font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin-bottom:8px;">⏳ Pending</div>
            <div style="font-size:24px; font-weight:700; color:#d97706;">RM {{ number_format($totalPending, 2) }}</div>
            <div style="font-size:12px; color:#6b7280; margin-top:4px;">Awaiting confirmation</div>
        </div>

        {{-- On Hold --}}
        <div style="background:#fff; border-radius:10px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,.08); border-left:4px solid #dc2626;">
            <div style="font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin-bottom:8px;">🔒 On Hold</div>
            <div style="font-size:24px; font-weight:700; color:#dc2626;">RM {{ number_format($totalHeld, 2) }}</div>
            <div style="font-size:12px; color:#6b7280; margin-top:4px;">Claim pending resolution</div>
        </div>
    </div>

    {{-- ── Held Commission Alert ── --}}
    @if($totalHeld > 0)
    <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:14px 18px; margin-bottom:20px; display:flex; align-items:flex-start; gap:10px;">
        <span style="font-size:18px; flex-shrink:0;">🔒</span>
        <div>
            <div style="font-weight:600; color:#991b1b; font-size:13px;">Commission On Hold — Action Required</div>
            <div style="font-size:12px; color:#7f1d1d; margin-top:2px;">
                RM {{ number_format($totalHeld, 2) }} of your commission is frozen pending claim resolution.
                Commission will be released automatically once the vendor payment is confirmed.
                {{ count($heldItems) }} transaction(s) affected.
            </div>
        </div>
    </div>
    @endif

    {{-- ── Two column: Breakdown panels ── --}}
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px;">

        {{-- By Product Type --}}
        <div style="background:#fff; border-radius:10px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,.08);">
            <h3 style="font-size:14px; font-weight:600; color:#374151; margin:0 0 16px;">Commission by Product Type</h3>
            @php
                $typeColors = [
                    'MOTOR' => ['bg' => '#dbeafe', 'text' => '#1e40af', 'bar' => '#3b82f6'],
                    'PERSONAL_ACCIDENT' => ['bg' => '#dcfce7', 'text' => '#166534', 'bar' => '#22c55e'],
                    'FIRE' => ['bg' => '#fef3c7', 'text' => '#92400e', 'bar' => '#f59e0b'],
                    'OTHER' => ['bg' => '#f3f4f6', 'text' => '#374151', 'bar' => '#9ca3af'],
                ];
                $typeLabels = [
                    'MOTOR' => 'Motor',
                    'PERSONAL_ACCIDENT' => 'Personal Accident',
                    'FIRE' => 'Fire',
                    'OTHER' => 'Other',
                ];
                $maxType = $byProductType->max('total_commission') ?: 1;
            @endphp
            @forelse($byProductType as $pt)
                @php $c = $typeColors[$pt->product_type] ?? $typeColors['OTHER']; @endphp
                <div style="margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="background:{{ $c['bg'] }}; color:{{ $c['text'] }}; font-size:11px; font-weight:600; padding:2px 8px; border-radius:20px;">
                                {{ $typeLabels[$pt->product_type] ?? $pt->product_type }}
                            </span>
                            <span style="font-size:12px; color:#6b7280;">{{ $pt->txn_count }} txn</span>
                        </div>
                        <span style="font-size:13px; font-weight:600; color:#111827;">RM {{ number_format($pt->total_commission, 2) }}</span>
                    </div>
                    <div style="background:#f3f4f6; border-radius:4px; height:8px; overflow:hidden;">
                        <div style="background:{{ $c['bar'] }}; height:8px; width:{{ ($pt->total_commission / $maxType) * 100 }}%; border-radius:4px; transition:width .3s;"></div>
                    </div>
                    <div style="display:flex; gap:12px; margin-top:4px;">
                        <span style="font-size:11px; color:#16a34a;">✓ RM {{ number_format($pt->earned, 2) }} earned</span>
                        <span style="font-size:11px; color:#d97706;">⏳ RM {{ number_format($pt->pending, 2) }} pending</span>
                    </div>
                </div>
            @empty
                <div style="color:#9ca3af; font-size:13px; text-align:center; padding:20px 0;">No commission data yet.</div>
            @endforelse
        </div>

        {{-- By Role in Group --}}
        <div style="background:#fff; border-radius:10px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,.08);">
            <h3 style="font-size:14px; font-weight:600; color:#374151; margin:0 0 16px;">Group Commission by Role</h3>
            @php
                $roleColors = [
                    'GROUP_LEADER' => ['bg' => '#d1fae5', 'text' => '#065f46', 'bar' => '#10b981', 'label' => 'Group Leader'],
                    'TEAM_LEADER'  => ['bg' => '#dbeafe', 'text' => '#1e40af', 'bar' => '#3b82f6', 'label' => 'Team Leader'],
                    'INTRODUCER'   => ['bg' => '#ede9fe', 'text' => '#5b21b6', 'bar' => '#8b5cf6', 'label' => 'Introducer'],
                ];
                $maxRole = $byRole->max('total_commission') ?: 1;
                $groupTotal = $byRole->sum('total_commission');
            @endphp
            <div style="font-size:12px; color:#6b7280; margin-bottom:12px;">Group total: <strong style="color:#111827;">RM {{ number_format($groupTotal, 2) }}</strong></div>
            @forelse($byRole as $role)
                @php $rc = $roleColors[$role->role_at_transaction] ?? ['bg'=>'#f3f4f6','text'=>'#374151','bar'=>'#9ca3af','label'=>$role->role_at_transaction]; @endphp
                <div style="margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="background:{{ $rc['bg'] }}; color:{{ $rc['text'] }}; font-size:11px; font-weight:600; padding:2px 8px; border-radius:20px;">{{ $rc['label'] }}</span>
                            <span style="font-size:12px; color:#6b7280;">{{ $role->txn_count }} txn</span>
                        </div>
                        <span style="font-size:13px; font-weight:600; color:#111827;">RM {{ number_format($role->total_commission, 2) }}</span>
                    </div>
                    <div style="background:#f3f4f6; border-radius:4px; height:8px; overflow:hidden;">
                        <div style="background:{{ $rc['bar'] }}; height:8px; width:{{ ($role->total_commission / $maxRole) * 100 }}%; border-radius:4px;"></div>
                    </div>
                    <div style="display:flex; gap:12px; margin-top:4px;">
                        <span style="font-size:11px; color:#16a34a;">✓ RM {{ number_format($role->earned, 2) }} earned</span>
                        <span style="font-size:11px; color:#d97706;">⏳ RM {{ number_format($role->pending, 2) }} pending</span>
                    </div>
                </div>
            @empty
                <div style="color:#9ca3af; font-size:13px; text-align:center; padding:20px 0;">No group commission data yet.</div>
            @endforelse
        </div>
    </div>

    {{-- ── Held Commission Detail ── --}}
    @if(count($heldItems) > 0)
    <div style="background:#fff; border-radius:10px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,.08); margin-bottom:24px;">
        <h3 style="font-size:14px; font-weight:600; color:#dc2626; margin:0 0 14px;">🔒 Held Commission Detail</h3>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="background:#fef2f2;">
                        <th style="text-align:left; padding:10px 12px; font-weight:600; color:#374151; border-bottom:1px solid #fecaca;">Transaction</th>
                        <th style="text-align:left; padding:10px 12px; font-weight:600; color:#374151; border-bottom:1px solid #fecaca;">Customer</th>
                        <th style="text-align:left; padding:10px 12px; font-weight:600; color:#374151; border-bottom:1px solid #fecaca;">Product</th>
                        <th style="text-align:left; padding:10px 12px; font-weight:600; color:#374151; border-bottom:1px solid #fecaca;">Hold Reason</th>
                        <th style="text-align:right; padding:10px 12px; font-weight:600; color:#374151; border-bottom:1px solid #fecaca;">Held Amount</th>
                        <th style="text-align:right; padding:10px 12px; font-weight:600; color:#374151; border-bottom:1px solid #fecaca;">Days Held</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($heldItems as $item)
                    <tr style="border-bottom:1px solid #f9fafb;">
                        <td style="padding:10px 12px;">
                            <a href="{{ route('gl.transactions.show', $item->policy_id) }}" style="color:#2563eb; font-weight:600; text-decoration:none;">
                                {{ $item->policy_number ?? 'N/A' }}
                            </a>
                        </td>
                        <td style="padding:10px 12px; color:#374151;">{{ $item->customer_name ?? '—' }}</td>
                        <td style="padding:10px 12px; color:#374151;">
                            @php $tc = $typeColors[$item->product_type ?? 'OTHER'] ?? $typeColors['OTHER']; @endphp
                            <span style="background:{{ $tc['bg'] }}; color:{{ $tc['text'] }}; font-size:11px; padding:2px 7px; border-radius:12px;">{{ $typeLabels[$item->product_type ?? 'OTHER'] ?? $item->product_type }}</span>
                            <span style="display:block; font-size:12px; color:#6b7280; margin-top:2px;">{{ $item->product_name ?? '' }}</span>
                        </td>
                        <td style="padding:10px 12px;">
                            @php
                                $holdReasonMap = [
                                    'CLAIM_PENDING' => ['label' => 'Claim Pending', 'color' => '#d97706'],
                                    'VENDOR_UNMATCHED' => ['label' => 'Vendor Unmatched', 'color' => '#dc2626'],
                                    'AGENT_NOT_CONFIRMED' => ['label' => 'Agent Not Confirmed', 'color' => '#7c3aed'],
                                ];
                                $hr = $holdReasonMap[$item->hold_reason] ?? ['label' => $item->hold_reason, 'color' => '#6b7280'];
                            @endphp
                            <span style="color:{{ $hr['color'] }}; font-size:12px; font-weight:500;">{{ $hr['label'] }}</span>
                        </td>
                        <td style="padding:10px 12px; text-align:right; font-weight:600; color:#dc2626;">RM {{ number_format($item->held_amount, 2) }}</td>
                        <td style="padding:10px 12px; text-align:right;">
                            @php $days = $item->days_held ?? 0; @endphp
                            <span style="color:{{ $days >= 14 ? '#dc2626' : ($days >= 7 ? '#d97706' : '#374151') }}; font-weight:{{ $days >= 7 ? '600' : '400' }};">
                                {{ $days }}d
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ── Commission Transactions Table ── --}}
    <div style="background:#fff; border-radius:10px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,.08);">

        {{-- Filter bar --}}
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
            <h3 style="font-size:14px; font-weight:600; color:#374151; margin:0;">Commission History</h3>
            <form method="GET" action="{{ route('gl.commissions.index') }}" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search ref no or customer…"
                    style="border:1px solid #d1d5db; border-radius:6px; padding:6px 12px; font-size:13px; min-width:200px; outline:none;">

                <select name="filter" style="border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:13px; background:#fff;">
                    <option value="all" {{ $filter==='all' ? 'selected' : '' }}>All</option>
                    <option value="earned" {{ $filter==='earned' ? 'selected' : '' }}>Earned</option>
                    <option value="pending" {{ $filter==='pending' ? 'selected' : '' }}>Pending</option>
                    <option value="held" {{ $filter==='held' ? 'selected' : '' }}>On Hold</option>
                </select>

                <button type="submit" style="background:#2563eb; color:#fff; border:none; border-radius:6px; padding:7px 16px; font-size:13px; font-weight:500; cursor:pointer;">Filter</button>
                <a href="{{ route('gl.commissions.index') }}" style="background:#f3f4f6; color:#374151; border-radius:6px; padding:7px 16px; font-size:13px; font-weight:500; text-decoration:none;">Reset</a>
            </form>
        </div>

        {{-- Status filter tabs --}}
        <div style="display:flex; gap:8px; margin-bottom:16px;">
            @foreach([
                ['all', 'All'],
                ['earned', '✅ Earned'],
                ['pending', '⏳ Pending'],
                ['held', '🔒 On Hold'],
            ] as [$val, $lbl])
            <a href="{{ route('gl.commissions.index', array_merge(request()->query(), ['filter' => $val])) }}"
               style="padding:5px 14px; border-radius:20px; font-size:12px; font-weight:500; text-decoration:none;
                      background:{{ $filter === $val ? '#2563eb' : '#f3f4f6' }};
                      color:{{ $filter === $val ? '#fff' : '#374151' }};">
                {{ $lbl }}
            </a>
            @endforeach
        </div>

        <div style="font-size:12px; color:#6b7280; margin-bottom:12px;">
            Showing {{ $commissions->firstItem() ?? 0 }}–{{ $commissions->lastItem() ?? 0 }} of {{ $commissions->total() }} commission records
        </div>

        @php
            $statusStyles = [
                'CONFIRMED' => ['bg' => '#d1fae5', 'text' => '#065f46', 'label' => 'Confirmed'],
                'PAID'      => ['bg' => '#d1fae5', 'text' => '#065f46', 'label' => 'Paid'],
                'PENDING'   => ['bg' => '#fef3c7', 'text' => '#92400e', 'label' => 'Pending'],
                'HELD'      => ['bg' => '#fee2e2', 'text' => '#991b1b', 'label' => 'On Hold'],
                'REVERSED'  => ['bg' => '#f3f4f6', 'text' => '#6b7280', 'label' => 'Reversed'],
            ];
        @endphp

        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="background:#f9fafb;">
                        <th style="text-align:left; padding:10px 12px; font-weight:600; color:#374151; border-bottom:2px solid #e5e7eb; white-space:nowrap;">Transaction</th>
                        <th style="text-align:left; padding:10px 12px; font-weight:600; color:#374151; border-bottom:2px solid #e5e7eb;">Customer</th>
                        <th style="text-align:left; padding:10px 12px; font-weight:600; color:#374151; border-bottom:2px solid #e5e7eb;">Product / Vendor</th>
                        <th style="text-align:right; padding:10px 12px; font-weight:600; color:#374151; border-bottom:2px solid #e5e7eb; white-space:nowrap;">Premium (RM)</th>
                        <th style="text-align:right; padding:10px 12px; font-weight:600; color:#374151; border-bottom:2px solid #e5e7eb; white-space:nowrap;">Rate %</th>
                        <th style="text-align:right; padding:10px 12px; font-weight:600; color:#374151; border-bottom:2px solid #e5e7eb; white-space:nowrap;">Commission (RM)</th>
                        <th style="text-align:center; padding:10px 12px; font-weight:600; color:#374151; border-bottom:2px solid #e5e7eb;">Status</th>
                        <th style="text-align:left; padding:10px 12px; font-weight:600; color:#374151; border-bottom:2px solid #e5e7eb;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($commissions as $c)
                    @php $ss = $statusStyles[$c->status] ?? ['bg'=>'#f3f4f6','text'=>'#6b7280','label'=>$c->status]; @endphp
                    <tr style="border-bottom:1px solid #f3f4f6; transition:background .15s;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
                        <td style="padding:10px 12px;">
                            <a href="{{ route('gl.transactions.show', $c->policy_id) }}" style="color:#2563eb; font-weight:600; text-decoration:none; font-size:12px;">
                                {{ $c->policy_number }}
                            </a>
                            <div style="font-size:11px; color:#9ca3af; margin-top:1px;">{{ $c->product_code }}</div>
                        </td>
                        <td style="padding:10px 12px; color:#374151;">{{ $c->customer_name }}</td>
                        <td style="padding:10px 12px;">
                            @php $tc2 = $typeColors[$c->product_type ?? 'OTHER'] ?? $typeColors['OTHER']; @endphp
                            <span style="background:{{ $tc2['bg'] }}; color:{{ $tc2['text'] }}; font-size:11px; padding:1px 7px; border-radius:12px;">
                                {{ $typeLabels[$c->product_type ?? 'OTHER'] ?? $c->product_type }}
                            </span>
                            <div style="font-size:12px; color:#374151; margin-top:2px;">{{ $c->product_name }}</div>
                            <div style="font-size:11px; color:#9ca3af;">{{ $c->vendor_name }}</div>
                        </td>
                        <td style="padding:10px 12px; text-align:right; color:#374151; font-weight:500;">{{ number_format($c->premium_amount, 2) }}</td>
                        <td style="padding:10px 12px; text-align:right; color:#6b7280;">{{ number_format($c->commission_pct, 1) }}%</td>
                        <td style="padding:10px 12px; text-align:right; font-weight:700; color:{{ $c->status === 'HELD' ? '#dc2626' : '#111827' }};">
                            {{ number_format($c->commission_amount, 2) }}
                            @if($c->status === 'HELD')
                                <span style="font-size:10px;">🔒</span>
                            @endif
                        </td>
                        <td style="padding:10px 12px; text-align:center;">
                            <span style="background:{{ $ss['bg'] }}; color:{{ $ss['text'] }}; font-size:11px; font-weight:600; padding:3px 10px; border-radius:20px; white-space:nowrap;">
                                {{ $ss['label'] }}
                            </span>
                        </td>
                        <td style="padding:10px 12px; color:#6b7280; white-space:nowrap; font-size:12px;">
                            {{ \Carbon\Carbon::parse($c->created_at)->format('d M Y') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="text-align:center; padding:40px 12px; color:#9ca3af; font-size:13px;">
                            No commission records found.
                            @if($search || $filter !== 'all')
                                <a href="{{ route('gl.commissions.index') }}" style="color:#2563eb; margin-left:6px;">Clear filters</a>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($commissions->count() > 0)
                <tfoot>
                    <tr style="background:#f9fafb;">
                        <td colspan="5" style="padding:10px 12px; font-weight:600; color:#374151; font-size:13px;">Page Total</td>
                        <td style="padding:10px 12px; text-align:right; font-weight:700; color:#111827; font-size:13px;">
                            RM {{ number_format($commissions->sum('commission_amount'), 2) }}
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

        {{-- Pagination --}}
        @if($commissions->hasPages())
        <div style="margin-top:16px; display:flex; justify-content:space-between; align-items:center;">
            <div style="font-size:12px; color:#6b7280;">
                Page {{ $commissions->currentPage() }} of {{ $commissions->lastPage() }}
            </div>
            <div style="display:flex; gap:4px;">
                @if($commissions->onFirstPage())
                    <span style="padding:6px 12px; border:1px solid #e5e7eb; border-radius:6px; font-size:13px; color:#d1d5db;">← Prev</span>
                @else
                    <a href="{{ $commissions->previousPageUrl() }}" style="padding:6px 12px; border:1px solid #e5e7eb; border-radius:6px; font-size:13px; color:#374151; text-decoration:none;">← Prev</a>
                @endif
                @if($commissions->hasMorePages())
                    <a href="{{ $commissions->nextPageUrl() }}" style="padding:6px 12px; border:1px solid #e5e7eb; border-radius:6px; font-size:13px; color:#374151; text-decoration:none;">Next →</a>
                @else
                    <span style="padding:6px 12px; border:1px solid #e5e7eb; border-radius:6px; font-size:13px; color:#d1d5db;">Next →</span>
                @endif
            </div>
        </div>
        @endif
    </div>

</div>
@endsection
