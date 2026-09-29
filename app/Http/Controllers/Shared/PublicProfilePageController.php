<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// NEW 25 Jul 2026 — Growth & Outreach Center (task #214). Lets an agent
// write a short bio and publish a public page (no login required) they
// can share on social media — reuses the existing My Profile photo
// (agent_profiles.photo_path) rather than a second upload flow.
class PublicProfilePageController extends Controller
{
    public function edit()
    {
        $agent = Auth::guard('agent')->user();
        $profile = DB::table('agent_profiles')->where('agent_id', $agent->agent_id)->first();

        $publicUrl = url('/agent/' . $agent->agent_code);

        return view('growth.public-profile-edit', compact('agent', 'profile', 'publicUrl'));
    }

    public function update(Request $request)
    {
        $agent = Auth::guard('agent')->user();

        $request->validate([
            'bio'          => ['nullable', 'string', 'max:1000'],
            'is_published' => ['nullable', 'in:0,1'],
            'banner_image' => ['nullable', 'image', 'max:5120'],
            'video_source' => ['nullable', 'in:LINK,UPLOAD'],
            'video_url'    => ['nullable', 'url', 'max:255'],
            'video_file'   => ['nullable', 'mimes:mp4,mov,webm', 'max:51200'],
        ]);

        $existing = DB::table('agent_profiles')->where('agent_id', $agent->agent_id)->first();

        $bannerPath = $existing->banner_image_path ?? null;
        if ($request->hasFile('banner_image')) {
            if ($bannerPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($bannerPath)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($bannerPath);
            }
            $bannerPath = $request->file('banner_image')->store('profile-banners', 'public');
        }

        $videoUrl = $existing->video_url ?? null;
        $videoFilePath = $existing->video_file_path ?? null;
        if ($request->input('video_source') === 'UPLOAD' && $request->hasFile('video_file')) {
            if ($videoFilePath && \Illuminate\Support\Facades\Storage::disk('public')->exists($videoFilePath)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($videoFilePath);
            }
            $videoFilePath = $request->file('video_file')->store('profile-videos', 'public');
            $videoUrl = null;
        } elseif ($request->input('video_source') === 'LINK') {
            $videoUrl = $request->input('video_url') ?: null;
            $videoFilePath = null;
        }

        $values = [
            'bio'              => $request->input('bio'),
            'is_published'     => $request->boolean('is_published'),
            'banner_image_path' => $bannerPath,
            'video_url'        => $videoUrl,
            'video_file_path'  => $videoFilePath,
            'updated_at'       => now(),
        ];

        if ($existing) {
            DB::table('agent_profiles')->where('agent_id', $agent->agent_id)->update($values);
        } else {
            DB::table('agent_profiles')->insert(array_merge($values, [
                'profile_id' => (string) \Illuminate\Support\Str::uuid(),
                'agent_id'   => $agent->agent_id,
                'created_at' => now(),
            ]));
        }

        AuditService::logChange('agent_profiles', $agent->agent_id, 'PUBLIC_PROFILE_UPDATED', $existing, $values);

        return redirect()->route('public-profile.edit')->with('success', 'Public profile saved.');
    }
}
