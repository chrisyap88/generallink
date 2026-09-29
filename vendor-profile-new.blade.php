@extends('layouts.dashboard')

@section('page-title', 'Master File — Vendors')

@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; flex-shrink:0;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br> @endforeach</div>
    @endif

    {{-- VENDOR SECTION --}}
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0;">
        {{-- Tabs --}}
        <div style="display:flex; border-bottom:2px solid #e0f2fe; background:#f8fafc; border-radius:10px 10px 0 0;">
            <a href="{{ route('admin.vendors.index', ['mode'=>'add']) }}" style="padding:9px 20px; font-size:12px; font-weight:600; text-decoration:none; border-bottom:3px solid {{ $mode==='add' ? '#1565C0' : 'transparent' }}; color:{{ $mode==='add' ? '#1565C0' : '#6b7280' }};">➕ Add Vendor</a>
            <a href="{{ route('admin.vendors.index', ['mode'=>'edit']) }}" style="padding:9px 20px; font-size:12px; font-weight:600; text-decoration:none; border-bottom:3px solid {{ $mode==='edit' ? '#1565C0' : 'transparent' }}; color:{{ $mode==='edit' ? '#1565C0' : '#6b7280' }};">✏️ Search to Edit</a>
            <a href="{{ route('admin.vendors.index', ['mode'=>'search']) }}" style="padding:9px 20px; font-size:12px; font-weight:600; text-decoration:none; border-bottom:3px solid {{ $mode==='search' ? '#1565C0' : 'transparent' }}; color:{{ $mode==='search' ? '#1565C0' : '#6b7280' }};">🔍 Search All</a>
        </div>
        <div style="padding:12px;">
            @if($mode === 'add')
                <form method="POST" action="{{ route('admin.vendors.store') }}" autocomplete="off">
                    @csrf
                    @include('masterfile.partials.vendor-form', ['vendor'=>null, 'states'=>$states, 'submitLabel'=>'➕ Add Vendor', 'showStatus'=>false])
                </form>
            @elseif($mode === 'edit')
                @if($selectedVendor)
                    <form method="POST" action="{{ route('admin.vendors.update', $selectedVendor->vendor_id) }}" autocomplete="off">
                        @csrf @method('PUT')
                        @include('masterfile.partials.vendor-form', ['vendor'=>$selectedVendor, 'states'=>$states, 'submitLabel'=>'💾 Update Vendor', 'showStatus'=>true])
                    </form>
                @else
                    <form method="GET" action="{{ route('admin.vendors.index') }}" autocomplete="off">
                        <input type="hidden" name="mode" value="edit">
                        @include('masterfile.partials.vendor-form', ['vendor'=>null, 'states'=>$states, 'submitLabel'=>'🔍 Search to Edit', 'showStatus'=>false])
                    </form>
                    @if($vendors !== null && count($vendors) > 0)
                    <div style="margin-top:10px; background:#f0f9ff; border-radius:8px; padding:10px; border:1px solid #e0f2fe;">
                        <div style="font-size:11px; font-weight:600; color:#1565C0; margin-bottom:8px;">{{ count($vendors) }} vendor(s) found — click to edit:</div>
                        @foreach($vendors as $v)
                        <a href="{{ route('admin.vendors.index', ['mode'=>'edit', 'vendor_id'=>$v->vendor_id]) }}" style="display:flex; justify-content:space-between; padding:6px 10px; background:#fff; border-radius:6px; margin-bottom:4px; text-decoration:none; border:1px solid #e0f2fe;">
                            <span style="font-size:11px; font-weight:600; color:#111827;">{{ $v->vendor_name }}</span>
                            <span style="font-size:10px; color:#6b7280;">{{ $v->vendor_code }} — {{ $v->vendor_city ?? '' }} {{ $v->vendor_state ?? '' }}</span>
                        </a>
                        @endforeach
                    </div>
                    @endif
                @endif
            @elseif($mode === 'search')
                <form method="GET" action="{{ route('admin.vendors.index') }}" autocomplete="off">
                    <input type="hidden" name="mode" value="search">
                    @include('masterfile.partials.vendor-form', ['vendor'=>null, 'states'=>$states, 'submitLabel'=>'🔍 Search All', 'showStatus'=>false])
                </form>
            @endif
        </div>
    </div>

    {{-- Search Results --}}
    @if($mode === 'search' && $vendors !== null)
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        <div style="padding:8px 12px; border-bottom:1px solid #e0f2fe; font-size:11px; font-weight:700; color:#1565C0; flex-shrink:0;">
            Search Results — <strong>{{ $vendors->total() }}</strong> record(s) found
        </div>
        <div style="flex:1; overflow-y:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:11px;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:2px solid #e0f2fe;">
                        <th style="text-align:left; padding:7px 10px; font-weight:600; color:#374151; white-space:nowrap;">Vendor Name</th>
                        <th style="text-align:left; padding:7px 10px; font-weight:600; color:#374151; white-space:nowrap;">Code</th>
                        <th style="text-align:left; padding:7px 10px; font-weight:600; color:#374151; white-space:nowrap;">Phone</th>
                        <th style="text-align:left; padding:7px 10px; font-weight:600; color:#374151; white-space:nowrap;">Email</th>
                        <th style="text-align:left; padding:7px 10px; font-weight:600; color:#374151; white-space:nowrap;">Postcode</th>
                        <th style="text-align:left; padding:7px 10px; font-weight:600; color:#374151; white-space:nowrap;">City</th>
                        <th style="text-align:left; padding:7px 10px; font-weight:600; color:#374151; white-space:nowrap;">State</th>
                        <th style="text-align:left; padding:7px 10px; font-weight:600; color:#374151; white-space:nowrap;">PIC Name</th>
                        <th style="text-align:left; padding:7px 10px; font-weight:600; color:#374151; white-space:nowrap;">PIC Phone</th>
                        <th style="text-align:center; padding:7px 10px; font-weight:600; color:#374151; white-space:nowrap;">Status</th>
                        <th style="text-align:center; padding:7px 10px; font-weight:600; color:#374151; white-space:nowrap;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($vendors as $v)
                    <tr style="border-bottom:1px solid #f3f4f6; {{ $loop->even ? 'background:#fafafa;' : '' }}">
                        <td style="padding:7px 10px; font-weight:600; color:#111827;">{{ $v->vendor_name }}</td>
                        <td style="padding:7px 10px; font-family:monospace; color:#6b7280;">{{ $v->vendor_code }}</td>
                        <td style="padding:7px 10px;">{{ $v->vendor_office_phone ?? '—' }}</td>
                        <td style="padding:7px 10px;">{{ $v->vendor_email ?? '—' }}</td>
                        <td style="padding:7px 10px;">{{ $v->vendor_postcode ?? '—' }}</td>
                        <td style="padding:7px 10px;">{{ $v->vendor_city ?? '—' }}</td>
                        <td style="padding:7px 10px;">{{ $v->vendor_state ?? '—' }}</td>
                        <td style="padding:7px 10px;">{{ $v->pic_name ?? '—' }}</td>
                        <td style="padding:7px 10px;">{{ $v->pic_phone ?? '—' }}</td>
                        <td style="padding:7px 10px; text-align:center;">
                            <span style="background:{{ $v->is_active ? '#d1fae5' : '#fee2e2' }}; color:{{ $v->is_active ? '#065f46' : '#991b1b' }}; font-size:10px; font-weight:600; padding:2px 8px; border-radius:20px;">{{ $v->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td style="padding:7px 10px; text-align:center;">
                            <a href="{{ route('admin.vendors.index', ['mode'=>'edit', 'vendor_id'=>$v->vendor_id]) }}" style="background:#e0f2fe; color:#1565C0; text-decoration:none; border-radius:6px; padding:3px 10px; font-size:10px; font-weight:600;">✏️ Edit</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{-- Pagination --}}
        <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; border-top:1px solid #f3f4f6; flex-shrink:0;">
            <div style="font-size:11px; color:#6b7280;">
                Showing <strong>{{ $vendors->firstItem() }}</strong>–<strong>{{ $vendors->lastItem() }}</strong> of <strong>{{ $vendors->total() }}</strong> records
            </div>
            <div style="display:flex; gap:6px; align-items:center;">
                @if($vendors->onFirstPage())
                    <span style="background:#f3f4f6; color:#9ca3af; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:600;">← Prev</span>
                @else
                    <a href="{{ $vendors->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:600;">← Prev</a>
                @endif
                <span style="font-size:11px; color:#374151; font-weight:600;">Page {{ $vendors->currentPage() }} / {{ $vendors->lastPage() }}</span>
                @if($vendors->hasMorePages())
                    <a href="{{ $vendors->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:600;">Next →</a>
                @else
                    <span style="background:#f3f4f6; color:#9ca3af; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:600;">Next →</span>
                @endif
            </div>
        </div>
    </div>
    @elseif($mode === 'search' && $vendors !== null && $vendors->total() === 0)
    <div style="background:#fff; border-radius:10px; padding:20px; text-align:center; color:#9ca3af; font-size:12px;">No vendors found matching your search criteria.</div>
    @endif

    {{-- BRANCH SECTION --}}
    @if($selectedVendor)
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        <div style="display:flex; border-bottom:2px solid #e0f2fe; background:#f8fafc; align-items:center; flex-shrink:0; border-radius:10px 10px 0 0;">
            <div style="padding:9px 14px; font-size:12px; font-weight:700; color:#1565C0; border-right:1px solid #e0f2fe; white-space:nowrap;">🏢 Branches — {{ $selectedVendor->vendor_name }}</div>
            <a href="{{ route('admin.vendors.index', ['mode'=>'edit', 'vendor_id'=>$selectedVendor->vendor_id, 'branch_mode'=>'add']) }}" style="padding:9px 16px; font-size:12px; font-weight:600; text-decoration:none; border-bottom:3px solid {{ $branchMode==='add' ? '#1565C0' : 'transparent' }}; color:{{ $branchMode==='add' ? '#1565C0' : '#6b7280' }};">➕ Add Branch</a>
            <a href="{{ route('admin.vendors.index', ['mode'=>'edit', 'vendor_id'=>$selectedVendor->vendor_id, 'branch_mode'=>'edit']) }}" style="padding:9px 16px; font-size:12px; font-weight:600; text-decoration:none; border-bottom:3px solid {{ $branchMode==='edit' ? '#1565C0' : 'transparent' }}; color:{{ $branchMode==='edit' ? '#1565C0' : '#6b7280' }};">✏️ Search to Edit</a>
            <a href="{{ route('admin.vendors.index', ['mode'=>'edit', 'vendor_id'=>$selectedVendor->vendor_id, 'branch_mode'=>'search_all']) }}" style="padding:9px 16px; font-size:12px; font-weight:600; text-decoration:none; border-bottom:3px solid {{ $branchMode==='search_all' ? '#1565C0' : 'transparent' }}; color:{{ $branchMode==='search_all' ? '#1565C0' : '#6b7280' }};">🔍 Search All</a>
        </div>
        <div style="flex:1; overflow-y:auto; padding:12px;">
            @if($branchMode === 'add')
                <form method="POST" action="{{ route('admin.vendors.branches.store', $selectedVendor->vendor_id) }}" autocomplete="off">
                    @csrf
                    <input type="hidden" name="vendor_id" value="{{ $selectedVendor->vendor_id }}">
                    @include('masterfile.partials.branch-form', ['branch'=>null, 'states'=>$states, 'submitLabel'=>'➕ Add Branch', 'showStatus'=>false])
                </form>
            @elseif($branchMode === 'edit')
                @if($selectedBranch)
                    <form method="POST" action="{{ route('admin.vendors.branches.update', $selectedBranch->branch_id) }}" autocomplete="off">
                        @csrf @method('PUT')
                        @include('masterfile.partials.branch-form', ['branch'=>$selectedBranch, 'states'=>$states, 'submitLabel'=>'💾 Update Branch', 'showStatus'=>true])
                    </form>
                @else
                    <form method="GET" action="{{ route('admin.vendors.index') }}" autocomplete="off">
                        <input type="hidden" name="mode" value="edit">
                        <input type="hidden" name="vendor_id" value="{{ $selectedVendor->vendor_id }}">
                        <input type="hidden" name="branch_mode" value="edit">
                        @include('masterfile.partials.branch-form', ['branch'=>null, 'states'=>$states, 'submitLabel'=>'🔍 Search Branch', 'showStatus'=>false])
                    </form>
                @endif
            @elseif($branchMode === 'search_all')
                <form method="GET" action="{{ route('admin.vendors.index') }}" autocomplete="off">
                    <input type="hidden" name="mode" value="edit">
                    <input type="hidden" name="vendor_id" value="{{ $selectedVendor->vendor_id }}">
                    <input type="hidden" name="branch_mode" value="search_all">
                    @include('masterfile.partials.branch-form', ['branch'=>null, 'states'=>$states, 'submitLabel'=>'🔍 Search All Branches', 'showStatus'=>false])
                </form>
            @endif

            @if($branches->isNotEmpty())
            <div style="margin-top:10px;">
                <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:6px;">{{ $branches->total() }} branch(es) found</div>
                <table style="width:100%; border-collapse:collapse; font-size:11px;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:2px solid #e0f2fe;">
                            <th style="text-align:left; padding:7px 10px; font-weight:600; color:#374151;">Branch Name</th>
                            <th style="text-align:left; padding:7px 10px; font-weight:600; color:#374151;">Code</th>
                            <th style="text-align:left; padding:7px 10px; font-weight:600; color:#374151;">Phone</th>
                            <th style="text-align:left; padding:7px 10px; font-weight:600; color:#374151;">City</th>
                            <th style="text-align:left; padding:7px 10px; font-weight:600; color:#374151;">State</th>
                            <th style="text-align:left; padding:7px 10px; font-weight:600; color:#374151;">PIC</th>
                            <th style="text-align:center; padding:7px 10px; font-weight:600; color:#374151;">Status</th>
                            <th style="text-align:center; padding:7px 10px; font-weight:600; color:#374151;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($branches as $b)
                        <tr style="border-bottom:1px solid #f3f4f6; {{ $loop->even ? 'background:#fafafa;' : '' }}">
                            <td style="padding:7px 10px; font-weight:600; color:#111827;">{{ $b->branch_name }}</td>
                            <td style="padding:7px 10px; font-family:monospace; color:#6b7280;">{{ $b->branch_code }}</td>
                            <td style="padding:7px 10px;">{{ $b->phone ?? '—' }}</td>
                            <td style="padding:7px 10px;">{{ $b->city ?? '—' }}</td>
                            <td style="padding:7px 10px;">{{ $b->state ?? '—' }}</td>
                            <td style="padding:7px 10px;">{{ $b->pic_name ?? '—' }}</td>
                            <td style="padding:7px 10px; text-align:center;">
                                <span style="background:{{ $b->is_active ? '#d1fae5' : '#fee2e2' }}; color:{{ $b->is_active ? '#065f46' : '#991b1b' }}; font-size:10px; font-weight:600; padding:2px 8px; border-radius:20px;">{{ $b->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td style="padding:7px 10px; text-align:center;">
                                <a href="{{ route('admin.vendors.index', ['mode'=>'edit', 'vendor_id'=>$selectedVendor->vendor_id, 'branch_mode'=>'edit', 'branch_id'=>$b->branch_id]) }}" style="background:#e0f2fe; color:#1565C0; text-decoration:none; border-radius:6px; padding:3px 10px; font-size:10px; font-weight:600;">✏️ Edit</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                {{-- Branch Pagination --}}
                @if($branches->lastPage() > 1)
                <div style="display:flex; justify-content:space-between; align-items:center; padding-top:8px; margin-top:6px; border-top:1px solid #f3f4f6;">
                    <div style="font-size:11px; color:#6b7280;">Showing {{ $branches->firstItem() }}–{{ $branches->lastItem() }} of <strong>{{ $branches->total() }}</strong> branches</div>
                    <div style="display:flex; gap:6px;">
                        @if($branches->onFirstPage())
                            <span style="background:#f3f4f6; color:#9ca3af; border-radius:6px; padding:4px 12px; font-size:11px;">← Prev</span>
                        @else
                            <a href="{{ $branches->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:4px 12px; font-size:11px;">← Prev</a>
                        @endif
                        <span style="font-size:11px; color:#374151;">Page {{ $branches->currentPage() }} / {{ $branches->lastPage() }}</span>
                        @if($branches->hasMorePages())
                            <a href="{{ $branches->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:4px 12px; font-size:11px;">Next →</a>
                        @else
                            <span style="background:#f3f4f6; color:#9ca3af; border-radius:6px; padding:4px 12px; font-size:11px;">Next →</span>
                        @endif
                    </div>
                </div>
                @endif
            </div>
            @else
            <div style="text-align:center; padding:20px; color:#9ca3af; font-size:12px; margin-top:8px;">No branches found. Add using ➕ Add Branch tab above.</div>
            @endif
        </div>
    </div>
    @endif

</div>

@push('scripts')
<script>
var _glPCt, _glCTt;
function glPC(inp) {
    clearTimeout(_glPCt);
    var v=inp.value.trim(), dd=document.getElementById('gl_pc_dd');
    if(v.length<3){dd.style.display='none';return;}
    _glPCt=setTimeout(function(){
        fetch('/admin/postcode-lookup?postcode='+encodeURIComponent(v)+'&partial=1')
        .then(function(r){return r.json();}).then(function(data){
            if(!data||!data.length){dd.style.display='none';return;}
            dd.innerHTML='';
            data.forEach(function(item){
                var d=document.createElement('div');
                d.style.cssText='padding:7px 12px;cursor:pointer;font-size:12px;border-bottom:1px solid #f3f4f6;white-space:nowrap;';
                d.innerHTML='<strong>'+item.postcode+'</strong> — '+item.city+' <span style="color:#6b7280;">('+item.state+')</span>';
                d.onmouseover=function(){this.style.background='#f0f9ff';};
                d.onmouseout=function(){this.style.background='';};
                d.onmousedown=function(e){
                    e.preventDefault();
                    document.getElementById('gl_vendor_postcode').value=item.postcode;
                    document.getElementById('gl_vendor_city').value=item.city;
                    var s=document.getElementById('gl_vendor_state');
                    if(s){for(var i=0;i<s.options.length;i++){if(s.options[i].value===item.state){s.selectedIndex=i;break;}}}
                    dd.style.display='none';
                };
                dd.appendChild(d);
            });
            dd.style.display='block';
        }).catch(function(){dd.style.display='none';});
    },300);
}
function glCity(inp) {
    clearTimeout(_glCTt);
    var v=inp.value.trim(), dd=document.getElementById('gl_city_dd');
    if(v.length<2){dd.style.display='none';return;}
    _glCTt=setTimeout(function(){
        fetch('/admin/postcode-lookup?city='+encodeURIComponent(v))
        .then(function(r){return r.json();}).then(function(data){
            if(!data||!data.length){dd.style.display='none';return;}
            dd.innerHTML='';
            var seen={};
            data.forEach(function(item){
                if(seen[item.city])return; seen[item.city]=true;
                var d=document.createElement('div');
                d.style.cssText='padding:7px 12px;cursor:pointer;font-size:12px;border-bottom:1px solid #f3f4f6;white-space:nowrap;';
                d.innerHTML=item.city+' <span style="color:#6b7280;">('+item.state+')</span>';
                d.onmouseover=function(){this.style.background='#f0f9ff';};
                d.onmouseout=function(){this.style.background='';};
                d.onmousedown=function(e){
                    e.preventDefault();
                    document.getElementById('gl_vendor_city').value=item.city;
                    var s=document.getElementById('gl_vendor_state');
                    if(s){for(var i=0;i<s.options.length;i++){if(s.options[i].value===item.state){s.selectedIndex=i;break;}}}
                    dd.style.display='none';
                };
                dd.appendChild(d);
            });
            dd.style.display='block';
        }).catch(function(){dd.style.display='none';});
    },300);
}
document.addEventListener('click',function(e){
    var a=document.getElementById('gl_pc_dd');
    var b=document.getElementById('gl_city_dd');
    if(a&&!a.contains(e.target))a.style.display='none';
    if(b&&!b.contains(e.target))b.style.display='none';
});
</script>
@endpush
@endsection
