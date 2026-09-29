{{-- Shared "Watch Intro Video" button + player modal, per Chris: "i want
     it standardize across all login page." Included on every guest
     login/register page (auth/login.blade.php, auth/register.blade.php,
     auth/vendor-login.blade.php, auth/vendor-register.blade.php) so the
     button always looks and behaves identically everywhere, and only
     needs to be updated in one place. Expects an $introVideo variable
     (nullable) to already be passed in from the controller — the button
     simply doesn't render at all if no video has been uploaded/marked
     Active yet in Admin > Video Library, so a prospect never sees a
     dead/broken button. --}}
@if($introVideo)
<style>
    .introVideoBtn { background: #0D5A8E; color: #fff; border: none; border-radius: 20px; padding: 7px 16px; font-size: clamp(.58rem, .95vw, .72rem); font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(13,90,142,0.25); white-space: nowrap; }
    .introVideoBtn:hover { background: #094a75; }
</style>
<button type="button" class="introVideoBtn" onclick="openIntroVideo()" title="{{ __('partials.intro_video_tooltip') }}">
    ▶ {{ __('partials.watch_intro_video_button') }}
</button>
<div id="introVideoModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.75); z-index:99999; align-items:center; justify-content:center;">
    <div style="background:#000; border-radius:10px; overflow:hidden; max-width:80vw; max-height:80vh; position:relative;">
        <button type="button" onclick="closeIntroVideo()" style="position:absolute; top:6px; right:6px; background:rgba(255,255,255,.9); border:none; border-radius:50%; width:26px; height:26px; font-size:14px; font-weight:700; cursor:pointer; z-index:1;">✕</button>
        <div style="padding:8px 12px; background:#111; color:#fff; font-size:11px; font-weight:600;">{{ $introVideo->video_name }}</div>
        <video id="introVideoPlayer" controls style="max-width:80vw; max-height:70vh; display:block;">
            <source src="{{ route('video-library.stream', $introVideo->video_id) }}">
        </video>
    </div>
</div>
<script>
    function openIntroVideo(){
        var m = document.getElementById('introVideoModal');
        if (!m) { return; }
        m.style.display = 'flex';
        var v = document.getElementById('introVideoPlayer');
        v.play().catch(function(){});
    }
    function closeIntroVideo(){
        var m = document.getElementById('introVideoModal');
        if (!m) { return; }
        var v = document.getElementById('introVideoPlayer');
        v.pause();
        m.style.display = 'none';
    }
</script>
@endif
