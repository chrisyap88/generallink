<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ __('cbe_records.minutes_pdf_title') }} — {{ $minute->title }}</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; color:#263238; margin:0; padding:0; }
    .sheet { padding:22px 26px; border:2px solid #1565C0; }
    .r-title { font-size:16px; font-weight:bold; color:#1565C0; text-align:center; margin:0 0 2px 0; }
    .r-sub { font-size:11px; color:#6b7280; text-align:center; margin:0 0 14px 0; }
    table.info { width:100%; border-collapse:collapse; margin-bottom:14px; }
    table.info td { padding:4px 0; border-bottom:1px dashed #cbd5e1; font-size:10.5px; vertical-align:top; }
    table.info td.lbl { color:#6b7280; width:28%; }
    table.info td.val { color:#263238; font-weight:bold; }
    .sec-heading { font-size:12px; font-weight:bold; color:#1565C0; margin:14px 0 4px 0; }
    .sec-text { font-size:10.5px; color:#263238; line-height:1.5; white-space:pre-wrap; }
    table.att-list { width:100%; border-collapse:collapse; margin-top:4px; }
    table.att-list td { font-size:9.5px; padding:3px 6px; }
    .status-yes { color:#2e7d32; }
    .status-no { color:#c62828; }
    table.res { width:100%; border-collapse:collapse; margin-top:4px; }
    table.res th { background:#1565C0; color:#fff; font-size:9px; text-align:left; padding:5px 8px; }
    table.res td { font-size:9px; padding:5px 8px; border-bottom:1px solid #e5e7eb; }
</style>
</head>
<body>
    <div class="sheet">
        <div class="r-title">{{ $nodeName }}</div>
        <div class="r-sub">{{ __('cbe_records.minutes_pdf_title') }}</div>

        <table class="info">
            <tr><td class="lbl">{{ __('cbe_records.field_title') }}</td><td class="val">{{ $minute->title }}</td></tr>
            @if($minute->agenda)<tr><td class="lbl">{{ __('cbe_records.field_agenda') }}</td><td class="val">{{ $minute->agenda }}</td></tr>@endif
            <tr><td class="lbl">{{ __('cbe_records.field_meeting_date') }}</td><td class="val">{{ \Carbon\Carbon::parse($minute->meeting_date)->format('l, d M Y') }}</td></tr>
            <tr><td class="lbl">{{ __('cbe_records.field_meeting_time') }}</td><td class="val">
                {{ $minute->meeting_time ? \Carbon\Carbon::parse($minute->meeting_time)->format('g:i A') : '—' }}
                @if($minute->meeting_end_time) &ndash; {{ \Carbon\Carbon::parse($minute->meeting_end_time)->format('g:i A') }} @endif
            </td></tr>
            <tr><td class="lbl">{{ __('cbe_records.field_venue') }}</td><td class="val">{{ $minute->meeting_mode === 'ONLINE' ? __('cbe_records.mode_online') : ($minute->venue ?: '—') }}</td></tr>
            <tr><td class="lbl">{{ __('cbe_records.field_organizer') }}</td><td class="val">{{ $organizer ?: '—' }}</td></tr>
        </table>

        <div class="sec-heading">{{ __('cbe_records.tab_attendees') }}</div>
        <table class="att-list">
            @forelse($attendees as $at)
            <tr>
                <td>{{ $at->full_name ?: $at->guest_name }}</td>
                <td class="{{ $at->attended ? 'status-yes' : 'status-no' }}">{{ $at->attended ? __('cbe_records.attended_label') : __('cbe_records.not_attended_label') }}</td>
            </tr>
            @empty
            <tr><td>{{ __('cbe_records.no_attendees_note') }}</td></tr>
            @endforelse
        </table>

        @forelse($sections as $sec)
        <div class="sec-heading">{{ $sec['heading'] ?? '' }}</div>
        <div class="sec-text">
            @foreach(($sec['paragraphs'] ?? []) as $p)
                {{ $p['text'] }}@if(!$loop->last)<br>@endif
            @endforeach
        </div>
        @empty
        <div class="sec-heading">{{ __('cbe_records.no_content_note') }}</div>
        @endforelse

        @if($resolutions->count())
        <div class="sec-heading">{{ __('cbe_records.tab_resolutions') }}</div>
        <table class="res">
            <tr><th>{{ __('cbe_records.resolution_text_placeholder') }}</th><th>{{ __('cbe_records.field_outcome') }}</th></tr>
            @foreach($resolutions as $r)
            <tr><td>{{ $r->resolution_text }}</td><td>{{ __('cbe_records.outcome_' . strtolower($r->outcome)) }}</td></tr>
            @endforeach
        </table>
        @endif

        <div style="margin-top:20px; font-size:8.5px; color:#9ca3af;">{{ __('cbe_records.pdf_generated_note', ['date' => now()->format('d M Y, g:i A')]) }}</div>
    </div>
</body>
</html>
