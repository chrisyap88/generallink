@extends('layouts.dashboard')

@section('page-title', __('growth.my_public_profile_title'))

@section('content')

{{-- NEW 25 Jul 2026 (task #214) — Growth & Outreach Center. A
     shareable public page with your photo, bio, and referral QR — no
     login needed for whoever you send it to. Your photo comes from My
     Profile; update it there if you want to change it. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; align-items:flex-start; justify-content:space-between; gap:10px;">
        <div>
            <div style="font-size:9.5px; color:#9ca3af; margin-top:2px;">{{ __('growth.public_profile_edit_subtitle') }}</div>
        </div>
        @include('partials.feature-video-widget', ['featureKey' => 'PUBLIC_PROFILE'])
    </div>

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0; margin-bottom:6px;">✅ {{ session('success') }}</div>
    @endif

    <div style="display:flex; gap:12px; flex:1; min-height:0;">
        <form method="POST" action="{{ route('public-profile.update') }}" enctype="multipart/form-data" style="flex:1; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px 16px; display:flex; flex-direction:column; gap:10px; min-height:0; overflow-y:auto;">
            @csrf
            <div>
                <div style="font-size:9px; color:#6b7280; margin-bottom:3px;">{{ __('growth.bio_label') }}</div>
                <textarea name="bio" id="publicBioBody" rows="6" maxlength="1000" placeholder="{{ __('growth.bio_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:8px; font-size:11px; box-sizing:border-box; resize:none;">{{ $profile->bio ?? '' }}</textarea>
                @include('partials.carolyn-write-helper', [
                    'uid' => 'bio',
                    'bodyFieldId' => 'publicBioBody',
                    'contentType' => 'public_profile_bio',
                    'assistUrl' => route('ai-write-assist'),
                ])
            </div>
            <div>
                <div style="font-size:9px; color:#6b7280; margin-bottom:3px;">{{ __('growth.banner_image_label') }}</div>
                @if($profile->banner_image_path ?? null)
                <img src="{{ asset('storage/' . $profile->banner_image_path) }}" alt="" style="width:100%; height:70px; object-fit:cover; border-radius:6px; margin-bottom:4px;">
                @endif
                <input type="file" name="banner_image" accept="image/*" style="width:100%; font-size:10.5px;">
            </div>
            <div>
                <div style="font-size:9px; color:#6b7280; margin-bottom:3px;">{{ __('growth.video_optional_label') }}</div>
                <div style="display:flex; gap:10px; font-size:10.5px; color:#374151; margin-bottom:4px;">
                    <label><input type="radio" name="video_source" value="LINK" {{ !($profile->video_file_path ?? null) ? 'checked' : '' }} onchange="toggleProfileVideoSource()"> {{ __('growth.paste_link_option') }}</label>
                    <label><input type="radio" name="video_source" value="UPLOAD" {{ ($profile->video_file_path ?? null) ? 'checked' : '' }} onchange="toggleProfileVideoSource()"> {{ __('growth.upload_file_option') }}</label>
                </div>
                <div id="profileVideoLink" style="{{ ($profile->video_file_path ?? null) ? 'display:none;' : '' }}">
                    <input type="url" name="video_url" value="{{ $profile->video_url ?? '' }}" placeholder="{{ __('growth.video_url_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 8px; font-size:11px; box-sizing:border-box;">
                </div>
                <div id="profileVideoUpload" style="{{ ($profile->video_file_path ?? null) ? '' : 'display:none;' }}">
                    <input type="file" name="video_file" accept="video/mp4,video/quicktime,video/webm" style="width:100%; font-size:10.5px;">
                    <div style="font-size:8.5px; color:#9ca3af; margin-top:2px;">{{ __('growth.video_max_size_note') }}@if($profile->video_file_path ?? null) {{ __('growth.video_already_uploaded_note') }}@endif</div>
                </div>
            </div>
            <label style="display:flex; align-items:center; gap:8px; font-size:11px; font-weight:600; color:#111827; cursor:pointer;">
                <input type="checkbox" name="is_published" value="1" {{ ($profile->is_published ?? false) ? 'checked' : '' }} style="width:15px; height:15px;">
                {{ __('growth.publish_this_page_label') }}
            </label>
            <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 20px; font-size:11px; font-weight:600; cursor:pointer; align-self:flex-start;">{{ __('growth.save_button') }}</button>
        </form>

        <div style="flex:0 0 260px; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; display:flex; flex-direction:column; gap:8px;">
            <div style="font-size:9px; color:#6b7280; text-transform:uppercase; font-weight:600;">{{ __('growth.your_public_page_label') }}</div>
            @if($profile->is_published ?? false)
            <div style="font-size:9.5px; color:#166534; background:#f0fdf4; border-radius:6px; padding:6px 8px;">{{ __('growth.page_live_note') }}</div>
            @else
            <div style="font-size:9.5px; color:#92400e; background:#fffbeb; border-radius:6px; padding:6px 8px;">{{ __('growth.not_published_yet_note') }}</div>
            @endif
            <div style="display:flex; gap:6px;">
                <input type="text" readonly value="{{ $publicUrl }}" style="flex:1; border:1px solid #d1d5db; border-radius:5px; padding:6px 7px; font-size:10px; color:#374151; background:#f9fafb;">
                <a href="{{ $publicUrl }}" target="_blank" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:5px; padding:6px 12px; font-size:10px; font-weight:700; white-space:nowrap;">{{ __('growth.preview_button') }}</a>
            </div>
        </div>
    </div>

    {{-- Prev, filled blue, bottom-left — per Chris's standing rule. --}}
    <div style="flex-shrink:0; padding-top:6px;">
        <a href="{{ route($agent->role === 'ADMIN' ? 'admin.dashboard' : ($agent->role === 'GROUP_LEADER' ? 'gl.dashboard' : ($agent->role === 'TEAM_LEADER' ? 'tl.dashboard' : 'introducer.dashboard'))) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700; display:inline-block;">{{ __('growth.prev') }}</a>
    </div>

</div>

<script>
function toggleProfileVideoSource() {
    var upload = document.querySelector('input[name="video_source"]:checked').value === 'UPLOAD';
    document.getElementById('profileVideoLink').style.display = upload ? 'none' : 'block';
    document.getElementById('profileVideoUpload').style.display = upload ? 'block' : 'none';
}
</script>
@endsection
