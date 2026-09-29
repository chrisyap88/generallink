@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
{{-- FIXED 1 Aug 2026 — same fix as all-tls.blade.php: scope to the
     selected GL's own group ($filterGroupLabelId, from the controller)
     when one is picked, otherwise stay generic. --}}
@section('title', 'Network — All ' . \App\Services\RoleLabelService::plural('INTRODUCER', $filterGroupLabelId ?? null))
@section('page-title', 'Network — All ' . \App\Services\RoleLabelService::plural('INTRODUCER', $filterGroupLabelId ?? null))

@push('styles')
<style>
.net-wrap{padding:5px 8px;display:flex;flex-direction:column;gap:3px;height:calc(100vh - 66px);box-sizing:border-box;overflow:hidden;}
.net-card{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:4px 8px;}
.net-table{width:100%;border-collapse:collapse;font-size:10px;table-layout:fixed;}
.net-table th{padding:2px 5px;text-align:left;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;white-space:nowrap;background:#F7FAFC;font-size:10px;}
.net-table td{padding:2px 5px;border-bottom:1px solid #F7FAFC;white-space:nowrap;font-size:10px;}
.net-table tr:hover td{background:#EBF5FB;cursor:pointer;}
.fbar{display:flex;align-items:flex-end;gap:5px;flex-wrap:wrap;}
.flbl{font-size:8px;color:#718096;margin-bottom:1px;}
.finp{font-size:9px;padding:2px 6px;border-radius:4px;border:1px solid #B2EBF2;height:24px;outline:none;box-sizing:border-box;}
.fsel{font-size:9px;padding:2px 4px;border-radius:4px;border:1px solid #B2EBF2;height:24px;background:#fff;color:#0D5A8E;}
.fbtn{padding:2px 10px;font-size:9px;height:24px;border:none;border-radius:4px;cursor:pointer;font-weight:600;white-space:nowrap;}
.ta-wrap{position:relative;display:inline-block;}
.ta-dropdown{position:absolute;top:26px;left:0;background:#fff;border:1px solid #B2EBF2;border-radius:5px;box-shadow:0 4px 12px rgba(0,0,0,.15);z-index:999;min-width:200px;max-height:180px;overflow-y:auto;display:none;}
.ta-item{padding:4px 8px;font-size:10px;cursor:pointer;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;}
.ta-item:hover{background:#EBF5FB;}
.ta-name{font-weight:600;color:#0D5A8E;}.ta-code{color:#9ca3af;font-size:9px;margin-left:8px;}
</style>
@endpush

@section('content')
<div class="net-wrap">

    {{-- Filter Bar --}}
    <div class="net-card">
        <form method="GET" action="{{ route('admin.network.all-intros') }}">
            <div class="fbar">
                <div>
                    <button type="submit" name="show_all" value="1" class="fbtn" style="background:#38A169;color:#fff;margin-top:13px;">{{ __('network.show_all_button') }}</button>
                </div>
                <div>
                    <div class="flbl">{{ __('network.name_label', ['role' => \App\Services\RoleLabelService::shortLabel('INTRODUCER', groupLabelId: $filterGroupLabelId ?? null)]) }}</div>
                    <div class="ta-wrap">
                        <input type="text" id="intro-inp" name="search" class="finp" value="{{ request('search') }}" placeholder="{{ __('network.type_name_placeholder', ['role' => \App\Services\RoleLabelService::shortLabel('INTRODUCER', groupLabelId: $filterGroupLabelId ?? null)]) }}" style="width:130px;" autocomplete="off">
                        <div id="intro-drop" class="ta-dropdown"></div>
                    </div>
                </div>
                <div>
                    <div class="flbl">{{ \App\Services\RoleLabelService::label('GROUP_LEADER', $filterGroupLabelId ?? null) }}</div>
                    <select name="gl_id" id="gl-sel" class="fsel" style="width:110px;" onchange="toggleTLDiv()">
                        <option value="">{{ __('network.all_role_plain', ['role' => \App\Services\RoleLabelService::shortLabel('GROUP_LEADER', groupLabelId: $filterGroupLabelId ?? null).'s']) }}</option>
                        @foreach($glList as $gl)
                        <option value="{{ $gl->agent_id }}" {{ request('gl_id')===$gl->agent_id?'selected':'' }}>{{ $gl->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="scope-div" style="{{ request('gl_id') ? '' : 'display:none;' }}">
                    <div class="flbl">{{ __('network.show_label') }}</div>
                    <select name="scope" class="fsel" style="width:100px;">
                        <option value="direct" {{ request('scope','direct')==='direct'?'selected':'' }}>{{ __('network.direct_only_option') }}</option>
                        <option value="all" {{ request('scope')==='all'?'selected':'' }}>{{ __('network.all_incl_option', ['role' => \App\Services\RoleLabelService::shortLabel('TEAM_LEADER', groupLabelId: $filterGroupLabelId ?? null).'s']) }}</option>
                    </select>
                </div>
                <div id="tl-div" style="{{ request('gl_id') ? '' : 'display:none;' }}">
                    <div class="flbl">{{ \App\Services\RoleLabelService::label('TEAM_LEADER', $filterGroupLabelId ?? null) }}</div>
                    <select name="tl_id" class="fsel" style="width:120px;">
                        <option value="">{{ __('network.all_role_plain', ['role' => \App\Services\RoleLabelService::shortLabel('TEAM_LEADER', groupLabelId: $filterGroupLabelId ?? null).'s']) }}</option>
                        @isset($tlList)
                        @foreach($tlList as $tl)
                        <option value="{{ $tl->agent_id }}" {{ request('tl_id')===$tl->agent_id?'selected':'' }}>{{ $tl->full_name }}</option>
                        @endforeach
                        @endisset
                    </select>
                </div>
                <div>
                    <div class="flbl">{{ __('network.status') }}</div>
                    <select name="status" class="fsel" style="width:80px;">
                        <option value="">{{ __('network.all_status_option') }}</option>
                        <option value="ACTIVE" {{ request('status')==='ACTIVE'?'selected':'' }}>{{ __('network.active') }}</option>
                        <option value="INACTIVE" {{ request('status')==='INACTIVE'?'selected':'' }}>{{ __('network.inactive') }}</option>
                        <option value="TERMINATED" {{ request('status')==='TERMINATED'?'selected':'' }}>{{ __('network.terminated') }}</option>
                    </select>
                </div>
                <div>
                    <div class="flbl">{{ __('network.joined_from_label') }}</div>
                    <input type="date" name="joined_from" class="finp" value="{{ request('joined_from') }}" style="width:105px;">
                </div>
                <div>
                    <div class="flbl">{{ __('network.joined_to_label') }}</div>
                    <input type="date" name="joined_to" class="finp" value="{{ request('joined_to') }}" style="width:105px;">
                </div>
                <div style="margin-top:13px;display:flex;gap:4px;">
                    <button type="submit" class="fbtn" style="background:#1B9AE4;color:#fff;">{{ __('network.go_button') }}</button>
                    <a href="{{ route('admin.network.all-intros') }}" class="fbtn" style="background:#f3f4f6;color:#4A5568;text-decoration:none;display:inline-flex;align-items:center;">{{ __('network.clear') }}</a>
                </div>
            </div>
        </form>
    </div>

    {{-- Results --}}
    <div class="net-card" style="flex:1;overflow:hidden;display:flex;flex-direction:column;">
        @if($intros->total() === 0 && !request()->hasAny(['show_all','search','gl_id','tl_id','status','joined_from','joined_to']))
            <div style="text-align:center;color:#A0AEC0;padding:60px 0;font-size:12px;">
                {!! __('network.use_filter_prompt', ['button' => '<strong>'.__('network.show_all_button').'</strong>', 'role' => \App\Services\RoleLabelService::plural('INTRODUCER', $filterGroupLabelId ?? null)]) !!}
            </div>
            <div style="margin-top:8px;">
                <a href="{{ route('admin.dashboard') }}" style="display:inline-block;background:#1565C0;color:#fff;border-radius:5px;padding:5px 16px;font-size:11px;font-weight:600;text-decoration:none;box-shadow:0 2px 8px rgba(0,0,0,.2);">{{ __('network.back_dashboard') }}</a>
            </div>
        @elseif($intros->total() === 0)
            <div style="text-align:center;color:#A0AEC0;padding:40px;font-size:12px;">{{ __('network.no_role_found', ['role' => \App\Services\RoleLabelService::plural('INTRODUCER', $filterGroupLabelId ?? null)]) }}</div>
            <div style="margin-top:8px;">
                <a href="{{ route('admin.dashboard') }}" style="display:inline-block;background:#1565C0;color:#fff;border-radius:5px;padding:5px 16px;font-size:11px;font-weight:600;text-decoration:none;box-shadow:0 2px 8px rgba(0,0,0,.2);">{{ __('network.back_dashboard') }}</a>
            </div>
        @else
            @php $showParent = !request('tl_id') && !request('gl_id'); @endphp
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:10px;color:#718096;flex-shrink:0;">
                <span>{{ __('network.role_found_count', ['count' => number_format($intros->total()), 'role' => \App\Services\RoleLabelService::plural('INTRODUCER', $filterGroupLabelId ?? null)]) }}</span>
                <span>{{ __('network.page_of', ['current' => $intros->currentPage(), 'last' => $intros->lastPage()]) }}</span>
            </div>
            <div style="flex:1;overflow:hidden;min-height:0;">
            <table class="net-table">
                <thead>
                    <tr>
                        <th style="width:30px;">{{ __('network.col_no') }}</th>
                        <th style="width:170px;">{{ \App\Services\RoleLabelService::label('INTRODUCER', $filterGroupLabelId ?? null) }}</th>
                        <th style="width:80px;">{{ __('network.col_code') }}</th>
                        @if($showParent)<th style="width:150px;">{{ __('network.under_label', ['role1' => \App\Services\RoleLabelService::shortLabel('TEAM_LEADER', groupLabelId: $filterGroupLabelId ?? null), 'role2' => \App\Services\RoleLabelService::shortLabel('GROUP_LEADER', groupLabelId: $filterGroupLabelId ?? null)]) }}</th>@endif
                        <th style="width:60px;text-align:center;">{{ __('network.col_status') }}</th>
                        <th style="width:85px;">{{ __('network.col_joined') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($intros as $intro)
                    @php
                        $rowNum = $loop->iteration;
                        // FIXED 2 Aug 2026 — real GL/TL ids from the controller now
                        // (was hardcoded 'na' + duplicate id, which always 404'd).
                        $rowGlId = $intro->gl_agent_id ?? $intro->parent_agent_id ?? $intro->agent_id;
                        $rowTlId = $intro->parent_agent_id ?? $intro->agent_id;
                        $introUrl = route('admin.network.introducer', [$rowGlId, $rowTlId, $intro->agent_id])
                            . '?from=all-intros'
                            . '&gl_id=' . urlencode(request('gl_id',''))
                            . '&tl_id=' . urlencode(request('tl_id',''))
                            . '&search=' . urlencode(request('search',''))
                            . '&sort=' . urlencode(request('sort',''));
                    @endphp
                    <tr onclick="window.location='{{ $introUrl }}'" onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''">
                        <td style="text-align:center;color:#718096;">{{ $rowNum }}</td>
                        <td style="font-weight:600;color:#0D5A8E;">{{ $intro->full_name }} →</td>
                        <td style="color:#9ca3af;">{{ $intro->agent_code }}</td>
                        @if($showParent)<td style="color:#718096;">{{ $intro->parent_name ?? '—' }}</td>@endif
                        <td style="text-align:center;">
                            <span style="padding:1px 6px;border-radius:20px;font-size:9px;font-weight:600;background:{{ $intro->status==='ACTIVE'?'#C8E6C9':'#FFCDD2' }};color:{{ $intro->status==='ACTIVE'?'#1B5E20':'#B71C1C' }};">{{ __('network.'.strtolower($intro->status)) }}</span>
                        </td>
                        <td style="color:#9ca3af;">{{ \Carbon\Carbon::parse($intro->created_at)->format('d M Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            {{-- Pagination --}}
            <div style="margin-top:6px;flex-shrink:0;display:flex;align-items:center;justify-content:space-between;">
                @if($intros->onFirstPage())
                    <a href="{{ route('admin.network.all-intros') }}" class="fbtn" style="background:#1565C0;color:#fff;text-decoration:none;display:inline-flex;align-items:center;">{{ __('network.back_to_filter') }}</a>
                @else
                    <a href="{{ $intros->appends(request()->query())->previousPageUrl() }}" class="fbtn" style="background:#1565C0;color:#fff;text-decoration:none;display:inline-flex;align-items:center;">{{ __('network.prev') }}</a>
                @endif
                <span style="font-size:10px;color:#374151;font-weight:600;">
                    {{ __('network.showing_records_page', ['first' => $intros->firstItem(), 'lastItem' => $intros->lastItem(), 'total' => number_format($intros->total()), 'current' => $intros->currentPage(), 'lastPage' => $intros->lastPage()]) }}
                </span>
                @if($intros->hasMorePages())
                    <a href="{{ $intros->appends(request()->query())->nextPageUrl() }}" class="fbtn" style="background:#1565C0;color:#fff;text-decoration:none;display:inline-flex;align-items:center;">{{ __('network.next') }}</a>
                @else
                    <span class="fbtn" style="background:#f3f4f6;color:#9ca3af;cursor:default;">{{ __('network.next') }}</span>
                @endif
            </div>
        @endif
    </div>


</div>

<script>
function toggleTLDiv(){
    var glId = document.getElementById('gl-sel').value;
    document.getElementById('scope-div').style.display = glId ? '' : 'none';
    document.getElementById('tl-div').style.display = glId ? '' : 'none';
}
function initTA(inpId,dropId,mode){
    var inp=document.getElementById(inpId),drop=document.getElementById(dropId);
    if(!inp)return;
    var t;
    inp.addEventListener('input',function(){
        clearTimeout(t);var q=this.value.trim();
        if(!q){drop.style.display='none';return;}
        t=setTimeout(function(){
            fetch('/admin/network/ajax/typeahead?q='+encodeURIComponent(q)+'&mode='+mode)
            .then(r=>r.json()).then(function(data){
                if(!data.length){drop.style.display='none';return;}
                drop.innerHTML=data.map(function(d){
                    return '<div class="ta-item" onclick="selTA(\''+inpId+'\',\''+dropId+'\',\''+d.name.replace(/'/g,"\\'")+'\')">'
                        +'<span class="ta-name">'+d.name+'</span><span class="ta-code">'+d.code+'</span></div>';
                }).join('');
                drop.style.display='block';
            });
        },200);
    });
    document.addEventListener('click',function(e){if(!drop.contains(e.target)&&e.target!==inp)drop.style.display='none';});
}
function selTA(inpId,dropId,name){document.getElementById(inpId).value=name;document.getElementById(dropId).style.display='none';}
document.addEventListener('DOMContentLoaded',function(){initTA('intro-inp','intro-drop','intro');});
</script>
@endsection
