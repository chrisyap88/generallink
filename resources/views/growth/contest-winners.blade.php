@extends('layouts.dashboard')

@section('page-title', __('growth.contest_winners_title', ['title' => $contest->title]))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px;">
        <h4 style="font-weight:700; margin:0; font-size:13px; color:#1565C0;">{{ __('growth.contest_winners_title', ['title' => $contest->title]) }}</h4>
        <div style="font-size:9.5px; color:#9ca3af; margin-top:2px;">{{ __('growth.awarded_note') }}</div>
    </div>

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0; margin-bottom:6px;">✅ {{ session('success') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow-y:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:#f0f9ff;">
                        @if($contest->contest_mode === 'RANKED_TOP3')
                        <th style="text-align:center; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.place') }}</th>
                        @endif
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.agent') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.achieved') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.reward') }}</th>
                        <th style="text-align:center; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.status') }}</th>
                        <th style="text-align:center; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($winners as $w)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        @if($contest->contest_mode === 'RANKED_TOP3')
                        <td style="padding:5px 8px; text-align:center; font-weight:700; color:#92400e;">{{ $w->placement === 1 ? __('growth.place_1st') : ($w->placement === 2 ? __('growth.place_2nd') : ($w->placement === 3 ? __('growth.place_3rd') : '—')) }}</td>
                        @endif
                        <td style="padding:5px 8px; color:#111827;">{{ $w->full_name }} <span style="color:#9ca3af;">({{ $w->agent_code }})</span></td>
                        <td style="padding:5px 8px; text-align:right;">{{ $contest->metric === 'RECRUIT_COUNT' ? rtrim(rtrim(number_format($w->achieved_value,2),'0'),'.') : 'RM '.number_format($w->achieved_value,2) }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:700; color:#1565C0;">{{ $w->reward_type === 'POINTS' ? __('growth.pts_suffix', ['value' => number_format($w->reward_value,0)]) : ($w->reward_type === 'DOCUMENT_CREDIT' ? __('growth.doc_credit_suffix', ['value' => number_format($w->reward_value,2)]) : 'RM '.number_format($w->reward_value,2)) }}</td>
                        <td style="padding:5px 8px; text-align:center;">
                            @if($w->status === 'PENDING')
                            <span style="background:#fffbeb; color:#92400e; font-size:8.5px; font-weight:700; padding:2px 8px; border-radius:20px;">{{ __('growth.pending') }}</span>
                            @else
                            <span style="background:#f0fdf4; color:#166534; font-size:8.5px; font-weight:700; padding:2px 8px; border-radius:20px;">{{ __('growth.awarded') }}</span>
                            @endif
                        </td>
                        <td style="padding:5px 8px; text-align:center;">
                            @if($w->status === 'PENDING')
                            <form method="POST" action="{{ route('admin.growth.contests.mark-awarded', $w->award_id) }}">
                                @csrf
                                <button type="submit" style="background:none; border:none; color:#166534; font-size:9px; font-weight:600; cursor:pointer;">{{ __('growth.mark_as_awarded') }}</button>
                            </form>
                            @else
                            &mdash;
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="{{ $contest->contest_mode === 'RANKED_TOP3' ? 6 : 5 }}" style="padding:16px; text-align:center; color:#9ca3af;">{{ $contest->contest_mode === 'RANKED_TOP3' ? __('growth.not_finalized_yet') : __('growth.nobody_hit_target_yet') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Prev, filled blue, bottom-left — per Chris's standing rule. --}}
    <div style="flex-shrink:0; padding-top:6px;">
        <a href="{{ route('admin.growth.contests.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700; display:inline-block;">{{ __('growth.prev') }}</a>
    </div>

</div>
@endsection
