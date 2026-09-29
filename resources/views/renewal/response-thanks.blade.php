<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('renewal.thank_you_title') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{font-family:Arial,Helvetica,sans-serif;background:#f0f4f8;margin:0;padding:20px;color:#111827;}
        .card{max-width:480px;margin:60px auto;background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.08);padding:28px;text-align:center;}
        h1{font-size:17px;color:#1565C0;margin:0 0 10px;}
        p{font-size:13px;color:#374151;}
    </style>
</head>
<body>
<div class="card">
    @if($decision === 'YES')
        <h1>{{ __('renewal.thank_you_heading') }}</h1>
        <p>{{ __('renewal.yes_thanks_text') }}</p>
    @elseif($decision === 'DISCUSS')
        <h1>{{ __('renewal.noted_heading') }}</h1>
        <p>{{ __('renewal.discuss_thanks_text') }}</p>
    @else
        <h1>{{ __('renewal.noted_heading') }}</h1>
        <p>{{ __('renewal.no_thanks_text') }}</p>
    @endif
</div>
</body>
</html>
