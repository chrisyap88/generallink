@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_exec.row_pending_approval_messages'))

@section('content')

{{-- NEW 27 Aug 2026 — Box 6's Pending Approval Messages row drill-down
(master spec Section 55), per Chris: "group approval into one and allow
to drill down to show the 2 tab" — confirmed as Pending / History
(Approved+Rejected), both from cbe_internal_approval_messages. The
Approve/Reject + OTP verification action screen is a separate follow-up
build; this is the list/inbox view. --}}
<style>
.cbe-tab-btn{padding:5px 14px; font-size:9px; font-weight:700; color:#6b7280; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; user-select:none; text-decoration:none; display:inline-block;}
.cbe-tab-btn.active{color:var(--gl-blue); border-bottom-color:var(--gl-blue);}
.cbe-appr-row{display:flex; align-items:center; justify-content:space-between; padding:4px 10px; border-bottom:1px solid #eef2f7; font-size:8.5px; line-height:1.25; gap:8px;}
.cbe-appr-row:last-child{border-bottom:none;}
.cbe-appr-subject{color:#263238; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.cbe-appr-meta{color:#6b7280; font-size:7.5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.cbe-appr-cat{background:var(--gl-light); color:var(--gl-blue); border-radius:4px; padding:2px 7px; font-size:7px; font-weight:700; white-space:nowrap; flex-shrink:0;}
.cbe-appr-status{border-radius:4px; padding:2px 7px; font-size:7px; font-weight:700; white-space:nowrap; flex-shrink:0;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:6px 16px; box-sizing:border-box; gap:5px;">

    <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; gap:10px;">
        <div style="min-width:0;">
            <div style="font-size:12px; font-weight:700; color:#263238; white-space:nowrap;">{{ __('cbe_exec.row_pending_approval_messages') }}</div>
            <div style="font-size:8.5px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $scopeLabel }}</div>
        </div>
        <a href="{{ route('admin.cbe-kpi', $backQuery) }}" style="font-size:8.5px; color:var(--gl-blue); text-decoration:none; font-weight:700; white-space:nowrap;">← {{ __('admin_cbe_kpi.back_to_dashboard') }}</a>
    </div>

    <div style="flex-shrink:0; display:flex; gap:4px; border-bottom:1px solid #e2e8f0;">
        <div class="cbe-tab-btn active" data-tab="pending" onclick="cbeApprTab('pending')">{{ __('cbe_exec.tab_pending') }} ({{ $pending->count() }})</div>
        <div class="cbe-tab-btn" data-tab="history" onclick="cbeApprTab('history')">{{ __('cbe_exec.tab_history') }} ({{ $history->count() }})</div>
    </div>

    <div id="cbe-appr-pending" style="flex:1; min-height:0; display:flex; flex-direction:column; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; box-shadow:0 2px 8px rgba(21,101,192,0.08); overflow:hidden;">
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($pending as $m)
            <a href="{{ route('admin.cbe-kpi.approvals.review', array_merge(['message' => $m->message_id], $backQuery)) }}" class="cbe-appr-row" style="text-decoration:none; cursor:pointer;">
                <div style="min-width:0; flex:1;">
                    <div class="cbe-appr-subject">{{ $m->subject }}</div>
                    <div class="cbe-appr-meta">{{ $m->sender_name ?? '—' }} · {{ \Carbon\Carbon::parse($m->created_at)->format('d M Y, g:i A') }}</div>
                </div>
                <span class="cbe-appr-cat">{{ __('cbe_exec.approval_cat_'.strtolower($m->category)) }}</span>
                <span class="cbe-appr-status" style="background:#FFF3E0; color:#E65100;">{{ __('cbe_exec.approval_status_pending') }} ›</span>
            </a>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('dashboard.admin_no_matches') }}</div>
            @endforelse
        </div>
    </div>

    <div id="cbe-appr-history" style="display:none; flex:1; min-height:0; flex-direction:column; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; box-shadow:0 2px 8px rgba(21,101,192,0.08); overflow:hidden;">
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($history as $m)
            <div class="cbe-appr-row">
                <div style="min-width:0; flex:1;">
                    <div class="cbe-appr-subject">{{ $m->subject }}</div>
                    <div class="cbe-appr-meta">{{ $m->sender_name ?? '—' }} · {{ $m->responded_at ? \Carbon\Carbon::parse($m->responded_at)->format('d M Y, g:i A') : '—' }}</div>
                </div>
                <span class="cbe-appr-cat">{{ __('cbe_exec.approval_cat_'.strtolower($m->category)) }}</span>
                <span class="cbe-appr-status" style="background:{{ $m->status === 'APPROVED' ? '#E8F5E9' : '#FFEBEE' }}; color:{{ $m->status === 'APPROVED' ? '#2e7d32' : '#c62828' }};">{{ $m->status === 'APPROVED' ? __('cbe_exec.approval_status_approved') : __('cbe_exec.approval_status_rejected') }}</span>
            </div>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('dashboard.admin_no_matches') }}</div>
            @endforelse
        </div>
    </div>
</div>

<script>
function cbeApprTab(tab){
    document.getElementById('cbe-appr-pending').style.display = (tab === 'pending') ? 'flex' : 'none';
    document.getElementById('cbe-appr-history').style.display = (tab === 'history') ? 'flex' : 'none';
    document.querySelectorAll('.cbe-tab-btn').forEach(function(b){ b.classList.toggle('active', b.getAttribute('data-tab') === tab); });
}
</script>
@endsection
