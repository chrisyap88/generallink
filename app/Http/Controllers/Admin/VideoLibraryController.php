<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

// NEW 9 Aug 2026 — Video Library, per Chris: an Admin-managed catalogue
// of videos (Introduction / Announcement / Marketing-Promotion / Product
// Explanation) reusable across the app — first use is the "Watch Intro
// Video" button on the Vendor Registration page. Deliberately does NOT
// store video bytes in the database (avoids DB bloat) — only metadata is
// stored in video_library; the actual files live on disk inside a
// folder path Admin configures (system_settings key
// 'video_library_folder_path'), and this controller reads/writes that
// folder directly using absolute paths (works for any folder — inside
// or outside the Laravel project — not just Laravel's configured disks).
class VideoLibraryController extends Controller
{
    public const SETTING_KEY = 'video_library_folder_path';

    public const TYPES = [
        'INTRODUCTION'         => 'Introduction / Corporate Overview',
        'ANNOUNCEMENT'         => 'Announcement',
        'MARKETING_PROMOTION'  => 'Marketing / Promotion',
        'PRODUCT_EXPLANATION'  => 'Product Explanation',
        'FEATURE_GUIDE'        => 'Feature Guide / How It Works',
        'OTHER'                => 'Other',
    ];

    // NEW 10 Aug 2026 — per Chris: "video is easier to understand all
    // this marketing initiative." A FEATURE_GUIDE video is tagged with
    // exactly ONE of these keys, so each Growth & Outreach Center screen
    // can show its own "▶ How This Works" button (see
    // partials.feature-video-widget) instead of every screen just
    // dumping text instructions. Keys match the sidebar's actual menu
    // labels so Admin never has to guess which one to pick.
    public const FEATURE_KEYS = [
        'REFERRAL_LINK'         => 'My Referral Link',
        'REBATE_OFFERS'         => 'Rebate Offer Search',
        'RECRUITMENT_CONTESTS'  => 'Recruitment Contests',
        'PUBLIC_PROFILE'        => 'My Public Profile',
        'SURVEY_MANAGEMENT'     => 'Survey Management',
        'SEND_SURVEY'           => 'Send Survey',
        'BROADCAST_CAMPAIGNS'   => 'Broadcast Campaigns',
        'CHANNEL_CONNECTIONS'   => 'Channel Connections',
    ];

    private const ALLOWED_EXT = ['mp4', 'mov', 'webm', 'avi', 'mkv', 'm4v'];

    // NEW 10 Aug 2026 — per Chris: "do you allow upload slide show and
    // marketing flyer, google link form?" Rather than 3 separate
    // dedicated modules, this SAME library now holds any of these 4
    // content types — reuses every bit of provenance/source-tracking/
    // audit/feature-tagging/pick-from-library machinery already built
    // for video. content_type is orthogonal to video_type (the Category
    // — Marketing/Promotion, Introduction, etc.): a Marketing/Promotion
    // item can be a VIDEO, a SLIDESHOW, a FLYER, or a LINK.
    public const CONTENT_TYPES = [
        'VIDEO'     => 'Video',
        'SLIDESHOW' => 'Slideshow (PDF)',
        'FLYER'     => 'Flyer (Image or PDF)',
        'LINK'      => 'External Link (Google Form, YouTube, etc.)',
    ];

    // PDF-only for Slideshow — browsers preview PDF inline natively;
    // PowerPoint files have no built-in in-browser preview here.
    private const SLIDESHOW_EXT = ['pdf'];
    private const FLYER_EXT = ['jpg', 'jpeg', 'png', 'pdf'];

    // NEW 10 Aug 2026 — extracted out of store() so
    // ContentSubmissionController (agent/vendor self-service uploads)
    // can save a file the exact same validated way, instead of
    // duplicating the extension-check-and-move logic. Returns
    // [storedFileName, originalFileName]; throws \RuntimeException with
    // a ready-to-show error message on an unsupported extension.
    public static function saveContentFile($file, string $contentType, string $folderPath, string $videoId): array
    {
        $allowedExt = match ($contentType) {
            'SLIDESHOW' => self::SLIDESHOW_EXT,
            'FLYER'     => self::FLYER_EXT,
            default     => self::ALLOWED_EXT,
        };
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, $allowedExt, true)) {
            throw new \RuntimeException('Cause: "' . $ext . '" is not a supported format for ' . self::CONTENT_TYPES[$contentType] . '. Fix: upload one of: ' . implode(', ', $allowedExt) . '.');
        }

        $storedName = $videoId . '.' . $ext;
        $originalName = $file->getClientOriginalName();
        $file->move($folderPath, $storedName);

        return [$storedName, $originalName];
    }

    // NEW 10 Aug 2026 — per Chris: "the admin wont remember when this
    // video upload come from who." GeneralLink has no automatic inbox for
    // vendor emails/WhatsApp (vendors have no Help Desk access), so this
    // is a short category + a free-text note Admin fills in at upload
    // time with the real identifying details — honest about what can and
    // can't be automatically linked back to.
    public const SOURCE_TYPES = [
        'DIRECT_UPLOAD'    => 'Direct Upload (no external source)',
        'EMAIL'            => 'Received by Email',
        'WHATSAPP'         => 'Received via WhatsApp',
        'HELP_DESK'        => 'Received via Help Desk Message',
        // NEW 10 Aug 2026 — per Chris: "all agents including vendor is
        // allow to send attachment...marketing documents...to admin."
        // Set automatically by ContentSubmissionController — the
        // submitter never picks this themselves, it's simply true.
        'AGENT_SUBMITTED'  => 'Submitted by Agent',
        'VENDOR_SUBMITTED' => 'Submitted by Vendor',
        'OTHER'            => 'Other',
    ];

    // NEW 10 Aug 2026 — Introduction and Feature Guide are Admin/system
    // concepts (Introduction = the corporate video on login pages,
    // Feature Guide = an internal "How This Works" explainer) — an
    // agent or vendor submitting their own marketing material should
    // never be able to tag it as either. This is the Category list
    // ContentSubmissionController offers instead of the full TYPES.
    public const SUBMITTABLE_TYPES = [
        'MARKETING_PROMOTION' => 'Marketing / Promotion',
        'ANNOUNCEMENT'        => 'Announcement',
        'PRODUCT_EXPLANATION' => 'Product Explanation',
        'OTHER'               => 'Other',
    ];

    // NEW 10 Aug 2026 — status labels for the Content Library filter
    // dropdown, now that submissions add two more possible values on
    // top of the original Active/Inactive.
    public const STATUSES = [
        'ACTIVE'         => 'Active',
        'INACTIVE'       => 'Inactive',
        'PENDING_REVIEW' => 'Pending Review',
        'REJECTED'       => 'Rejected',
    ];

    // NEW 9 Aug 2026 — per Chris: "i want it standardize across all login
    // page." Single source of truth for "which video is THE intro video
    // right now" (newest ACTIVE video tagged INTRODUCTION) — every guest
    // page (agent login, agent register, vendor login, vendor register)
    // calls this same helper via partials.intro-video-widget, so they can
    // never drift out of sync with each other. Wrapped in try/catch since
    // these are all public guest pages that must keep working even before
    // the video_library migration has been run.
    public static function latestIntroVideo(): ?object
    {
        try {
            return DB::table('video_library')
                ->where('video_type', 'INTRODUCTION')
                ->where('status', 'ACTIVE')
                // NEW 10 Aug 2026 — the guest-page widget plays this
                // through a <video> tag, so it must actually BE a video,
                // never a slideshow/flyer/link that happens to be
                // tagged Introduction. content_type may not exist yet
                // on older rows — the migration defaults it to VIDEO so
                // this stays safe either way.
                ->where('content_type', 'VIDEO')
                ->orderByDesc('created_at')
                ->first();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Video Library not available yet: ' . $e->getMessage());
            return null;
        }
    }

    // NEW 10 Aug 2026 — same pattern as latestIntroVideo() above, but
    // for a specific Growth & Outreach screen's own explainer video
    // (newest ACTIVE FEATURE_GUIDE video tagged with that feature_key).
    // Wrapped in try/catch so every screen keeps working even before the
    // feature_key migration has been run.
    public static function featureGuideVideo(string $featureKey): ?object
    {
        try {
            return DB::table('video_library')
                ->where('video_type', 'FEATURE_GUIDE')
                ->where('feature_key', $featureKey)
                ->where('status', 'ACTIVE')
                ->orderByDesc('created_at')
                ->first();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Video Library feature_key not available yet: ' . $e->getMessage());
            return null;
        }
    }

    public function index(Request $request)
    {
        $folderPath = DB::table('system_settings')->where('setting_key', self::SETTING_KEY)->value('setting_value');

        // NEW 10 Aug 2026 — per Chris: "does the admin can view the
        // message send to him earlier or created by admin before because
        // in live environment...the video was created by admin may left
        // the company and the new admin staff can view back the log."
        // Joins agents so WHO uploaded it survives even if that Admin's
        // own account is later deactivated (agents are soft-deleted, so
        // the name always still resolves).
        $query = DB::table('video_library as vl')
            ->leftJoin('vendors as v', 'vl.vendor_id', '=', 'v.vendor_id')
            ->leftJoin('agents as a', 'vl.created_by', '=', 'a.agent_id')
            ->orderByDesc('vl.created_at')
            ->select('vl.*', 'v.vendor_name', 'a.full_name as uploaded_by_name');
        if ($request->filled('type')) {
            $query->where('vl.video_type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('vl.status', $request->status);
        }
        if ($request->filled('content_type')) {
            $query->where('vl.content_type', $request->content_type);
        }
        $videos = $query->paginate(6)->withQueryString();

        // NEW 10 Aug 2026 — per Chris: with several Introduction videos
        // possibly sitting ACTIVE at once, Admin needs to be able to tell
        // at a glance which ONE is actually live on the login pages right
        // now (the newest Active Introduction video — see
        // latestIntroVideo()). Also flags anything past its expiry_date.
        $featuredIntroId = self::latestIntroVideo()->video_id ?? null;
        $today = now()->toDateString();
        foreach ($videos as $video) {
            $video->is_featured_intro = $video->video_id === $featuredIntroId;
            $video->is_expired = $video->expiry_date && $video->expiry_date < $today;
        }

        $folderReady = $folderPath && is_dir($folderPath) && is_writable($folderPath);

        // NEW 10 Aug 2026 — per Chris: agent/vendor submissions land as
        // Pending Review; Admin should see at a glance how many are
        // waiting without having to think to filter for them.
        $pendingCount = DB::table('video_library')->where('status', 'PENDING_REVIEW')->count();

        return view('admin.video-library.index', [
            'videos'       => $videos,
            'folderPath'   => $folderPath,
            'folderReady'  => $folderReady,
            'types'        => self::TYPES,
            'sourceTypes'  => self::SOURCE_TYPES,
            'contentTypes' => self::CONTENT_TYPES,
            'statuses'     => self::STATUSES,
            'pendingCount' => $pendingCount,
        ]);
    }

    // NEW 10 Aug 2026 per Chris: "your add video should have it won
    // screen and when save it go back to the video library screen" — the
    // list and the Add form used to be squeezed side-by-side (1fr/340px),
    // which crushed the list's own table columns. Add Video is now its
    // own dedicated screen, same pattern already used elsewhere in this
    // app for "list screen" + "New X screen" pairs (e.g. Document
    // Templates / New Document Template).
    public function create()
    {
        $folderPath = DB::table('system_settings')->where('setting_key', self::SETTING_KEY)->value('setting_value');
        $folderReady = $folderPath && is_dir($folderPath) && is_writable($folderPath);

        return view('admin.video-library.create', [
            'types'        => self::TYPES,
            'sourceTypes'  => self::SOURCE_TYPES,
            'featureKeys'  => self::FEATURE_KEYS,
            'contentTypes' => self::CONTENT_TYPES,
            'folderReady'  => $folderReady,
        ]);
    }

    // NOTE: the Add Video form's "Vendor (optional)" field reuses the
    // SAME vendor-typeahead endpoint already built for Rebate Offers /
    // Commission Structures (identical response shape:
    // [{vendor_id, vendor_name}]) — nothing new to build or maintain.

    public function updateFolderPath(Request $request)
    {
        $request->validate(['folder_path' => ['required', 'string', 'max:500']]);
        $path = rtrim(trim($request->folder_path), '\\/');

        if (!is_dir($path)) {
            // Try to create it for Chris automatically — a beginner should
            // not need to manually create a folder on his server first.
            if (!@mkdir($path, 0775, true)) {
                return back()->withErrors(['folder_path' => "Cause: this folder does not exist and GeneralLink could not create it automatically (often a permissions issue or a drive/path typo). Fix: check the path is correct and that XAMPP has permission to create folders there, e.g. D:\\GeneralLinkVideos. Alternative: create the folder yourself in File Explorer first, then paste the exact path here again."]);
            }
        }
        if (!is_writable($path)) {
            return back()->withErrors(['folder_path' => "Cause: GeneralLink found the folder but cannot write files into it (a Windows permissions restriction). Fix: right-click the folder in File Explorer -> Properties -> Security, and give your Windows user Full Control. Alternative: choose a different folder, e.g. one inside your own Documents folder."]);
        }

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => self::SETTING_KEY],
            ['setting_value' => $path, 'updated_by' => auth('agent')->user()->agent_id, 'updated_at' => now()]
        );

        \App\Services\AuditService::logChange('system_settings', self::SETTING_KEY, 'VIDEO_LIBRARY_FOLDER_SET', null, ['folder_path' => $path], auth('agent')->user()->agent_id);

        return back()->with('success', 'Content storage folder saved: ' . $path);
    }

    public function store(Request $request)
    {
        $contentType = $request->input('content_type', 'VIDEO');
        $isLink = $contentType === 'LINK';

        // NEW 10 Aug 2026 — a LINK entry has no file at all, so the
        // storage folder doesn't need to be configured for it.
        $folderPath = DB::table('system_settings')->where('setting_key', self::SETTING_KEY)->value('setting_value');
        if (!$isLink && (!$folderPath || !is_dir($folderPath) || !is_writable($folderPath))) {
            return back()->withErrors(['video_file' => 'Cause: no working content storage folder has been set up yet. Fix: scroll to "Content Storage Folder" above, enter a folder path (e.g. D:\\GeneralLinkVideos) and save it first, then upload again.'])->withInput();
        }

        $request->validate([
            'video_name'   => ['required', 'string', 'max:200'],
            'video_type'   => ['required', Rule::in(array_keys(self::TYPES))],
            // NEW 10 Aug 2026 — per Chris: "do you allow upload slide
            // show and marketing flyer, google link form?" Which kind of
            // content this is — drives which file extensions are
            // allowed, or whether a URL is expected instead of a file.
            'content_type' => ['required', Rule::in(array_keys(self::CONTENT_TYPES))],
            // NEW 10 Aug 2026 — per Chris: track which vendor a video is
            // for/from (e.g. a marketing clip they sent Admin), what it's
            // for, when it was actually sent, and when it stops being
            // relevant. All optional — GeneralLink's own corporate videos
            // (like the Introduction video) have no vendor at all.
            'vendor_id'      => ['nullable', 'exists:vendors,vendor_id'],
            'purpose'        => ['nullable', 'string', 'max:300'],
            'submitted_date' => ['nullable', 'date'],
            'expiry_date'    => ['nullable', 'date'],
            // NEW 10 Aug 2026 — per Chris: "design the drill down the
            // source of the video come from." Short category + free-text
            // note (real sender/date/reference) — see SOURCE_TYPES const.
            'source_type'    => ['required', Rule::in(array_keys(self::SOURCE_TYPES))],
            'source_note'    => ['nullable', 'string', 'max:300', 'required_unless:source_type,DIRECT_UPLOAD'],
            // NEW 10 Aug 2026 — per Chris: "video is easier to understand
            // all this marketing initiative." Only meaningful (and only
            // required) when this video IS a Feature Guide — picks which
            // one of the 8 Growth & Outreach screens it explains.
            'feature_key' => ['nullable', Rule::in(array_keys(self::FEATURE_KEYS)), 'required_if:video_type,FEATURE_GUIDE'],
            'ownership'  => ['nullable', 'string', 'max:150'],
            'status'     => ['required', 'in:ACTIVE,INACTIVE'],
            // NEW 10 Aug 2026 — file required for VIDEO/SLIDESHOW/FLYER,
            // a URL required for LINK instead (never both).
            'video_file'   => ['required_unless:content_type,LINK', 'nullable', 'file', 'max:512000'], // 500MB — see note to Chris about php.ini limits
            'external_url' => ['required_if:content_type,LINK', 'nullable', 'url', 'max:500'],
        ], [
            'source_note.required_unless' => 'Please note down where this came from (e.g. the sender\'s email/phone number and the date they sent it) — this is what a future Admin will read if they need to trace this video back.',
            'feature_key.required_if' => 'Please pick which screen this guide video explains, so its "How This Works" button appears in the right place.',
            'video_file.required_unless' => 'Please choose a file to upload.',
            'external_url.required_if' => 'Please paste the link (e.g. the Google Form URL).',
        ]);

        $videoId = (string) Str::uuid();
        $storedName = null;
        $originalName = null;

        if (!$isLink) {
            try {
                [$storedName, $originalName] = self::saveContentFile($request->file('video_file'), $contentType, $folderPath, $videoId);
            } catch (\RuntimeException $e) {
                return back()->withErrors(['video_file' => $e->getMessage()])->withInput();
            }
        }

        DB::table('video_library')->insert([
            'video_id'           => $videoId,
            'video_name'         => $request->video_name,
            'video_type'         => $request->video_type,
            'content_type'       => $contentType,
            'vendor_id'          => $request->vendor_id ?: null,
            'stored_file_name'   => $storedName,
            'original_file_name' => $originalName,
            'external_url'       => $isLink ? $request->external_url : null,
            'ownership'          => $request->ownership,
            'purpose'            => $request->purpose,
            'submitted_date'     => $request->submitted_date ?: now()->toDateString(),
            'expiry_date'        => $request->expiry_date ?: null,
            'source_type'        => $request->source_type,
            'source_note'        => $request->source_note,
            'feature_key'        => $request->video_type === 'FEATURE_GUIDE' ? $request->feature_key : null,
            'status'             => $request->status,
            'created_by'         => auth('agent')->user()->agent_id,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // NEW 10 Aug 2026 — per Chris: "how admin upload new version of
        // introduction video" — only ONE Introduction video can ever be
        // "the" featured one (the "Watch Intro Video" button always plays
        // the newest Active Introduction video). If this new upload is
        // itself an Active Introduction video, auto-deactivate every
        // OTHER Active Introduction video so the list never shows several
        // "Active" ones that aren't actually all live — uploading a new
        // version IS the entire replace workflow, no manual cleanup step.
        // Deliberately NOT applied to Marketing/Promotion — several of
        // those can legitimately run active at the same time.
        if ($request->video_type === 'INTRODUCTION' && $request->status === 'ACTIVE') {
            DB::table('video_library')
                ->where('video_type', 'INTRODUCTION')
                ->where('video_id', '!=', $videoId)
                ->where('status', 'ACTIVE')
                ->update(['status' => 'INACTIVE', 'updated_at' => now()]);
        }

        // NEW 10 Aug 2026 — per Chris: auto-notify eligible agents when
        // new Marketing/Promotion content goes Active, so Admin never
        // forgets to actually tell anyone it's available.
        if ($request->video_type === 'MARKETING_PROMOTION' && $request->status === 'ACTIVE') {
            $this->notifyMarketingContentActive($request->video_name, $contentType);
        }

        \App\Services\AuditService::logChange('video_library', $videoId, 'VIDEO_ADDED', null, $request->only(['video_name', 'video_type', 'content_type', 'status']), auth('agent')->user()->agent_id);

        return redirect()->route('admin.video-library.index')->with('success', self::CONTENT_TYPES[$contentType] . ' added: ' . $request->video_name);
    }

    // NEW 10 Aug 2026 — per Chris: "recommend an effective communication
    // ...strategy between the sender and the recipients" — when Admin
    // marks a Marketing/Promotion item Active (new upload, or an old one
    // reactivated via toggle()), eligible agents get told it's available
    // rather than Admin having to remember to announce it separately.
    // Same NotificationService + audience (downline agents) already used
    // for Recruitment Contest announcements.
    private function notifyMarketingContentActive(string $name, string $contentType): void
    {
        $recipients = \App\Models\Agent::where('status', 'ACTIVE')
            ->where('is_deleted', false)
            ->whereIn('role', ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER'])
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $kind = self::CONTENT_TYPES[$contentType] ?? 'content';
        app(\App\Services\NotificationService::class)->notify(
            $recipients->all(),
            'MARKETING_CONTENT_AVAILABLE',
            'New Marketing Material Available',
            "New {$kind} is now available: \"{$name}\". Check Growth & Outreach Center > Broadcast Campaigns or Recruitment Contests to use it, or ask Admin how to share it."
        );
    }

    // NEW 10 Aug 2026 — per Chris: "admin received and upload to the
    // master file and activate." Approving a Pending Review submission
    // makes it Active immediately (pickable in Broadcast Campaigns /
    // Recruitment Contests right away) — no separate "re-upload" step,
    // since the file is already saved from the moment it was submitted.
    public function approve(string $videoId)
    {
        $video = DB::table('video_library')->where('video_id', $videoId)->first();
        if (!$video) {
            abort(404);
        }
        if ($video->status !== 'PENDING_REVIEW') {
            return back()->withErrors(['status' => 'Only Pending Review items can be approved.']);
        }

        DB::table('video_library')->where('video_id', $videoId)->update([
            'status'      => 'ACTIVE',
            'reviewed_by' => auth('agent')->user()->agent_id,
            'reviewed_at' => now(),
            'updated_at'  => now(),
        ]);

        if ($video->video_type === 'MARKETING_PROMOTION') {
            $this->notifyMarketingContentActive($video->video_name, $video->content_type ?? 'VIDEO');
        }
        $this->notifySubmitter($video, 'ACTIVATED', null);

        \App\Services\AuditService::logChange('video_library', $videoId, 'VIDEO_SUBMISSION_APPROVED', ['status' => 'PENDING_REVIEW'], ['status' => 'ACTIVE'], auth('agent')->user()->agent_id);

        return back()->with('success', 'Approved and activated: ' . $video->video_name);
    }

    public function reject(Request $request, string $videoId)
    {
        $video = DB::table('video_library')->where('video_id', $videoId)->first();
        if (!$video) {
            abort(404);
        }
        if ($video->status !== 'PENDING_REVIEW') {
            return back()->withErrors(['status' => 'Only Pending Review items can be rejected.']);
        }
        $request->validate(['rejection_reason' => ['required', 'string', 'max:300']], [
            'rejection_reason.required' => 'Please explain why, so the sender knows what to fix or knows it was intentional.',
        ]);

        DB::table('video_library')->where('video_id', $videoId)->update([
            'status'            => 'REJECTED',
            'rejection_reason'  => $request->rejection_reason,
            'reviewed_by'       => auth('agent')->user()->agent_id,
            'reviewed_at'       => now(),
            'updated_at'        => now(),
        ]);

        $this->notifySubmitter($video, 'REJECTED', $request->rejection_reason);

        \App\Services\AuditService::logChange('video_library', $videoId, 'VIDEO_SUBMISSION_REJECTED', ['status' => 'PENDING_REVIEW'], ['status' => 'REJECTED', 'reason' => $request->rejection_reason], auth('agent')->user()->agent_id);

        return back()->with('success', 'Rejected: ' . $video->video_name);
    }

    // NEW 10 Aug 2026 — per Chris: submitter should be told either way
    // (Recommended option). Agents get the normal in-app bell + email
    // (NotificationService). Vendors have no bell/in-app notifications
    // in this app at all (confirmed — the notifications table is
    // agent-only) so they get a direct email instead, same pattern as
    // the vendor registration confirmation email.
    private function notifySubmitter(object $video, string $outcome, ?string $reason): void
    {
        $verb = $outcome === 'ACTIVATED' ? 'approved and is now live' : 'was not approved';
        $reasonLine = $reason ? "\n\nAdmin's note: {$reason}" : '';

        if ($video->created_by) {
            $agent = \App\Models\Agent::find($video->created_by);
            if ($agent) {
                app(\App\Services\NotificationService::class)->notify(
                    [$agent],
                    'CONTENT_SUBMISSION_' . $outcome,
                    'Your submitted content ' . ($outcome === 'ACTIVATED' ? 'is live' : 'was rejected'),
                    "\"{$video->video_name}\" was {$verb}.{$reasonLine}"
                );
            }
        } elseif ($video->vendor_id) {
            $vendor = DB::table('vendors')->where('vendor_id', $video->vendor_id)->first();
            if ($vendor && $vendor->vendor_email) {
                try {
                    \Illuminate\Support\Facades\Mail::raw(
                        "Hi {$vendor->pic_name},\n\nThe content you sent us, \"{$video->video_name}\", was {$verb}.{$reasonLine}\n\nKind Regards,\nGeneralLink Admin",
                        function ($mail) use ($vendor) {
                            $mail->to($vendor->vendor_email)->subject('GeneralLink — Update on Your Submitted Content');
                        }
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Vendor content-submission email failed: ' . $e->getMessage());
                }
            }
        }
    }

    public function toggle(string $videoId)
    {
        $video = DB::table('video_library')->where('video_id', $videoId)->first();
        if (!$video) {
            abort(404);
        }
        // NEW 10 Aug 2026 — a Pending Review / Rejected submission must
        // go through approve()/reject() (they have their own review
        // trail + notify-the-submitter logic), never this plain
        // Active<->Inactive flip.
        if (!in_array($video->status, ['ACTIVE', 'INACTIVE'], true)) {
            return back()->withErrors(['status' => 'This item is still Pending Review (or was Rejected) — use the Approve/Reject buttons instead.']);
        }
        $newStatus = $video->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
        DB::table('video_library')->where('video_id', $videoId)->update(['status' => $newStatus, 'updated_at' => now()]);

        // Same Introduction-exclusivity rule as store() — manually
        // activating an old Introduction video makes it "the" featured
        // one, so every other Active Introduction video steps aside.
        if ($video->video_type === 'INTRODUCTION' && $newStatus === 'ACTIVE') {
            DB::table('video_library')
                ->where('video_type', 'INTRODUCTION')
                ->where('video_id', '!=', $videoId)
                ->where('status', 'ACTIVE')
                ->update(['status' => 'INACTIVE', 'updated_at' => now()]);
        }

        // NEW 10 Aug 2026 — reactivating an old Marketing/Promotion item
        // is just as much "new to agents right now" as a fresh upload —
        // same auto-notify as store().
        if ($video->video_type === 'MARKETING_PROMOTION' && $newStatus === 'ACTIVE') {
            $this->notifyMarketingContentActive($video->video_name, $video->content_type ?? 'VIDEO');
        }

        \App\Services\AuditService::logChange('video_library', $videoId, 'VIDEO_STATUS_TOGGLED', ['status' => $video->status], ['status' => $newStatus], auth('agent')->user()->agent_id);

        return back()->with('success', 'Content ' . ($newStatus === 'ACTIVE' ? 'activated' : 'deactivated') . '.');
    }

    public function destroy(string $videoId)
    {
        $video = DB::table('video_library')->where('video_id', $videoId)->first();
        if (!$video) {
            abort(404);
        }
        $folderPath = DB::table('system_settings')->where('setting_key', self::SETTING_KEY)->value('setting_value');
        if ($folderPath) {
            $fullPath = rtrim($folderPath, '\\/') . DIRECTORY_SEPARATOR . $video->stored_file_name;
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
        DB::table('video_library')->where('video_id', $videoId)->delete();

        \App\Services\AuditService::logChange('video_library', $videoId, 'VIDEO_DELETED', ['video_name' => $video->video_name], null, auth('agent')->user()->agent_id);

        return back()->with('success', 'Removed: ' . $video->video_name);
    }

    // PUBLIC route — reachable from the guest-facing Vendor Registration
    // page as well as from the Admin screen. ACTIVE videos are always
    // servable; INACTIVE ones only stream for a logged-in Admin previewing
    // their own library. Uses response()->file() (a Symfony
    // BinaryFileResponse) which handles HTTP Range requests automatically,
    // so the browser's <video> tag can seek/scrub normally.
    public function stream(string $videoId)
    {
        $video = DB::table('video_library')->where('video_id', $videoId)->first();
        if (!$video) {
            abort(404, 'Video not found.');
        }

        $isAdmin = auth('agent')->check() && auth('agent')->user()->role === 'ADMIN';
        // NEW 10 Aug 2026 — the original submitter can preview their
        // own Pending Review / Rejected item from "My Submissions",
        // even though it isn't Active yet.
        $isOwnSubmission = (auth('agent')->check() && $video->created_by === auth('agent')->user()->agent_id)
            || (auth('vendor')->check() && $video->vendor_id === auth('vendor')->user()->vendor_id);
        if ($video->status !== 'ACTIVE' && !$isAdmin && !$isOwnSubmission) {
            abort(404, 'Video not available.');
        }

        // NEW 10 Aug 2026 — a LINK entry has no file on disk at all —
        // just send the browser straight to the external URL.
        if (($video->content_type ?? 'VIDEO') === 'LINK') {
            if (!$video->external_url) {
                abort(404, 'Link not set.');
            }
            return redirect()->away($video->external_url);
        }

        $folderPath = DB::table('system_settings')->where('setting_key', self::SETTING_KEY)->value('setting_value');
        if (!$folderPath) {
            abort(404, 'Video storage folder is not configured.');
        }

        $fullPath = realpath(rtrim($folderPath, '\\/') . DIRECTORY_SEPARATOR . $video->stored_file_name);
        $folderReal = realpath($folderPath);

        // Basic path-traversal guard — the resolved file must stay inside
        // the configured folder.
        if (!$fullPath || !$folderReal || !str_starts_with($fullPath, $folderReal)) {
            abort(404, 'Video file not found.');
        }
        if (!is_file($fullPath)) {
            abort(404, 'Video file is missing from the storage folder — it may have been moved or deleted outside GeneralLink.');
        }

        return response()->file($fullPath);
    }
}
