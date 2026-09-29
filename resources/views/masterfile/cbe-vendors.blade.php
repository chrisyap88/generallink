@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_vendors.page_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow:hidden; padding:6px 16px; box-sizing:border-box; display:flex; flex-direction:column;">

    <div style="margin-bottom:5px; display:flex; align-items:center; justify-content:space-between;">
        <div>
            <div style="font-size:14px; font-weight:700; color:#1565C0;">{{ __('cbe_vendors.page_title') }}</div>
            <div style="font-size:9.5px; color:#6b7280; max-width:640px;">{{ __('cbe_vendors.intro') }}</div>
        </div>
        <span onclick="cbeVShowForm()" style="background:#1B5E20; color:#fff; border-radius:5px; padding:6px 14px; font-size:11px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_vendors.register_button') }}</span>
    </div>

    <form method="GET" style="display:flex; gap:6px; margin-bottom:6px; flex-shrink:0;">
        <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('cbe_vendors.search_placeholder') }}" style="flex:1; max-width:360px; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:5px 14px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_vendors.go') }}</button>
        @if($search !== '')
        <a href="{{ route('admin.masterfile.cbe-vendors') }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:5px; padding:5px 14px; font-size:10.5px; font-weight:500;">{{ __('cbe_vendors.clear') }}</a>
        @endif
    </form>

    @php $pageSize = 8; $total = $vendors->count(); $totalPages = $total > 0 ? (int) ceil($total / $pageSize) : 1; @endphp
    <div style="flex:1; min-height:0; border:1px solid #d1d5db; border-radius:8px; background:#fff; display:flex; flex-direction:column; overflow:hidden;">
        <div style="display:grid; grid-template-columns:1.8fr 1.4fr 1.2fr 1.6fr 0.7fr; gap:6px; padding:6px 10px; background:#f0f9ff; border-bottom:1px solid #e0f2fe; font-size:9px; font-weight:700; color:#6b7280; text-transform:uppercase;">
            <div>{{ __('cbe_vendors.col_vendor_name') }}</div>
            <div>{{ __('cbe_vendors.col_contact') }}</div>
            <div>{{ __('cbe_vendors.col_phone') }}</div>
            <div>{{ __('cbe_vendors.col_email') }}</div>
            <div></div>
        </div>
        <div style="flex:1; min-height:0; overflow:hidden;">
            @forelse($vendors as $v)
            <div class="cbeVRow" data-page="{{ intdiv($loop->index, $pageSize) + 1 }}" style="display:{{ $loop->index < $pageSize ? 'grid' : 'none' }}; grid-template-columns:1.8fr 1.4fr 1.2fr 1.6fr 0.7fr; gap:6px; padding:5px 10px; border-bottom:1px solid #f3f4f6; font-size:10.5px; align-items:center;">
                <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $v->vendor_name }}">{{ $v->vendor_name }}</div>
                <div style="color:#6b7280; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $v->contact_person }}</div>
                <div style="color:#6b7280; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $v->phone }}</div>
                <div style="color:#6b7280; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $v->email }}</div>
                <div><a href="{{ route('admin.masterfile.cbe-vendors.show', $v->vendor_id) }}" style="color:#1565C0; font-weight:600; text-decoration:none; font-size:9.5px;">{{ __('cbe_vendors.view_link') }} ›</a></div>
            </div>
            @empty
            <div style="padding:16px; text-align:center; color:#9ca3af; font-size:10.5px;">{{ __('cbe_vendors.no_vendors') }}</div>
            @endforelse
        </div>
    </div>

    @if($total > $pageSize)
    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:5px; flex-shrink:0;">
        <span onclick="cbeVPageNav(-1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 16px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.prev') }}</span>
        <span id="cbeVPageLabel" style="font-size:9.5px; color:#6b7280;">1 / {{ $totalPages }} ({{ $total }})</span>
        <span onclick="cbeVPageNav(1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 16px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.next') }}</span>
    </div>
    @endif
</div>

{{-- Register New Vendor — a simple overlay form (not a separate screen,
so Prev/Next-only navigation is respected: this is a same-screen
action, not a jump screen), shown/hidden with plain JS. --}}
<div id="cbeVFormOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.35); z-index:50; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:10px; padding:18px 20px; width:360px; max-width:92vw; box-sizing:border-box;">
        <div style="font-size:12.5px; font-weight:700; color:#1565C0; margin-bottom:10px;">{{ __('cbe_vendors.form_title') }}</div>
        <form method="POST" action="{{ route('admin.masterfile.cbe-vendors.store') }}">
            @csrf
            <div style="display:flex; flex-direction:column; gap:8px;">
                <div id="cbeVLinkBox" style="background:#f0f6ff; border:1px solid #c7dcfa; border-radius:6px; padding:8px 10px; position:relative;">
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#374151;">{{ __('cbe_vendors.field_link_member_label') }}</label>
                    <div id="cbeVSearchRow">
                        <input type="text" id="cbeVSearchInput" placeholder="{{ __('cbe_vendors.field_link_member_placeholder') }}" autocomplete="off" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                        <div id="cbeVSearchResults" style="display:none; position:absolute; left:10px; right:10px; top:100%; background:#fff; border:1px solid #d1d5db; border-radius:6px; margin-top:2px; max-height:130px; overflow-y:auto; z-index:60; box-shadow:0 2px 6px rgba(0,0,0,0.1);"></div>
                    </div>
                    <div id="cbeVLinkedRow" style="display:none; align-items:center; justify-content:space-between;">
                        <div style="font-size:10.5px; font-weight:600; color:#1a3d7c;" id="cbeVLinkedName"></div>
                        <a href="#" id="cbeVLinkedChange" style="font-size:9.5px; color:#1565C0; text-decoration:none; font-weight:600;">{{ __('cbe_vendors.field_link_member_change') }}</a>
                    </div>
                    <input type="hidden" name="agent_id" id="cbeVAgentId">
                </div>
                <div id="cbeVManualName">
                    <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_vendors.field_vendor_name') }}</label>
                    <input type="text" name="vendor_name" id="cbeVNameInput" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_vendors.field_contact_person') }}</label>
                    <input type="text" name="contact_person" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div id="cbeVManualPhone">
                    <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_vendors.field_phone') }}</label>
                    <input type="text" name="phone" id="cbeVPhoneInput" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                    <div id="cbeVPhoneWarning" style="display:none; font-size:9px; color:#b45309; margin-top:3px;"></div>
                </div>
                <div id="cbeVManualEmail">
                    <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_vendors.field_email') }}</label>
                    <input type="email" name="email" id="cbeVEmailInput" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_vendors.field_address') }}</label>
                    <input type="text" name="address" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
            </div>
            <div style="display:flex; gap:8px; margin-top:14px; justify-content:flex-end;">
                <span onclick="cbeVHideForm()" style="background:#f3f4f6; color:#374151; border-radius:5px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_vendors.btn_cancel') }}</span>
                <button type="submit" style="background:#1B5E20; color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:10.5px; font-weight:700; cursor:pointer;">{{ __('cbe_vendors.btn_save') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
function cbeVShowForm(){ document.getElementById('cbeVFormOverlay').style.display = 'flex'; }
function cbeVHideForm(){ document.getElementById('cbeVFormOverlay').style.display = 'none'; }
@if($errors->any())
document.addEventListener('DOMContentLoaded', function(){ cbeVShowForm(); });
@endif

(function () {
    var cbeVCurrentPage = 1;
    var cbeVTotalPages = {{ $totalPages }};
    window.cbeVPageNav = function (dir) {
        var next = cbeVCurrentPage + dir;
        if (next < 1 || next > cbeVTotalPages) return;
        cbeVCurrentPage = next;
        document.querySelectorAll('.cbeVRow').forEach(function (row) {
            row.style.display = (parseInt(row.getAttribute('data-page'), 10) === cbeVCurrentPage) ? 'grid' : 'none';
        });
        document.getElementById('cbeVPageLabel').textContent = cbeVCurrentPage + ' / ' + cbeVTotalPages + ' ({{ $total }})';
    };
})();

(function () {
    var searchInput = document.getElementById('cbeVSearchInput');
    var resultsBox = document.getElementById('cbeVSearchResults');
    var linkedRow = document.getElementById('cbeVLinkedRow');
    var linkedName = document.getElementById('cbeVLinkedName');
    var linkedChange = document.getElementById('cbeVLinkedChange');
    var searchRow = document.getElementById('cbeVSearchRow');
    var agentIdInput = document.getElementById('cbeVAgentId');
    var nameInput = document.getElementById('cbeVNameInput');
    var phoneInput = document.getElementById('cbeVPhoneInput');
    var emailInput = document.getElementById('cbeVEmailInput');
    var manualName = document.getElementById('cbeVManualName');
    var manualPhone = document.getElementById('cbeVManualPhone');
    var manualEmail = document.getElementById('cbeVManualEmail');
    var phoneWarning = document.getElementById('cbeVPhoneWarning');
    var searchTimer = null;
    var phoneTimer = null;

    function setLinked(agent) {
        agentIdInput.value = agent.agent_id;
        nameInput.value = agent.full_name;
        phoneInput.value = agent.phone || '';
        emailInput.value = agent.email || '';
        linkedName.textContent = agent.full_name + (agent.agent_code ? ' (' + agent.agent_code + ')' : '');
        searchRow.style.display = 'none';
        linkedRow.style.display = 'flex';
        manualName.style.display = 'none';
        manualPhone.style.display = 'none';
        manualEmail.style.display = 'none';
        resultsBox.style.display = 'none';
        searchInput.value = '';
        phoneWarning.style.display = 'none';
    }

    function clearLinked() {
        agentIdInput.value = '';
        nameInput.value = '';
        phoneInput.value = '';
        emailInput.value = '';
        searchRow.style.display = 'block';
        linkedRow.style.display = 'none';
        manualName.style.display = 'block';
        manualPhone.style.display = 'block';
        manualEmail.style.display = 'block';
    }

    searchInput.addEventListener('input', function () {
        var q = searchInput.value.trim();
        clearTimeout(searchTimer);
        if (q.length < 1) { resultsBox.style.display = 'none'; return; }
        searchTimer = setTimeout(function () {
            fetch('{{ route('admin.masterfile.cbe-vendors.agent-typeahead') }}?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (rows) {
                    if (!rows.length) { resultsBox.style.display = 'none'; return; }
                    resultsBox.innerHTML = '';
                    rows.forEach(function (a) {
                        var item = document.createElement('div');
                        item.style.padding = '6px 10px';
                        item.style.fontSize = '10px';
                        item.style.cursor = 'pointer';
                        item.style.borderBottom = '1px solid #f3f4f6';
                        item.textContent = a.full_name + (a.agent_code ? ' — ' + a.agent_code : '');
                        item.addEventListener('mousedown', function (e) {
                            e.preventDefault();
                            setLinked(a);
                        });
                        resultsBox.appendChild(item);
                    });
                    resultsBox.style.display = 'block';
                });
        }, 200);
    });

    linkedChange.addEventListener('click', function (e) {
        e.preventDefault();
        clearLinked();
    });

    document.addEventListener('click', function (e) {
        if (!resultsBox.contains(e.target) && e.target !== searchInput) {
            resultsBox.style.display = 'none';
        }
    });

    // NEW 25 Sep 2026 -- per Chris's decision: warning only, never
    // blocks saving.
    phoneInput.addEventListener('input', function () {
        var p = phoneInput.value.trim();
        clearTimeout(phoneTimer);
        if (p.length < 5) { phoneWarning.style.display = 'none'; return; }
        phoneTimer = setTimeout(function () {
            fetch('{{ route('admin.masterfile.cbe-vendors.phone-check') }}?phone=' + encodeURIComponent(p))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.match) {
                        phoneWarning.textContent = '{{ __('cbe_vendors.phone_dup_warning_prefix') }} ' + data.match.label + ': ' + data.match.name;
                        phoneWarning.style.display = 'block';
                    } else {
                        phoneWarning.style.display = 'none';
                    }
                });
        }, 300);
    });
})();
</script>
@endsection
