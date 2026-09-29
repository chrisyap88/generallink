@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', $rank ? __('masterfile.edit_rank_title') : __('masterfile.add_new_rank_title'))
@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px;">

    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; flex-shrink:0;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    <div style="flex-shrink:0;">
    </div>

    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:16px; max-width:480px;">
        <form method="POST" action="{{ $rank ? route('admin.masterfile.role-ranks.update', $rank->rank_id) : route('admin.masterfile.role-ranks.store') }}">
            @csrf
            @if($rank) @method('PUT') @endif
            <input type="hidden" name="group_label_id" value="{{ $groupLabelId }}">

            <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.org_rewards_group_label') }}</label>
            <div style="background:#f0f9ff; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; color:#1565C0; font-weight:600; margin-bottom:10px;">{{ $groupLabelName }}</div>

            @if($rank)
            <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.rank_no_system_assigned') }}</label>
            <div style="background:#f3f4f6; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; color:#1565C0; font-weight:700; margin-bottom:10px;">{{ $rank->rank_no }} <span style="color:#9ca3af; font-weight:400;">{{ __('masterfile.reorder_hint') }}</span></div>
            @endif

            <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:5px;">{{ __('masterfile.org_category_label') }} <span style="color:#dc2626;">*</span> <span style="color:#9ca3af; font-weight:400;">{{ __('masterfile.org_category_pick_one_hint') }}</span></label>
            <div style="display:flex; gap:16px; margin-bottom:2px;">
                @foreach($roleLabels as $roleKey => $roleLabel)
                <label style="display:flex; align-items:center; gap:5px; font-size:11.5px; color:#374151; cursor:pointer;">
                    <input type="radio" name="role" value="{{ $roleKey }}" required {{ old('role', $rank->role ?? '') === $roleKey ? 'checked' : '' }}>
                    {{ $roleShortLabels[$roleKey] }} <span style="color:#9ca3af;">({{ $roleLabel }})</span>
                </label>
                @endforeach
            </div>
            @if($rank)
            <div style="font-size:9px; color:#9ca3af; margin-bottom:10px;">{{ __('masterfile.category_change_note') }}</div>
            @else
            <div style="margin-bottom:10px;"></div>
            @endif

            <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.col_rank_name') }} <span style="color:#dc2626;">*</span></label>
            <input type="text" name="rank_name" value="{{ old('rank_name', $rank->rank_name ?? '') }}" required maxlength="100" placeholder="e.g. CEO, Regional Director, Store Manager" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box; margin-bottom:10px;">

            @if($rank)
            <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.status') }} <span style="color:#dc2626;">*</span></label>
            <select name="is_active" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; background:#fff; box-sizing:border-box; margin-bottom:10px;">
                <option value="1" {{ $rank->is_active ? 'selected' : '' }}>{{ __('masterfile.active') }}</option>
                <option value="0" {{ !$rank->is_active ? 'selected' : '' }}>{{ __('masterfile.inactive') }}</option>
            </select>
            @endif

            <div style="display:flex; gap:8px; margin-top:6px;">
                <a href="{{ route('admin.masterfile.role-ranks', $groupLabelId ? ['group_label_id'=>$groupLabelId] : []) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 18px; font-size:12px; font-weight:700; display:inline-flex; align-items:center;">{{ __('masterfile.prev') }}</a>
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 24px; font-size:12px; font-weight:600; cursor:pointer;">💾 {{ __('masterfile.save') }}</button>
            </div>
        </form>
    </div>

</div>
@endsection
