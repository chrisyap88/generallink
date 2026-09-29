@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', $category ? __('admin_cbe_notice_styles.edit_button') : __('admin_cbe_document_categories.add_button'))

@section('content')
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ $category ? __('admin_cbe_notice_styles.edit_button') : __('admin_cbe_document_categories.add_button') }}</div>
        <a href="{{ route('admin.cbe-document-categories.index') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; overflow-y:auto;">
        <form method="POST" action="{{ $category ? route('admin.cbe-document-categories.update', $category->id) : route('admin.cbe-document-categories.store') }}" style="max-width:420px;">
            @csrf
            @if($category) @method('PUT') @endif

            <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('admin_cbe_document_categories.field_label') }}</label>
            <input type="text" name="label" value="{{ old('label', $category->label ?? '') }}" maxlength="100" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; margin-bottom:14px; box-sizing:border-box;">

            @if($category)
            <label style="display:flex; align-items:center; gap:6px; font-size:10px; color:#374151; margin-bottom:14px; cursor:pointer;">
                <input type="checkbox" name="is_active" value="1" {{ $category->is_system ? 'disabled checked' : '' }} @checked(old('is_active', $category->is_active))> {{ __('admin_cbe_document_categories.field_is_active') }}
            </label>
            @endif

            <div style="display:flex; gap:8px;">
                <a href="{{ route('admin.cbe-document-categories.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:7px 20px; font-size:11.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 20px; font-size:11.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
