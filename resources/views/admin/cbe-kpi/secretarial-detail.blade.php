@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_exec.box_secretarial_overview'))

@section('content')

{{-- NEW 27 Aug 2026 — Box 2 Secretarial Overview's 3-tab drill-down
(master spec Section 53, task #228). Same fixed-height, internal-scroll
pattern as the Financial/Bills/Approvals drilldowns. --}}
<style>
.cbe-tab-btn{padding:5px 14px; font-size:9px; font-weight:700; color:#6b7280; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; user-select:none; text-decoration:none; display:inline-block;}
.cbe-tab-btn.active{color:var(--gl-blue); border-bottom-color:var(--gl-blue);}
.cbe-sec-row{display:flex; align-items:center; justify-content:space-between; padding:4px 10px; border-bottom:1px solid #eef2f7; font-size:8.5px; line-height:1.25; gap:8px;}
.cbe-sec-row:last-child{border-bottom:none;}
.cbe-sec-title{color:#263238; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.cbe-sec-meta{color:#6b7280; font-size:7.5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.cbe-sec-badge{background:var(--gl-light); color:var(--gl-blue); border-radius:4px; padding:2px 7px; font-size:7px; font-weight:700; white-space:nowrap; flex-shrink:0;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:6px 16px; box-sizing:border-box; gap:5px;">

    <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; gap:10px;">
        <div style="min-width:0;">
            <div style="font-size:12px; font-weight:700; color:#263238; white-space:nowrap;">{{ __('cbe_exec.box_secretarial_overview') }}</div>
            <div style="font-size:8.5px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $scopeLabel }} — {{ $periodLabel }}</div>
        </div>
        <a href="{{ route('admin.cbe-kpi', $backQuery) }}" style="font-size:8.5px; color:var(--gl-blue); text-decoration:none; font-weight:700; white-space:nowrap;">← {{ __('admin_cbe_kpi.back_to_dashboard') }}</a>
    </div>

    <div style="flex-shrink:0; display:flex; gap:4px; border-bottom:1px solid #e2e8f0;">
        <div class="cbe-tab-btn{{ $initialTab === 'committee' ? ' active' : '' }}" data-tab="committee" onclick="cbeSecTab('committee')">{{ __('cbe_exec.row_committee_members') }} ({{ $committee->count() }})</div>
        <div class="cbe-tab-btn{{ $initialTab === 'meetings' ? ' active' : '' }}" data-tab="meetings" onclick="cbeSecTab('meetings')">{{ __('cbe_exec.row_meetings_this_year') }} ({{ $meetings->count() }})</div>
        <div class="cbe-tab-btn{{ $initialTab === 'correspondence' ? ' active' : '' }}" data-tab="correspondence" onclick="cbeSecTab('correspondence')">{{ __('cbe_exec.row_correspondence_total') }} ({{ $correspondence->count() }})</div>
    </div>

    <div id="cbe-sec-committee" style="display:{{ $initialTab === 'committee' ? 'flex' : 'none' }}; flex:1; min-height:0; flex-direction:column; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; box-shadow:0 2px 8px rgba(21,101,192,0.08); overflow:hidden;">
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($committee as $c)
            <div class="cbe-sec-row">
                <div style="min-width:0;">
                    <div class="cbe-sec-title">{{ $c->member_name ?? '—' }}</div>
                    <div class="cbe-sec-meta">{{ \Carbon\Carbon::parse($c->term_start_date)->format('d M Y') }} – {{ \Carbon\Carbon::parse($c->term_end_date)->format('d M Y') }}</div>
                </div>
                <span class="cbe-sec-badge">{{ $c->position_title }}</span>
            </div>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('dashboard.admin_no_matches') }}</div>
            @endforelse
        </div>
    </div>

    <div id="cbe-sec-meetings" style="display:{{ $initialTab === 'meetings' ? 'flex' : 'none' }}; flex:1; min-height:0; flex-direction:column; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; box-shadow:0 2px 8px rgba(21,101,192,0.08); overflow:hidden;">
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($meetings as $m)
            <div class="cbe-sec-row">
                <div style="min-width:0;">
                    <div class="cbe-sec-title">{{ $m->title }}</div>
                    <div class="cbe-sec-meta">{{ \Carbon\Carbon::parse($m->meeting_date)->format('d M Y') }}@if($m->venue) · {{ $m->venue }}@endif</div>
                </div>
                <span class="cbe-sec-badge" style="background:{{ $m->status === 'COMPLETED' ? '#E8F5E9' : '#FFF3E0' }}; color:{{ $m->status === 'COMPLETED' ? '#2e7d32' : '#E65100' }};">{{ $m->status }}</span>
            </div>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('dashboard.admin_no_matches') }}</div>
            @endforelse
        </div>
    </div>

    <div id="cbe-sec-correspondence" style="display:{{ $initialTab === 'correspondence' ? 'flex' : 'none' }}; flex:1; min-height:0; flex-direction:column; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; box-shadow:0 2px 8px rgba(21,101,192,0.08); overflow:hidden;">
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($correspondence as $c)
            <div class="cbe-sec-row">
                <div style="min-width:0;">
                    <div class="cbe-sec-title">{{ $c->subject }}</div>
                    <div class="cbe-sec-meta">{{ \Carbon\Carbon::parse($c->correspondence_date)->format('d M Y') }}@if($c->direction) · {{ $c->direction }}@endif</div>
                </div>
                <span class="cbe-sec-badge">{{ $c->type }}</span>
            </div>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('dashboard.admin_no_matches') }}</div>
            @endforelse
        </div>
    </div>
</div>

<script>
function cbeSecTab(tab){
    ['committee','meetings','correspondence'].forEach(function(t){
        document.getElementById('cbe-sec-'+t).style.display = (t === tab) ? 'flex' : 'none';
    });
    document.querySelectorAll('.cbe-tab-btn').forEach(function(b){ b.classList.toggle('active', b.getAttribute('data-tab') === tab); });
}
</script>
@endsection
