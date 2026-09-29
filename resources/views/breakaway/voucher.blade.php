<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ __('breakaway.voucher_page_title') }}</title>
<style>
    @page { size: A4; margin: 20mm; }
    body { font-family: Arial, Helvetica, sans-serif; color: #1f2937; margin: 0; padding: 24px 32px; }
    .no-print { }
    @media print { .no-print { display: none !important; } body { padding: 0; } }
    .header { text-align: center; border-bottom: 3px solid #1565C0; padding-bottom: 12px; margin-bottom: 20px; }
    .header .brand { font-size: 20px; font-weight: 700; color: #1565C0; }
    .header .doc-title { font-size: 14px; font-weight: 600; color: #374151; margin-top: 4px; letter-spacing: 0.5px; }
    .meta-row { display: flex; justify-content: space-between; font-size: 11px; color: #6b7280; margin-bottom: 20px; }
    table.details { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    table.details td { padding: 8px 10px; font-size: 12px; border-bottom: 1px solid #e5e7eb; }
    table.details td.label { color: #6b7280; width: 42%; }
    table.details td.value { color: #111827; font-weight: 600; text-align: right; }
    .highlight-box { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 16px 20px; margin-bottom: 20px; text-align: center; }
    .highlight-box .amount { font-size: 26px; font-weight: 700; color: #1565C0; }
    .highlight-box .amount-label { font-size: 10.5px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
    .message { font-size: 11.5px; line-height: 1.7; color: #374151; margin-bottom: 24px; }
    .status-badge { display: inline-block; font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
    .footer-note { font-size: 9.5px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 30px; }
    .print-btn { background: #1565C0; color: #fff; border: none; border-radius: 6px; padding: 8px 18px; font-size: 12px; font-weight: 600; cursor: pointer; margin-bottom: 16px; }
</style>
</head>
<body>

<div class="no-print">
    <button class="print-btn" onclick="window.print()">{{ __('breakaway.print_save_pdf_button') }}</button>
</div>

<div class="header">
    <div class="brand">GeneralLink</div>
    <div class="doc-title">{{ __('breakaway.voucher_doc_title') }}</div>
</div>

<div class="meta-row">
    <div>{{ __('breakaway.claim_reference_label', ['ref' => strtoupper(substr($claim->claim_id, 0, 8))]) }}</div>
    <div>{{ __('breakaway.date_issued_label', ['date' => now()->format('d M Y')]) }}</div>
</div>

<p class="message">
    {{ __('breakaway.dear_name_greeting', ['name' => $claim->original_gl_name]) }}
</p>

<p class="message">
    {!! __('breakaway.voucher_congrats_note', ['promoted_gl_name' => '<strong>' . e($claim->promoted_gl_name) . '</strong>']) !!}
</p>

<div class="highlight-box">
    <div class="amount-label">{{ __('breakaway.your_bonus_amount_label') }}</div>
    <div class="amount">RM {{ number_format($claim->bonus_amount, 2) }}</div>
</div>

<table class="details">
    <tr><td class="label">{{ __('breakaway.row_breakaway_group') }}</td><td class="value">{{ $claim->promoted_gl_name }} ({{ $claim->promoted_gl_code }})</td></tr>
    <tr><td class="label">{{ __('breakaway.row_original_gl') }}</td><td class="value">{{ $claim->original_gl_name }} ({{ $claim->original_gl_code }})</td></tr>
    <tr><td class="label">{{ __('breakaway.row_period_covered') }}</td><td class="value">{{ \Illuminate\Support\Carbon::parse($claim->period_start)->format('d M Y') }} &ndash; {{ \Illuminate\Support\Carbon::parse($claim->period_end)->format('d M Y') }}</td></tr>
    <tr><td class="label">{{ __('breakaway.row_metric_measured') }}</td><td class="value">{{ $claim->target_metric === 'PREMIUM' ? __('breakaway.metric_premium') : __('breakaway.metric_earning_income') }}</td></tr>
    <tr><td class="label">{{ __('breakaway.row_target_amount') }}</td><td class="value">RM {{ number_format($claim->target_amount, 2) }}</td></tr>
    <tr><td class="label">{{ __('breakaway.row_amount_achieved') }}</td><td class="value">RM {{ number_format($claim->achieved_amount, 2) }}</td></tr>
    <tr><td class="label">{{ __('breakaway.row_bonus_percentage') }}</td><td class="value">{{ rtrim(rtrim(number_format($claim->bonus_pct, 2), '0'), '.') }}%</td></tr>
    <tr><td class="label">{{ __('breakaway.row_bonus_amount') }}</td><td class="value">RM {{ number_format($claim->bonus_amount, 2) }}</td></tr>
    <tr>
        <td class="label">{{ __('breakaway.row_claim_status') }}</td>
        <td class="value">
            @if($claim->status === 'PENDING')
            <span class="status-badge" style="background:#fffbeb; color:#92400e;">{{ __('breakaway.status_badge_pending') }}</span>
            @else
            <span class="status-badge" style="background:#f0fdf4; color:#166534;">{{ __('breakaway.status_badge_claimed') }}{{ $claim->claimed_at ? __('breakaway.claimed_on_suffix', ['date' => \Illuminate\Support\Carbon::parse($claim->claimed_at)->format('d M Y')]) : '' }}</span>
            @endif
        </td>
    </tr>
</table>

<p class="message">
    {{ __('breakaway.voucher_file_claim_note') }}
</p>

<p class="message">
    {{ __('breakaway.voucher_thank_you_note') }}
</p>

<div class="footer-note">
    {{ __('breakaway.voucher_footer_note', ['ref' => strtoupper(substr($claim->claim_id, 0, 8)), 'timestamp' => now()->format('d M Y, H:i')]) }}
</div>

</body>
</html>
