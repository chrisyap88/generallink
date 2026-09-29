@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', $type ? __('admin_practitioner_types.edit_title') : __('admin_practitioner_types.add_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:6px 16px; box-sizing:border-box;">

    <div>
        <a href="{{ route('admin.practitioner-types.index') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('admin_practitioner_types.back_to_types') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:5px 10px; font-size:11px; color:#b71c1c; margin:6px 0;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    <form method="POST" action="{{ $type ? route('admin.practitioner-types.update', $type->id) : route('admin.practitioner-types.store') }}" style="margin-top:6px;">
        @csrf
        @if($type) @method('PUT') @endif

        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; max-width:480px;">

            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('admin_practitioner_types.col_type_label') }} <span style="color:#e53935;">*</span></label>
            <input type="text" name="type_label" value="{{ old('type_label', $type->type_label ?? '') }}" required placeholder="{{ __('admin_practitioner_types.type_label_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box; margin-bottom:10px;">

            @if($type && $type->is_system)
            <div style="font-size:10.5px; color:#94A3B8; margin-bottom:10px;">{{ __('admin_practitioner_types.system_locked_note') }}</div>
            @elseif($type)
            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('masterfile.status') }} <span style="color:#e53935;">*</span></label>
            <select name="is_active" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; background:#fff; box-sizing:border-box; margin-bottom:10px;">
                <option value="1" {{ $type->is_active ? 'selected' : '' }}>{{ __('masterfile.active') }}</option>
                <option value="0" {{ !$type->is_active ? 'selected' : '' }}>{{ __('masterfile.inactive') }}</option>
            </select>
            @endif

            <div style="display:flex; gap:8px;">
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 22px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('masterfile.save') }}</button>
                <a href="{{ route('admin.practitioner-types.index') }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:7px 18px; font-size:12px; font-weight:500;">{{ __('masterfile.cancel') }}</a>
            </div>
        </div>
    </form>

</div>
@endsection
