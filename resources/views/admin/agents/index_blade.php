@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('title','Agent Network Tree')
@section('page-title','Agent Network Tree')

@section('content')
<div style="height:calc(100vh - 58px); overflow:hidden; padding:6px 10px; display:flex; flex-direction:column; gap:6px;">

@if(session('success'))
<div style="background:#C6F6D5;border:1px solid #9AE6B4;border-radius:6px;padding:5px 12px;color:#22543D;font-size:11px;font-weight:500;flex-shrink:0;">✓ {{ session('success') }}</div>
@endif

{{-- Filter Bar --}}
<div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:8px 12px;flex-shrink:0;">

    {{-- Row 1: Role + Search By --}}
    <div style="display:flex;gap:10px;align-items:flex-end;margin-bottom:6px;">
        <div style="display:flex;flex-direction:column;gap:2px;">
            <label style="font-size:10px;font-weight:600;color:#4A5568;">Role <span style="color:#dc2626;">*</span></label>
            <select id="f_role" onchange="onRoleChange()"
                style="padding:4px 8px;border:1.5px solid #b2ebf2;border-radius:5px;font-size:11px;background:#fff;outline:none;cursor:pointer;height:28px;width:130px;">
                <option value="">— Select Role —</option>
                <option value="GROUP_LEADER" {{ request('f_role')==='GROUP_LEADER'?'selected':'' }}>Group Leader</option>
                <option value="TEAM_LEADER"  {{ request('f_role')==='TEAM_LEADER' ?'selected':'' }}>Team Leader</option>
                <option value="INTRODUCER"   {{ request('f_role')==='INTRODUCER'  ?'selected':'' }}>Introducer</option>
            </select>
        </div>

        <div id="wrap_mode" style="display:none;flex-direction:column;gap:2px;">
            <label style="font-size:10px;font-weight:600;color:#4A5568;">Search By</label>
            <div style="display:flex;gap:8px;align-items:center;height:28px;">
                <label style="display:flex;align-items:center;gap:3px;font-size:11px;cursor:pointer;white-space:nowrap;"><input type="radio" name="f_mode" value="all" onchange="onModeChange()" checked> All</label>
                <label style="display:flex;align-items:center;gap:3px;font-size:11px;cursor:pointer;white-space:nowrap;"><input type="radio" name="f_mode" value="group" onchange="onModeChange()"> By Group</label>
                <label id="mode_tl_label" style="display:none;align-items:center;gap:3px;font-size:11px;cursor:pointer;white-space:nowrap;"><input type="radio" name="f_mode" value="tl" onchange="onModeChange()"> By TL</label>
                <label style="display:flex;align-items:center;gap:3px;font-size:11px;cursor:pointer;white-space:nowrap;"><input type="radio" name="f_mode" value="individual" onchange="onModeChange()"> Individual</label>
            </div>
        </div>
    </div>

    {{-- Row 2: Typeahead (only when needed) --}}
    <div id="wrap_search" style="display:none;margin-bottom:6px;">
        <label id="search_label" style="font-size:10px;font-weight:600;color:#4A5568;display:block;margin-bottom:2px;">Search</label>
        <div style="position:relative;">
            <div style="display:flex;align-items:center;border:1.5px solid #b2ebf2;border-radius:5px;background:#fff;height:28px;overflow:hidden;">
                <div id="f_selected_wrap" style="display:flex;flex-wrap:nowrap;gap:3px;padding:0 6px;overflow-x:auto;flex-shrink:0;max-width:400px;"></div>
                <input type="text" id="f_search_input" placeholder="Type to search..." oninput="onSearchInput()"
                    style="flex:1;padding:0 8px;border:none;outline:none;font-size:11px;background:transparent;height:26px;">
            </div>
            <div id="f_search_dd" style="display:none;position:absolute;top:100%;left:0;width:300px;background:#fff;border:1px solid #b2ebf2;border-radius:5px;box-shadow:0 4px 16px rgba(0,0,0,.12);z-index:9999;max-height:200px;overflow-y:auto;margin-top:2px;"></div>
        </div>
    </div>

    {{-- Row 3: Status + From + To + Buttons --}}
    <div style="display:flex;gap:8px;align-items:flex-end;">
        <div style="display:flex;flex-direction:column;gap:2px;">
            <label style="font-size:10px;font-weight:600;color:#4A5568;">Status</label>
            <select id="f_status" style="padding:4px 8px;border:1.5px solid #b2ebf2;border-radius:5px;font-size:11px;background:#fff;outline:none;height:28px;width:110px;">
                <option value="">— All —</option>
                <option value="ACTIVE"     {{ request('f_status')==='ACTIVE'    ?'selected':'' }}>Active</option>
                <option value="INACTIVE"   {{ request('f_status')==='INACTIVE'  ?'selected':'' }}>Inactive</option>
                <option value="TERMINATED" {{ request('f_status')==='TERMINATED'?'selected':'' }}>Terminated</option>
                <option value="RESIGNED"   {{ request('f_status')==='RESIGNED'  ?'selected':'' }}>Resigned</option>
                <option value="DECEASED"   {{ request('f_status')==='DECEASED'  ?'selected':'' }}>Deceased</option>
            </select>
        </div>
        <div style="display:flex;flex-direction:column;gap:2px;">
            <label style="font-size:10px;font-weight:600;color:#4A5568;">Joined From</label>
            <input type="date" id="f_from" value="{{ request('f_from') }}"
                style="padding:4px 8px;border:1.5px solid #b2ebf2;border-radius:5px;font-size:11px;outline:none;height:28px;width:130px;">
        </div>
        <div style="display:flex;flex-direction:column;gap:2px;">
            <label style="font-size:10px;font-weight:600;color:#4A5568;">Joined To</label>
            <input type="date" id="f_to" value="{{ request('f_to') }}"
                style="padding:4px 8px;border:1.5px solid #b2ebf2;border-radius:5px;font-size:11px;outline:none;height:28px;width:130px;">
        </div>
        <button id="btnSearch" onclick="doSearch()" disabled
            style="background:#9ca3af;color:#fff;border:none;border-radius:5px;padding:0 16px;font-size:11px;font-weight:600;cursor:not-allowed;height:28px;white-space:nowrap;">
            🔍 Search
        </button>
        <button onclick="resetFilter()"
            style="background:#f3f4f6;color:#374151;border:1px solid #E2E8F0;border-radius:5px;padding:0 12px;font-size:11px;font-weight:600;cursor:pointer;height:28px;white-space:nowrap;">
            Clear
        </button>
    </div>

    <input type="hidden" id="f_selected_ids" value="{{ request('f_selected_ids') }}">
</div>

{{-- Results --}}
@if(isset($agents) && $agents !== null)
<div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);flex:1;min-height:0;display:flex;flex-direction:column;overflow:hidden;">
    <div style="flex-shrink:0;overflow:hidden;border-bottom:2px solid #E2E8F0;">
        <table style="width:100%;border-collapse:collapse;font-size:10px;">
            <thead>
                <tr style="background:#F7FAFC;">
                    <th style="padding:2px 6px;width:24px;"></th>
                    <th style="padding:2px 6px;text-align:left;color:#0D5A8E;font-weight:700;white-space:nowrap;">Name</th>
                    <th style="padding:2px 6px;text-align:left;color:#0D5A8E;font-weight:700;white-space:nowrap;">Member Code</th>
                    <th style="padding:2px 6px;text-align:center;color:#0D5A8E;font-weight:700;white-space:nowrap;">Role</th>
                    <th style="padding:2px 6px;text-align:right;color:#16a34a;font-weight:700;white-space:nowrap;">TLs</th>
                    <th style="padding:2px 6px;text-align:right;color:#7C3AED;font-weight:700;white-space:nowrap;">Introducers</th>
                    <th style="padding:2px 6px;text-align:left;color:#0D5A8E;font-weight:700;white-space:nowrap;">Email</th>
                    <th style="padding:2px 6px;text-align:left;color:#0D5A8E;font-weight:700;white-space:nowrap;">Phone</th>
                    <th style="padding:2px 6px;text-align:center;color:#0D5A8E;font-weight:700;white-space:nowrap;">Status</th>
                    <th style="padding:2px 6px;text-align:left;color:#0D5A8E;font-weight:700;white-space:nowrap;">Joined</th>
                    <th style="padding:2px 6px;text-align:center;color:#0D5A8E;font-weight:700;white-space:nowrap;">Action</th>
                </tr>
            </thead>
        </table>
    </div>
    <div style="flex:1;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:10px;" id="treeTable">
            <tbody id="treeRows">
                @foreach($agents as $a)
                @include('agents.partials.tree-row', ['agent'=>$a, 'depth'=>0])
                @endforeach
            </tbody>
        </table>
        @if($agents->hasPages())
        <div style="padding:5px 10px;border-top:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;font-size:11px;">
            <div style="color:#718096;">Showing {{ $agents->firstItem() }}–{{ $agents->lastItem() }} of {{ $agents->total() }} records</div>
            <div style="display:flex;gap:4px;">
                @if($agents->onFirstPage())
                    <span style="background:#f3f4f6;color:#9ca3af;border-radius:5px;padding:3px 10px;font-size:11px;font-weight:600;">← Prev</span>
                @else
                    <a href="{{ $agents->previousPageUrl() }}" style="background:#0D5A8E;color:#fff;text-decoration:none;border-radius:5px;padding:3px 10px;font-size:11px;font-weight:600;">← Prev</a>
                @endif
                <span style="font-size:11px;color:#374151;font-weight:600;padding:3px 4px;">Page {{ $agents->currentPage() }} / {{ $agents->lastPage() }}</span>
                @if($agents->hasMorePages())
                    <a href="{{ $agents->nextPageUrl() }}" style="background:#0D5A8E;color:#fff;text-decoration:none;border-radius:5px;padding:3px 10px;font-size:11px;font-weight:600;">Next →</a>
                @else
                    <span style="background:#f3f4f6;color:#9ca3af;border-radius:5px;padding:3px 10px;font-size:11px;font-weight:600;">Next →</span>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
@else
<div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);flex:1;display:flex;align-items:center;justify-content:center;">
    <div style="text-align:center;color:#9ca3af;">
        <div style="font-size:36px;margin-bottom:10px;">🌳</div>
        <div style="font-size:13px;font-weight:600;color:#374151;">Network Tree</div>
        <div style="font-size:11px;margin-top:4px;">Select a Role and click Search to view.</div>
    </div>
</div>
@endif

</div>

@push('scripts')
<script>
var selectedItems={},searchTimer=null,currentRole='{{ request("f_role") }}',currentMode='all';

window.addEventListener('DOMContentLoaded',function(){
    if(currentRole){document.getElementById('f_role').value=currentRole;onRoleChange(true);}
});

function onRoleChange(silent){
    var role=document.getElementById('f_role').value;currentRole=role;
    selectedItems={};renderSelected();
    document.getElementById('f_search_dd').style.display='none';
    document.getElementById('f_search_input').value='';
    document.getElementById('f_selected_ids').value='';
    document.getElementById('wrap_search').style.display='none';
    if(!role){document.getElementById('wrap_mode').style.display='none';disableSearch();return;}
    document.getElementById('wrap_mode').style.display='flex';
    document.getElementById('mode_tl_label').style.display=role==='INTRODUCER'?'flex':'none';
    document.querySelector('input[name="f_mode"][value="all"]').checked=true;
    currentMode='all';enableSearch();
}

function onModeChange(){
    currentMode=document.querySelector('input[name="f_mode"]:checked').value;
    var labels={group:'Search Group',tl:'Search Team Leader',individual:'Search Agent'};
    selectedItems={};renderSelected();
    document.getElementById('f_search_input').value='';
    document.getElementById('f_selected_ids').value='';
    document.getElementById('f_search_dd').style.display='none';
    if(currentMode==='all'){document.getElementById('wrap_search').style.display='none';enableSearch();}
    else{document.getElementById('search_label').textContent=labels[currentMode]||'Search';document.getElementById('wrap_search').style.display='block';disableSearch();}
}

function onSearchInput(){
    clearTimeout(searchTimer);
    var q=document.getElementById('f_search_input').value.trim();
    var dd=document.getElementById('f_search_dd');
    if(!q){dd.style.display='none';return;}
    searchTimer=setTimeout(function(){
        fetch('/admin/network/ajax/typeahead?q='+encodeURIComponent(q)+'&mode='+currentMode+'&role='+currentRole)
            .then(r=>r.json()).then(data=>{
                dd.innerHTML='';
                if(!data||!data.length){dd.innerHTML='<div style="padding:8px;font-size:11px;color:#9ca3af;">No results</div>';dd.style.display='block';return;}
                data.forEach(function(item){
                    var div=document.createElement('div');
                    div.style.cssText='padding:6px 10px;cursor:pointer;font-size:11px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:6px;';
                    div.onmouseover=function(){this.style.background='#f0f9ff';};
                    div.onmouseout=function(){this.style.background='';};
                    var checked=selectedItems[item.id]?'checked':'';
                    div.innerHTML='<input type="checkbox" '+checked+' style="cursor:pointer;flex-shrink:0;"><div><div style="font-weight:600;color:#1a202c;">'+escHtml(item.name)+'</div><div style="font-size:10px;color:#718096;">'+escHtml(item.code)+(item.extra?' · '+escHtml(item.extra):'')+'</div></div>';
                    div.onmousedown=function(e){
                        e.preventDefault();
                        var cb=this.querySelector('input[type=checkbox]');
                        if(selectedItems[item.id]){delete selectedItems[item.id];cb.checked=false;}
                        else{selectedItems[item.id]=item.name+' ('+item.code+')';cb.checked=true;}
                        renderSelected();
                        if(Object.keys(selectedItems).length>0)enableSearch();else disableSearch();
                    };
                    dd.appendChild(div);
                });
                if(data.length===20){var m=document.createElement('div');m.style.cssText='padding:4px 10px;font-size:10px;color:#9ca3af;background:#f9fafb;';m.textContent='Showing first 20 — type more to narrow';dd.appendChild(m);}
                dd.style.display='block';
            });
    },200);
}

function renderSelected(){
    var wrap=document.getElementById('f_selected_wrap');
    var inp=document.getElementById('f_selected_ids');
    wrap.innerHTML='';
    Object.keys(selectedItems).forEach(function(id){
        var tag=document.createElement('div');
        tag.style.cssText='background:#e0f2fe;color:#1565C0;border-radius:20px;padding:1px 8px;font-size:10px;font-weight:600;display:flex;align-items:center;gap:2px;cursor:pointer;white-space:nowrap;flex-shrink:0;';
        tag.innerHTML=escHtml(selectedItems[id])+' <span>×</span>';
        tag.onclick=function(){delete selectedItems[id];renderSelected();if(Object.keys(selectedItems).length===0&&currentMode!=='all')disableSearch();};
        wrap.appendChild(tag);
    });
    inp.value=Object.keys(selectedItems).join(',');
}

function enableSearch(){var b=document.getElementById('btnSearch');b.disabled=false;b.style.background='#0D5A8E';b.style.cursor='pointer';}
function disableSearch(){var b=document.getElementById('btnSearch');b.disabled=true;b.style.background='#9ca3af';b.style.cursor='not-allowed';}

function doSearch(){
    var role=document.getElementById('f_role').value;
    var mode=document.querySelector('input[name="f_mode"]:checked')?document.querySelector('input[name="f_mode"]:checked').value:'all';
    var ids=document.getElementById('f_selected_ids').value;
    var status=document.getElementById('f_status').value;
    var from=document.getElementById('f_from').value;
    var to=document.getElementById('f_to').value;
    if(!role)return;
    var url='{{ route("admin.agents.index") }}?f_role='+encodeURIComponent(role);
    if(mode)url+='&f_mode='+encodeURIComponent(mode);
    if(ids)url+='&f_ids='+encodeURIComponent(ids);
    if(status)url+='&f_status='+encodeURIComponent(status);
    if(from)url+='&f_from='+encodeURIComponent(from);
    if(to)url+='&f_to='+encodeURIComponent(to);
    window.location=url;
}

function resetFilter(){window.location='{{ route("admin.agents.index") }}';}

document.addEventListener('click',function(e){
    var dd=document.getElementById('f_search_dd');
    var input=document.getElementById('f_search_input');
    if(dd&&input&&!dd.contains(e.target)&&e.target!==input)dd.style.display='none';
});

function toggleNode(agentId,btn){
    var childRow=document.getElementById('children_'+agentId);
    if(childRow){var isHidden=childRow.style.display==='none';childRow.style.display=isHidden?'':'none';btn.textContent=isHidden?'▼':'▶';return;}
    btn.textContent='⏳';btn.disabled=true;
    fetch('/admin/network/ajax/children?agent_id='+agentId)
        .then(r=>r.json()).then(data=>{
            btn.disabled=false;
            if(!data||!data.agents||!data.agents.length){btn.textContent='•';btn.disabled=true;return;}
            btn.textContent='▼';
            var currentRow=document.getElementById('row_'+agentId);
            var depth=parseInt(currentRow.getAttribute('data-depth'))+1;
            var wrapperRow=document.createElement('tr');wrapperRow.id='children_'+agentId;
            var wrapperCell=document.createElement('td');wrapperCell.colSpan=11;wrapperCell.style.padding='0';
            var childTable=document.createElement('table');childTable.style.cssText='width:100%;border-collapse:collapse;';
            var tbody=document.createElement('tbody');
            data.agents.forEach(function(agent){tbody.appendChild(buildRow(agent,depth));});
            childTable.appendChild(tbody);
            if(data.pagination){var pagDiv=document.createElement('div');pagDiv.style.cssText='padding:4px 10px;border-top:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;font-size:10px;background:#fafafa;';pagDiv.innerHTML=buildPagination(data.pagination,agentId);childTable.appendChild(pagDiv);}
            wrapperCell.appendChild(childTable);wrapperRow.appendChild(wrapperCell);
            currentRow.insertAdjacentElement('afterend',wrapperRow);
        }).catch(function(){btn.textContent='▶';btn.disabled=false;});
}

function buildRow(agent,depth){
    var tr=document.createElement('tr');tr.id='row_'+agent.agent_id;tr.setAttribute('data-depth',depth);tr.style.cssText='border-bottom:1px solid #F7FAFC;';
    tr.onmouseover=function(){this.style.background='#EBF5FB';};tr.onmouseout=function(){this.style.background='';};
    var indent=depth*20;
    var rc={'GROUP_LEADER':{bg:'#C8E6C9',color:'#1B5E20',label:'GL'},'TEAM_LEADER':{bg:'#BBDEFB',color:'#1565C0',label:'TL'},'INTRODUCER':{bg:'#E9D5FF',color:'#4C1D95',label:'I'}}[agent.role]||{bg:'#f3f4f6',color:'#374151',label:'?'};
    var sc={'ACTIVE':{bg:'#C8E6C9',color:'#1B5E20'},'INACTIVE':{bg:'#FFF9C4',color:'#F57F17'},'TERMINATED':{bg:'#FFCDD2',color:'#B71C1C'},'RESIGNED':{bg:'#FFCDD2',color:'#B71C1C'},'DECEASED':{bg:'#FFCDD2',color:'#B71C1C'}}[agent.status]||{bg:'#f3f4f6',color:'#374151'};
    
    // Build drill down URL based on role
    var drillUrl = '';
    if(agent.role === 'GROUP_LEADER') drillUrl = '/admin/network/' + agent.agent_id;
    else if(agent.role === 'TEAM_LEADER' && agent.gl_id) drillUrl = '/admin/network/' + agent.gl_id + '/' + agent.agent_id;
    else if(agent.role === 'INTRODUCER' && agent.gl_id && agent.tl_id) drillUrl = '/admin/network/' + agent.gl_id + '/' + agent.tl_id + '/' + agent.agent_id;
    
    if(drillUrl) tr.style.cursor = 'pointer';
    if(drillUrl) tr.onclick = function(e){ if(!e.target.closest('button') && !e.target.closest('a')) window.location = drillUrl; };
    tr.innerHTML=
        '<td style="padding:5px 8px;width:26px;"><div style="padding-left:'+indent+'px;">'+(agent.has_children?'<button onclick="toggleNode(\''+agent.agent_id+'\',this)" style="background:none;border:1px solid #b2ebf2;border-radius:3px;cursor:pointer;width:16px;height:16px;font-size:9px;color:#0D5A8E;display:inline-flex;align-items:center;justify-content:center;">▶</button>':'<span style="display:inline-block;width:16px;text-align:center;color:#d1d5db;font-size:10px;">•</span>')+'</div></td>'+
        '<td style="padding:5px 8px;font-weight:600;white-space:nowrap;"><div style="padding-left:'+indent+'px;">'+escHtml(agent.full_name)+'</div></td>'+
        '<td style="padding:5px 8px;font-family:monospace;font-size:10px;color:#0D5A8E;font-weight:600;white-space:nowrap;">'+escHtml(agent.member_code||'—')+'</td>'+
        '<td style="padding:5px 8px;text-align:center;"><span style="background:'+rc.bg+';color:'+rc.color+';padding:1px 5px;border-radius:3px;font-size:10px;font-weight:700;">'+rc.label+'</span></td>'+
        '<td style="padding:5px 8px;text-align:right;font-weight:600;color:#16a34a;">'+(agent.total_tl!==undefined?agent.total_tl:'—')+'</td>'+
        '<td style="padding:5px 8px;text-align:right;font-weight:600;color:#7C3AED;">'+(agent.total_intro!==undefined?agent.total_intro:'—')+'</td>'+
        '<td style="padding:5px 8px;color:#718096;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'+escHtml(agent.email||'—')+'</td>'+
        '<td style="padding:5px 8px;color:#718096;white-space:nowrap;">'+escHtml(agent.phone||'—')+'</td>'+
        '<td style="padding:5px 8px;text-align:center;"><span style="background:'+sc.bg+';color:'+sc.color+';padding:1px 5px;border-radius:20px;font-size:10px;font-weight:600;">'+escHtml(agent.status)+'</span></td>'+
        '<td style="padding:5px 8px;color:#718096;white-space:nowrap;font-size:10px;">'+escHtml(agent.joined||'—')+'</td>'+
        '<td style="padding:5px 8px;text-align:center;white-space:nowrap;"><a href="/admin/agents/'+agent.agent_id+'" style="background:#EBF8FF;border:1px solid #BEE3F8;color:#2B6CB0;padding:2px 6px;border-radius:4px;font-size:10px;text-decoration:none;font-weight:600;">👁</a> <a href="/admin/network/agent/'+agent.agent_id+'/edit?back='+encodeURIComponent(window.location.href)+'" style="background:#e0f2fe;color:#1565C0;padding:2px 6px;border-radius:4px;font-size:10px;text-decoration:none;font-weight:600;">✏️</a></td>';
    return tr;
}

function buildPagination(pag,parentId){
    var html='<span style="color:#718096;">'+pag.from+'–'+pag.to+' of '+pag.total+'</span><div style="display:flex;gap:3px;">';
    html+=pag.current_page>1?'<a href="#" onclick="loadPage(\''+parentId+'\','+(pag.current_page-1)+');return false;" style="background:#0D5A8E;color:#fff;text-decoration:none;border-radius:3px;padding:2px 7px;font-size:10px;font-weight:600;">← Prev</a>':'<span style="background:#f3f4f6;color:#9ca3af;border-radius:3px;padding:2px 7px;font-size:10px;">← Prev</span>';
    html+='<span style="font-size:10px;color:#374151;padding:2px 3px;">'+pag.current_page+'/'+pag.last_page+'</span>';
    html+=pag.current_page<pag.last_page?'<a href="#" onclick="loadPage(\''+parentId+'\','+(pag.current_page+1)+');return false;" style="background:#0D5A8E;color:#fff;text-decoration:none;border-radius:3px;padding:2px 7px;font-size:10px;font-weight:600;">Next →</a>':'<span style="background:#f3f4f6;color:#9ca3af;border-radius:3px;padding:2px 7px;font-size:10px;">Next →</span>';
    return html+'</div>';
}

function loadPage(parentId,page){
    var childRow=document.getElementById('children_'+parentId);if(childRow)childRow.remove();
    fetch('/admin/network/ajax/children?agent_id='+parentId+'&page='+page).then(r=>r.json()).then(data=>{
        var currentRow=document.getElementById('row_'+parentId);
        var depth=parseInt(currentRow.getAttribute('data-depth'))+1;
        var wrapperRow=document.createElement('tr');wrapperRow.id='children_'+parentId;
        var wrapperCell=document.createElement('td');wrapperCell.colSpan=11;wrapperCell.style.padding='0';
        var childTable=document.createElement('table');childTable.style.cssText='width:100%;border-collapse:collapse;';
        var tbody=document.createElement('tbody');
        data.agents.forEach(function(agent){tbody.appendChild(buildRow(agent,depth));});
        childTable.appendChild(tbody);
        if(data.pagination){var pagDiv=document.createElement('div');pagDiv.style.cssText='padding:4px 10px;border-top:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;font-size:10px;background:#fafafa;';pagDiv.innerHTML=buildPagination(data.pagination,parentId);childTable.appendChild(pagDiv);}
        wrapperCell.appendChild(childTable);wrapperRow.appendChild(wrapperCell);
        currentRow.insertAdjacentElement('afterend',wrapperRow);
    });
}

function escHtml(str){if(!str)return'';return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
</script>
@endpush
@endsection
