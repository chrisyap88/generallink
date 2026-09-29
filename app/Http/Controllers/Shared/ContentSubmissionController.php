<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Admin\VideoLibraryController;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

// NEW 10 Aug 2026 — per Chris: "all agents including vendor is allow
// to send attachment...marketing documents...video, flyers, url link,
// slide show...to admin. admin received and upload to master file and
// activate." Shared by BOTH agents (any role, via the normal
// authenticated agent routes) and vendors (via the vendor guard) — one
// controller, since the form and workflow are identical; only WHO is
// submitting differs, detected here from which guard is logged in.
// Lands in the SAME video_library table Admin's own Content Library
// uses, with status='PENDING_REVIEW' — Admin reviews and Approves
// (goes Active, immediately pickable in Broadcast Campaigns /
// Recruitment Contests) or Rejects (with a reason) from the Content
// Library screen itself — see VideoLibraryController::approve()/
// reject(). No separate "inbox" table, no re-upload step — the file is
// already saved from the moment it's submitted.
class ContentSubmissionController extends Controller
{
    private function submitterIsVendor(): bool
    {
        return auth('vendor')->check();
    }

    public function create()
    {
        $folderPath = DB::table('system_settings')->where('setting_key', VideoLibraryController::SETTING_KEY)->value('setting_value');
        $folderReady = $folderPath && is_dir($folderPath) && is_writable($folderPath);

        $view = $this->submitterIsVendor() ? 'vendor.submit-content' : 'growth.submit-content';

        return view($view, [
            'types'        => VideoLibraryController::SUBMITTABLE_TYPES,
            'contentTypes' => VideoLibraryController::CONTENT_TYPES,
            'folderReady'  => $folderReady,
        ]);
    }

    public function store(Request $request)
    {
        $isVendor = $this->submitterIsVendor();
        $contentType = $request->input('content_type', 'VIDEO');
        $isLink = $contentType === 'LINK';

        $folderPath = DB::table('system_settings')->where('setting_key', VideoLibraryController::SETTING_KEY)->value('setting_value');
        if (!$isLink && (!$folderPath || !is_dir($folderPath) || !is_writable($folderPath))) {
            return back()->withErrors(['content_file' => 'Cause: Admin has not set up a working content storage folder yet. Fix: try again later, or send this directly to Admin instead for now.'])->withInput();
        }

        $request->validate([
            'video_name'    => ['required', 'string', 'max:200'],
            'video_type'    => ['required', Rule::in(array_keys(VideoLibraryController::SUBMITTABLE_TYPES))],
            'content_type'  => ['required', Rule::in(array_keys(VideoLibraryController::CONTENT_TYPES))],
            'purpose'       => ['nullable', 'string', 'max:300'],
            'content_file'  => ['required_unless:content_type,LINK', 'nullable', 'file', 'max:512000'],
            'external_url'  => ['required_if:content_type,LINK', 'nullable', 'url', 'max:500'],
        ], [
            'content_file.required_unless' => 'Please choose a file to upload.',
            'external_url.required_if' => 'Please paste the link.',
        ]);

        $videoId = (string) Str::uuid();
        $storedName = null;
        $originalName = null;

        if (!$isLink) {
            try {
                [$storedName, $originalName] = VideoLibraryController::saveContentFile($request->file('content_file'), $contentType, $folderPath, $videoId);
            } catch (\RuntimeException $e) {
                return back()->withErrors(['content_file' => $e->getMessage()])->withInput();
            }
        }

        $agent = auth('agent')->user();
        $vendor = auth('vendor')->user();

        DB::table('video_library')->insert([
            'video_id'           => $videoId,
            'video_name'         => $request->video_name,
            'video_type'         => $request->video_type,
            'content_type'       => $contentType,
            'vendor_id'          => $isVendor ? $vendor->vendor_id : null,
            'stored_file_name'   => $storedName,
            'original_file_name' => $originalName,
            'external_url'       => $isLink ? $request->external_url : null,
            'purpose'            => $request->purpose,
            'submitted_date'     => now()->toDateString(),
            'source_type'        => $isVendor ? 'VENDOR_SUBMITTED' : 'AGENT_SUBMITTED',
            'status'             => 'PENDING_REVIEW',
            'created_by'         => $isVendor ? null : $agent->agent_id,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        AuditService::logChange('video_library', $videoId, 'VIDEO_SUBMITTED', null, ['video_name' => $request->video_name, 'submitted_by' => $isVendor ? $vendor->vendor_name : $agent->full_name], $isVendor ? null : $agent->agent_id);

        // NEW 10 Aug 2026 — Admin gets told right away instead of having
        // to remember to check the Content Library's Pending Review
        // filter on their own.
        $admins = Agent::where('role', 'ADMIN')->where('is_deleted', false)->get();
        if ($admins->isNotEmpty()) {
            $senderName = $isVendor ? $vendor->vendor_name . ' (vendor)' : $agent->full_name . ' (' . $agent->role . ')';
            app(NotificationService::class)->notify(
                $admins->all(),
                'CONTENT_SUBMITTED',
                'New Content Submitted for Review',
                "{$senderName} submitted \"{$request->video_name}\" (" . VideoLibraryController::CONTENT_TYPES[$contentType] . ") for review. Go to Master File Maintenance > Content Library > filter Status = Pending Review to approve or reject it."
            );
        }

        $redirectRoute = $isVendor ? 'vendor.content-submission.index' : 'content-submission.index';
        return redirect()->route($redirectRoute)->with('success', 'Sent to Admin for review: ' . $request->video_name);
    }

    public function index()
    {
        $isVendor = $this->submitterIsVendor();

        $query = DB::table('video_library')->orderByDesc('created_at');
        if ($isVendor) {
            $query->where('vendor_id', auth('vendor')->user()->vendor_id)->where('source_type', 'VENDOR_SUBMITTED');
        } else {
            $query->where('created_by', auth('agent')->user()->agent_id)->where('source_type', 'AGENT_SUBMITTED');
        }
        $submissions = $query->paginate(6)->withQueryString();

        $view = $isVendor ? 'vendor.my-submissions' : 'growth.my-submissions';

        return view($view, [
            'submissions'  => $submissions,
            'types'        => VideoLibraryController::SUBMITTABLE_TYPES,
            'contentTypes' => VideoLibraryController::CONTENT_TYPES,
        ]);
    }
}
