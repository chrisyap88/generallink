@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_events.reports_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_events.reports_page_title') }}</div>
            <div style="font-size:9.5px; color:#6b7280;">{{ $event->event_name }}</div>
        </div>
        <a href="{{ route('cbe.events.show', $event->event_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_events.back_to_event') }}</a>
    </div>

    <div style="flex:1; min-height:0; display:flex; gap:10px;">
        <div style="flex:1; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; display:flex; flex-direction:column;">
            <div style="font-size:18px;">📖</div>
            <div style="font-size:11px; font-weight:700; color:#263238; margin-top:8px;">{{ __('cbe_events.report_donation_register') }}</div>
            <div style="font-size:9px; color:#6b7280; margin-top:4px; flex:1;">{{ __('cbe_events.report_donation_register_desc') }}</div>
            <a href="{{ route('cbe.event-reports.donation-register', $event->event_id) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:16px; padding:6px 12px; font-size:9.5px; font-weight:600; text-align:center;">{{ __('cbe_records.download_button') }}</a>
        </div>
        <div style="flex:1; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; display:flex; flex-direction:column;">
            <div style="font-size:18px;">📋</div>
            <div style="font-size:11px; font-weight:700; color:#263238; margin-top:8px;">{{ __('cbe_events.report_donation_summary') }}</div>
            <div style="font-size:9px; color:#6b7280; margin-top:4px; flex:1;">{{ __('cbe_events.report_donation_summary_desc') }}</div>
            <a href="{{ route('cbe.event-reports.donation-summary', $event->event_id) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:16px; padding:6px 12px; font-size:9.5px; font-weight:600; text-align:center;">{{ __('cbe_records.download_button') }}</a>
        </div>
        <div style="flex:1; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; display:flex; flex-direction:column;">
            <div style="font-size:18px;">🤝</div>
            <div style="font-size:11px; font-weight:700; color:#263238; margin-top:8px;">{{ __('cbe_events.report_sponsorship') }}</div>
            <div style="font-size:9px; color:#6b7280; margin-top:4px; flex:1;">{{ __('cbe_events.report_sponsorship_desc') }}</div>
            <a href="{{ route('cbe.event-reports.sponsorship', $event->event_id) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:16px; padding:6px 12px; font-size:9.5px; font-weight:600; text-align:center;">{{ __('cbe_records.download_button') }}</a>
        </div>
        <div style="flex:1; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; display:flex; flex-direction:column;">
            <div style="font-size:18px;">🔨</div>
            <div style="font-size:11px; font-weight:700; color:#263238; margin-top:8px;">{{ __('cbe_events.report_auction') }}</div>
            <div style="font-size:9px; color:#6b7280; margin-top:4px; flex:1;">{{ __('cbe_events.report_auction_desc') }}</div>
            <a href="{{ route('cbe.event-reports.auction', $event->event_id) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:16px; padding:6px 12px; font-size:9.5px; font-weight:600; text-align:center;">{{ __('cbe_records.download_button') }}</a>
        </div>
        <div style="flex:1; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; display:flex; flex-direction:column;">
            <div style="font-size:18px;">💰</div>
            <div style="font-size:11px; font-weight:700; color:#263238; margin-top:8px;">{{ __('cbe_events.report_income_expenditure') }}</div>
            <div style="font-size:9px; color:#6b7280; margin-top:4px; flex:1;">{{ __('cbe_events.report_income_expenditure_desc') }}</div>
            <a href="{{ route('cbe.event-reports.income-expenditure', $event->event_id) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:16px; padding:6px 12px; font-size:9.5px; font-weight:600; text-align:center;">{{ __('cbe_records.download_button') }}</a>
        </div>
    </div>
</div>
@endsection
