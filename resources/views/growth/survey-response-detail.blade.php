@extends('layouts.dashboard')

@section('page-title', __('growth.response_detail_title'))

@section('content')

{{-- NEW 25 Jul 2026 — Survey Management Module, Phase 2 (task #232).
     Full answer-by-answer drill-down for one respondent. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px;">
        <a href="{{ route('admin.growth.surveys.responses', $survey->survey_id) }}" style="color:#1565C0; text-decoration:none; font-size:9.5px; font-weight:600; display:inline-block; margin-bottom:4px;">{{ __('growth.all_responses_link') }}</a>
        <h4 style="font-weight:700; margin:0; font-size:13px; color:#0f5c5a;">{{ $response->is_anonymous ? __('growth.anonymous_respondent') : ($response->respondent_name ?: __('growth.unnamed_respondent')) }}</h4>
        <div style="font-size:9px; color:#607d8b; margin-top:2px;">
            {{ $survey->title }} &middot;
            {{ $response->respondent_type ? str_replace('_',' ', $response->respondent_type) : __('growth.type_not_given') }}
            @if($response->respondent_email) &middot; {{ $response->respondent_email }} @endif
            @if($response->respondent_phone) &middot; {{ $response->respondent_phone }} @endif
            @if($customer) &middot; {{ __('growth.matched_customer_label') }} <a href="{{ route('admin.customers.show', $customer->customer_id) }}" style="color:#0f5c5a; font-weight:600;">{{ $customer->full_name }}</a> @endif
        </div>
        <div style="font-size:9px; color:#607d8b; margin-top:1px;">
            {{ __('growth.status_colon') }} <strong>{{ str_replace('_',' ', $response->completion_status) }}</strong>
            @if($response->submitted_at) &middot; {{ __('growth.submitted_at', ['datetime' => \Illuminate\Support\Carbon::parse($response->submitted_at)->format('d M Y, g:ia')]) }} @endif
            @if($response->completion_seconds) &middot; {{ __('growth.took_duration', ['duration' => gmdate('i:s', $response->completion_seconds)]) }} @endif
            @if($invitation) &middot; {{ __('growth.via_channel', ['channel' => $invitation->channel]) }} @endif
        </div>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px 16px; flex:1; min-height:0; overflow-y:auto;">
        @forelse($answers as $i => $a)
        <div style="border-bottom:1px solid #f3f4f6; padding:8px 0;">
            <div style="font-size:10.5px; font-weight:700; color:#111827;">{{ $i+1 }}. {{ $a->question_text }}</div>
            <div style="font-size:10.5px; color:#0f5c5a; font-weight:600; margin-top:3px;">
                @switch($a->question_type)
                    @case('SINGLE_CHOICE')
                    @case('DROPDOWN')
                    @case('YES_NO')
                        {{ $a->option_text ?? $a->answer_text ?? '—' }}
                        @break
                    @case('MULTIPLE_CHOICE')
                        {{ isset($a->option_texts) && $a->option_texts->isNotEmpty() ? $a->option_texts->implode(', ') : '—' }}
                        @break
                    @case('RATING_SCALE')
                    @case('NUMERIC_RATING')
                    @case('LIKERT_SCALE')
                        {{ $a->answer_number ?? '—' }}
                        @break
                    @case('STAR_RATING')
                        {{ $a->answer_number ? str_repeat('★ ', (int) $a->answer_number) : '—' }}
                        @break
                    @case('DATE')
                        {{ $a->answer_date ? \Illuminate\Support\Carbon::parse($a->answer_date)->format('d M Y') : '—' }}
                        @break
                    @case('TIME')
                        {{ $a->answer_time ?? '—' }}
                        @break
                    @case('NUMBER')
                        {{ $a->answer_number ?? '—' }}
                        @break
                    @case('MATRIX')
                        @if(!empty($a->matrix_answer))
                        <div style="font-weight:400; font-size:9.5px; color:#374151;">
                            @foreach($a->matrix_answer as $row => $col)
                            <div>{{ $row }}: <strong>{{ $col }}</strong></div>
                            @endforeach
                        </div>
                        @else — @endif
                        @break
                    @default
                        <span style="font-weight:400; white-space:pre-line;">{{ $a->answer_text ?? '—' }}</span>
                @endswitch
            </div>
        </div>
        @empty
        <div style="text-align:center; color:#9ca3af; padding:24px 0; font-size:10.5px;">{{ __('growth.no_answers_recorded') }}</div>
        @endforelse
    </div>
</div>
@endsection
