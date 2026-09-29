@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.account_categories_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.add_account_category_button') }}</div>
        <a href="{{ route('cbe.accounting.account-categories') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_coa') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; max-width:480px;">
        <form method="POST" action="{{ route('cbe.accounting.account-categories.store') }}">
            @csrf
            <div style="margin-bottom:10px;">
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_category_name') }}</label>
                <input type="text" name="category_name" maxlength="100" required autofocus style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:11px; box-sizing:border-box;">
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_category_name_zh') }}</label>
                <input type="text" name="category_name_zh" maxlength="100" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:11px; box-sizing:border-box;">
            </div>
            <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:8px 24px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.add_account_category_button') }}</button>
        </form>
    </div>
</div>
@endsection
