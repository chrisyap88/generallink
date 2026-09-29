@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.add_customer_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.add_customer_button') }}</div>
        <a href="{{ route('cbe.accounting.customers') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.accounting.customers.store') }}" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            <div style="flex:1; min-height:0; display:flex; flex-direction:column; gap:10px; max-width:480px;">
                <div id="memberLinkBox" style="background:#f0f6ff; border:1px solid #c7dcfa; border-radius:6px; padding:8px 10px; position:relative;">
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_link_member_label') }}</label>
                    <div id="memberSearchRow">
                        <input type="text" id="memberSearchInput" placeholder="{{ __('cbe_accounting.field_link_member_placeholder') }}" autocomplete="off" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                        <div id="memberSearchResults" style="display:none; position:absolute; left:10px; right:10px; top:100%; background:#fff; border:1px solid #d1d5db; border-radius:6px; margin-top:2px; max-height:150px; overflow-y:auto; z-index:20; box-shadow:0 2px 6px rgba(0,0,0,0.1);"></div>
                    </div>
                    <div id="memberLinkedRow" style="display:none; align-items:center; justify-content:space-between;">
                        <div style="font-size:11px; font-weight:600; color:#1a3d7c;" id="memberLinkedName"></div>
                        <a href="#" id="memberLinkedChange" style="font-size:10px; color:var(--gl-blue); text-decoration:none; font-weight:600;">{{ __('cbe_accounting.field_link_member_change') }}</a>
                    </div>
                    <input type="hidden" name="agent_id" id="memberAgentId" value="{{ old('agent_id') }}">
                </div>
                <div id="manualNameField">
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_customer_name') }}</label>
                    <input type="text" name="customer_name" id="customerNameInput" value="{{ old('customer_name') }}" maxlength="150" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_contact_person') }}</label>
                    <input type="text" name="contact_person" value="{{ old('contact_person') }}" maxlength="100" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                </div>
                <div id="manualPhoneField">
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_phone') }}</label>
                    <input type="text" name="phone" id="customerPhoneInput" value="{{ old('phone') }}" maxlength="30" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                    <div id="customerPhoneWarning" style="display:none; font-size:9px; color:#b45309; margin-top:3px;"></div>
                </div>
                <div id="manualEmailField">
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_email') }}</label>
                    <input type="email" name="email" id="customerEmailInput" value="{{ old('email') }}" maxlength="150" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                </div>
                <div style="display:flex; gap:8px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_customer_category') }}</label>
                        <select name="category_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                            <option value="">{{ __('cbe_accounting.field_category_uncategorised') }}</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->category_id }}" {{ old('category_id') == $cat->category_id ? 'selected' : '' }}>{{ $cat->category_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_payment_terms') }}</label>
                        <select name="payment_terms_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                            <option value="">{{ __('cbe_accounting.field_payment_terms_none') }}</option>
                            @foreach($paymentTerms as $term)
                            <option value="{{ $term->term_id }}" {{ old('payment_terms_id') == $term->term_id ? 'selected' : '' }}>{{ $term->term_name }} ({{ $term->net_days }} {{ __('cbe_accounting.days_suffix') }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.accounting.customers') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var searchInput = document.getElementById('memberSearchInput');
    var resultsBox = document.getElementById('memberSearchResults');
    var linkedRow = document.getElementById('memberLinkedRow');
    var linkedName = document.getElementById('memberLinkedName');
    var linkedChange = document.getElementById('memberLinkedChange');
    var searchRow = document.getElementById('memberSearchRow');
    var agentIdInput = document.getElementById('memberAgentId');
    var nameInput = document.getElementById('customerNameInput');
    var phoneInput = document.getElementById('customerPhoneInput');
    var emailInput = document.getElementById('customerEmailInput');
    var manualName = document.getElementById('manualNameField');
    var manualPhone = document.getElementById('manualPhoneField');
    var manualEmail = document.getElementById('manualEmailField');
    var timer = null;

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
        clearTimeout(timer);
        if (q.length < 1) { resultsBox.style.display = 'none'; return; }
        timer = setTimeout(function () {
            fetch('{{ route('cbe.accounting.customers.agent-typeahead') }}?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (rows) {
                    if (!rows.length) { resultsBox.style.display = 'none'; return; }
                    resultsBox.innerHTML = '';
                    rows.forEach(function (a) {
                        var item = document.createElement('div');
                        item.style.padding = '6px 10px';
                        item.style.fontSize = '10.5px';
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
    var phoneWarning = document.getElementById('customerPhoneWarning');
    var phoneTimer = null;
    phoneInput.addEventListener('input', function () {
        var p = phoneInput.value.trim();
        clearTimeout(phoneTimer);
        if (p.length < 5) { phoneWarning.style.display = 'none'; return; }
        phoneTimer = setTimeout(function () {
            fetch('{{ route('cbe.accounting.customers.phone-check') }}?phone=' + encodeURIComponent(p))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.match) {
                        phoneWarning.textContent = '{{ __('cbe_accounting.phone_dup_warning_prefix') }} ' + data.match.label + ': ' + data.match.name;
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
