@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_exec.director_title'))

@section('content')

<style>
.ed-box{flex:1; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; padding:10px 12px; display:flex; flex-direction:column; min-width:0; box-sizing:border-box; box-shadow:0 2px 10px rgba(21,101,192,0.10);}
.ed-box-title{font-size:9px; font-weight:700; color:var(--gl-blue); text-transform:uppercase; letter-spacing:0.3px; margin-bottom:6px; padding-bottom:5px; border-bottom:1px solid #eef2f7; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.ed-rows{flex:1; display:flex; flex-direction:column; gap:3px; justify-content:center; min-height:0;}
.ed-row{display:flex; justify-content:space-between; align-items:center; font-size:8px; gap:6px;}
.ed-row span:first-child{color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.ed-row span:last-child{font-weight:700; color:#0d3c72; white-space:nowrap; flex-shrink:0;}
.ed-lock{font-size:7.5px; color:#92700a; background:#FFF8E1; border-radius:4px; padding:3px 6px; margin-top:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; gap:7px;">

    <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_exec.director_title') }}</div>
            <div style="font-size:9px; color:#6b7280;">{{ $officer->node_name }}{{ $officer->node_name_zh ? ' ('.$officer->node_name_zh.')' : '' }} — {{ $officer->group_name }}</div>
        </div>
        <span style="background:{{ $isPaid ? '#2e7d32' : '#94A3B8' }}; color:#fff; border-radius:12px; padding:4px 12px; font-size:8.5px; font-weight:700;">{{ $isPaid ? __('cbe_exec.tier_paid') : __('cbe_exec.tier_free') }}</span>
    </div>

    <div style="flex:1; min-height:0; display:flex; gap:8px;">
        <div class="ed-box">
            <div class="ed-box-title">{{ __('cbe_exec.box_membership_overview') }}</div>
            <div class="ed-rows">
                <div class="ed-row"><span>{{ __('cbe_exec.row_total_members') }}</span><span>{{ number_format($totalMembers) }}</span></div>
                <div class="ed-row"><span>{{ __('cbe_exec.row_new_members_month') }}</span><span>{{ number_format($newMembersThisMonth) }}</span></div>
                @if($isPaid)
                <div class="ed-row"><span>{{ __('cbe_exec.row_branches') }}</span><span>{{ number_format($childBranchCount) }}</span></div>
                @else
                <div class="ed-lock">{{ __('cbe_exec.upgrade_locked', ['feature' => 'renewal & growth trends']) }}</div>
                @endif
            </div>
        </div>
        <div class="ed-box">
            <div class="ed-box-title">{{ __('cbe_exec.box_financial_overview') }}</div>
            <div class="ed-rows">
                <div class="ed-row"><span>{{ __('cbe_exec.row_total_income') }}</span><span>RM {{ number_format($financial['total_income'], 2) }}</span></div>
                <div class="ed-row"><span>{{ __('cbe_exec.row_total_expenses') }}</span><span>RM {{ number_format($financial['total_expense'], 2) }}</span></div>
                <div class="ed-row"><span>{{ __('cbe_exec.row_cash_balance') }}</span><span>RM {{ number_format($financial['cash_balance'], 2) }}</span></div>
                @if(!$isPaid)
                <div class="ed-lock">{{ __('cbe_exec.upgrade_locked', ['feature' => 'consolidated AR/AP view']) }}</div>
                @endif
            </div>
        </div>
        <div class="ed-box">
            <div class="ed-box-title">{{ __('cbe_exec.box_org_performance') }}</div>
            <div class="ed-rows">
                <div class="ed-row"><span>{{ __('cbe_exec.row_branches') }}</span><span>{{ number_format($childBranchCount) }}</span></div>
                <div class="ed-row"><span>{{ __('cbe_exec.row_committee_members') }}</span><span>{{ __('cbe_exec.coming_soon') }}</span></div>
                <div class="ed-row"><span>{{ __('cbe_exec.row_volunteers') }}</span><span>{{ __('cbe_exec.coming_soon') }}</span></div>
            </div>
        </div>
    </div>

    <div style="flex:1; min-height:0; display:flex; gap:8px;">
        <div class="ed-box">
            <div class="ed-box-title">{{ __('cbe_exec.box_activities_calendar') }}</div>
            <div class="ed-rows">
                <div class="ed-row"><span>{{ __('cbe_exec.row_upcoming_events') }}</span><span>{{ number_format($upcomingEvents) }}</span></div>
                <div class="ed-row"><span>{{ __('cbe_exec.row_completed_events') }}</span><span>{{ number_format($completedEvents) }}</span></div>
                <div class="ed-row"><span>{{ __('cbe_exec.row_meetings_this_year') }}</span><span>{{ number_format($meetingsThisYear) }}</span></div>
            </div>
        </div>
        <div class="ed-box">
            <div class="ed-box-title">{{ __('cbe_exec.box_approvals_pending') }}</div>
            <div class="ed-rows">
                <div class="ed-row"><span>{{ __('cbe_exec.row_pending_bill_payments') }}</span><span>{{ number_format($apBillCount) }}</span></div>
                <div class="ed-row"><span>{{ __('cbe_exec.row_membership_applications') }}</span><span>{{ __('cbe_exec.coming_soon') }}</span></div>
                @if(!$isPaid)
                <div class="ed-lock">{{ __('cbe_exec.upgrade_locked', ['feature' => 'approval workflow']) }}</div>
                @endif
            </div>
        </div>
        <div class="ed-box">
            <div class="ed-box-title">{{ __('cbe_exec.box_notifications_alerts') }}</div>
            <div class="ed-rows">
                <div class="ed-row"><span>{{ __('cbe_exec.row_bills_due_soon') }}</span><span>{{ number_format($billsDueSoon) }}</span></div>
                <div class="ed-row"><span>{{ __('cbe_exec.row_bills_overdue') }}</span><span style="color:{{ $billsOverdue > 0 ? '#c62828' : 'var(--gl-blue)' }};">{{ number_format($billsOverdue) }}</span></div>
                <div class="ed-row"><span>{{ __('cbe_exec.row_memberships_expiring') }}</span><span>{{ __('cbe_exec.coming_soon') }}</span></div>
            </div>
        </div>
    </div>

    <div style="flex:1.1; min-height:0;">
        <div class="ed-box" style="height:100%;">
            <div class="ed-box-title">{{ __('cbe_exec.box_executive_analytics') }}</div>
            @if($isPaid)
            <div style="flex:1; display:flex; align-items:center; gap:14px; padding:4px 0;">
                <div style="text-align:center; flex:1;">
                    <div style="font-size:16px; font-weight:700; color:#2e7d32;">RM {{ number_format($financial['net_surplus'], 2) }}</div>
                    <div style="font-size:7.5px; color:#6b7280;">{{ __('cbe_exec.row_net_surplus') }}</div>
                </div>
                <div style="text-align:center; flex:1;">
                    <div style="font-size:16px; font-weight:700; color:var(--gl-blue);">{{ number_format($totalMembers) }}</div>
                    <div style="font-size:7.5px; color:#6b7280;">{{ __('cbe_exec.row_total_members') }}</div>
                </div>
                <div style="text-align:center; flex:1;">
                    <div style="font-size:16px; font-weight:700; color:var(--gl-blue);">{{ number_format($upcomingEvents + $completedEvents) }}</div>
                    <div style="font-size:7.5px; color:#6b7280;">{{ __('cbe_exec.box_activities_calendar') }}</div>
                </div>
                <div style="text-align:center; flex:1;">
                    <div style="font-size:16px; font-weight:700; color:var(--gl-blue);">{{ number_format($nodeCount) }}</div>
                    <div style="font-size:7.5px; color:#6b7280;">{{ __('cbe_exec.row_branches') }}</div>
                </div>
            </div>
            @else
            <div class="ed-lock" style="flex:1; display:flex; align-items:center; justify-content:center;">{{ __('cbe_exec.upgrade_locked', ['feature' => 'Executive Analytics (trends, YoY comparisons, drill-down)']) }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
