@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', $theme ? __('admin_cbe_notice_styles.edit_button') : __('admin_cbe_season_themes.add_button'))

@section('content')
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ $theme ? __('admin_cbe_notice_styles.edit_button') : __('admin_cbe_season_themes.add_button') }}</div>
        <a href="{{ route('admin.cbe-season-themes.index') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; overflow-y:auto;">
        <form method="POST" action="{{ $theme ? route('admin.cbe-season-themes.update', $theme->id) : route('admin.cbe-season-themes.store') }}" style="max-width:480px;">
            @csrf
            @if($theme) @method('PUT') @endif

            <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('admin_cbe_notice_styles.field_label') }}</label>
            <input type="text" name="label" value="{{ old('label', $theme->label ?? '') }}" maxlength="60" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; margin-bottom:10px; box-sizing:border-box;">

            <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('admin_cbe_season_themes.field_date_range') }}</label>
            <div style="display:flex; align-items:center; gap:6px; margin-bottom:4px;">
                <input type="number" name="start_day" min="1" max="31" value="{{ old('start_day', $theme->start_day ?? '') }}" required placeholder="{{ __('admin_cbe_season_themes.day_placeholder') }}" style="width:60px; border:1px solid #d1d5db; border-radius:6px; padding:7px; font-size:11px; box-sizing:border-box;">
                <input type="number" name="start_month" min="1" max="12" value="{{ old('start_month', $theme->start_month ?? '') }}" required placeholder="{{ __('admin_cbe_season_themes.month_placeholder') }}" style="width:60px; border:1px solid #d1d5db; border-radius:6px; padding:7px; font-size:11px; box-sizing:border-box;">
                <span style="color:#9ca3af; font-size:10px;">{{ __('admin_cbe_season_themes.range_to') }}</span>
                <input type="number" name="end_day" min="1" max="31" value="{{ old('end_day', $theme->end_day ?? '') }}" required placeholder="{{ __('admin_cbe_season_themes.day_placeholder') }}" style="width:60px; border:1px solid #d1d5db; border-radius:6px; padding:7px; font-size:11px; box-sizing:border-box;">
                <input type="number" name="end_month" min="1" max="12" value="{{ old('end_month', $theme->end_month ?? '') }}" required placeholder="{{ __('admin_cbe_season_themes.month_placeholder') }}" style="width:60px; border:1px solid #d1d5db; border-radius:6px; padding:7px; font-size:11px; box-sizing:border-box;">
            </div>
            <div style="font-size:8.5px; color:#9ca3af; margin-bottom:10px;">{{ __('admin_cbe_season_themes.date_range_hint') }}</div>

            <div style="display:flex; gap:10px; margin-bottom:10px;">
                <div style="flex:1;">
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('admin_cbe_notice_styles.field_bg_color') }}</label>
                    <input type="color" name="bg_color" value="{{ old('bg_color', $theme->bg_color ?? '#0B3D2E') }}" style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:6px;">
                </div>
                <div style="flex:1;">
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('admin_cbe_notice_styles.field_accent_color') }}</label>
                    <input type="color" name="accent_color" value="{{ old('accent_color', $theme->accent_color ?? '#C1272D') }}" style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:6px;">
                </div>
            </div>

            <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('admin_cbe_season_themes.field_greeting') }}</label>
            <input type="text" name="greeting_text" value="{{ old('greeting_text', $theme->greeting_text ?? '') }}" maxlength="200" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; margin-bottom:10px; box-sizing:border-box;">

            <label style="display:flex; align-items:center; gap:6px; font-size:10px; color:#374151; margin-bottom:8px; cursor:pointer;">
                <input type="checkbox" name="post_greeting_notice" value="1" @checked(old('post_greeting_notice', $theme->post_greeting_notice ?? true))> {{ __('admin_cbe_season_themes.field_post_greeting') }}
            </label>
            <label style="display:flex; align-items:center; gap:6px; font-size:10px; color:#374151; margin-bottom:14px; cursor:pointer;">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $theme->is_active ?? true))> {{ __('admin_cbe_notice_styles.field_is_active') }}
            </label>

            <div style="display:flex; gap:8px;">
                <a href="{{ route('admin.cbe-season-themes.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:7px 20px; font-size:11.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 20px; font-size:11.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
