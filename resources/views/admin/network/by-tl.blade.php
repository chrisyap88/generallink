@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
{{-- FIXED 1 Aug 2026 — same fix as by-gl.blade.php: scope every
     RoleLabelService call to this TL's own group, not Admin's null
     (system default) group. --}}
@php
    $tlGroupId = $tl->group_label_id;
@endphp
@section('title', $tl->full_name . ' — ' . \App\Services\RoleLabelService::plural('INTRODUCER', $tlGroupId))
@section('page-title', $tl->full_name . ' — ' . \App\Services\RoleLabelService::plural('INTRODUCER', $tlGroupId))

@push('styles')
<style>
.net-wrap{padding:5px 8px;display:flex;flex-direction:column;gap:3px;height:calc(100vh - 66px);box-sizing:border-box;overflow:hidden;}
.net-card{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:4px 8px;}
.net-table{width:100%;border-collapse:collapse;font-size:10px;table-layout:fixed;}
.net-table th{padding:2px 5px;text-align:left;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;white-space:nowrap;background:#F7FAFC;position:sticky;top:0;}
.net-table td{padding:1px 5px;border-bottom:1px solid #F7FAFC;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.net-table tr:hover td{background:#EBF5FB;cursor:pointer;}
.fbar{display:flex;align-items:flex-end;gap:5px;flex-wrap:nowrap;}
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

    <div style="font-size:10px;color:#718096;flex-shrink:0;">
        <a href="{{ route('admin.network') }}" style="color:#1B9AE4;text-decoration:none;">{{ __('network.all_role_plain', ['role' => \App\Services\RoleLabelService::shortLabel('GROUP_LEADER', groupLabelId: $tlGroupId).'s']) }}</a> ›
        <a href="{{ route('admin.network.gl', $gl->agent_id) }}" style="color:#1B9AE4;text-decoration:none;">{{ $gl->full_name }}</a> ›
        <strong style="color:#0D5A8E;">{{ $tl->full_name }} ({{ $tl->agent_code }})</strong>
        <span style="margin-left:8px;background:#E8F5E9;padding:1px 8px;border-radius:10px;color:#1B5E20;font-size:9px;">{{ \App\Services\RoleLabelService::plural('INTRODUCER', $tlGroupId) }}</span>
    </div>

    <div class="net-card">
        {{-- Month/Year is picked once on the GL (Cawangan) screen above this
             one and carried forward silently from here on — no need to ask
             again on TL/Introducer screens, it's already "understood". --}}
        <form method="GET" action="{{ route('admin.network.tl', [$gl->agent_id, $tl->agent_id]) }}">
            <input type="hidden" name="month" value="{{ $selMonth }}">
            <input type="hidden" name="year" value="{{ $selYear }}">
            <div class="fbar">
                <div><button type="submit" name="show_all" value="1" class="fbtn" style="background:#38A169;color:#fff;margin-top:13px;">{{ __('network.show_all_button') }}</button></div>
                <div>
                    <div class="flbl">{{ __('network.name_under_label', ['role' => \App\Services\RoleLabelService::shortLabel('INTRODUCER', groupLabelId: $tlGroupId), 'parent' => $tl->full_name]) }}</div>
                    <div class="ta-wrap">
                        <input type="text" id="intro-inp" name="search" class="finp" value="{{ request('search') }}" placeholder="{{ __('network.type_name_placeholder', ['role' => \App\Services\RoleLabelService::shortLabel('INTRODUCER', groupLabelId: $tlGroupId)]) }}" style="width:180px;" autocomplete="off">
                        <div id="intro-drop" class="ta-dropdown"></div>
                    </div>
                </div>
                <div>
                    <div class="flbl">{{ __('network.status') }}</div>
                    <select name="status" class="fsel" style="width:90px;">
                        <option value="">{{ __('network.all_status_option') }}</option>
                        <option value="ACTIVE" {{ request('status')==='ACTIVE'?'selected':'' }}>{{ __('network.active') }}</option>
                        <option value="INACTIVE" {{ request('status')==='INACTIVE'?'selected':'' }}>{{ __('network.inactive') }}</option>
                        <option value="TERMINATED" {{ request('status')==='TERMINATED'?'selected':'' }}>{{ __('network.terminated') }}</option>
                    </select>
                </div>
                <div>
                    <div class="flbl">{{ __('network.joined_from_label') }}</div>
                    <input type="date" name="joined_from" class="finp" value="{{ request('joined_from') }}" style="width:115px;">
                </div>
                <div>
                    <div class="flbl">{{ __('network.joined_to_label') }}</div>
                    <input type="date" name="joined_to" class="finp" value="{{ request('joined_to') }}" style="width:115px;">
                </div>
                <div style="margin-top:13px;display:flex;gap:4px;">
                    <button type="submit" class="fbtn" style="background:#1B9AE4;color:#fff;">{{ __('network.go_button') }}</button>
                    <a href="{{ route('admin.network.tl', [$gl->agent_id, $tl->agent_id]) }}" class="fbtn" style="background:#f3f4f6;color:#4A5568;text-decoration:none;display:inline-flex;align-items:center;">{{ __('network.clear') }}</a>
                </div>
            </div>
        </form>
    </div>

    <div class="net-card" style="flex:1;overflow:hidden;display:flex;flex-direction:column;">
        @if($introducers->total() === 0 && !request()->hasAny(['show_all','search','status','joined_from','joined_to']))
            <div style="text-align:center;color:#A0AEC0;padding:60px 0;font-size:12px;">{!! __('network.use_filter_prompt_under', ['button' => '<strong>'.__('network.show_all_button').'</strong>', 'role' => \App\Services\RoleLabelService::plural('INTRODUCER', $tlGroupId), 'parent' => '<strong>'.$tl->full_name.'</strong>']) !!}</div>
        @elseif($introducers->isEmpty())
            <div style="text-align:center;color:#A0AEC0;padding:40px;font-size:12px;">{{ __('network.no_role_found', ['role' => \App\Services\RoleLabelService::plural('INTRODUCER', $tlGroupId)]) }}</div>
        @else
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:10px;color:#718096;">
                <span>{{ __('network.role_found_under', ['count' => $introducers->total(), 'role' => \App\Services\RoleLabelService::plural('INTRODUCER', $tlGroupId), 'parent' => $tl->full_name]) }}</span>
                @if($introducers->hasPages())<span>{{ __('network.page_of', ['current' => $introducers->currentPage(), 'last' => $introducers->lastPage()]) }}</span>@endif
            </div>
            <div style="flex:1;overflow:hidden;min-height:0;">
            <table class="net-table">
                <thead><tr>
                    <th style="width:30px;">{{ __('network.col_no') }}</th>
                    <th style="width:160px;">{{ \App\Services\RoleLabelService::label('INTRODUCER', $tlGroupId) }}</th>
                    <th style="width:65px;">{{ __('network.col_code') }}</th>
                    <th style="width:60px;text-align:center;">{{ __('network.col_txns') }}</th>
                    <th style="width:90px;text-align:right;">{{ __('network.col_sales_mtd') }}</th>
                    <th style="width:95px;text-align:right;">{{ __('network.col_earning_mtd') }}</th>
                    <th style="width:60px;text-align:center;">{{ __('network.col_status') }}</th>
                    <th style="width:75px;">{{ __('network.col_joined') }}</th>
                </tr></thead>
                <tbody>
                @foreach($introducers as $intro)
                @php $rowNum=$loop->iteration; @endphp
                <tr onclick="window.location='{{ route('admin.network.introducer', [$gl->agent_id, $tl->agent_id, $intro->agent_id]) }}?month={{ $selMonth }}&year={{ $selYear }}'" onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''">
                    <td style="text-align:center;color:#718096;">{{ $rowNum }}</td>
                    <td style="font-weight:600;color:#0D5A8E;">{{ $intro->full_name }} →</td>
                    <td style="color:#9ca3af;">{{ $intro->agent_code }}</td>
                    <td style="text-align:center;">{{ $intro->total_transactions }}</td>
                    <td style="text-align:right;font-weight:700;">{{ number_format($intro->total_premium,2) }}</td>
                    <td style="text-align:right;font-weight:700;color:#38A169;">{{ number_format($intro->total_commission,2) }}</td>
                    <td style="text-align:center;"><span style="padding:1px 6px;border-radius:20px;font-size:9px;font-weight:600;background:{{ $intro->status==='ACTIVE'?'#C8E6C9':'#FFCDD2' }};color:{{ $intro->status==='ACTIVE'?'#1B5E20':'#B71C1C' }};">{{ __('network.'.strtolower($intro->status)) }}</span></td>
                    <td style="color:#9ca3af;">{{ \Carbon\Carbon::parse($intro->created_at)->format('d M Y') }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            {{-- FIXED 2 Aug 2026 — per Chris's standing rule: Prev/Next
                 navigation only, no numbered "jump to page" links. --}}
            @if($introducers->hasPages())
            <div style="margin-top:6px;flex-shrink:0;display:flex;align-items:center;justify-content:space-between;">
                @if($introducers->onFirstPage())
                    <span class="fbtn" style="background:#f3f4f6;color:#9ca3af;cursor:default;">{{ __('network.prev') }}</span>
                @else
                    <a href="{{ $introducers->appends(request()->query())->previousPageUrl() }}" class="fbtn" style="background:#1565C0;color:#fff;text-decoration:none;display:inline-flex;align-items:center;">{{ __('network.prev') }}</a>
                @endif
                <span style="font-size:10px;color:#374151;font-weight:600;">
                    {{ __('network.showing_results_page', ['first' => $introducers->firstItem(), 'lastItem' => $introducers->lastItem(), 'total' => number_format($introducers->total()), 'current' => $introducers->currentPage(), 'lastPage' => $introducers->lastPage()]) }}
                </span>
                @if($introducers->hasMorePages())
                    <a href="{{ $introducers->appends(request()->query())->nextPageUrl() }}" class="fbtn" style="background:#1565C0;color:#fff;text-decoration:none;display:inline-flex;align-items:center;">{{ __('network.next') }}</a>
                @else
                    <span class="fbtn" style="background:#f3f4f6;color:#9ca3af;cursor:default;">{{ __('network.next') }}</span>
                @endif
            </div>
            @endif
        @endif
    </div>

    {{-- FIXED 2 Aug 2026 — per Chris: this button was position:fixed,
         floating outside the flex layout, so on a shorter browser window
         it overlapped the pagination row above it and truncated the
         "Showing X of Y" text. Moved into normal flex flow (flex-shrink:0)
         so the layout always reserves real space for it — guarantees no
         overlap and no page-level scroll on any window height. --}}
    <div style="flex-shrink:0;">
        <a href="{{ route('admin.network.gl', $gl->agent_id) }}" style="display:inline-block;background:#1565C0;color:#fff;border-radius:5px;padding:5px 16px;font-size:11px;font-weight:600;text-decoration:none;box-shadow:0 2px 8px rgba(0,0,0,.2);">{{ __('network.back_to_role_of_name', ['name' => $gl->full_name, 'role' => \App\Services\RoleLabelService::plural('TEAM_LEADER', $tlGroupId)]) }}</a>
    </div>
</div>
<script>
function initTA(inpId,dropId,mode,scope){
    var inp=document.getElementById(inpId),drop=document.getElementById(dropId);
    if(!inp)return;
    var t;
    inp.addEventListener('input',function(){
        clearTimeout(t);var q=this.value.trim();
        if(!q){drop.style.display='none';return;}
        t=setTimeout(function(){
            fetch('{{ route("admin.network.ajax.typeahead") }}?q='+encodeURIComponent(q)+'&mode='+mode+(scope?'&scope='+scope:''))
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
document.addEventListener('DOMContentLoaded',function(){initTA('intro-inp','intro-drop','intro','{{ $tl->agent_id }}');});
</script>
@endsection
