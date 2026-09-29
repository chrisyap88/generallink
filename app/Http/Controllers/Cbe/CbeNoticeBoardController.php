<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use App\Services\AiAssistantService;
use App\Services\CbeCommitteeAuthService;
use App\Services\CbeNoticeStyleService;
use App\Services\CbeSeasonThemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// NEW 17 Sep 2026 — per Chris: "as a member of the temple/entity i like
// to manage add/edit/delete the temple noticeboard what are the event
// planned and announce by the temple." Every member of the node can
// read; only that node's officers or current Secretary can post/edit/
// delete (CbeCommitteeAuthService — see that file's own comment).
// Deliberately its own table (cbe_temple_notices), never the
// platform-wide `notices` table Admin\NoticeBoardController manages —
// that one broadcasts to every agent on GeneralLink, which would be the
// wrong audience for one temple's own announcements.
//
// UPDATED 17 Sep 2026 — per Chris's 4-part follow-up request: (a)
// Carolyn AI rewrite reused here (aiAssist), (b) a live preview of how
// the notice will display — content-aware + seasonal styling, done via
// styleDetect() feeding the create/edit form's own preview panel, no
// separate screen needed, (c) some notices are posted automatically by
// the system (see App\Console\Commands\GenerateCbeSystemNotices) —
// festival greetings and opt-in birthday notices — and are shown here
// read-only, (d) three independent attachment slots (Flyer/Catalog/
// Video), each either a pasted link or an uploaded file.
class CbeNoticeBoardController extends Controller
{
    use ResolvesCbeActiveNode;

    private const CATEGORIES = ['ANNOUNCEMENT', 'EVENT', 'GENERAL'];
    private const STYLE_KEYS = ['AUTO', 'SECURITY_ALERT', 'SCAM_WARNING', 'CELEBRATION', 'BIRTHDAY', 'PROMO', 'GENERAL'];

    public function index(Request $request, CbeNoticeStyleService $styles, CbeSeasonThemeService $seasons)
    {
        $agent = Auth::guard('agent')->user();
        // FIXED 17 Sep 2026 — per Chris: this screen must be readable by
        // every member, not just officers/Admin. resolveCbeNodeId() alone
        // only ever resolves for an officer or Admin; the member fallback
        // (via cbe_group_memberships) lives in resolveCbeNodeIdForMember().
        $nodeId = $this->resolveCbeNodeIdForMember($agent);

        if (! $nodeId && $agent->role === 'ADMIN') {
            return $this->renderCbeNodePicker('cbe.notice-board.index', __('cbe_records.notice_board_page_title'), leafOnly: true, countResolver: function (array $nodeIds) {
                return DB::table('cbe_temple_notices')
                    ->whereIn('cbe_node_id', $nodeIds)->where('is_deleted', false)
                    ->selectRaw('cbe_node_id, COUNT(*) as cnt')
                    ->groupBy('cbe_node_id')
                    ->pluck('cnt', 'cbe_node_id')
                    ->all();
            });
        }

        $canManage = CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId);
        $tab = $request->get('tab', 'active');
        $today = now()->toDateString();

        $notices = collect();
        $myBirthdayCard = null;
        $seasonTheme = $seasons->current();

        if ($nodeId) {
            // The member's own private birthday card, if the system has
            // posted one for them this year — never shown to anyone else.
            $myBirthdayCard = DB::table('cbe_temple_notices')
                ->where('cbe_node_id', $nodeId)->where('is_deleted', false)
                ->where('visibility', 'MEMBER_PRIVATE')->where('target_agent_id', $agent->agent_id)
                ->where('notice_type', 'BIRTHDAY_CARD')
                ->where(function ($q) use ($today) { $q->whereNull('expires_at')->orWhere('expires_at', '>=', $today); })
                ->orderByDesc('created_at')->first();

            $query = DB::table('cbe_temple_notices')->where('cbe_node_id', $nodeId)->where('is_deleted', false)
                ->where('visibility', 'PUBLIC');
            if ($tab === 'expired') {
                $query->whereNotNull('expires_at')->where('expires_at', '<', $today);
            } else {
                $query->where(function ($q) use ($today) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>=', $today);
                });
            }
            $notices = $query->orderByRaw('expires_at IS NULL DESC')->orderByDesc('created_at')
                ->paginate(8, ['*'], 'nbPage')->appends(['tab' => $tab]);

            foreach ($notices as $n) {
                $n->styleRow = $styles->find($n->style_key);
                $n->listingRow = $n->listing_id
                    ? DB::table('cbe_marketplace_listings')->where('listing_id', $n->listing_id)->where('status', 'ACTIVE')->first()
                    : null;
            }
        }

        return view('cbe.notice-board.index', [
            'notices' => $notices, 'hasNode' => (bool) $nodeId, 'canManage' => $canManage, 'tab' => $tab,
            'myBirthdayCard' => $myBirthdayCard, 'seasonTheme' => $seasonTheme,
            'shareBirthday' => (bool) $agent->share_birthday_public,
        ]);
    }

    public function create(CbeNoticeStyleService $styles)
    {
        $agent = Auth::guard('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        abort_unless(CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId), 403, __('cbe_records.not_authorized_note'));

        // NEW 18 Sep 2026 — per Chris: promote a vendor/entity Marketplace
        // Listing straight from a Notice Board blast. Optional — an
        // ordinary announcement just leaves this unselected.
        $listings = DB::table('cbe_marketplace_listings')->where('cbe_node_id', $nodeId)->where('status', 'ACTIVE')->orderBy('title')->get();

        return view('cbe.notice-board.create', ['styleOptions' => $styles->activeStyles(), 'listings' => $listings]);
    }

    public function store(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        abort_unless(CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId), 403, __('cbe_records.not_authorized_note'));

        $data = $this->validateNoticeInput($request);
        $attachments = $this->handleAttachments($request);

        DB::table('cbe_temple_notices')->insert(array_merge($data, $attachments, [
            'notice_id'          => (string) Str::uuid(),
            'cbe_node_id'        => $nodeId,
            'notice_source'      => 'OFFICER',
            'visibility'         => 'PUBLIC',
            'posted_by_agent_id' => $agent->agent_id,
            'is_deleted'         => false,
            'created_at'         => now(), 'updated_at' => now(),
        ]));

        return redirect()->route('cbe.notice-board.index')->with('success', __('cbe_records.notice_board_posted_success'));
    }

    // NEW 18 Sep 2026 — per Chris: "if a member interested to join as
    // member as per the cbe offer" — one tap on a promoted Notice
    // creates a Marketplace Order for the tapping member, reusing
    // cbe_marketplace_listings/orders exactly as already built (Task
    // #418), not a new ordering engine. The member's own Customer
    // Master record is resolved-or-created via their agent_id link
    // (Phase 1 normalization) rather than re-typing their name —
    // matches Chris's own donor/customer normalization rule going
    // forward for every NEW record, even though the older rows aren't
    // migrated yet.
    public function joinListing(Request $request, string $noticeId)
    {
        $agent = Auth::guard('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);
        abort_unless($nodeId, 404);

        $notice = DB::table('cbe_temple_notices')->where('notice_id', $noticeId)->where('cbe_node_id', $nodeId)->where('is_deleted', false)->firstOrFail();
        abort_unless($notice->listing_id, 404);

        $listing = DB::table('cbe_marketplace_listings')->where('listing_id', $notice->listing_id)->where('cbe_node_id', $nodeId)->where('status', 'ACTIVE')->firstOrFail();

        $customerId = DB::table('cbe_customers')->where('cbe_node_id', $nodeId)->where('agent_id', $agent->agent_id)->value('customer_id');
        if (! $customerId) {
            $customerId = (string) Str::uuid();
            DB::table('cbe_customers')->insert([
                'customer_id' => $customerId,
                'cbe_node_id' => $nodeId,
                'agent_id' => $agent->agent_id,
                'is_donor' => false,
                'customer_name' => $agent->full_name,
                'phone' => $agent->phone ?? null,
                'email' => $agent->email ?? null,
                'created_by' => $agent->agent_id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('cbe_marketplace_orders')->insert([
            'order_id' => (string) Str::uuid(),
            'cbe_node_id' => $nodeId,
            'listing_id' => $listing->listing_id,
            'buyer_customer_id' => $customerId,
            'quantity' => 1,
            'unit_price' => $listing->price,
            'total_amount' => $listing->price,
            'payment_status' => 'PENDING_PAYMENT',
            'recorded_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('cbe.notice-board.index')->with('success', __('cbe_records.notice_board_join_success'));
    }

    public function edit(string $noticeId, CbeNoticeStyleService $styles)
    {
        $agent = Auth::guard('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        abort_unless(CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId), 403, __('cbe_records.not_authorized_note'));

        $notice = DB::table('cbe_temple_notices')->where('notice_id', $noticeId)->where('cbe_node_id', $nodeId)->firstOrFail();
        abort_if($notice->notice_source === 'SYSTEM', 403, __('cbe_records.system_notice_readonly_note'));

        $listings = DB::table('cbe_marketplace_listings')->where('cbe_node_id', $nodeId)->where('status', 'ACTIVE')->orderBy('title')->get();

        return view('cbe.notice-board.edit', ['notice' => $notice, 'styleOptions' => $styles->activeStyles(), 'listings' => $listings]);
    }

    public function update(Request $request, string $noticeId)
    {
        $agent = Auth::guard('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        abort_unless(CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId), 403, __('cbe_records.not_authorized_note'));

        $notice = DB::table('cbe_temple_notices')->where('notice_id', $noticeId)->where('cbe_node_id', $nodeId)->firstOrFail();
        abort_if($notice->notice_source === 'SYSTEM', 403, __('cbe_records.system_notice_readonly_note'));

        $data = $this->validateNoticeInput($request);
        $attachments = $this->handleAttachments($request, $notice);

        DB::table('cbe_temple_notices')->where('notice_id', $noticeId)->update(array_merge($data, $attachments, [
            'updated_at' => now(),
        ]));

        return redirect()->route('cbe.notice-board.index')->with('success', __('cbe_records.notice_board_updated_success'));
    }

    public function destroy(string $noticeId)
    {
        $agent = Auth::guard('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        abort_unless(CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId), 403, __('cbe_records.not_authorized_note'));

        $updated = DB::table('cbe_temple_notices')->where('notice_id', $noticeId)->where('cbe_node_id', $nodeId)
            ->update(['is_deleted' => true, 'updated_at' => now()]);
        abort_if($updated === 0, 404);

        return back()->with('success', __('cbe_records.notice_board_deleted_success'));
    }

    public function attachment(string $noticeId)
    {
        return $this->serveAttachment($noticeId, 'attachment_file_path');
    }

    public function catalogFile(string $noticeId)
    {
        return $this->serveAttachment($noticeId, 'catalog_file_path');
    }

    public function videoFile(string $noticeId)
    {
        return $this->serveAttachment($noticeId, 'video_file_path');
    }

    private function serveAttachment(string $noticeId, string $column)
    {
        $agent = Auth::guard('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);

        $notice = DB::table('cbe_temple_notices')->where('notice_id', $noticeId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $path = $notice->{$column};
        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($path));
    }

    // NEW 17 Sep 2026 — "use back ask carolyn help to rephrase the
    // notice": exact same AiAssistantService::improveNoticeText() Admin's
    // own Notice Board already uses (see Admin\NoticeBoardController::
    // aiAssist and AiAssistantService::improveNoticeText). Nothing is
    // auto-applied — officer reviews Carolyn's suggestion and clicks
    // "Use This" themselves.
    public function aiAssist(Request $request, AiAssistantService $ai)
    {
        $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'body'  => ['required', 'string', 'max:3000'],
        ]);

        $result = $ai->improveNoticeText((string) $request->input('title', ''), (string) $request->input('body'));

        if ($result['status'] !== 'OK') {
            return response()->json(['status' => 'ERROR', 'message' => $result['message'] ?? "Sorry, Carolyn couldn't rewrite that just now — please try again."], 200);
        }

        return response()->json(['status' => 'OK', 'title' => $result['title'], 'body' => $result['body']]);
    }

    // NEW 17 Sep 2026 — feeds the create/edit form's live preview panel:
    // given whatever title/body is currently typed, return the style
    // (colours/icon/label) that would be auto-applied, so the officer
    // sees the exact same card that will appear on the board.
    public function styleDetect(Request $request, CbeNoticeStyleService $styles)
    {
        $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'body'  => ['nullable', 'string', 'max:3000'],
        ]);

        $key = $styles->detect((string) $request->input('title', ''), (string) $request->input('body', ''));
        $style = $styles->find($key);

        return response()->json(['status' => 'OK', 'style' => $style]);
    }

    // NEW 17 Sep 2026 — per Chris: birthday notices are opt-in only. A
    // member turns this on/off for themselves right here on their own
    // Notice Board — no separate profile screen needed for one toggle.
    public function toggleBirthdayShare(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        $on = $request->boolean('share_birthday_public');

        DB::table('agents')->where('agent_id', $agent->agent_id)->update(['share_birthday_public' => $on]);

        return response()->json(['status' => 'OK', 'share_birthday_public' => $on]);
    }

    private function validateNoticeInput(Request $request): array
    {
        $styleService = app(CbeNoticeStyleService::class);
        // Admin-editable catalog — validate against whatever's actually
        // active right now, never a hardcoded list, so a style Admin adds
        // later works here immediately.
        $allowedStyleKeys = $styleService->activeStyles()->pluck('style_key')->push('AUTO')->all();

        $request->validate([
            'title'          => ['required', 'string', 'max:150'],
            'category'       => ['required', 'in:'.implode(',', self::CATEGORIES)],
            'body'           => ['required', 'string', 'max:3000'],
            'expires_at'     => ['nullable', 'date'],
            'style_key'      => ['nullable', 'in:'.implode(',', $allowedStyleKeys)],
            'flyer_type'     => ['nullable', 'in:NONE,LINK,FILE'],
            'flyer_link_url' => ['nullable', 'url', 'max:500'],
            'attachment'     => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
            'catalog_type'       => ['nullable', 'in:NONE,LINK,FILE'],
            'catalog_link_url'   => ['nullable', 'url', 'max:500'],
            'catalog_attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
            'video_type'       => ['nullable', 'in:NONE,LINK,FILE'],
            'video_link_url'   => ['nullable', 'url', 'max:500'],
            'video_attachment' => ['nullable', 'file', 'mimes:mp4,mov,jpg,jpeg,png,pdf', 'max:20480'],
            'listing_id'       => ['nullable', 'uuid', 'exists:cbe_marketplace_listings,listing_id'],
        ]);

        $requestedStyle = $request->input('style_key', 'AUTO');
        $isOverride = $requestedStyle && $requestedStyle !== 'AUTO';
        $styleKey = $isOverride ? $requestedStyle : $styleService->detect((string) $request->input('title'), (string) $request->input('body'));

        return [
            'title'            => $request->input('title'),
            'body'             => $request->input('body'),
            'category'         => $request->input('category'),
            'expires_at'       => $request->input('expires_at'),
            'style_key'        => $styleKey,
            'style_is_override' => $isOverride,
            'listing_id'       => $request->input('listing_id') ?: null,
        ];
    }

    private function handleAttachments(Request $request, ?object $existing = null): array
    {
        $out = [
            'attachment_file_name' => $existing->attachment_file_name ?? null,
            'attachment_file_path' => $existing->attachment_file_path ?? null,
            'flyer_link_url'       => null,
            'catalog_file_name'    => $existing->catalog_file_name ?? null,
            'catalog_file_path'    => $existing->catalog_file_path ?? null,
            'catalog_link_url'     => null,
            'video_file_name'      => $existing->video_file_name ?? null,
            'video_file_path'      => $existing->video_file_path ?? null,
            'video_link_url'       => null,
        ];

        // Flyer
        $flyerType = $request->input('flyer_type', 'NONE');
        if ($flyerType === 'LINK') {
            $out['flyer_link_url'] = $request->input('flyer_link_url');
            $out['attachment_file_name'] = null;
            $out['attachment_file_path'] = null;
        } elseif ($flyerType === 'FILE' && $request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $out['attachment_file_path'] = $file->store('cbe-temple-notice-attachments', 'local');
            $out['attachment_file_name'] = $file->getClientOriginalName();
        } elseif ($flyerType === 'NONE') {
            $out['attachment_file_name'] = null;
            $out['attachment_file_path'] = null;
        }

        // Catalog
        $catalogType = $request->input('catalog_type', 'NONE');
        if ($catalogType === 'LINK') {
            $out['catalog_link_url'] = $request->input('catalog_link_url');
            $out['catalog_file_name'] = null;
            $out['catalog_file_path'] = null;
        } elseif ($catalogType === 'FILE' && $request->hasFile('catalog_attachment')) {
            $file = $request->file('catalog_attachment');
            $out['catalog_file_path'] = $file->store('cbe-temple-notice-attachments', 'local');
            $out['catalog_file_name'] = $file->getClientOriginalName();
        } elseif ($catalogType === 'NONE') {
            $out['catalog_file_name'] = null;
            $out['catalog_file_path'] = null;
        }

        // Video
        $videoType = $request->input('video_type', 'NONE');
        if ($videoType === 'LINK') {
            $out['video_link_url'] = $request->input('video_link_url');
            $out['video_file_name'] = null;
            $out['video_file_path'] = null;
        } elseif ($videoType === 'FILE' && $request->hasFile('video_attachment')) {
            $file = $request->file('video_attachment');
            $out['video_file_path'] = $file->store('cbe-temple-notice-attachments', 'local');
            $out['video_file_name'] = $file->getClientOriginalName();
        } elseif ($videoType === 'NONE') {
            $out['video_file_name'] = null;
            $out['video_file_path'] = null;
        }

        return $out;
    }
}
