@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_cbe_directory.customers_title'))

@section('content')

<style>
.cbd-box{flex:1; min-height:0; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; box-shadow:0 2px 8px rgba(21,101,192,0.08); display:flex; flex-direction:column; overflow:hidden; box-sizing:border-box;}
.cbd-field{display:flex; flex-direction:column; gap:3px;}
.cbd-field label{font-size:8px; font-weight:700; color:#6b7280; text-transform:uppercase;}
.cbd-field input, .cbd-field select{font-family:'Poppins',sans-serif; font-size:9px; color:#263238; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; box-sizing:border-box; width:100%;}
.cbd-btn{background:var(--gl-blue); color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:9px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;}
.cbd-btn-outline{background:var(--gl-light); color:var(--gl-blue); border:1px solid var(--gl-cyan2); border-radius:5px; padding:6px 16px; font-size:9px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;}
.cbd-row{display:flex; align-items:center; justify-content:space-between; padding:6px 12px; border-bottom:1px solid #eef2f7; font-size:9px;}
.cbd-row:last-child{border-bottom:none;}
.cbd-pg-btn{background:#0D5A8E; color:#fff; text-decoration:none; border-radius:5px; padding:4px 12px; font-size:9px; font-weight:700;}
.cbd-pg-btn-disabled{background:#f3f4f6; color:#9ca3af; border-radius:5px; padding:4px 12px; font-size:9px; font-weight:700;}
.cbd-tabtoggle{padding:5px 14px; font-size:9px; font-weight:700; color:#6b7280; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px; user-select:none;}
.cbd-tabtoggle.active{color:var(--gl-blue); border-bottom-color:var(--gl-blue);}
.cbd-back-btn{display:inline-flex; align-items:center; gap:4px; background:var(--gl-blue); color:#fff; text-decoration:none; font-size:8.5px; font-weight:700; padding:5px 14px; border-radius:5px;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; gap:7px;">

    <div style="flex-shrink:0;">
        <div style="font-size:13px; font-weight:700; color:#263238; white-space:nowrap;">{{ __('admin_cbe_directory.customers_title') }}</div>
        <div style="font-size:9px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $nodePrimary }}{{ $nodeSecondary ? ' ('.$nodeSecondary.')' : '' }}</div>
    </div>

    {{-- NEW 27 Aug 2026 — per Chris: "i dont want the triple ... wording
    and i ONLY want one row in proper sequence tap." Merged the old
    Search/Find Customer toggle row into this single row via
    $localTabs. --}}
    @include('admin.cbe-kpi.partials.persistent-tabs', [
        'activeTab' => 'customers',
        'primaryTabKey' => 'customers',
        'primaryTabLabel' => __('admin_cbe_directory.customers_title'),
        'primaryTabRoute' => 'admin.cbe-kpi.customers',
        'primaryLocalKey' => 'existing',
        'localTabs' => [
            ['label' => __('admin_cbe_directory.btn_find_customer'), 'key' => 'find', 'active' => $findMode === 'search'],
        ],
    ])

    <div id="cbd-panel-existing" style="display:{{ $findMode !== 'search' ? 'flex' : 'none' }}; flex-direction:column; flex:1; min-height:0; gap:7px;">
        <div class="cbd-box" style="flex:0 0 auto; padding:10px 12px;">
            <form method="GET" action="{{ route('admin.cbe-kpi.customers') }}" autocomplete="off">
                <input type="hidden" name="node" value="{{ $node->node_id }}">
                <input type="hidden" name="mode" value="search">
                <input type="hidden" name="do_search" value="1">
                <div style="font-size:8.5px; color:#94A3B8; margin-bottom:8px;">{{ __('admin_cbe_directory.search_prompt') }}</div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <div class="cbd-field" style="flex:1; min-width:150px; position:relative;">
                        <label>{{ __('admin_cbe_directory.search_customer_name') }}</label>
                        <input type="text" id="cbd-cust-input" name="full_name" value="{{ request('full_name') }}">
                        <div id="cbd-cust-dd" style="display:none; position:absolute; top:100%; left:0; margin-top:2px; background:#fff; border:1px solid #b2ebf2; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,0.15); max-height:200px; overflow-y:auto; z-index:60; width:260px;"></div>
                    </div>
                    <div class="cbd-field" style="flex:1; min-width:130px;">
                        <label>{{ __('admin_cbe_directory.search_customer_phone') }}</label>
                        <input type="text" name="phone" value="{{ request('phone') }}">
                    </div>
                    <div class="cbd-field" style="flex:1; min-width:150px;">
                        <label>{{ __('admin_cbe_directory.search_customer_email') }}</label>
                        <input type="text" name="email" value="{{ request('email') }}">
                    </div>
                    <div class="cbd-field" style="flex:1; min-width:130px;">
                        <label>{{ __('admin_cbe_directory.search_customer_city') }}</label>
                        <input type="text" name="city" value="{{ request('city') }}">
                    </div>
                    {{-- NEW 26 Aug 2026, 16th pass — per Chris: "I want
                    ALL DB Field to become search criteria." Remaining
                    human-searchable columns on customers (nric fields
                    are encrypted/hashed, not readable text to search). --}}
                    <div class="cbd-field" style="flex:1; min-width:150px;">
                        <label>{{ __('admin_cbe_directory.search_customer_address') }}</label>
                        <input type="text" name="address" value="{{ request('address') }}">
                    </div>
                    <div class="cbd-field" style="flex:1; min-width:110px;">
                        <label>{{ __('admin_cbe_directory.search_customer_postcode') }}</label>
                        <input type="text" name="postcode" value="{{ request('postcode') }}">
                    </div>
                    <div class="cbd-field" style="flex:1; min-width:120px;">
                        <label>{{ __('admin_cbe_directory.search_customer_state') }}</label>
                        <input type="text" name="state" value="{{ request('state') }}">
                    </div>
                </div>
                <div style="display:flex; gap:8px; margin-top:10px;">
                    <button type="submit" class="cbd-btn">🔍 {{ __('admin_cbe_directory.btn_search') }}</button>
                    @if($doSearch)
                    <a href="{{ route('admin.cbe-kpi.customers', ['node' => $node->node_id]) }}" class="cbd-btn-outline">{{ __('admin_cbe_directory.btn_new_search') }}</a>
                    @endif
                </div>
            </form>
        </div>

        {{-- NEW 26 Aug 2026, 21st pass — per Chris: search jumps straight
        to the first matching customer's profile (Prev/Next through the
        rest) instead of listing rows here. This block now only appears
        before a search, or when a search found zero matches. --}}
        @if($doSearch)
        <div style="flex:1; display:flex; align-items:center; justify-content:center;">
            <div style="text-align:center; color:#94A3B8; font-size:10px; max-width:420px;">{{ __('admin_cbe_directory.no_results') }}</div>
        </div>
        @else
        <div style="flex:1; display:flex; align-items:center; justify-content:center;">
            <div style="text-align:center; color:#94A3B8; font-size:10px; max-width:420px;">{{ __('admin_cbe_directory.search_prompt') }}</div>
        </div>
        @endif
    </div>

    <div id="cbd-panel-find" style="display:{{ $findMode === 'search' ? 'flex' : 'none' }}; flex-direction:column; flex:1; min-height:0; gap:7px;">
        <div class="cbd-box" style="flex:0 0 auto; padding:10px 12px;">
            <form method="GET" action="{{ route('admin.cbe-kpi.customers') }}" autocomplete="off">
                <input type="hidden" name="node" value="{{ $node->node_id }}">
                <input type="hidden" name="find_mode" value="search">
                <input type="hidden" name="find_do_search" value="1">
                <div style="font-size:8.5px; color:#94A3B8; margin-bottom:8px;">{{ __('admin_cbe_directory.find_any_customer_prompt') }}</div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <div class="cbd-field" style="flex:1; min-width:150px;">
                        <label>{{ __('admin_cbe_directory.search_customer_name') }}</label>
                        <input type="text" name="find_full_name" value="{{ request('find_full_name') }}">
                    </div>
                    <div class="cbd-field" style="flex:1; min-width:130px;">
                        <label>{{ __('admin_cbe_directory.search_customer_phone') }}</label>
                        <input type="text" name="find_phone" value="{{ request('find_phone') }}">
                    </div>
                    <div class="cbd-field" style="flex:1; min-width:150px;">
                        <label>{{ __('admin_cbe_directory.search_customer_email') }}</label>
                        <input type="text" name="find_email" value="{{ request('find_email') }}">
                    </div>
                </div>
                <div style="display:flex; gap:8px; margin-top:10px;">
                    <button type="submit" class="cbd-btn">🔍 {{ __('admin_cbe_directory.btn_search') }}</button>
                </div>
            </form>
        </div>
        <div class="cbd-box">
            <div style="flex:1; min-height:0; overflow-y:auto;">
                @forelse(($findResults ?? []) as $c)
                <div class="cbd-row">
                    <div style="min-width:0; display:flex; gap:10px; flex:1;">
                        <span style="font-weight:600; color:#263238; flex:1.3; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $c->full_name }}</span>
                        <span style="color:#94A3B8; flex:1;">{{ $c->phone }}</span>
                        <span style="color:#94A3B8; flex:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $c->email }}</span>
                    </div>
                    <a href="{{ route('admin.cbe-kpi.customers.show', ['node' => $node->node_id, 'id' => $c->customer_id]) }}" class="cbd-btn" style="padding:4px 12px;">{{ __('admin_cbe_directory.btn_view') }}</a>
                </div>
                @empty
                <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('admin_cbe_directory.no_results') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    <div style="flex-shrink:0;">
        <a href="{{ route('admin.cbe-kpi', ['node' => $node->node_id]) }}" class="cbd-back-btn"><i class="ti ti-arrow-back-up"></i> {{ __('admin_cbe_directory.back_to_temple') }}</a>
    </div>
</div>

<script>
(function(){
    var url = '{{ route("admin.cbe-kpi.customers.typeahead") }}';
    var nodeId = '{{ $node->node_id }}';
    var showUrl = '{{ route("admin.cbe-kpi.customers.show") }}';
    var input = document.getElementById('cbd-cust-input');
    var dd = document.getElementById('cbd-cust-dd');
    var timer = null;

    function search(q){
        fetch(url+'?node='+encodeURIComponent(nodeId)+'&q='+encodeURIComponent(q)+'&_='+Date.now(), {cache:'no-store'})
            .then(function(r){ return r.json(); })
            .then(render)
            .catch(function(){ dd.style.display='none'; });
    }
    function render(list){
        if(!list.length){ dd.style.display='none'; return; }
        dd.innerHTML = list.map(function(it, i){
            return '<div class="cbd-sugg" data-i="'+i+'" style="padding:6px 8px;font-size:8.5px;color:var(--gl-blue);font-weight:600;cursor:pointer;border-bottom:1px solid #eee;">'+it.label+'</div>';
        }).join('');
        dd.style.display = 'block';
        Array.prototype.forEach.call(dd.querySelectorAll('.cbd-sugg'), function(el){
            el.addEventListener('mousedown', function(e){
                e.preventDefault();
                var it = list[parseInt(el.getAttribute('data-i'), 10)];
                window.location = showUrl+'?node='+encodeURIComponent(nodeId)+'&id='+encodeURIComponent(it.customer_id);
            });
        });
    }
    input.addEventListener('input', function(){
        var q = input.value.trim();
        if(timer) clearTimeout(timer);
        if(q.length < 1){ dd.style.display='none'; return; }
        timer = setTimeout(function(){ search(q); }, 250);
    });
    document.addEventListener('click', function(e){
        if(e.target !== input) dd.style.display = 'none';
    });

    window.cbdSwitchPanel = function(p){
        document.getElementById('cbd-panel-existing').style.display = (p === 'existing') ? 'flex' : 'none';
        document.getElementById('cbd-panel-find').style.display = (p === 'find') ? 'flex' : 'none';
        Array.prototype.forEach.call(document.querySelectorAll('.cbd-tabtoggle'), function(el){
            el.classList.toggle('active', el.getAttribute('data-p') === p);
        });
    };
})();
</script>
@endsection
