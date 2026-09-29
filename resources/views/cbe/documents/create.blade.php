@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_documents.add_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_documents.add_button') }}</div>
        <a href="{{ route('cbe.documents.index') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; overflow-y:auto;">
        <form method="POST" action="{{ route('cbe.documents.store') }}" enctype="multipart/form-data" style="max-width:460px;">
            @csrf

            <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_documents.field_title') }}</label>
            <input type="text" name="title" value="{{ old('title') }}" maxlength="200" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; margin-bottom:12px; box-sizing:border-box;">

            <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_documents.field_category') }}</label>
            <select name="category_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; margin-bottom:12px; box-sizing:border-box;">
                <option value="">{{ __('cbe_documents.field_category_none') }}</option>
                @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected(old('category_id') === $cat->id)>{{ $cat->label }}</option>
                @endforeach
            </select>

            <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_documents.field_description') }}</label>
            <textarea name="description" maxlength="2000" rows="3" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; margin-bottom:12px; box-sizing:border-box; resize:vertical;">{{ old('description') }}</textarea>

            <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_documents.field_file') }}</label>
            <input type="file" name="file" required accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:10.5px; margin-bottom:6px; box-sizing:border-box;">
            <div style="font-size:8.5px; color:#9ca3af; margin-bottom:14px;">{{ __('cbe_documents.field_file_hint') }}</div>

            <div style="display:flex; gap:8px;">
                <a href="{{ route('cbe.documents.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:7px 20px; font-size:11.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:7px 20px; font-size:11.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
