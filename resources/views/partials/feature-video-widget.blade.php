{{-- NEW 10 Aug 2026 — per Chris: "video is easier to understand all
     this marketing initiative...make sure the video is easier to
     understand." Shared "▶ How This Works" button + player modal for
     any Growth & Outreach Center screen. Looks itself up — just pass
     the feature_key (see VideoLibraryController::FEATURE_KEYS) and
     this partial does its own DB lookup and renders nothing at all if
     Admin hasn't uploaded an explainer video for that screen yet, so
     no screen ever shows a dead/broken button. Deliberately does the
     lookup itself (rather than requiring every one of the 8
     controllers to fetch and pass a variable) so adding this to a 9th
     screen later is a one-line @include, nothing else to wire up.

     Usage: @include('partials.feature-video-widget', ['featureKey' => 'REFERRAL_LINK'])
     Optional: pass 'buttonStyle' => 'pill' (default) or 'plain' when a
     screen's header doesn't have room for a pill-shaped button (e.g.
     Survey Management already has a Prev/Next-style pill up top and
     Chris flagged a second pill there as confusing). --}}
@php
    $featureVideo = \App\Http\Controllers\Admin\VideoLibraryController::featureGuideVideo($featureKey ?? '');
    $fvStyle = $buttonStyle ?? 'pill';
@endphp
@php
    $fvContentType = $featureVideo->content_type ?? 'VIDEO';
    $fvHowLabel = __('partials.how_this_works_label');
    $fvLabel = ['VIDEO' => '▶ '.$fvHowLabel, 'SLIDESHOW' => '📊 '.$fvHowLabel, 'FLYER' => '🖼 '.$fvHowLabel, 'LINK' => '🔗 '.$fvHowLabel][$fvContentType] ?? '▶ '.$fvHowLabel;
@endphp
@if($featureVideo)
<style>
    .fvBtn-pill { background:#eff6ff; color:#1565C0; border:1px solid #bfdbfe; border-radius:20px; padding:4px 12px; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap; }
    .fvBtn-pill:hover { background:#dbeafe; }
    .fvBtn-plain { background:none; border:none; color:#1565C0; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap; padding:0; text-decoration:none; }
    .fvBtn-plain:hover { text-decoration:underline; }
</style>
@if($fvContentType === 'VIDEO')
<button type="button" class="fvBtn-{{ $fvStyle }}" onclick="openFeatureVideo()" title="{{ $featureVideo->video_name }}">{{ $fvLabel }}</button>
<div id="fvModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.75); z-index:99999; align-items:center; justify-content:center;">
    <div style="background:#000; border-radius:10px; overflow:hidden; max-width:80vw; max-height:80vh; position:relative;">
        <button type="button" onclick="closeFeatureVideo()" style="position:absolute; top:6px; right:6px; background:rgba(255,255,255,.9); border:none; border-radius:50%; width:26px; height:26px; font-size:14px; font-weight:700; cursor:pointer; z-index:1;">✕</button>
        <div style="padding:8px 12px; background:#111; color:#fff; font-size:11px; font-weight:600;">{{ $featureVideo->video_name }}</div>
        <video id="fvPlayer" controls style="max-width:80vw; max-height:70vh; display:block;">
            <source src="{{ route('video-library.stream', $featureVideo->video_id) }}">
        </video>
    </div>
</div>
<script>
    function openFeatureVideo(){
        var m = document.getElementById('fvModal');
        if (!m) { return; }
        m.style.display = 'flex';
        var v = document.getElementById('fvPlayer');
        v.play().catch(function(){});
    }
    function closeFeatureVideo(){
        var m = document.getElementById('fvModal');
        if (!m) { return; }
        var v = document.getElementById('fvPlayer');
        v.pause();
        m.style.display = 'none';
    }
</script>
@else
{{-- NEW 10 Aug 2026 — a Slideshow/Flyer/Link guide has no in-app
     player to open — just opens in a new tab (browser natively
     previews PDF/images; a Link goes straight to the external page). --}}
<a href="{{ route('video-library.stream', $featureVideo->video_id) }}" target="_blank" class="fvBtn-{{ $fvStyle }}" title="{{ $featureVideo->video_name }}" style="text-decoration:none; display:inline-block;">{{ $fvLabel }}</a>
@endif
@endif
