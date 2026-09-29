<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $agent->full_name }} — GeneralLink</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Rajdhani:wght@600;700&display=swap" rel="stylesheet">
<style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Outfit',sans-serif;background:linear-gradient(160deg,#e0f7fa 0%,#f7fdff 40%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px 16px;}
    .card{background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(13,90,142,.15);max-width:420px;width:100%;padding:32px 28px;text-align:center;}
    .banner{width:100%;height:110px;object-fit:cover;border-radius:12px;margin-bottom:14px;display:block;}
    .video-link{display:inline-block;font-size:11px;color:#1B9AE4;font-weight:700;text-decoration:none;margin-bottom:14px;}
    .photo{width:110px;height:110px;border-radius:50%;object-fit:cover;margin:0 auto 14px;display:block;border:3px solid #1B9AE4;}
    .photo-placeholder{width:110px;height:110px;border-radius:50%;margin:0 auto 14px;background:#e0f2f1;display:flex;align-items:center;justify-content:center;font-size:36px;color:#0D5A8E;font-weight:700;border:3px solid #1B9AE4;}
    .name{font-family:'Rajdhani',sans-serif;font-size:22px;font-weight:700;color:#0D5A8E;}
    .role{font-size:12px;color:#38A169;font-weight:600;margin-top:2px;margin-bottom:14px;}
    .bio{font-size:12.5px;color:#4b5563;line-height:1.6;margin-bottom:18px;white-space:pre-line;}
    .contact{display:flex;flex-direction:column;gap:6px;font-size:11.5px;color:#374151;margin-bottom:18px;}
    .qr-box{background:#f0f9ff;border-radius:10px;padding:16px;margin-bottom:8px;}
    .qr-box img{width:150px;height:150px;}
    .qr-label{font-size:10.5px;color:#0D5A8E;font-weight:700;margin-top:8px;}
    .cta{display:inline-block;margin-top:14px;background:linear-gradient(90deg,#1B9AE4 0%,#0D5A8E 100%);color:#fff;text-decoration:none;padding:10px 26px;border-radius:8px;font-size:12px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;}
    .footer{font-size:9px;color:#b0bec5;letter-spacing:.1em;text-transform:uppercase;margin-top:20px;}
</style>
</head>
<body>
<div class="card">
    @if($profile->banner_image_path ?? null)
    <img class="banner" src="{{ asset('storage/' . $profile->banner_image_path) }}" alt="">
    @endif
    @if($profile->photo_path ?? null)
    <img class="photo" src="{{ asset('storage/' . $profile->photo_path) }}" alt="{{ $agent->full_name }}">
    @else
    <div class="photo-placeholder">{{ strtoupper(substr($agent->full_name, 0, 1)) }}</div>
    @endif
    <div class="name">{{ $agent->full_name }}</div>
    <div class="role">{{ \App\Services\RoleLabelService::label($agent->role) }} &middot; GeneralLink</div>

    @if($profile->bio)
    <div class="bio">{{ $profile->bio }}</div>
    @endif

    @if($profile->video_url ?? null)
    <a class="video-link" href="{{ $profile->video_url }}" target="_blank">{{ __('growth.watch_video') }}</a>
    @elseif($profile->video_file_path ?? null)
    <a class="video-link" href="{{ asset('storage/' . $profile->video_file_path) }}" target="_blank">{{ __('growth.watch_video') }}</a>
    @endif

    <div class="contact">
        @if($agent->phone)<div>📞 {{ $agent->phone }}</div>@endif
        @if($agent->email)<div>✉️ {{ $agent->email }}</div>@endif
    </div>

    <div class="qr-box">
        <img src="{{ $qrDataUri }}" alt="{{ __('growth.register_via_name', ['name' => $agent->full_name]) }}">
        <div class="qr-label">{{ __('growth.scan_to_register_with', ['name' => $agent->full_name]) }}</div>
    </div>

    <a href="{{ $referralUrl }}" class="cta">{{ __('growth.register_now') }}</a>

    <div class="footer">{{ __('growth.footer_tagline') }}</div>
</div>
</body>
</html>
