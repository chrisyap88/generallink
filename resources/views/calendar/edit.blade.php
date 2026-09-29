@extends('layouts.dashboard')

@section('page-title', $event ? __('calendar.edit_event_title') : __('calendar.add_new_event_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:6px 16px; box-sizing:border-box;">

    <div>
        <a href="{{ route('calendar.index') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('calendar.back_to_calendar_link') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:5px 10px; font-size:11px; color:#b71c1c; margin:6px 0;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    <form method="POST" action="{{ $event ? route('admin.calendar.update', $event->event_id) : route('admin.calendar.store') }}" style="margin-top:6px;">
        @csrf
        @if($event) @method('PUT') @endif

        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; max-width:480px;">
            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('growth.title_label') }} <span style="color:#e53935;">*</span></label>
            <input type="text" name="title" value="{{ old('title', $event->title ?? '') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box; margin-bottom:10px;">

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-bottom:10px;">
                <div>
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('calendar.field_date_label') }} <span style="color:#e53935;">*</span></label>
                    <input type="date" name="event_date" value="{{ old('event_date', $event->event_date ?? '') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('calendar.field_time_optional_label') }} <span style="font-weight:400; color:#9ca3af;">({{ __('network.optional_placeholder') }})</span></label>
                    <input type="time" name="event_time" value="{{ old('event_time', $event->event_time ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('calendar.field_type_label') }} <span style="color:#e53935;">*</span></label>
                    <select name="event_type" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; background:#fff; box-sizing:border-box;">
                        <option value="BRIEFING" {{ old('event_type', $event->event_type ?? '')=='BRIEFING'?'selected':'' }}>{{ __('calendar.event_type_briefing') }}</option>
                        <option value="TRAINING" {{ old('event_type', $event->event_type ?? '')=='TRAINING'?'selected':'' }}>{{ __('calendar.event_type_training') }}</option>
                        <option value="NEWS" {{ old('event_type', $event->event_type ?? '')=='NEWS'?'selected':'' }}>{{ __('calendar.event_type_news') }}</option>
                        <option value="OTHER" {{ old('event_type', $event->event_type ?? '')=='OTHER'?'selected':'' }}>{{ __('calendar.event_type_other') }}</option>
                    </select>
                </div>
            </div>

            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('calendar.field_description_optional_label') }} <span style="font-weight:400; color:#9ca3af;">({{ __('network.optional_placeholder') }})</span></label>
            <textarea name="description" rows="3" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box; margin-bottom:10px; resize:vertical;">{{ old('description', $event->description ?? '') }}</textarea>

            <div style="display:flex; gap:8px;">
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 22px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('integrations.save_button') }}</button>
                <a href="{{ route('calendar.index') }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:7px 18px; font-size:12px; font-weight:500;">{{ __('gl.cancel_button') }}</a>
            </div>
        </div>
    </form>

</div>
@endsection
