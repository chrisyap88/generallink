@extends('layouts.dashboard')

@section('page-title', __('growth.preview_survey_title'))

@section('content')

{{-- NEW 25 Jul 2026 — Survey Management Module, Phase 1 (task #231).
     Read-only render of exactly what a respondent will see — spec
     section 6 "Preview Survey" action. Inputs are disabled; this is
     not the live public response form (the real one is
     public.survey.show, built in Phase 2 — task #232). --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px;">
        {{-- Plain text link, NOT a blue pill — the real Prev lives at the
             bottom-left of this screen (see footer below). --}}
        <a href="{{ route('admin.growth.surveys.builder', $survey->survey_id) }}" style="color:#1565C0; text-decoration:none; font-size:9.5px; font-weight:600; display:inline-block; margin-bottom:4px;">{{ __('growth.question_builder_link') }}</a>
        <div style="font-size:9px; color:#7c3aed; font-weight:700;">{{ __('growth.preview_mode_banner') }}</div>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:10px; padding:16px 20px; flex:1; min-height:0; overflow-y:auto; max-width:640px; margin:0 auto; width:100%;">
        <h3 style="margin:0 0 4px; font-size:15px; color:#111827;">{{ $survey->title }}</h3>
        @if($survey->description)<div style="font-size:11px; color:#6b7280; margin-bottom:12px;">{{ $survey->description }}</div>@endif

        @foreach($questions as $i => $q)
        <div style="border-top:1px solid #f3f4f6; padding:10px 0;">
            <div style="font-size:11px; font-weight:600; color:#111827;">{{ $i+1 }}. {{ $q->question_text }} @if($q->is_required)<span style="color:#dc2626;">*</span>@endif</div>
            @if($q->question_description)<div style="font-size:9.5px; color:#6b7280; margin-top:2px;">{{ $q->question_description }}</div>@endif

            <div style="margin-top:6px;">
                @switch($q->question_type)
                    @case('SINGLE_CHOICE')
                    @case('YES_NO')
                        @foreach($q->question_type === 'YES_NO' ? collect([(object)['option_text'=>__('growth.yes_option')],(object)['option_text'=>__('growth.no_option')]]) : $q->question_options as $o)
                        <label style="display:block; font-size:10.5px; color:#374151; margin-bottom:3px;"><input type="radio" disabled> {{ $o->option_text }}</label>
                        @endforeach
                        @break
                    @case('MULTIPLE_CHOICE')
                        @foreach($q->question_options as $o)
                        <label style="display:block; font-size:10.5px; color:#374151; margin-bottom:3px;"><input type="checkbox" disabled> {{ $o->option_text }}</label>
                        @endforeach
                        @break
                    @case('DROPDOWN')
                        <select disabled style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#f9fafb;">
                            <option>{{ __('growth.select_dash_dash') }}</option>
                            @foreach($q->question_options as $o)<option>{{ $o->option_text }}</option>@endforeach
                        </select>
                        @break
                    @case('RATING_SCALE')
                    @case('NUMERIC_RATING')
                        <div style="display:flex; gap:4px;">
                            @for($n=1; $n<=($q->scale_max ?? 5); $n++)<div style="width:24px; height:24px; border:1px solid #d1d5db; border-radius:4px; display:flex; align-items:center; justify-content:center; font-size:9.5px; color:#6b7280;">{{ $n }}</div>@endfor
                        </div>
                        @break
                    @case('STAR_RATING')
                        <div style="font-size:16px; color:#d1d5db;">{{ str_repeat('★ ', $q->scale_max ?? 5) }}</div>
                        @break
                    @case('LIKERT_SCALE')
                        <div style="display:grid; grid-template-columns:repeat({{ $q->scale_max ?? 5 }}, 1fr); gap:3px; font-size:8px; color:#6b7280; text-align:center;">
                            @for($n=1; $n<=($q->scale_max ?? 5); $n++)<div><input type="radio" disabled><div>{{ $n }}</div></div>@endfor
                        </div>
                        @break
                    @case('SHORT_TEXT')
                    @case('EMAIL')
                    @case('PHONE')
                        <input type="text" disabled placeholder="{{ $q->question_type }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#f9fafb; box-sizing:border-box;">
                        @break
                    @case('LONG_TEXT')
                        <textarea disabled rows="3" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#f9fafb; box-sizing:border-box;"></textarea>
                        @break
                    @case('DATE')
                        <input type="date" disabled style="border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#f9fafb;">
                        @break
                    @case('TIME')
                        <input type="time" disabled style="border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#f9fafb;">
                        @break
                    @case('NUMBER')
                        <input type="number" disabled placeholder="{{ $q->min_value !== null ? __('growth.min_value_placeholder', ['value' => $q->min_value]) : '' }} {{ $q->max_value !== null ? __('growth.max_value_placeholder', ['value' => $q->max_value]) : '' }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#f9fafb; box-sizing:border-box;">
                        @break
                    @case('MATRIX')
                        @if($q->matrix)
                        <table style="width:100%; border-collapse:collapse; font-size:9px;">
                            <tr><td></td>@foreach($q->matrix['columns'] ?? [] as $col)<td style="text-align:center; padding:3px; color:#6b7280;">{{ $col }}</td>@endforeach</tr>
                            @foreach($q->matrix['rows'] ?? [] as $row)
                            <tr><td style="padding:3px; color:#374151;">{{ $row }}</td>@foreach($q->matrix['columns'] ?? [] as $col)<td style="text-align:center;"><input type="radio" disabled></td>@endforeach</tr>
                            @endforeach
                        </table>
                        @endif
                        @break
                @endswitch
            </div>
            @if($q->help_text)<div style="font-size:8.5px; color:#9ca3af; margin-top:3px;">💡 {{ $q->help_text }}</div>@endif
        </div>
        @endforeach

        @if($questions->isEmpty())
        <div style="text-align:center; color:#9ca3af; padding:24px 0; font-size:10.5px;">{{ __('growth.no_questions_added_yet') }}</div>
        @endif
    </div>

    {{-- Prev, filled blue, bottom-left — per Chris's standing rule. --}}
    <div style="flex-shrink:0; padding-top:6px;">
        <a href="{{ route('admin.growth.surveys.builder', $survey->survey_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700; display:inline-block;">{{ __('growth.prev') }}</a>
    </div>
</div>
@endsection
