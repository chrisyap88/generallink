@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', $reasonCode ? __('masterfile.edit_reason_code_title') : __('masterfile.add_reason_code_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:6px 16px; box-sizing:border-box;">

    <div>
        <a href="{{ route('admin.masterfile.reason-codes') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">&larr; {{ __('masterfile.back_to_reason_code_maintenance') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:5px 10px; font-size:11px; color:#b71c1c; margin:6px 0;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    <form method="POST" action="{{ $reasonCode ? route('admin.masterfile.reason-codes.update', $reasonCode->reason_code_id) : route('admin.masterfile.reason-codes.store') }}" style="margin-top:6px;">
        @csrf
        @if($reasonCode) @method('PUT') @endif

        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; max-width:480px;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:10px;">
                <div>
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('masterfile.col_category') }} <span style="color:#e53935;">*</span></label>
                    @if($reasonCode)
                        <input type="text" name="category" value="{{ old('category', $reasonCode->category) }}" required list="categoryList" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box;">
                    @else
                        <input type="text" name="category" value="{{ old('category') }}" required list="categoryList" placeholder="{{ __('masterfile.placeholder_category_example') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box;">
                    @endif
                    <datalist id="categoryList">
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}">
                        @endforeach
                    </datalist>
                </div>
                <div>
                    <label style="font-size:10px; font-weight:600; color:#9ca3af; display:block; margin-bottom:4px;">{{ __('masterfile.col_code') }} @if($reasonCode)<span style="font-weight:400;">({{ __('masterfile.locked_label') }})</span>@endif</label>
                    @if($reasonCode)
                        <input type="text" value="{{ $reasonCode->code }}" disabled style="width:100%; border:1px solid #B2EBF2; border-radius:6px; padding:7px 10px; font-size:12px; background:#E0F7FA; color:#1a1a1a; box-sizing:border-box;">
                    @else
                        <input type="text" name="code" value="{{ old('code') }}" required placeholder="{{ __('masterfile.placeholder_code_example') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box;">
                    @endif
                </div>
            </div>

            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('masterfile.col_description') }} <span style="color:#e53935;">*</span></label>
            <input type="text" name="description" value="{{ old('description', $reasonCode->description ?? '') }}" required placeholder="{{ __('masterfile.placeholder_description_example') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box; margin-bottom:10px;">

            @if($reasonCode)
            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('masterfile.status') }} <span style="color:#e53935;">*</span></label>
            <select name="is_active" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; background:#fff; box-sizing:border-box; margin-bottom:10px;">
                <option value="1" {{ $reasonCode->is_active ? 'selected' : '' }}>{{ __('masterfile.active') }}</option>
                <option value="0" {{ !$reasonCode->is_active ? 'selected' : '' }}>{{ __('masterfile.inactive') }}</option>
            </select>
            @endif

            <div style="display:flex; gap:8px;">
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 22px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('masterfile.save') }}</button>
                <a href="{{ route('admin.masterfile.reason-codes') }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:7px 18px; font-size:12px; font-weight:500;">{{ __('masterfile.cancel') }}</a>
            </div>
        </div>
    </form>

</div>
@endsection
