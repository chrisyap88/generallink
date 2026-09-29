<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ __('admin_cbe_directory.receipt_title') }} — {{ $receipt->receipt_no }}</title>
<style>
    {{-- NEW 10 Sep 2026 (Phase 17) — dompdf does not support flexbox, so
    this layout uses tables/block elements only, unlike the browser-print
    show.blade.php view it mirrors. Same single standard font/colour
    scheme as the rest of the app (blue #1565C0). --}}
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; color:#263238; margin:0; padding:0; }
    .sheet { padding:22px 26px; border:2px solid #1565C0; }
    .r-title { font-size:16px; font-weight:bold; color:#1565C0; text-align:center; margin:0 0 2px 0; }
    .r-sub { font-size:11px; color:#6b7280; text-align:center; margin:0 0 14px 0; }
    .r-no { text-align:center; font-size:12px; font-weight:bold; color:#263238; margin:0 0 14px 0; }
    table.r-table { width:100%; border-collapse:collapse; margin-bottom:10px; }
    table.r-table td { padding:6px 0; border-bottom:1px dashed #cbd5e1; font-size:11px; vertical-align:top; }
    table.r-table td.r-label { color:#6b7280; width:38%; }
    table.r-table td.r-value { color:#263238; font-weight:bold; text-align:right; }
    .r-amount { text-align:center; font-size:20px; font-weight:bold; color:#1565C0; margin:14px 0 4px 0; }
    .r-words { text-align:center; font-size:11px; font-style:italic; color:#263238; margin:0 0 20px 0; }
    table.r-sign { width:100%; margin-top:30px; }
    table.r-sign td { width:50%; font-size:10px; color:#263238; text-align:center; padding-top:26px; border-top:1px solid #263238; }
</style>
</head>
<body>
    <div class="sheet">
        <div class="r-title">{{ $nodeName }}</div>
        <div class="r-sub">{{ __('admin_cbe_directory.receipt_title') }}</div>
        <div class="r-no">{{ __('admin_cbe_directory.receipt_no_label') }}: {{ $receipt->receipt_no }}</div>

        <table class="r-table">
            <tr>
                <td class="r-label">{{ __('admin_cbe_directory.receipt_date_label') }}</td>
                <td class="r-value">{{ \Carbon\Carbon::parse($receipt->issued_at)->format('d M Y, h:i A') }}</td>
            </tr>
            <tr>
                <td class="r-label">{{ __('admin_cbe_directory.receipt_received_from_label') }}</td>
                <td class="r-value">{{ $receipt->payer_name }}</td>
            </tr>
            <tr>
                <td class="r-label">{{ __('admin_cbe_directory.receipt_description_label') }}</td>
                <td class="r-value">{{ $receipt->description ?: '—' }}</td>
            </tr>
            <tr>
                <td class="r-label">{{ __('admin_cbe_directory.receipt_issued_by_label') }}</td>
                <td class="r-value">{{ $receipt->issued_by_name ?: '—' }}</td>
            </tr>
        </table>

        <div class="r-amount">RM {{ number_format($receipt->amount, 2) }}</div>
        <div class="r-words">{{ $amountWords }}</div>

        <table class="r-sign">
            <tr>
                <td>{{ __('admin_cbe_directory.receipt_received_by_label') }}</td>
                <td>{{ __('admin_cbe_directory.receipt_authorized_signature_label') }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
