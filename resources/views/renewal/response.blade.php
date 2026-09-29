<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('renewal.renewal_reminder_title', ['ref' => $txn->document_reference_number]) }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{font-family:Arial,Helvetica,sans-serif;background:#f0f4f8;margin:0;padding:20px;color:#111827;}
        .card{max-width:560px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.08);padding:24px;}
        h1{font-size:18px;color:#1565C0;margin:0 0 4px;}
        .sub{font-size:12px;color:#6b7280;margin-bottom:18px;}
        .row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f3f4f6;font-size:13px;}
        .row span:first-child{color:#6b7280;}
        .row span:last-child{font-weight:600;text-align:right;}
        .note{font-size:11px;color:#9ca3af;margin-top:4px;}
        .addons{font-size:13px;white-space:pre-line;background:#f9fafb;border-radius:8px;padding:10px 12px;margin-top:6px;}
        form{margin-top:22px;}
        .btn{display:block;width:100%;text-align:center;border:none;border-radius:8px;padding:12px;font-size:14px;font-weight:700;cursor:pointer;margin-bottom:10px;}
        .btn-yes{background:#1565C0;color:#fff;}
        .btn-no{background:#f3f4f6;color:#374151;}
        .btn-discuss{background:#fff;color:#1565C0;border:1px solid #1565C0;}
        .already{background:#fef3c7;color:#92400e;border-radius:8px;padding:10px 12px;font-size:12px;margin-top:16px;}
    </style>
</head>
<body>
<div class="card">
    <h1>{{ __('renewal.renewal_reminder_heading') }}</h1>
    <div class="sub">{{ __('renewal.policy_dated_sub', ['ref' => $txn->document_reference_number, 'date' => \Illuminate\Support\Carbon::parse($renewal->coverage_start)->format('d M Y')]) }}</div>

    <p style="font-size:13px;">{{ __('renewal.dear_greeting', ['name' => $txn->customer_name]) }}</p>
    <p style="font-size:13px;">{!! __('renewal.due_for_renewal_text', ['vendor' => $txn->vendor_name, 'date' => \Illuminate\Support\Carbon::parse($renewal->coverage_end)->format('d M Y')]) !!}</p>

    <div class="row"><span>{{ __('renewal.coverage_type_label') }}</span><span>{{ $attributes['COVERAGE_TYPE'] ?? '—' }}</span></div>
    <div class="row"><span>{{ __('renewal.sum_insured_label') }}</span><span>RM {{ number_format($txn->sum_insured ?? 0, 2) }}</span></div>
    <div class="note">{{ __('renewal.sum_insured_reassessed_note') }}</div>

    @if(!empty($attributes['ADD_ONS']))
    <p style="font-size:12px;color:#6b7280;margin:14px 0 2px;">{{ __('renewal.add_ons_extensions_label') }}</p>
    <div class="addons">{{ $attributes['ADD_ONS'] }}</div>
    @endif

    @if($existingRequest && $existingRequest->decision === 'YES')
    <div class="already">
        {{ __('renewal.already_requested_note', ['date' => \Illuminate\Support\Carbon::parse($existingRequest->requested_at)->format('d M Y')]) }}
        @if($existingRequest->status === 'QUOTATION_SENT')
            {{ __('renewal.quotation_sent_note') }}
        @else
            {{ __('renewal.agent_preparing_note') }}
        @endif
    </div>
    @else
    <form method="POST" action="{{ route('renewal.response.submit', $txn->policy_id) }}?{{ http_build_query(request()->query()) }}">
        @csrf
        <button type="submit" name="decision" value="YES" class="btn btn-yes">{{ __('renewal.yes_renew_button') }}</button>
        <button type="submit" name="decision" value="DISCUSS" class="btn btn-discuss">{{ __('renewal.discuss_button') }}</button>
        <button type="submit" name="decision" value="NO" class="btn btn-no">{{ __('renewal.no_renew_button') }}</button>
    </form>
    @endif
</div>
</body>
</html>
