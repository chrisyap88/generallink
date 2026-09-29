@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('growth.survey_management_title'))

@section('content')

{{-- REPLACED 25 Jul 2026 — Survey Management Module, Phase 1 (task
     #227, #231). Dashboard (lite) + survey list. Full Analytics
     Dashboard (trend charts, top/low performers) is Phase 3 — this
     screen covers the spec's Dashboard section 5 "Key Information"
     stat cards plus the list/filter/actions needed to actually run
     Phase 1's workflow day to day. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px;">
        {{-- Plain text link, NOT a blue pill — this screen already has
             its own real Prev/Next pagination at the bottom (see
             footer). A second blue pill up here would read as a
             duplicate Prev, which is exactly what Chris flagged. --}}
        <a href="{{ route('admin.dashboard') }}" style="color:var(--gl-blue); text-decoration:none; font-size:9.5px; font-weight:600; display:inline-block; margin-bottom:4px;">{{ __('growth.dashboard_link') }}</a>
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px;">
            <div>
                <div style="display:flex; align-items:center; gap:10px;">
                    @include('partials.feature-video-widget', ['featureKey' => 'SURVEY_MANAGEMENT', 'buttonStyle' => 'plain'])
                </div>
                <div style="font-size:9px; color:#9ca3af; margin-top:2px;">{{ __('growth.surveys_admin_subtitle') }}</div>
            </div>
            <div style="display:flex; gap:8px; align-items:center;">
                @if($googleConnected)
                    <div style="font-size:9px; color:#166534; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:5px 10px; display:flex; align-items:center; gap:6px;">
                        {{ __('growth.google_connected_note') }} @if($googleConnection->google_email) &middot; {{ $googleConnection->google_email }}@endif
                        <form method="POST" action="{{ route('google.disconnect') }}" onsubmit="return confirm({{ Js::from(__('growth.disconnect_confirm')) }});" style="display:inline;">@csrf
                            <button type="submit" style="background:none; border:none; color:#991b1b; font-size:9px; font-weight:600; cursor:pointer; text-decoration:underline;">{{ __('growth.disconnect_button') }}</button>
                        </form>
                    </div>
                @else
                    <a href="{{ route('google.connect') }}" style="background:#0369a1; color:#fff; text-decoration:none; border-radius:6px; padding:6px 14px; font-size:10.5px; font-weight:600;">{{ __('growth.connect_google_account_button') }}</a>
                @endif
                <a href="{{ route('admin.growth.surveys.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 14px; font-size:10.5px; font-weight:600;">{{ __('growth.create_survey_plus_button') }}</a>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0; margin-bottom:6px;">✅ {{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fee2e2; border:1px solid #fca5a5; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; font-weight:500; flex-shrink:0; margin-bottom:6px;">⚠️ {{ session('error') }}</div>
    @endif

    {{-- Stat cards — spec section 5. --}}
    <div style="display:grid; grid-template-columns:repeat(7,1fr); gap:6px; flex-shrink:0; margin-bottom:8px;">
        @foreach(['DRAFT'=>__('growth.draft_status'),'PUBLISHED'=>__('growth.published_status'),'ACTIVE'=>__('growth.active_status'),'PAUSED'=>__('growth.paused_status'),'CLOSED'=>__('growth.closed_status'),'ARCHIVED'=>__('growth.archived_status')] as $code => $label)
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:6px 8px; text-align:center;">
            <div style="font-size:14px; font-weight:700; color:var(--gl-blue);">{{ number_format($statCards[$code] ?? 0) }}</div>
            <div style="font-size:8px; color:#6b7280; text-transform:uppercase;">{{ $label }}</div>
        </div>
        @endforeach
        <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:6px 8px; text-align:center;">
            <div style="font-size:14px; font-weight:700; color:#0369a1;">{{ $responseRate }}%</div>
            <div style="font-size:8px; color:#0369a1; text-transform:uppercase;">{{ __('growth.response_rate_label') }}</div>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.growth.surveys.index') }}" style="display:flex; gap:6px; margin-bottom:6px; flex-shrink:0;">
        <select name="category_id" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; background:#fff;">
            <option value="">{{ __('growth.all_categories_option') }}</option>
            @foreach($categories as $c)
            <option value="{{ $c->category_id }}" {{ $categoryId === $c->category_id ? 'selected' : '' }}>{{ $c->name }}</option>
            @endforeach
        </select>
        <select name="status" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; background:#fff;">
            <option value="">{{ __('growth.all_statuses_label') }}</option>
            @foreach(['DRAFT','PUBLISHED','ACTIVE','PAUSED','CLOSED','ARCHIVED'] as $s)
            <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ __('growth.survey_status_'.strtolower($s)) }}</option>
            @endforeach
        </select>
        @if($categoryId || $status)
        <a href="{{ route('admin.growth.surveys.index') }}" style="font-size:9.5px; color:#64748B; align-self:center; text-decoration:none; font-weight:600;">{{ __('growth.clear') }}</a>
        @endif
    </form>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($surveys as $s)
            <div style="border-bottom:1px solid #f3f4f6; padding:8px 4px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-size:11px; font-weight:700; color:#111827;">{{ $s->name }}</div>
                    <span style="background:{{ ['DRAFT'=>'#f3f4f6','PUBLISHED'=>'#f0f9ff','ACTIVE'=>'#f0fdf4','PAUSED'=>'#fef3c7','CLOSED'=>'#fee2e2','ARCHIVED'=>'#ede9fe'][$s->status] }}; color:{{ ['DRAFT'=>'#6b7280','PUBLISHED'=>'#0369a1','ACTIVE'=>'#166534','PAUSED'=>'#92400e','CLOSED'=>'#991b1b','ARCHIVED'=>'#5b21b6'][$s->status] }}; font-size:8px; font-weight:700; padding:2px 7px; border-radius:20px;">{{ __('growth.survey_status_'.strtolower($s->status)) }}</span>
                </div>
                <div style="font-size:9.5px; color:#6b7280; margin:3px 0;">
                    {{ $s->category_name ?? __('growth.uncategorized') }} &middot; {{ __('growth.response_count_label', ['count' => $s->response_count]) }}
                    @if($s->start_date) &middot; {{ \Illuminate\Support\Carbon::parse($s->start_date)->format('d M Y') }}@if($s->end_date) &ndash; {{ \Illuminate\Support\Carbon::parse($s->end_date)->format('d M Y') }}@endif @endif
                </div>
                @if($s->objective)
                <div style="font-size:9px; color:#9ca3af; margin-bottom:4px; font-style:italic;">🎯 {{ \Illuminate\Support\Str::limit($s->objective, 120) }}</div>
                @endif
                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <a href="{{ route('admin.growth.surveys.builder', $s->survey_id) }}" style="font-size:9.5px; color:var(--gl-blue); font-weight:600; text-decoration:none;">{{ __('growth.questions_link') }}</a>
                    <a href="{{ route('admin.growth.surveys.edit', $s->survey_id) }}" style="font-size:9.5px; color:var(--gl-blue); font-weight:600; text-decoration:none;">{{ __('growth.edit_info_link') }}</a>
                    <a href="{{ route('admin.growth.surveys.preview', $s->survey_id) }}" style="font-size:9.5px; color:#7c3aed; font-weight:600; text-decoration:none;">{{ __('growth.preview_link') }}</a>
                    @if(in_array($s->status, ['PUBLISHED','ACTIVE','PAUSED','CLOSED']))
                    <a href="{{ route('admin.growth.surveys.distribute', $s->survey_id) }}" style="font-size:9.5px; color:#0369a1; font-weight:600; text-decoration:none;">{{ __('growth.distribute_link_plain') }}</a>
                    <a href="{{ route('admin.growth.surveys.responses', $s->survey_id) }}" style="font-size:9.5px; color:#0369a1; font-weight:600; text-decoration:none;">{{ __('growth.responses_count_link', ['count' => $s->response_count]) }}</a>
                    @endif
                    @if($s->google_form_id)
                    <span style="font-size:8px; color:#166534; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:20px; padding:2px 8px; font-weight:700;">{{ __('growth.on_google_forms_badge') }}</span>
                    @endif

                    @if($s->status === 'DRAFT')
                    <form method="POST" action="{{ route('admin.growth.surveys.publish', $s->survey_id) }}" style="display:inline;">@csrf<button type="submit" style="background:none; border:none; color:#0369a1; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('growth.publish_button') }}</button></form>
                    @elseif($s->status === 'PUBLISHED' || $s->status === 'PAUSED')
                    <form method="POST" action="{{ route('admin.growth.surveys.activate', $s->survey_id) }}" style="display:inline;">@csrf<button type="submit" style="background:none; border:none; color:#166534; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('growth.activate_button') }}</button></form>
                    @endif
                    @if($s->status === 'ACTIVE')
                    <form method="POST" action="{{ route('admin.growth.surveys.pause', $s->survey_id) }}" style="display:inline;">@csrf<button type="submit" style="background:none; border:none; color:#92400e; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('growth.pause_button') }}</button></form>
                    @endif
                    @if(in_array($s->status, ['ACTIVE','PAUSED']))
                    <form method="POST" action="{{ route('admin.growth.surveys.close', $s->survey_id) }}" style="display:inline;">@csrf<button type="submit" style="background:none; border:none; color:#991b1b; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('growth.close_button') }}</button></form>
                    @endif
                    @if($s->status === 'CLOSED')
                    <form method="POST" action="{{ route('admin.growth.surveys.archive', $s->survey_id) }}" style="display:inline;">@csrf<button type="submit" style="background:none; border:none; color:#5b21b6; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('growth.archive_button') }}</button></form>
                    @endif

                    <form method="POST" action="{{ route('admin.growth.surveys.duplicate', $s->survey_id) }}" style="display:inline;">@csrf<button type="submit" style="background:none; border:none; color:#374151; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('growth.duplicate_button') }}</button></form>
                    @if($s->status === 'DRAFT')
                    <form method="POST" action="{{ route('admin.growth.surveys.destroy', $s->survey_id) }}" style="display:inline;" onsubmit="return confirm({{ Js::from(__('growth.delete_draft_confirm')) }});">@csrf @method('DELETE')<button type="submit" style="background:none; border:none; color:#dc2626; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('growth.delete_button') }}</button></form>
                    @endif
                </div>
            </div>
            @empty
            <div style="text-align:center; color:#9ca3af; padding:20px 0; font-size:10.5px;">{{ __('growth.no_surveys_yet') }}</div>
            @endforelse
        </div>
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($surveys->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.prev') }}</span>
            @else
                <a href="{{ $surveys->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:600;">{{ __('growth.prev') }}</a>
            @endif
            <span style="font-size:9px; color:#6b7280;">{{ __('growth.page_of', ['current' => $surveys->currentPage(), 'last' => $surveys->lastPage()]) }}</span>
            @if($surveys->hasMorePages())
                <a href="{{ $surveys->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:600;">{{ __('growth.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
