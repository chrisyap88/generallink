@extends('layouts.dashboard')

@section('page-title', __('growth.survey_responses_title'))

@section('content')

{{-- NEW 25 Jul 2026 — Survey Management Module, Phase 2 (task #232).
     Response Management — spec section 11. Every response in/out of
     progress for this survey, with drill-down to the full answer set
     per respondent. Aggregate Analytics (charts, per-question
     breakdowns) is Phase 3 — this screen is the raw list. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px;">
        <a href="{{ route('admin.growth.surveys.builder', $survey->survey_id) }}" style="color:#1565C0; text-decoration:none; font-size:9.5px; font-weight:600; display:inline-block; margin-bottom:4px;">&lsaquo; {{ $survey->name }}</a>
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px;">
            <div>
                <h4 style="font-weight:700; margin:0; font-size:13px; color:#1565C0;">{{ __('growth.responses_heading', ['title' => $survey->title]) }}</h4>
                <div style="font-size:9px; color:#9ca3af; margin-top:2px;">{{ __('growth.responses_summary', ['total' => $counts->sum(), 'completed' => $counts['COMPLETED'] ?? 0, 'in_progress' => $counts['IN_PROGRESS'] ?? 0, 'abandoned' => $counts['ABANDONED'] ?? 0]) }}</div>
            </div>
            <a href="{{ route('admin.growth.surveys.distribute', $survey->survey_id) }}" style="font-size:9.5px; color:#1565C0; font-weight:600; text-decoration:none;">{{ __('growth.distribute_link') }}</a>
        </div>
    </div>

    <form method="GET" style="display:flex; gap:6px; margin-bottom:6px; flex-shrink:0;">
        <select name="status" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; background:#fff;">
            <option value="">{{ __('growth.all_statuses_label') }}</option>
            @foreach(['COMPLETED'=>__('growth.status_completed'),'IN_PROGRESS'=>__('growth.status_in_progress'),'ABANDONED'=>__('growth.status_abandoned')] as $code => $label)
            <option value="{{ $code }}" {{ $status === $code ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @if($status)
        <a href="{{ route('admin.growth.surveys.responses', $survey->survey_id) }}" style="font-size:9.5px; color:#64748B; align-self:center; text-decoration:none; font-weight:600;">{{ __('growth.clear') }}</a>
        @endif
    </form>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow-y:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:#f0f9ff;">
                        <th style="text-align:left; padding:5px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.respondent_col') }}</th>
                        <th style="text-align:left; padding:5px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.type_col') }}</th>
                        <th style="text-align:left; padding:5px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.matched_customer_col') }}</th>
                        <th style="text-align:left; padding:5px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.status') }}</th>
                        <th style="text-align:right; padding:5px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.submitted_col') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; color:#111827;">{{ $r->is_anonymous ? __('growth.anonymous') : ($r->respondent_name ?: '—') }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $r->respondent_type ? str_replace('_',' ', $r->respondent_type) : '—' }}</td>
                        <td style="padding:5px 8px; color:#0f9c96; font-weight:600;">{{ $r->matched_customer_name ?? '—' }}</td>
                        <td style="padding:5px 8px;">
                            <span style="background:{{ ['COMPLETED'=>'#f0fdf4','IN_PROGRESS'=>'#fffbeb','ABANDONED'=>'#fee2e2'][$r->completion_status] }}; color:{{ ['COMPLETED'=>'#166534','IN_PROGRESS'=>'#92400e','ABANDONED'=>'#991b1b'][$r->completion_status] }}; font-size:8px; font-weight:700; padding:2px 7px; border-radius:20px;">{{ __('growth.status_'.strtolower($r->completion_status)) }}</span>
                        </td>
                        <td style="padding:5px 8px; text-align:right; color:#6b7280;">{{ $r->submitted_at ? \Illuminate\Support\Carbon::parse($r->submitted_at)->format('d M Y, g:ia') : '—' }}</td>
                        <td style="padding:5px 8px; text-align:right;"><a href="{{ route('admin.growth.surveys.responses.show', [$survey->survey_id, $r->response_id]) }}" style="color:#1565C0; font-weight:600; text-decoration:none; font-size:9px;">{{ __('growth.view_arrow') }}</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:16px; text-align:center; color:#94a3b8;">{{ __('growth.no_responses_yet') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:6px;">
            @if($rows->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.prev') }}</span>
            @else
                <a href="{{ $rows->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.prev') }}</a>
            @endif
            <span style="font-size:9px; color:#607d8b;">{{ __('growth.page_of', ['current' => $rows->currentPage(), 'last' => $rows->lastPage()]) }}</span>
            @if($rows->hasMorePages())
                <a href="{{ $rows->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
