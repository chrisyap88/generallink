@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', $type ? __('admin_committee_types.edit_title') : __('admin_committee_types.add_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:6px 16px; box-sizing:border-box;">

    <div>
        <a href="{{ route('admin.committee-position-types.index', $groupLabelId ? ['group_label_id' => $groupLabelId] : []) }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('admin_committee_types.back_to_position_types') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:5px 10px; font-size:11px; color:#b71c1c; margin:6px 0;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    <form method="POST" action="{{ $type ? route('admin.committee-position-types.update', $type->id) : route('admin.committee-position-types.store') }}" style="margin-top:6px;">
        @csrf
        @if($type) @method('PUT') @endif

        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; max-width:480px;">

            {{-- NEW 24 Sep 2026 -- per Chris: per-CBE-group scoping (same
                 pattern as Role Ranks). An existing position's group can
                 never change here (matches Role Ranks); a new one is
                 created under whichever group is picked. --}}
            @if($type)
            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('admin_committee_types.group_picker_label') }}</label>
            <div style="font-size:12px; color:#263238; font-weight:600; background:#f0f9ff; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; margin-bottom:10px;">{{ $groupLabelName }}</div>
            @else
            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('admin_committee_types.group_picker_label') }} <span style="color:#e53935;">*</span></label>
            <select name="group_label_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; background:#fff; box-sizing:border-box; margin-bottom:10px;">
                @foreach($cbeGroups as $g)
                <option value="{{ $g->group_label_id }}" {{ old('group_label_id', $groupLabelId) === $g->group_label_id ? 'selected' : '' }}>{{ $g->group_name }}</option>
                @endforeach
            </select>
            @endif

            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('admin_committee_types.col_position_label') }} <span style="color:#e53935;">*</span></label>
            <input type="text" name="position_label" value="{{ old('position_label', $type->position_label ?? '') }}" required placeholder="{{ __('admin_committee_types.position_label_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box; margin-bottom:10px;">

            @if($type)
            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('masterfile.status') }} <span style="color:#e53935;">*</span></label>
            <select name="is_active" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; background:#fff; box-sizing:border-box; margin-bottom:10px;">
                <option value="1" {{ $type->is_active ? 'selected' : '' }}>{{ __('masterfile.active') }}</option>
                <option value="0" {{ !$type->is_active ? 'selected' : '' }}>{{ __('masterfile.inactive') }}</option>
            </select>
            @endif

            <div style="display:flex; gap:8px;">
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 22px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('masterfile.save') }}</button>
                <a href="{{ route('admin.committee-position-types.index', $groupLabelId ? ['group_label_id' => $groupLabelId] : []) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:7px 18px; font-size:12px; font-weight:500;">{{ __('masterfile.cancel') }}</a>
            </div>
        </div>
    </form>

</div>
@endsection
