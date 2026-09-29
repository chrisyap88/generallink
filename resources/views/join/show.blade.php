<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('member_file.join_title') }} — {{ $node->node_name }}</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body{margin:0; font-family:'Poppins',sans-serif; background:#e0f7fa; color:#263238; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:16px; box-sizing:border-box;}
.card{background:#fff; border-radius:14px; box-shadow:0 6px 24px rgba(0,0,0,.12); padding:22px; width:min(440px,100%); box-sizing:border-box;}
h1{font-size:17px; color:#1565C0; margin:0 0 4px;} .sub{font-size:12px; color:#6b7280;}
.btn{display:block; width:100%; text-align:center; background:#1565C0; color:#fff; border:none; border-radius:22px; padding:11px; font-size:14px; font-weight:700; text-decoration:none; margin-top:10px; cursor:pointer; box-sizing:border-box; font-family:inherit;}
.btn.o{background:#fff; color:#1565C0; border:2px solid #1565C0;}
label.t{display:flex; align-items:center; gap:8px; font-size:14px; padding:7px 0; border-bottom:1px solid #f1f5f9;}
.ok{background:#e8f5e9; color:#2e7d32; font-weight:700; border-radius:8px; padding:10px; font-size:13px; margin-top:10px;}
</style>
</head>
<body>
{{-- NEW 28 Sep 2026 — per Chris (member file item 31): entity QR join page. --}}
<div class="card">
    <div class="sub">{{ $node->group_name }}</div>
    <h1>{{ $node->node_name }}@if($node->node_name_zh && $node->node_name_zh !== $node->node_name) {{ $node->node_name_zh }}@endif</h1>
    <div class="sub">{{ __('member_file.join_prompt') }}</div>
    @if(session()->has('joined'))
        <div class="ok">✓ {{ __('member_file.join_done', ['entity' => $node->node_name]) }}</div>
    @endif
    @if($agent)
        <form method="POST" action="{{ route('join.post', $node->join_token) }}" style="margin-top:12px;">
            @csrf
            <div class="sub" style="margin-bottom:4px;">{{ __('member_file.join_hello', ['name' => $agent->nick_name ?: $agent->full_name]) }}</div>
            @foreach($types as $t)
                <label class="t" style="{{ in_array($t, $have, true) ? 'opacity:.5;' : '' }}"><input type="checkbox" name="types[]" value="{{ $t }}" {{ in_array($t, $have, true) ? 'checked disabled' : '' }}> {{ $labels[$t] ?? $t }}@if(in_array($t, $have, true)) <span class="sub">✓</span>@endif</label>
            @endforeach
            <button class="btn">{{ __('member_file.join_btn') }}</button>
        </form>
    @else
        <a class="btn" href="{{ route('auth.login') }}">{{ __('member_file.join_login') }}</a>
        <a class="btn o" href="{{ url('/register') }}">{{ __('member_file.join_register') }}</a>
    @endif
</div>
</body>
</html>
