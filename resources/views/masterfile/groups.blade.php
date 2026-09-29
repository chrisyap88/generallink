@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.groups_title'))
@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; flex-shrink:0;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    {{-- MAIN --}}
    @if($mode === 'main')
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:20px; flex-shrink:0;">
        <div style="font-size:14px; font-weight:700; color:#1565C0; margin-bottom:16px;">{{ __('masterfile.group_management') }}</div>
        <div style="display:flex; gap:16px;">
            <a href="{{ route('admin.masterfile.groups', ['mode'=>'add']) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:8px; padding:12px 30px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:8px; box-shadow:0 2px 8px rgba(21,101,192,.3);">➕ {{ __('masterfile.add_new_group') }}</a>
            <a href="{{ route('admin.masterfile.groups', ['mode'=>'search']) }}" style="background:#fff; color:#1565C0; text-decoration:none; border-radius:8px; padding:12px 30px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:8px; box-shadow:0 2px 8px rgba(0,0,0,.08); border:2px solid #1565C0;">🔍 {{ __('masterfile.search_group') }}</a>
        </div>
    </div>

    {{-- ADD --}}
    @elseif($mode === 'add')
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0;">
        <div style="padding:10px 16px; border-bottom:1px solid #e0f2fe; display:flex; align-items:center; justify-content:space-between;">
            <div style="font-size:13px; font-weight:700; color:#1565C0;">➕ {{ __('masterfile.add_new_group') }}</div>
            <a href="{{ route('admin.masterfile.groups', ['mode'=>'main']) }}" style="font-size:11px; color:#6b7280; text-decoration:none;">← {{ __('masterfile.back') }}</a>
        </div>
        <div style="padding:12px 16px;">
            <form method="POST" action="{{ route('admin.masterfile.groups.store') }}" autocomplete="off">
                @csrf
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:10px;">
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_group_name') }} <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="group_name" value="{{ old('group_name') }}" required maxlength="200" placeholder="{{ __('masterfile.placeholder_group_name_example') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.group_code_label') }} <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="group_code" value="{{ old('group_code') }}" required maxlength="20" placeholder="{{ __('masterfile.placeholder_group_code_example') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()">
                        <div style="font-size:10px; color:#6b7280; margin-top:2px;">{{ __('masterfile.group_code_hint') }}</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.group_email_label') }} <span style="color:#ef4444;">*</span></label>
                        <input type="email" name="group_email" value="{{ old('group_email') }}" required placeholder="group@example.com" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.member_code_separator_label') }}</label>
                        <select name="separator_char" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                            <option value="-" {{ old('separator_char','-') === '-' ? 'selected' : '' }}>{{ __('masterfile.hyphen_option') }}</option>
                            <option value="." {{ old('separator_char') === '.' ? 'selected' : '' }}>{{ __('masterfile.dot_option') }}</option>
                            <option value="_" {{ old('separator_char') === '_' ? 'selected' : '' }}>{{ __('masterfile.underscore_option') }}</option>
                        </select>
                        <div style="font-size:10px; color:#6b7280; margin-top:2px;">{{ __('masterfile.separator_hint') }}</div>
                    </div>
                    <div style="grid-column:span 2; background:#f0f9ff; border-radius:8px; padding:10px; font-size:10px; color:#0369a1; border:1px solid #e0f2fe; display:flex; align-items:center;">
                        ℹ️ {{ __('masterfile.group_activation_note', ['role' => \App\Services\RoleLabelService::label('GROUP_LEADER')]) }}
                    </div>
                    <div style="grid-column:span 3; display:flex; gap:8px; margin-top:4px;">
                        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 28px; font-size:12px; font-weight:600; cursor:pointer;">➕ {{ __('masterfile.create_group_btn') }}</button>
                        <a href="{{ route('admin.masterfile.groups', ['mode'=>'main']) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600;">{{ __('masterfile.cancel') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- SEARCH CRITERIA --}}
    @elseif($mode === 'search' && $groups === null)
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0;">
        <div style="padding:10px 16px; border-bottom:1px solid #e0f2fe; display:flex; align-items:center; justify-content:space-between;">
            <div style="font-size:13px; font-weight:700; color:#1565C0;">🔍 {{ __('masterfile.search_group') }}</div>
            <a href="{{ route('admin.masterfile.groups', ['mode'=>'main']) }}" style="font-size:11px; color:#6b7280; text-decoration:none;">← {{ __('masterfile.back') }}</a>
        </div>
        <div style="padding:12px 16px;">
            <form method="GET" action="{{ route('admin.masterfile.groups') }}" autocomplete="off">
                <input type="hidden" name="mode" value="search">
                <div style="display:grid; grid-template-columns:repeat(5,1fr); gap:8px;">
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_group_name') }}</label>
                        <input type="text" name="group_name" value="{{ request('group_name') }}" placeholder="{{ __('masterfile.any_part_of_name') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.group_code_label') }}</label>
                        <input type="text" name="group_code" value="{{ request('group_code') }}" placeholder="{{ __('masterfile.placeholder_group_code_example') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.email') }}</label>
                        <input type="text" name="group_email" value="{{ request('group_email') }}" placeholder="{{ __('masterfile.any_part') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.status') }}</label>
                        <select name="is_active" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                            <option value="">{{ __('masterfile.all_option') }}</option>
                            <option value="1" {{ request('is_active')==='1' ? 'selected' : '' }}>{{ __('masterfile.active') }}</option>
                            <option value="0" {{ request('is_active')==='0' ? 'selected' : '' }}>{{ __('masterfile.inactive') }}</option>
                        </select>
                    </div>
                    <div style="display:flex; align-items:flex-end; gap:8px;">
                        <button type="submit" name="do_search" value="1" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 24px; font-size:12px; font-weight:600; cursor:pointer;">🔍 {{ __('masterfile.search') }}</button>
                        <a href="{{ route('admin.masterfile.groups', ['mode'=>'search']) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:7px 16px; font-size:12px;">{{ __('masterfile.clear_btn') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- SEARCH RESULTS --}}
    @elseif($mode === 'search' && $groups !== null)
    @php $backToResults = request()->fullUrl(); @endphp
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0; padding:10px 16px; display:flex; align-items:center; justify-content:space-between;">
        <div style="font-size:11px; font-weight:700; color:#1565C0;">{{ __('masterfile.search_results_count', ['count' => $groups->total()]) }}</div>
        <div style="display:flex; gap:8px; align-items:center;">
            <a href="{{ route('admin.masterfile.groups', ['mode'=>'search']) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:600;">← {{ __('masterfile.new_search') }}</a>
            <a href="{{ route('admin.masterfile.groups', ['mode'=>'main']) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:600;">🏠 {{ __('masterfile.main_btn') }}</a>
        </div>
    </div>
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        <div style="flex:1; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:11px;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:2px solid #e0f2fe; position:sticky; top:0; z-index:1;">
                        <th style="text-align:center; padding:8px 10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.col_no') }}</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.col_group_name') }}</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.col_code') }}</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.email') }}</th>
                        <th style="text-align:center; padding:8px 10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.col_separator') }}</th>
                        <th style="text-align:center; padding:8px 10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.status') }}</th>
                        <th style="text-align:center; padding:8px 10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.col_created') }}</th>
                        <th style="text-align:center; padding:8px 10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.col_action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($groups as $group)
                    <tr style="border-bottom:1px solid #f3f4f6; {{ $loop->even ? 'background:#fafafa;' : '' }}">
                        <td style="padding:8px 10px; text-align:center; color:#9ca3af;">{{ $loop->iteration }}</td>
                        <td style="padding:8px 10px; font-weight:600; color:#111827;">{{ $group->group_name }}</td>
                        <td style="padding:8px 10px; font-family:monospace; color:#6b7280;">{{ $group->group_code }}</td>
                        <td style="padding:8px 10px;">{{ $group->group_email }}</td>
                        <td style="padding:8px 10px; text-align:center; font-family:monospace; font-weight:700; color:#1565C0;">{{ $group->separator_char }}</td>
                        <td style="padding:8px 10px; text-align:center;"><span style="background:{{ $group->is_active ? '#d1fae5' : '#fee2e2' }}; color:{{ $group->is_active ? '#065f46' : '#991b1b' }}; font-size:10px; font-weight:600; padding:2px 8px; border-radius:20px;">{{ $group->is_active ? __('masterfile.active') : __('masterfile.inactive') }}</span></td>
                        <td style="padding:8px 10px; text-align:center; font-size:10px; color:#6b7280;">{{ $group->created_at ? \Carbon\Carbon::parse($group->created_at)->format('d M Y') : '—' }}</td>
                        <td style="padding:8px 10px; text-align:center;">
                            <a href="{{ route('admin.masterfile.groups', ['mode'=>'edit', 'group_id'=>$group->group_id, 'back'=>urlencode($backToResults)]) }}" style="background:#e0f2fe; color:#1565C0; text-decoration:none; border-radius:6px; padding:3px 10px; font-size:10px; font-weight:600;">✏️ {{ __('masterfile.edit') }}</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="padding:8px 12px; border-top:1px solid #f3f4f6; flex-shrink:0; display:flex; align-items:center; justify-content:space-between;">
            @if($groups->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</span>
            @else
                <a href="{{ $groups->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</a>
            @endif
            <span style="font-size:11px; color:#6b7280;">{{ __('masterfile.showing_records', ['first' => $groups->firstItem(), 'last' => $groups->lastItem(), 'total' => $groups->total()]) }} &middot; {{ __('masterfile.page_of', ['current' => $groups->currentPage(), 'last' => $groups->lastPage()]) }}</span>
            @if($groups->hasMorePages())
                <a href="{{ $groups->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</span>
            @endif
        </div>
    </div>

    {{-- EDIT GROUP --}}
    @elseif($mode === 'edit' && $selectedGroup)
    @php $backUrl = request('back') ? urldecode(request('back')) : route('admin.masterfile.groups', ['mode'=>'search']); @endphp
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0;">
        <div style="padding:10px 16px; border-bottom:1px solid #e0f2fe; display:flex; align-items:center; justify-content:space-between;">
            <div style="font-size:13px; font-weight:700; color:#1565C0;">✏️ {{ __('masterfile.edit_group_dash', ['name' => $selectedGroup->group_name]) }}</div>
            <div style="display:flex; gap:10px; align-items:center;">
                <a href="{{ $backUrl }}" style="font-size:11px; color:#6b7280; text-decoration:none;">← {{ __('masterfile.back_to_results') }}</a>
                <a href="{{ route('admin.masterfile.groups', ['mode'=>'main']) }}" style="font-size:11px; color:#6b7280; text-decoration:none;">🏠 {{ __('masterfile.main_btn') }}</a>
            </div>
        </div>
        <div style="padding:12px 16px;">
            <form method="POST" action="{{ route('admin.masterfile.groups.update', $selectedGroup->group_id) }}" autocomplete="off">
                @csrf @method('PUT')
                <input type="hidden" name="back" value="{{ urlencode($backUrl) }}">
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:10px;">
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_group_name') }} <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="group_name" value="{{ old('group_name', $selectedGroup->group_name) }}" required maxlength="200" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.group_code_label') }} <span style="color:#9ca3af;">({{ __('masterfile.fixed_label') }})</span></label>
                        <div style="background:#f3f4f6; border:1px solid #d1d5db; border-radius:5px; padding:6px 10px; font-size:11px; font-family:monospace;">{{ $selectedGroup->group_code }}</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.group_email_label') }} <span style="color:#ef4444;">*</span></label>
                        <input type="email" name="group_email" value="{{ old('group_email', $selectedGroup->group_email) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.member_code_separator_label') }}</label>
                        <select name="separator_char" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                            <option value="-" {{ old('separator_char', $selectedGroup->separator_char) === '-' ? 'selected' : '' }}>{{ __('masterfile.hyphen_option') }}</option>
                            <option value="." {{ old('separator_char', $selectedGroup->separator_char) === '.' ? 'selected' : '' }}>{{ __('masterfile.dot_option') }}</option>
                            <option value="_" {{ old('separator_char', $selectedGroup->separator_char) === '_' ? 'selected' : '' }}>{{ __('masterfile.underscore_option') }}</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.status') }} <span style="color:#ef4444;">*</span></label>
                        <select name="is_active" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                            <option value="1" {{ old('is_active', $selectedGroup->is_active)==1 ? 'selected' : '' }}>{{ __('masterfile.active') }}</option>
                            <option value="0" {{ old('is_active', $selectedGroup->is_active)==0 ? 'selected' : '' }}>{{ __('masterfile.inactive') }}</option>
                        </select>
                    </div>
                    <div style="grid-column:span 3; display:flex; gap:8px; margin-top:4px;">
                        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 28px; font-size:12px; font-weight:600; cursor:pointer;">💾 {{ __('masterfile.update_group_btn') }}</button>
                        <a href="{{ $backUrl }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600;">{{ __('masterfile.cancel') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>
@endsection
