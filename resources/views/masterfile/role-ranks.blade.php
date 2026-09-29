@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.org_rank_hierarchy_title'))
@section('content')
<div style="height:calc(100vh - 46px); overflow:hidden; padding:6px 12px; display:flex; flex-direction:column; gap:6px; box-sizing:border-box;">

    <div id="rr-flash">
        @if(session('success'))
        <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:4px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0;">✅ {{ session('success') }}</div>
        @endif
        @if($errors->any())
        <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:4px 12px; color:#991b1b; font-size:11px; flex-shrink:0;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
        @endif
        @if($groupLabels->isEmpty())
        <div style="background:#fff8e1; border:1px solid #ffe082; border-radius:6px; padding:4px 12px; color:#92400e; font-size:11px; flex-shrink:0;">{{ __('masterfile.no_org_rewards_groups_yet') }}</div>
        @endif
    </div>

    <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
        <form method="GET" action="{{ route('admin.masterfile.role-ranks') }}" style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
            <label style="font-size:10.5px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.defining_ranks_for_label') }}</label>
            <select name="group_label_id" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:5px; padding:3px 8px; font-size:11.5px; background:#fff;">
                @foreach($groupLabels as $g)
                <option value="{{ $g->group_label_id }}" {{ $groupLabelId === $g->group_label_id ? 'selected' : '' }}>{{ $g->group_name }}</option>
                @endforeach
            </select>
        </form>
        <div title="{{ __('masterfile.rank_no_sequence_tooltip', ['gl' => $roleShortLabels['GROUP_LEADER'], 'tl' => $roleShortLabels['TEAM_LEADER'], 'introducer' => $roleShortLabels['INTRODUCER']]) }}" style="font-size:10px; color:#6b7280; flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{!! __('masterfile.rank_no_sequence_summary', ['gl' => $roleShortLabels['GROUP_LEADER'], 'tl' => $roleShortLabels['TEAM_LEADER'], 'introducer' => $roleShortLabels['INTRODUCER']]) !!}</div>
    </div>

    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); display:flex; flex-direction:column; overflow:hidden; flex-shrink:0;">
        <div style="overflow-x:hidden;" id="rr-scroll">
            <table style="width:100%; table-layout:fixed; border-collapse:collapse; font-size:10.5px;">
                <colgroup>
                    <col style="width:9%;"><col style="width:27%;">
                    <col style="width:8%;"><col style="width:8%;"><col style="width:8%;">
                    <col style="width:12%;"><col style="width:28%;">
                </colgroup>
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e0f2fe;">
                        <th style="text-align:center; padding:4px 4px; color:#374151; font-weight:600; white-space:nowrap;">{{ __('masterfile.col_rank_no') }}</th>
                        <th style="text-align:left; padding:4px 6px; color:#374151; font-weight:600; white-space:nowrap;">{{ __('masterfile.col_rank_name') }}</th>
                        <th style="text-align:center; padding:4px 2px; color:#16a34a; font-weight:600; white-space:nowrap;">{{ $roleShortLabels['GROUP_LEADER'] }}</th>
                        <th style="text-align:center; padding:4px 2px; color:#0891b2; font-weight:600; white-space:nowrap;">{{ $roleShortLabels['TEAM_LEADER'] }}</th>
                        <th style="text-align:center; padding:4px 2px; color:#7c3aed; font-weight:600; white-space:nowrap;">{{ $roleShortLabels['INTRODUCER'] }}</th>
                        <th style="text-align:center; padding:4px 4px; color:#374151; font-weight:600; white-space:nowrap;">{{ __('masterfile.status') }}</th>
                        <th style="text-align:center; padding:4px 4px; color:#374151; font-weight:600; white-space:nowrap;">↑ ↓ + ✎ −</th>
                    </tr>
                </thead>
                <tbody id="rr-tbody">
                    @include('masterfile.partials.role-ranks-rows', ['paginator' => $paginator, 'roleShortLabels' => $roleShortLabels])
                </tbody>
            </table>
        </div>
        <div id="rr-footer" style="padding:5px 12px; border-top:1px solid #f3f4f6; flex-shrink:0; display:flex; align-items:center; justify-content:space-between;">
            @include('masterfile.partials.role-ranks-footer', ['paginator' => $paginator])
        </div>
    </div>

</div>

@push('scripts')
<script>
(function(){
    var GROUP_LABEL_ID = @json($groupLabelId);
    var ROLE_SHORT_LABELS = @json($roleShortLabels);
    var RR_I18N = {
        rankNamePlaceholder: @json(__('masterfile.rank_name_placeholder')),
        save: @json(__('masterfile.save_icon_title')),
        cancel: @json(__('masterfile.cancel_icon_title')),
        newLabel: @json(__('masterfile.new_label')),
        active: @json(__('masterfile.active')),
        deleteConfirm: @json(__('masterfile.delete_rank_confirm')),
        rankRemoved: @json(__('masterfile.rank_removed_msg')),
        deleteFailed: @json(__('masterfile.rank_delete_failed_msg')),
        rankAdded: @json(__('masterfile.rank_added_msg')),
        rankUpdated: @json(__('masterfile.rank_updated_msg'))
    };
    var ROLES = ['GROUP_LEADER','TEAM_LEADER','INTRODUCER'];
    var CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var ROWS_URL = "{{ route('admin.masterfile.role-ranks.rows') }}";
    var STORE_URL = "{{ route('admin.masterfile.role-ranks.store') }}";
    var MOVE_UP_TEMPLATE = "{{ route('admin.masterfile.role-ranks.move-up', ['id' => 'RANKID']) }}";
    var MOVE_DOWN_TEMPLATE = "{{ route('admin.masterfile.role-ranks.move-down', ['id' => 'RANKID']) }}";
    var UPDATE_TEMPLATE = "{{ route('admin.masterfile.role-ranks.update', ['id' => 'RANKID']) }}";
    var DESTROY_TEMPLATE = "{{ route('admin.masterfile.role-ranks.destroy', ['id' => 'RANKID']) }}";
    var currentPage = 1;

    function apiHeaders(extra){
        return Object.assign({'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'}, extra || {});
    }

    function refresh(page){
        page = page || currentPage;
        var url = ROWS_URL + '?page=' + page + (GROUP_LABEL_ID ? '&group_label_id=' + encodeURIComponent(GROUP_LABEL_ID) : '');
        fetch(url, {headers: apiHeaders()})
            .then(function(r){ return r.json(); })
            .then(function(data){
                currentPage = page;
                document.getElementById('rr-tbody').innerHTML = data.html;
                document.getElementById('rr-footer').innerHTML = data.footer;
            });
    }

    function flash(msg, isError){
        var box = document.getElementById('rr-flash');
        box.innerHTML = '<div style="background:' + (isError ? '#fee2e2' : '#d1fae5') + '; border:1px solid ' + (isError ? '#fecaca' : '#6ee7b7') + '; border-radius:6px; padding:5px 12px; color:' + (isError ? '#991b1b' : '#065f46') + '; font-size:11px; font-weight:500;">' + (isError ? '⚠ ' : '✅ ') + msg + '</div>';
    }

    function catRadios(selectedRole){
        return ROLES.map(function(role){
            var checked = role === selectedRole ? 'checked' : '';
            return '<label style="display:inline-flex; align-items:center; gap:2px; cursor:pointer; margin:0 4px;">'
                + '<input type="radio" name="rr-cat-radio" value="' + role + '" ' + checked + ' style="margin:0;"> '
                + '<span style="font-size:10px; color:#6b7280;">' + ROLE_SHORT_LABELS[role] + '</span></label>';
        }).join('');
    }

    function iconBtn(symbol, color, action, title){
        return '<button type="button" class="rr-icon" data-action="' + action + '" title="' + title + '" style="background:none; border:1px solid ' + color + '; color:' + color + '; width:16px; height:16px; border-radius:3px; font-size:10.5px; line-height:1; cursor:pointer; padding:0;">' + symbol + '</button>';
    }

    function addRowHtml(afterRankId, defaultRole){
        return '<tr class="rr-edit-row" data-mode="new" data-after="' + (afterRankId || '') + '" style="background:#eef6ff; border-bottom:1px solid #bfdbfe;">'
            + '<td style="padding:3px 6px; text-align:center; font-weight:700; color:#1565C0;">&hellip; <span style="font-size:8px; color:#9ca3af; display:block;">auto</span></td>'
            + '<td style="padding:3px 6px;"><input type="text" class="rr-name-input" placeholder="' + RR_I18N.rankNamePlaceholder + '" style="width:100%; border:1px solid #93c5fd; border-radius:4px; padding:2px 6px; font-size:10.5px; box-sizing:border-box;"></td>'
            + '<td colspan="3" style="padding:3px 6px; text-align:center;">' + catRadios(defaultRole) + '</td>'
            + '<td style="padding:3px 6px; text-align:center; color:#9ca3af; font-size:9px;">' + RR_I18N.newLabel + '</td>'
            + '<td style="padding:3px 6px; text-align:center;"><div style="display:flex; align-items:center; justify-content:center; gap:4px;">'
            + iconBtn('&#10003;', '#16a34a', 'save-new', RR_I18N.save)
            + iconBtn('&#10005;', '#6b7280', 'cancel', RR_I18N.cancel)
            + '</div></td></tr>';
    }

    // Browsers only parse <tr> markup correctly if the Range used to build
    // it is anchored inside a table — otherwise <tr> gets silently
    // dropped by the HTML parser. Anchoring the range to #rr-tbody fixes
    // that for both "add first rank" and "insert after".
    function trFragment(html){
        var tbody = document.getElementById('rr-tbody');
        var range = document.createRange();
        range.selectNodeContents(tbody);
        return range.createContextualFragment(html);
    }

    function editRowEl(tr){
        var rankId = tr.getAttribute('data-rank-id');
        var role = tr.getAttribute('data-role');
        var name = tr.getAttribute('data-rank-name');
        var isActive = tr.getAttribute('data-is-active') === '1';
        var rankNoCell = tr.children[0].innerHTML;
        var newTr = document.createElement('tr');
        newTr.className = 'rr-edit-row';
        newTr.setAttribute('data-mode', 'edit');
        newTr.setAttribute('data-rank-id', rankId);
        newTr.style.background = '#eef6ff';
        newTr.style.borderBottom = '1px solid #bfdbfe';
        newTr.innerHTML = '<td style="padding:3px 6px; text-align:center; font-weight:700; color:#1565C0;">' + rankNoCell + '</td>'
            + '<td style="padding:3px 6px;"><input type="text" class="rr-name-input" value="' + name.replace(/"/g, '&quot;') + '" style="width:100%; border:1px solid #93c5fd; border-radius:4px; padding:2px 6px; font-size:10.5px; box-sizing:border-box;"></td>'
            + '<td colspan="3" style="padding:3px 6px; text-align:center;">' + catRadios(role) + '</td>'
            + '<td style="padding:3px 6px; text-align:center;"><label style="font-size:9px; color:#374151;"><input type="checkbox" class="rr-active-input" ' + (isActive ? 'checked' : '') + ' style="margin-right:3px;">' + RR_I18N.active + '</label></td>'
            + '<td style="padding:3px 6px; text-align:center;"><div style="display:flex; align-items:center; justify-content:center; gap:4px;">'
            + iconBtn('&#10003;', '#16a34a', 'save-edit', RR_I18N.save)
            + iconBtn('&#10005;', '#6b7280', 'cancel', RR_I18N.cancel)
            + '</div></td>';
        return newTr;
    }

    document.addEventListener('click', function(ev){
        var pageBtn = ev.target.closest('.rr-page-btn');
        if (pageBtn){
            refresh(parseInt(pageBtn.getAttribute('data-page'), 10));
            return;
        }

        var addFirstBtn = ev.target.closest('#rr-add-first');
        if (addFirstBtn){
            var tbody = document.getElementById('rr-tbody');
            tbody.innerHTML = '';
            tbody.appendChild(trFragment(addRowHtml('', 'GROUP_LEADER')));
            return;
        }

        var btn = ev.target.closest('.rr-icon');
        if (!btn) return;
        var action = btn.getAttribute('data-action');
        var tr = btn.closest('tr');

        if (action === 'cancel'){
            refresh();
            return;
        }

        if (action === 'add-after'){
            var afterId = tr.getAttribute('data-rank-id');
            var role = tr.getAttribute('data-role');
            var frag = trFragment(addRowHtml(afterId, role));
            tr.parentNode.insertBefore(frag, tr.nextSibling);
            return;
        }

        if (action === 'edit'){
            tr.parentNode.replaceChild(editRowEl(tr), tr);
            return;
        }

        if (action === 'up' || action === 'down'){
            var rankIdMove = tr.getAttribute('data-rank-id');
            var moveTemplate = action === 'up' ? MOVE_UP_TEMPLATE : MOVE_DOWN_TEMPLATE;
            var moveUrl = moveTemplate.replace('RANKID', rankIdMove);
            fetch(moveUrl, {method: 'PATCH', headers: apiHeaders({'Content-Type': 'application/json'})})
                .then(function(r){ return r.json(); })
                .then(function(){ refresh(); });
            return;
        }

        if (action === 'delete'){
            if (!confirm(RR_I18N.deleteConfirm)) return;
            var rankIdDel = tr.getAttribute('data-rank-id');
            var delUrl = DESTROY_TEMPLATE.replace('RANKID', rankIdDel);
            fetch(delUrl, {method: 'DELETE', headers: apiHeaders({'Content-Type': 'application/json'})})
                .then(function(r){ return r.json().then(function(d){ return {ok: r.ok, body: d}; }); })
                .then(function(res){
                    if (res.ok){ flash(res.body.message || RR_I18N.rankRemoved); refresh(); }
                    else { flash(res.body.message || RR_I18N.deleteFailed, true); }
                });
            return;
        }

        if (action === 'save-new'){
            var nameInputNew = tr.querySelector('.rr-name-input');
            var name = nameInputNew.value.trim();
            var roleNew = (tr.querySelector('input[name="rr-cat-radio"]:checked') || {}).value || 'GROUP_LEADER';
            var afterId = tr.getAttribute('data-after');
            if (!name){ nameInputNew.style.borderColor = '#dc2626'; nameInputNew.focus(); return; }
            var body = new URLSearchParams();
            body.set('role', roleNew);
            body.set('rank_name', name);
            if (GROUP_LABEL_ID) body.set('group_label_id', GROUP_LABEL_ID);
            if (afterId) body.set('insert_after_rank_id', afterId);
            fetch(STORE_URL, {method: 'POST', headers: apiHeaders({'Content-Type': 'application/x-www-form-urlencoded'}), body: body.toString()})
                .then(function(r){ return r.json(); })
                .then(function(data){ flash(data.message || RR_I18N.rankAdded); refresh(); });
            return;
        }

        if (action === 'save-edit'){
            var rankIdEdit = tr.getAttribute('data-rank-id');
            var nameInputEdit = tr.querySelector('.rr-name-input');
            var name2 = nameInputEdit.value.trim();
            var roleEdit = (tr.querySelector('input[name="rr-cat-radio"]:checked') || {}).value;
            var isActive = tr.querySelector('.rr-active-input').checked ? '1' : '0';
            if (!name2){ nameInputEdit.style.borderColor = '#dc2626'; nameInputEdit.focus(); return; }
            var editUrl = UPDATE_TEMPLATE.replace('RANKID', rankIdEdit);
            var body2 = new URLSearchParams();
            body2.set('_method', 'PUT');
            body2.set('role', roleEdit);
            body2.set('rank_name', name2);
            body2.set('is_active', isActive);
            fetch(editUrl, {method: 'POST', headers: apiHeaders({'Content-Type': 'application/x-www-form-urlencoded'}), body: body2.toString()})
                .then(function(r){ return r.json(); })
                .then(function(data){ flash(data.message || RR_I18N.rankUpdated); refresh(); });
            return;
        }
    });
})();
</script>
@endpush
@endsection
