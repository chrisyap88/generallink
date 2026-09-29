@extends('layouts.dashboard')

@section('page-title', __('growth.recruitment_contests_title'))

@section('content')

{{-- NEW 25 Jul 2026 (task #211) — Growth & Outreach Center. Every
     currently-running contest that applies to you, with your own live
     progress toward the target. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; align-items:flex-start; justify-content:space-between; gap:10px;">
        <div>
            <div style="font-size:9.5px; color:#9ca3af; margin-top:2px;">{{ __('growth.contests_subtitle') }}</div>
        </div>
        @include('partials.feature-video-widget', ['featureKey' => 'RECRUITMENT_CONTESTS'])
    </div>

    <div style="flex:1; min-height:0; overflow-y:auto; display:grid; grid-template-columns:1fr 1fr; gap:10px; align-content:start;">
        @forelse($contests as $c)
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px 14px;">
            @if($c->banner_image_path)
            <img src="{{ asset('storage/' . $c->banner_image_path) }}" alt="" style="width:100%; height:80px; object-fit:cover; border-radius:6px; margin-bottom:8px;">
            @endif
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:4px;">
                <div style="font-size:12px; font-weight:700; color:#1565C0;">{{ $c->title }}</div>
                @if($c->already_won)
                <span style="background:#f0fdf4; color:#166534; font-size:8.5px; font-weight:700; padding:2px 8px; border-radius:20px;">{{ __('growth.won_badge') }}</span>
                @else
                <span style="background:#fffbeb; color:#92400e; font-size:8.5px; font-weight:700; padding:2px 8px; border-radius:20px;">{{ __('growth.days_left_badge', ['days' => $c->days_left]) }}</span>
                @endif
            </div>
            @if($c->contest_mode === 'RANKED_TOP3')
            <div style="font-size:8.5px; color:#92400e; background:#fef3c7; border-radius:5px; padding:2px 7px; display:inline-block; margin-bottom:6px;">{{ __('growth.top3_note') }}</div>
            @endif
            @if($c->description)
            <div style="font-size:9.5px; color:#6b7280; margin-bottom:8px;">{{ $c->description }}</div>
            @endif
            @if($c->rules_text)
            <div style="font-size:8.5px; color:#9ca3af; margin-bottom:8px;">{{ __('growth.rules_label', ['text' => $c->rules_text]) }}</div>
            @endif
            @if($c->sponsor_vendor_name)
            <div style="font-size:9px; color:#7c3aed; background:#f5f3ff; border-radius:5px; padding:3px 7px; display:inline-block; margin-bottom:6px;">{{ __('growth.sponsored_by', ['name' => $c->sponsor_vendor_name]) }}</div>
            @endif
            @if($c->video_id ?? null)
            <div style="margin-bottom:6px;"><a href="{{ route('video-library.stream', $c->video_id) }}" target="_blank" style="font-size:9.5px; color:#1565C0; font-weight:600; text-decoration:none;">{{ __('growth.view_content_link') }}</a></div>
            @elseif($c->video_url || $c->video_file_path)
            <div style="margin-bottom:6px;"><a href="{{ $c->video_url ?: asset('storage/' . $c->video_file_path) }}" target="_blank" style="font-size:9.5px; color:#1565C0; font-weight:600; text-decoration:none;">{{ __('growth.watch_video') }}</a></div>
            @endif
            <div style="font-size:9.5px; color:#374151; margin-bottom:6px;">
                {{ __('growth.target_label') }} <strong>{{ $c->metric === 'RECRUIT_COUNT' ? __('growth.new_active_recruits_suffix', ['value' => rtrim(rtrim(number_format($c->target_value,2),'0'),'.')]) : 'RM '.number_format($c->target_value,2).' '.($c->metric === 'SALES_VOLUME' ? __('growth.premium_word') : __('growth.earning_income_word')) }}</strong>
                @if($c->contest_mode === 'RANKED_TOP3')
                &middot; {{ __('growth.ordinal_1st') }} <strong>{{ $c->reward_type === 'POINTS' ? __('growth.pts_suffix', ['value' => number_format($c->reward_value,0)]) : ($c->reward_type === 'DOCUMENT_CREDIT' ? __('growth.doc_credit_suffix', ['value' => number_format($c->reward_value,2)]) : 'RM '.number_format($c->reward_value,2)) }}</strong>
                @if($c->reward_value_2nd) &middot; {{ __('growth.ordinal_2nd') }} <strong>{{ number_format($c->reward_value_2nd,2) }}</strong>@endif
                @if($c->reward_value_3rd) &middot; {{ __('growth.ordinal_3rd') }} <strong>{{ number_format($c->reward_value_3rd,2) }}</strong>@endif
                @else
                &middot; {{ __('growth.reward_colon') }} <strong>{{ $c->reward_type === 'POINTS' ? __('growth.pts_word', ['value' => number_format($c->reward_value,0)]) : ($c->reward_type === 'DOCUMENT_CREDIT' ? __('growth.doc_credits_word', ['value' => number_format($c->reward_value,2)]) : 'RM '.number_format($c->reward_value,2)) }}</strong>
                @endif
            </div>
            <div style="background:#f3f4f6; border-radius:20px; height:14px; overflow:hidden; margin-bottom:4px;">
                <div style="background:{{ $c->pct >= 100 ? '#16a34a' : '#1565C0' }}; height:100%; width:{{ $c->pct }}%;"></div>
            </div>
            <div style="font-size:9px; color:#6b7280; text-align:right;">
                {{ __('growth.progress_of', ['progress' => ($c->metric === 'RECRUIT_COUNT' ? rtrim(rtrim(number_format($c->my_progress,2),'0'),'.') : 'RM '.number_format($c->my_progress,2)), 'target' => ($c->metric === 'RECRUIT_COUNT' ? rtrim(rtrim(number_format($c->target_value,2),'0'),'.') : 'RM '.number_format($c->target_value,2)), 'pct' => $c->pct]) }}
            </div>
        </div>
        @empty
        <div style="grid-column:1/-1; text-align:center; color:#9ca3af; padding:40px 0;">{{ __('growth.no_contests_running') }}</div>
        @endforelse
    </div>

    {{-- Prev, filled blue, bottom-left — per Chris's standing rule. --}}
    <div style="flex-shrink:0; padding-top:6px;">
        <a href="{{ route($agent->role === 'ADMIN' ? 'admin.dashboard' : ($agent->role === 'GROUP_LEADER' ? 'gl.dashboard' : ($agent->role === 'TEAM_LEADER' ? 'tl.dashboard' : 'introducer.dashboard'))) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700; display:inline-block;">{{ __('growth.prev') }}</a>
    </div>

</div>
@endsection
