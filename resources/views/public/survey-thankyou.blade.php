<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ __('public.thank_you_page_title_template', ['title' => $survey->title]) }}</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Outfit',sans-serif;background:linear-gradient(160deg,#e0f7fa 0%,#f7fdff 40%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px 16px;}
    .card{background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(13,90,142,.15);max-width:440px;width:100%;padding:36px 28px;text-align:center;}
    .icon{font-size:42px;margin-bottom:12px;}
    h3{font-size:17px;color:#0D5A8E;margin-bottom:8px;}
    p{font-size:12.5px;color:#6b7280;line-height:1.6;}
</style>
</head>
<body>
<div class="card">
    <div class="icon">🎉</div>
    <h3>{{ __('public.thank_you_title') }}</h3>
    <p>{{ __('public.response_recorded_template', ['title' => $survey->title]) }}</p>
</div>
</body>
</html>
