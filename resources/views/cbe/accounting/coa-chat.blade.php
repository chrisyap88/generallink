@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('masterfile.ai_assistant_tile_coa'))

@section('content')

{{-- NEW 19 Sep 2026 -- "COA Chat", Phase 1 of the AI Master Data
     Assistant (per Chris's uploaded spec): describe an account in
     plain language, the AI (CoaChatAssistantService) either finds an
     existing match or classifies a new one -- Type/Category/Group/
     Code/Name/Name(zh)/Description, in that exact display order per
     Chris -- and Chris confirms before anything is saved. Nothing here
     ever writes to the database directly; Save posts to the same,
     already-validated chart-of-accounts.store route the manual Add
     Account form uses. --}}
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('masterfile.ai_assistant_tile_coa') }}</div>
        <a href="{{ route('cbe.accounting.chart-of-accounts') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">&larr; {{ __('cbe_accounting.tile_chart_of_accounts') }}</a>
    </div>

    @if($errors->any())
    <div style="flex-shrink:0; background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="flex-shrink:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 14px;">
        <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:4px;">{{ __('coa_chat.describe_label') }}</label>
        <div style="display:flex; gap:8px;">
            <input type="text" id="cDescription" placeholder="{{ __('coa_chat.describe_placeholder') }}" autocomplete="off" style="flex:1; border:1px solid #d1d5db; border-radius:6px; padding:8px 11px; font-size:11.5px; box-sizing:border-box;">
            <button type="button" id="cAskBtn" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:8px 22px; font-size:11px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('coa_chat.ask_button') }}</button>
        </div>
    </div>

    <div style="flex:1; min-height:0; margin-top:8px; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; display:flex; flex-direction:column; justify-content:center;" id="cResultPanel">
        <div id="cIdle" style="text-align:center; font-size:11px; color:#9ca3af;">{{ __('coa_chat.idle_hint') }}</div>

        <div id="cLoading" style="display:none; text-align:center; font-size:11px; color:#78909C;">{{ __('coa_chat.thinking') }}</div>

        <div id="cError" style="display:none; text-align:center; font-size:11px; color:#b71c1c;"></div>

        {{-- MATCH result: an existing account already covers it. --}}
        <div id="cMatch" style="display:none;">
            <div style="font-size:10.5px; font-weight:700; color:#263238; margin-bottom:8px;">{{ __('coa_chat.match_title') }}</div>
            <div style="display:grid; grid-template-columns:110px 1fr; row-gap:5px; font-size:10.5px;">
                <div style="color:#78909C; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.field_account_type') }}</div><div id="mType" style="color:#263238;"></div>
                <div style="color:#78909C; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.field_account_category') }}</div><div id="mCategory" style="color:#263238;"></div>
                <div style="color:#78909C; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.field_account_group') }}</div><div id="mGroup" style="color:#263238;"></div>
                <div style="color:#78909C; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.field_account_code') }}</div><div id="mCode" style="color:#263238; font-weight:700;"></div>
                <div style="color:#78909C; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.field_account_name') }}</div><div id="mName" style="color:#263238;"></div>
                <div style="color:#78909C; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.field_account_name_zh') }}</div><div id="mNameZh" style="color:#263238;"></div>
                <div style="color:#78909C; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.field_description') }}</div><div id="mDescription" style="color:#263238;"></div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:12px;">
                <button type="button" class="cAskAgain" style="background:#c4c9d0; color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('coa_chat.ask_again_button') }}</button>
                <a id="mEditLink" href="#" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600;">{{ __('coa_chat.view_account_button') }}</a>
            </div>
        </div>

        {{-- NEW proposal: nothing saved yet. --}}
        <div id="cNew" style="display:none;">
            <div style="font-size:10.5px; font-weight:700; color:#263238; margin-bottom:8px;">{{ __('coa_chat.new_title') }}</div>
            <div style="display:grid; grid-template-columns:110px 1fr; row-gap:5px; font-size:10.5px;">
                <div style="color:#78909C; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.field_account_type') }}</div><div id="nType" style="color:#263238;"></div>
                <div style="color:#78909C; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.field_account_category') }}</div><div id="nCategory" style="color:#263238;"></div>
                <div style="color:#78909C; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.field_account_group') }}</div><div id="nGroup" style="color:#263238;"></div>
                <div style="color:#78909C; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.field_account_code') }}</div><div id="nCode" style="color:#263238; font-weight:700;"></div>
                <div style="color:#78909C; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.field_account_name') }}</div><div id="nName" style="color:#263238;"></div>
                <div style="color:#78909C; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.field_account_name_zh') }}</div><div id="nNameZh" style="color:#263238;"></div>
                <div style="color:#78909C; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.field_description') }}</div><div id="nDescription" style="color:#263238;"></div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:12px;">
                <button type="button" class="cAskAgain" style="background:#c4c9d0; color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('coa_chat.ask_again_button') }}</button>
                <button type="button" id="cSaveBtn" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('coa_chat.confirm_save_button') }}</button>
            </div>
        </div>
    </div>

    <form id="cSaveForm" method="POST" action="{{ route('cbe.accounting.chart-of-accounts.store') }}" style="display:none;">
        @csrf
        <input type="hidden" name="account_type" id="fAccountType">
        <input type="hidden" name="account_group_id" id="fAccountGroupId">
        <input type="hidden" name="account_category_id" id="fAccountCategoryId">
        <input type="hidden" name="account_code" id="fAccountCode">
        <input type="hidden" name="account_name" id="fAccountName">
        <input type="hidden" name="account_name_zh" id="fAccountNameZh">
        <input type="hidden" name="description" id="fDescription">
        <input type="hidden" name="is_posting_account" value="1">
    </form>
</div>

<script>
(function () {
    var editUrlTemplate = '{{ route('cbe.accounting.chart-of-accounts.edit', ['account' => 'PLACEHOLDER']) }}';
    var typeLabels = @json([
        'ASSET' => __('cbe_accounting.type_asset'), 'LIABILITY' => __('cbe_accounting.type_liability'),
        'EQUITY' => __('cbe_accounting.type_equity'), 'INCOME' => __('cbe_accounting.type_income'),
        'EXPENSE' => __('cbe_accounting.type_expense'),
    ]);
    var dash = @json(__('coa_chat.none_placeholder'));

    function showOnly(id) {
        ['cIdle', 'cLoading', 'cError', 'cMatch', 'cNew'].forEach(function (i) {
            document.getElementById(i).style.display = (i === id) ? 'block' : 'none';
        });
        document.getElementById('cResultPanel').style.justifyContent = (id === 'cMatch' || id === 'cNew') ? 'flex-start' : 'center';
    }

    document.getElementById('cAskBtn').addEventListener('click', function () {
        var description = document.getElementById('cDescription').value.trim();
        if (!description) { return; }
        showOnly('cLoading');
        fetch('{{ route('cbe.accounting.chart-of-accounts.chat.classify') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ description: description })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status === 'MATCH') {
                    var a = data.account;
                    document.getElementById('mType').textContent = typeLabels[a.account_type] || a.account_type;
                    document.getElementById('mCategory').textContent = a.category_name || dash;
                    document.getElementById('mGroup').textContent = a.group_name || dash;
                    document.getElementById('mCode').textContent = a.account_code;
                    document.getElementById('mName').textContent = a.account_name;
                    document.getElementById('mNameZh').textContent = a.account_name_zh || dash;
                    document.getElementById('mDescription').textContent = a.description || dash;
                    document.getElementById('mEditLink').href = editUrlTemplate.replace('PLACEHOLDER', a.account_id);
                    showOnly('cMatch');
                } else if (data.status === 'NEW') {
                    var p = data.proposal;
                    document.getElementById('nType').textContent = typeLabels[p.account_type] || p.account_type;
                    document.getElementById('nCategory').textContent = p.account_category_name || dash;
                    document.getElementById('nGroup').textContent = p.account_group_name || dash;
                    document.getElementById('nCode').textContent = p.account_code;
                    document.getElementById('nName').textContent = p.account_name;
                    document.getElementById('nNameZh').textContent = p.account_name_zh || dash;
                    document.getElementById('nDescription').textContent = p.description || dash;

                    document.getElementById('fAccountType').value = p.account_type;
                    document.getElementById('fAccountGroupId').value = p.account_group_id || '';
                    document.getElementById('fAccountCategoryId').value = p.account_category_id || '';
                    document.getElementById('fAccountCode').value = p.account_code;
                    document.getElementById('fAccountName').value = p.account_name;
                    document.getElementById('fAccountNameZh').value = p.account_name_zh || '';
                    document.getElementById('fDescription').value = p.description || '';
                    showOnly('cNew');
                } else {
                    document.getElementById('cError').textContent = data.message || @json(__('coa_chat.generic_error'));
                    showOnly('cError');
                }
            })
            .catch(function () {
                document.getElementById('cError').textContent = @json(__('coa_chat.generic_error'));
                showOnly('cError');
            });
    });

    document.getElementById('cSaveBtn').addEventListener('click', function () {
        document.getElementById('cSaveForm').submit();
    });

    document.querySelectorAll('.cAskAgain').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('cDescription').value = '';
            document.getElementById('cDescription').focus();
            showOnly('cIdle');
        });
    });

    document.getElementById('cDescription').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { document.getElementById('cAskBtn').click(); }
    });
})();
</script>
@endsection
