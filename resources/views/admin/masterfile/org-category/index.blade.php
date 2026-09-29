@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('masterfile.org_category_maintenance_title'))

{{-- NEW 23 Jul 2026 — per Chris: "create an option for me to rename or
     edit the 3 level Group Leader Team Leader ... as a sub menu under
     Master [File Maintenance] as Organization Category structure ...
     just an edit function for me to change if is for different
     company". Exactly 3 fixed rows — GROUP_LEADER / TEAM_LEADER /
     INTRODUCER — no add, no delete, just renaming the display label.
     Does NOT touch the underlying `role` column, permissions, or
     commission logic anywhere — this only changes what these 3 roles
     are CALLED on screen. See App\Services\RoleLabelService for
     exactly which screens read this so far (sidebar badge + the 3
     Tier Structure Maintenance menu items) — most other screens still
     show the original words and will be wired up in a later pass. --}}

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:6px 16px; box-sizing:border-box;">

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:6px 12px; font-size:11px; margin-bottom:8px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2; border-left:3px solid #e53935; color:#991b1b; border-radius:6px; padding:6px 12px; font-size:11px; margin-bottom:8px;">
        @foreach($errors->all() as $e)
            <div>{{ $e }}</div>
        @endforeach
    </div>
    @endif

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
        <a href="{{ route('admin.dashboard') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('masterfile.back_to_dashboard') }}</a>
        <span style="width:110px;"></span>
    </div>

    <div style="background:#EBF5FB; border:1px solid #B2EBF2; border-radius:8px; padding:5px 12px; margin-bottom:8px; font-size:9.5px; color:#374151; line-height:1.5;">
        {!! __('masterfile.org_category_intro', ['max' => $maxLength, 'maxShort' => $maxShortLength]) !!}
    </div>

    @if($groupLabels->isEmpty())
    <div style="background:#fff8e1; border:1px solid #ffe082; border-radius:8px; padding:14px; font-size:11.5px; color:#92400e;">
        {{ __('masterfile.no_org_rewards_groups_yet') }}
    </div>
    @else

    <form method="GET" action="{{ route('admin.masterfile.org-category') }}" style="margin-bottom:8px; display:flex; align-items:center; gap:8px;">
        <label style="font-size:10.5px; font-weight:600; color:#374151;">{{ __('masterfile.editing_labels_for_label') }}</label>
        <select name="group_label_id" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 8px; font-size:11.5px; background:#fff;">
            @foreach($groupLabels as $g)
            <option value="{{ $g->group_label_id }}" {{ $groupLabelId === $g->group_label_id ? 'selected' : '' }}>{{ $g->group_name }}</option>
            @endforeach
        </select>
    </form>

    <form method="POST" action="{{ route('admin.masterfile.org-category.update') }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="group_label_id" value="{{ $groupLabelId }}">

        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:12px;">
                <thead>
                    <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                        <th style="text-align:left; padding:6px 10px; font-size:10.5px; color:#374151;">{{ __('masterfile.col_original_name') }}</th>
                        <th style="text-align:left; padding:6px 10px; font-size:10.5px; color:#374151;">{{ __('masterfile.col_your_label') }}</th>
                        <th style="text-align:left; padding:6px 10px; font-size:10.5px; color:#374151;">{{ __('masterfile.col_short_label') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 10px; color:#6b7280;">{{ $defaults[$row->role] }}</td>
                        <td style="padding:5px 10px;">
                            <input
                                type="text"
                                name="labels[{{ $row->role }}]"
                                value="{{ old('labels.' . $row->role, $row->label) }}"
                                maxlength="{{ $maxLength }}"
                                required
                                oninput="document.getElementById('cnt_{{ $row->role }}').textContent = this.value.length"
                                style="border:1px solid #d1d5db; border-radius:5px; padding:4px 8px; font-size:11.5px; width:220px; box-sizing:border-box;"
                            >
                            <span style="font-size:9px; color:#9ca3af; margin-left:6px;"><span id="cnt_{{ $row->role }}">{{ strlen($row->label) }}</span> / {{ $maxLength }}</span>
                        </td>
                        <td style="padding:5px 10px;">
                            <input
                                type="text"
                                name="short_labels[{{ $row->role }}]"
                                value="{{ old('short_labels.' . $row->role, $row->short_label) }}"
                                maxlength="{{ $maxShortLength }}"
                                placeholder="{{ $autoShort[$row->role] }}"
                                style="border:1px solid #d1d5db; border-radius:5px; padding:4px 8px; font-size:11.5px; width:70px; box-sizing:border-box;"
                            >
                            <span style="font-size:9px; color:#9ca3af; margin-left:4px;">{{ __('masterfile.eg_dynamic', ['value' => $autoShort[$row->role]]) }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="display:flex; justify-content:flex-end; align-items:center; gap:8px; margin-top:8px;">
            {{-- type="button" on purpose — this does NOT submit the update
                 form above (which spoofs PUT via @method); it just submits
                 the separate hidden reset form below via JS, so both
                 buttons can sit on one row without a method conflict. --}}
            <button type="button" onclick="if(confirm({{ json_encode(__('masterfile.restore_defaults_confirm')) }})){document.getElementById('resetForm').submit();}" style="background:#f3f4f6; color:#374151; border:1px solid #d1d5db; border-radius:6px; padding:6px 14px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('masterfile.reset_to_defaults_button') }}</button>
            <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:6px 22px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('masterfile.save') }}</button>
        </div>
    </form>

    <form id="resetForm" method="POST" action="{{ route('admin.masterfile.org-category.reset') }}" style="display:none;">
        @csrf
        <input type="hidden" name="group_label_id" value="{{ $groupLabelId }}">
    </form>

    @endif

</div>
@endsection
