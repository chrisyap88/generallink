<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ __('admin_cbe_directory.receipt_title') }} — {{ $receipt->receipt_no }}</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: 'Poppins', Arial, sans-serif; background:#eef2f7; margin:0; padding:24px; color:#263238; }
    .receipt { max-width:480px; margin:0 auto; background:#fff; border:1px solid #e2e8f0; border-top:5px solid var(--gl-blue); border-radius:8px; padding:24px 28px; }
    .r-title { font-size:16px; font-weight:700; color:var(--gl-blue); text-align:center; margin-bottom:2px; }
    .r-sub { font-size:11px; color:#6b7280; text-align:center; margin-bottom:18px; }
    .r-no { text-align:center; font-size:12px; font-weight:700; color:#263238; letter-spacing:1px; margin-bottom:18px; }
    .r-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #e2e8f0; font-size:11px; }
    .r-row span:first-child { color:#6b7280; }
    .r-row span:last-child { color:#263238; font-weight:600; text-align:right; }
    .r-amount { text-align:center; font-size:22px; font-weight:700; color:var(--gl-blue); margin:18px 0; }
    .r-print { display:inline-block; margin:20px 6px 0; background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:8px 20px; font-size:11px; font-weight:700; cursor:pointer; text-decoration:none; }
    .r-actions { text-align:center; }
    @media print { .r-actions { display:none; } body { background:#fff; padding:0; } .receipt { border:none; box-shadow:none; } }
</style>
</head>
<body>
    <div class="receipt">
        <div class="r-title">{{ $nodeName }}</div>
        <div class="r-sub">{{ __('admin_cbe_directory.receipt_title') }}</div>
        <div class="r-no">{{ __('admin_cbe_directory.receipt_no_label') }}: {{ $receipt->receipt_no }}</div>

        <div class="r-row"><span>{{ __('admin_cbe_directory.receipt_date_label') }}</span><span>{{ \Carbon\Carbon::parse($receipt->issued_at)->format('d M Y, h:i A') }}</span></div>
        <div class="r-row"><span>{{ __('admin_cbe_directory.receipt_received_from_label') }}</span><span>{{ $receipt->payer_name }}</span></div>
        <div class="r-row"><span>{{ __('admin_cbe_directory.receipt_description_label') }}</span><span>{{ $receipt->description ?: '—' }}</span></div>
        <div class="r-row"><span>{{ __('admin_cbe_directory.receipt_issued_by_label') }}</span><span>{{ $receipt->issued_by_name ?: '—' }}</span></div>

        <div class="r-amount">RM {{ number_format($receipt->amount, 2) }}</div>

        <div class="r-actions">
            <button class="r-print" onclick="window.print()">🖨️ {{ __('admin_cbe_directory.btn_print_receipt') }}</button>
            <a class="r-print" href="{{ route('admin.cbe-kpi.receipts.pdf', ['id' => $receipt->receipt_id]) }}">⬇ {{ __('admin_cbe_directory.btn_download_receipt_pdf') }}</a>
        </div>
    </div>
</body>
</html>
