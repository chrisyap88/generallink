@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
{{-- FIXED 1 Aug 2026 — per Chris: this screen is always viewing ONE
     specific GL's own group, so every RoleLabelService call below must
     pass $gl->group_label_id explicitly (same pattern already used on
     the Rank Hierarchy / Rank Allocation screens). Without it, these
     calls fall back to Admin's own (null) group and always show the
     generic system default, no matter which group's GL page you're on. --}}
@php
    $glGroupId = $gl->group_label_id;
@endphp
@section('title', $gl->full_name . ' — ' . \App\Services\RoleLabelService::plural('TEAM_LEADER', $glGroupId))
@section('page-title', $gl->full_name . ' — ' . \App\Services\RoleLabelService::plural('TEAM_LEADER', $glGroupId))
{{-- Page title kept dynamic (name + configurable role label) — no static text to translate here. --}}

@push('styles')
<style>
.net-wrap{padding:5px 8px;display:flex;flex-direction:column;gap:3px;height:calc(100vh - 66px);box-sizing:border-box;overflow:hidden;}
.net-card{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:4px 8px;}
.net-table{width:100%;border-collapse:collapse;font-size:10px;table-layout:fixed;}
.net-table th{padding:2px 5px;text-align:left;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;white-space:nowrap;background:#F7FAFC;position:sticky;top:0;}
.net-table td{padding:1px 5px;border-bottom:1px solid #F7FAFC;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.net-table tr:hover td{background:#EBF5FB;cursor:pointer;}
.fbar{display:flex;align-items:flex-end;gap:4px;flex-wrap:nowrap;}
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
        <a href="{{ route('admin.network') }}" style="color:#1B9AE4;text-decoration:none;">{{ __('network.all_role_plain', ['role' => \App\Services\RoleLabelService::shortLabel('GROUP_LEADER', groupLabelId: $glGroupId).'s']) }}</a> ›
        <strong style="color:#0D5A8E;">{{ $gl->full_name }}</strong>
        <span style="margin-left:8px;background:#E3F2FD;padding:1px 8px;border-radius:10px;color:#1565C0;font-size:9px;">{{ \App\Services\RoleLabelService::plural('TEAM_LEADER', $glGroupId) }}</span>
    </div>

    <div class="net-card">
        <form method="GET" action="{{ route('admin.network.gl', $gl->agent_id) }}">
            <div class="fbar">
                <div><button type="submit" name="show_all" value="1" class="fbtn" style="background:#38A169;color:#fff;margin-top:13px;">{{ __('network.show_all_button') }}</button></div>
                <div>
                    <div class="flbl">{{ __('network.month_label') }}</div>
                    <select name="month" class="fsel" style="width:75px;">
                        @foreach(range(1,12) as $m)
                            <option value="{{ $m }}" {{ $selMonth==$m?'selected':'' }}>{{ \Carbon\Carbon::create()->month($m)->format('M') }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <div class="flbl">{{ __('network.year_label') }}</div>
                    <select name="year" class="fsel" style="width:75px;">
                        @foreach(range(now()->year, now()->year - 3) as $y)
                            <option value="{{ $y }}" {{ $selYear==$y?'selected':'' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <div class="flbl">{{ __('network.name_under_label', ['role' => \App\Services\RoleLabelService::shortLabel('TEAM_LEADER', groupLabelId: $glGroupId), 'parent' => $gl->full_name]) }}</div>
                    <div class="ta-wrap">
                        <input type="text" id="tl-inp" name="search" class="finp" value="{{ request('search') }}" placeholder="{{ __('network.type_name_placeholder', ['role' => \App\Services\RoleLabelService::shortLabel('TEAM_LEADER', groupLabelId: $glGroupId)]) }}" style="width:160px;" autocomplete="off">
                        <div id="tl-drop" class="ta-dropdown"></div>
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
                    <a href="{{ route('admin.network.gl', $gl->agent_id) }}" class="fbtn" style="background:#f3f4f6;color:#4A5568;text-decoration:none;display:inline-flex;align-items:center;">{{ __('network.clear') }}</a>
                </div>
            </div>
        </form>
    </div>

    <div class="net-card" style="flex:1;overflow:hidden;display:flex;flex-direction:column;">
        @if($tls->total() === 0 && !request()->hasAny(['show_all','search','status','joined_from','joined_to']))
            <div style="text-align:center;color:#A0AEC0;padding:60px 0;font-size:12px;">{!! __('network.use_filter_prompt_under', ['button' => '<strong>'.__('network.show_all_button').'</strong>', 'role' => \App\Services\RoleLabelService::plural('TEAM_LEADER', $glGroupId), 'parent' => '<strong>'.$gl->full_name.'</strong>']) !!}</div>
        @elseif($tls->isEmpty())
            <div style="text-align:center;color:#A0AEC0;padding:40px;font-size:12px;">{{ __('network.no_role_found', ['role' => \App\Services\RoleLabelService::plural('TEAM_LEADER', $glGroupId)]) }}</div>
        @else
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:10px;color:#718096;">
                <span>{{ __('network.role_found_under', ['count' => $tls->total(), 'role' => \App\Services\RoleLabelService::plural('TEAM_LEADER', $glGroupId), 'parent' => $gl->full_name]) }}</span>
                @if($tls->hasPages())<span>{{ __('network.page_of', ['current' => $tls->currentPage(), 'last' => $tls->lastPage()]) }}</span>@endif
            </div>
            <div style="flex:1;overflow:hidden;min-height:0;">
            <table class="net-table">
                <thead><tr>
                    <th style="width:30px;">{{ __('network.col_no') }}</th>
                    <th style="width:180px;">{{ \App\Services\RoleLabelService::label('TEAM_LEADER', $glGroupId) }}</th>
                    <th style="width:65px;">{{ __('network.col_code') }}</th>
                    <th style="width:55px;text-align:center;">{{ \App\Services\RoleLabelService::shortLabel('INTRODUCER', groupLabelId: $glGroupId) }}s</th>
                    <th style="width:90px;text-align:right;">{{ __('network.col_sales_mtd') }}</th>
                    <th style="width:95px;text-align:right;">{{ __('network.col_earning_mtd') }}</th>
                    <th style="width:60px;text-align:center;">{{ __('network.col_status') }}</th>
                    <th style="width:75px;">{{ __('network.col_joined') }}</th>
                </tr></thead>
                <tbody>
                @foreach($tls as $tl)
                @php $rowNum=$loop->iteration; @endphp
                <tr onclick="window.location='{{ route('admin.network.tl', [$gl->agent_id, $tl->agent_id]) }}?month={{ $selMonth }}&year={{ $selYear }}'" onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''">
                    <td style="text-align:center;color:#718096;">{{ $rowNum }}</td>
                    <td style="font-weight:600;color:#0D5A8E;">{{ $tl->full_name }} →</td>
                    <td style="color:#9ca3af;">{{ $tl->agent_code }}</td>
                    <td style="text-align:center;">{{ $tl->total_intro }}</td>
                    <td style="text-align:right;font-weight:700;">{{ number_format((float)($tl->total_premium ?? 0),2) }}</td>
                    <td style="text-align:right;font-weight:700;color:#38A169;">{{ number_format((float)($tl->total_commission ?? 0),2) }}</td>
                    <td style="text-align:center;"><span style="padding:1px 6px;border-radius:20px;font-size:9px;font-weight:600;background:{{ $tl->status==='ACTIVE'?'#C8E6C9':'#FFCDD2' }};color:{{ $tl->status==='ACTIVE'?'#1B5E20':'#B71C1C' }};">{{ __('network.'.strtolower($tl->status)) }}</span></td>
                    <td style="color:#9ca3af;">{{ $tl->created_at ? \Carbon\Carbon::parse($tl->created_at)->format('d M Y') : '—' }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            {{-- FIXED 2 Aug 2026 — per Chris's standing rule: Prev/Next
                 navigation only, no numbered "jump to page" links. --}}
            @if($tls->hasPages())
            <div style="margin-top:4px;flex-shrink:0;display:flex;align-items:center;justify-content:space-between;">
                @if($tls->onFirstPage())
                    <span class="fbtn" style="background:#f3f4f6;color:#9ca3af;cursor:default;">{{ __('network.prev') }}</span>
                @else
                    <a href="{{ $tls->appends(request()->query())->previousPageUrl() }}" class="fbtn" style="background:#1565C0;color:#fff;text-decoration:none;display:inline-flex;align-items:center;">{{ __('network.prev') }}</a>
                @endif
                <span style="font-size:10px;color:#374151;font-weight:600;">
                    {{ __('network.showing_results_page', ['first' => $tls->firstItem(), 'lastItem' => $tls->lastItem(), 'total' => number_format($tls->total()), 'current' => $tls->currentPage(), 'lastPage' => $tls->lastPage()]) }}
                </span>
                @if($tls->hasMorePages())
                    <a href="{{ $tls->appends(request()->query())->nextPageUrl() }}" class="fbtn" style="background:#1565C0;color:#fff;text-decoration:none;display:inline-flex;align-items:center;">{{ __('network.next') }}</a>
                @else
                    <span class="fbtn" style="background:#f3f4f6;color:#9ca3af;cursor:default;">{{ __('network.next') }}</span>
                @endif
            </div>
            @endif
        @endif
    </div>

    {{-- FIXED 2 Aug 2026 — moved out of position:fixed (was floating
         outside the flex layout and could overlap the pagination row on a
         short window). Now a normal flex-shrink:0 child so real space is
         always reserved for it — no overlap, no page-level scroll. --}}
    <div style="flex-shrink:0;">
        <a href="{{ route('admin.network') }}" style="display:inline-block;background:#1565C0;color:#fff;border-radius:5px;padding:5px 16px;font-size:11px;font-weight:600;text-decoration:none;box-shadow:0 2px 8px rgba(0,0,0,.2);">{{ __('network.back_to_role', ['role' => \App\Services\RoleLabelService::shortLabel('GROUP_LEADER', groupLabelId: $glGroupId).'s']) }}</a>
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
document.addEventListener('DOMContentLoaded',function(){initTA('tl-inp','tl-drop','tl','{{ $gl->agent_id }}');});
</script>
@endsection
