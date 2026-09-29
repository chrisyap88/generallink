<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\ClaudeTranslationService;
use App\Services\DataScopeService;
use App\Services\DocumentCreditService;
use App\Services\LanguageService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// REDESIGNED 21 Jul 2026 — Help Desk (was "Enquiries" — agent-to-Admin
// only). Per Chris: this is now hierarchy-wide internal messaging for
// EVERY role, Admin included. The rule, straight from Chris: any agent
// may message their own upline and downline, however deep, but NEVER
// sideways to a peer at the same level. Admin sits above every chain
// and may message anyone, but only one person at a time (Admin's
// whole-company broadcasts are Notice Board's job, not this). CC is
// scoped to whoever is reachable by BOTH the sender's and the
// recipient's own chain (see DataScopeService::helpDeskCcOptionsFor)
// — this is what lets "the other people under this same group" loop
// each other in without ever exposing an unrelated peer.
//
// One controller serves all 4 roles — Admin is just another
// correspondent here, not a universal overseer: Admin only sees
// threads it's actually part of (as initiator, recipient, or CC),
// exactly like everyone else. That's a deliberate change from the old
// Enquiries design where Admin's screen was a global inbox of every
// agent's enquiries.
class HelpDeskController extends Controller
{
    public function index(Request $request, DataScopeService $scope, LanguageService $languageService, DocumentCreditService $creditService)
    {
        $agent = Auth::guard('agent')->user();

        $myThreads = DB::table('help_desk_threads as t')
            ->where(function ($q) use ($agent) {
                $q->where('t.initiator_agent_id', $agent->agent_id)
                  ->orWhere('t.recipient_agent_id', $agent->agent_id)
                  ->orWhereExists(function ($sub) use ($agent) {
                      $sub->select(DB::raw(1))->from('help_desk_cc')
                          ->whereColumn('help_desk_cc.thread_id', 't.thread_id')
                          ->where('help_desk_cc.agent_id', $agent->agent_id);
                  });
                // MERGED 12 Aug 2026 per Chris: Carolyn AI Tickets folded
                // into Help Desk — these have no single human recipient
                // (created_by_type = CAROLYN_AI, recipient_agent_id is
                // null), so ANY current Admin sees them here, same
                // override principle already used for the video-
                // submission Help Desk drilldown.
                if ($agent->role === 'ADMIN') {
                    $q->orWhere('t.created_by_type', 'CAROLYN_AI');
                }
            })
            ->leftJoin('agents as ini', 't.initiator_agent_id', '=', 'ini.agent_id')
            ->leftJoin('agents as rec', 't.recipient_agent_id', '=', 'rec.agent_id')
            ->orderByDesc('t.last_message_at')
            ->select('t.*', 'ini.full_name as initiator_name', 'rec.full_name as recipient_name')
            ->paginate(8, ['*'], 'hdPage');

        // Non-Admin roles have a naturally small eligible list (their
        // own upline + downline) — render as a plain dropdown. Admin's
        // eligible list is literally everyone, so Admin gets a
        // typeahead search box instead (same lesson learned on Agent
        // Balances: never dump hundreds of options in one <select>).
        $eligibleRecipients = $scope->isAdmin() ? collect() : $scope->helpDeskEligibleAgents();

        $dashboardRoute = match ($agent->role) {
            'ADMIN'        => 'admin.dashboard',
            'GROUP_LEADER' => 'gl.dashboard',
            'TEAM_LEADER'  => 'tl.dashboard',
            default        => 'introducer.dashboard',
        };

        // NEW 22 Jul 2026 — per Chris: TL/Introducer-only "Fix Wording"
        // is available on the compose form too, not just replies.
        $canUseLanguageFeatures = $languageService->isEligible($agent);
        $rephraseFee = $creditService->rephraseFee();

        return view('help-desk.index', compact('myThreads', 'eligibleRecipients', 'dashboardRoute', 'agent', 'canUseLanguageFeatures', 'rephraseFee'));
    }

    /**
     * AJAX — "To" typeahead search. Always filtered through the
     * sender's own eligible list first (upline+downline, or everyone
     * for Admin), so this can never be used to bypass the restriction.
     */
    public function recipientTypeahead(Request $request, DataScopeService $scope)
    {
        $agent = Auth::guard('agent')->user();
        $q = trim((string) $request->get('q', ''));

        $eligibleIds = $scope->isAdmin()
            ? null
            : collect($scope->helpDeskEligibleAgents())->pluck('agent_id')->all();

        $query = DB::table('agents')->where('is_deleted', false)->where('agent_id', '!=', $agent->agent_id);
        if ($eligibleIds !== null) {
            $query->whereIn('agent_id', $eligibleIds);
        }
        if ($q !== '') {
            $query->where(function ($q2) use ($q) {
                $q2->where('full_name', 'like', "%{$q}%")->orWhere('agent_code', 'like', "%{$q}%");
            });
        }

        $results = $query->orderBy('full_name')->limit(15)->get(['agent_id as id', 'full_name as name', 'agent_code as code', 'role']);

        return response()->json($results);
    }

    /**
     * AJAX — Cc options for a chosen "To" recipient, scoped per
     * helpDeskCcOptionsFor() (reachable by both sender and recipient).
     */
    public function ccOptions(Request $request, DataScopeService $scope)
    {
        $agent = Auth::guard('agent')->user();
        $recipientId = $request->get('recipient_id', '');

        if ($recipientId === '') {
            return response()->json([]);
        }

        $options = $scope->helpDeskCcOptionsFor($agent->agent_id, $recipientId);

        return response()->json($options->map(fn ($o) => ['id' => $o->agent_id, 'name' => $o->full_name, 'role' => $o->role])->values());
    }

    public function store(Request $request, DataScopeService $scope)
    {
        $agent = Auth::guard('agent')->user();

        $request->validate([
            'recipient_id' => ['required', 'string'],
            'category'     => ['required', 'in:CLAIM_UPDATE,TOPUP_PAYMENT,DATA_CORRECTION,GENERAL'],
            'subject'      => ['required', 'string', 'max:150'],
            'body'         => ['required', 'string', 'max:3000'],
            'attachment'   => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
            'cc'           => ['nullable', 'array'],
            'cc.*'         => ['string'],
        ]);

        $recipientId = $request->input('recipient_id');

        if ($recipientId === $agent->agent_id) {
            return back()->withErrors(['recipient_id' => 'You cannot message yourself.'])->withInput();
        }

        // Security: only ever to genuinely eligible agents (own
        // upline/downline) — never trust the submitted id blindly.
        $scope->verifyHelpDeskAccess($recipientId);

        $recipient = DB::table('agents')->where('agent_id', $recipientId)->where('is_deleted', false)->first();
        if (!$recipient) {
            return back()->withErrors(['recipient_id' => 'That agent could not be found.'])->withInput();
        }

        // Security: Cc list is intersected against what's actually
        // offered for this sender+recipient pair — silently drop
        // anything else instead of trusting the submitted list.
        $eligibleCcIds = $scope->helpDeskCcOptionsFor($agent->agent_id, $recipientId)->pluck('agent_id')->all();
        $ccIds = array_values(array_intersect($request->input('cc', []), $eligibleCcIds));

        $threadId = (string) Str::uuid();

        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('help-desk-attachments', 'local');
            $attachmentName = $file->getClientOriginalName();
        }

        DB::table('help_desk_threads')->insert([
            'thread_id'                   => $threadId,
            'initiator_agent_id'          => $agent->agent_id,
            'recipient_agent_id'          => $recipientId,
            'category'                    => $request->input('category'),
            'subject'                     => $request->input('subject'),
            'status'                      => 'OPEN',
            'last_message_at'             => now(),
            'last_viewed_by_initiator_at' => now(),
            'has_attachment'              => $attachmentPath !== null,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);

        DB::table('help_desk_messages')->insert([
            'message_id'           => (string) Str::uuid(),
            'thread_id'            => $threadId,
            'sender_agent_id'      => $agent->agent_id,
            'body'                 => $request->input('body'),
            'attachment_file_name' => $attachmentName,
            'attachment_file_path' => $attachmentPath,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        foreach ($ccIds as $ccAgentId) {
            DB::table('help_desk_cc')->insert([
                'cc_id'      => (string) Str::uuid(),
                'thread_id'  => $threadId,
                'agent_id'   => $ccAgentId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(NotificationService::class)->notify(
            [$recipient],
            'HELP_DESK_MESSAGE',
            'New Help Desk Message: ' . $request->input('subject'),
            "{$agent->full_name} ({$agent->agent_code}) sent you a Help Desk message: \"{$request->input('subject')}\"."
        );

        if ($ccIds) {
            $ccAgents = DB::table('agents')->whereIn('agent_id', $ccIds)->get();
            app(NotificationService::class)->notify(
                $ccAgents,
                'HELP_DESK_CC',
                'Cc: ' . $request->input('subject'),
                "{$agent->full_name} ({$agent->agent_code}) Cc'd you on a Help Desk message to {$recipient->full_name}: \"{$request->input('subject')}\"."
            );
        }

        return redirect()->route('help-desk.show', $threadId)->with('success', 'Message sent to ' . $recipient->full_name . '.');
    }

    public function show(string $threadId)
    {
        $agent = Auth::guard('agent')->user();
        $thread = DB::table('help_desk_threads')->where('thread_id', $threadId)->firstOrFail();

        $isInitiator = $thread->initiator_agent_id === $agent->agent_id;
        $isRecipient = $thread->recipient_agent_id === $agent->agent_id;
        $isCc = DB::table('help_desk_cc')->where('thread_id', $threadId)->where('agent_id', $agent->agent_id)->exists();
        // MERGED 12 Aug 2026 per Chris: a Carolyn-logged ticket has no
        // single human recipient — it's an Admin-queue item, so any
        // current Admin may open and act on it (same override principle
        // already used for the video-submission Help Desk drilldown).
        $isAdminQueue = $thread->created_by_type === 'CAROLYN_AI' && $agent->role === 'ADMIN';

        // Security: only the initiator, the recipient, someone they
        // Cc'd, or (for Carolyn-logged tickets only) any Admin may open
        // this thread — Admin is NOT a universal overseer for ordinary
        // agent-to-agent threads, only a party like anyone else.
        if (!$isInitiator && !$isRecipient && !$isCc && !$isAdminQueue) {
            abort(403, 'This message thread does not belong to you.');
        }

        if ($isInitiator) {
            DB::table('help_desk_threads')->where('thread_id', $threadId)->update(['last_viewed_by_initiator_at' => now()]);
        } elseif ($isRecipient) {
            DB::table('help_desk_threads')->where('thread_id', $threadId)->update(['last_viewed_by_recipient_at' => now()]);
        }

        $initiator = DB::table('agents')->where('agent_id', $thread->initiator_agent_id)->first();
        $recipient = DB::table('agents')->where('agent_id', $thread->recipient_agent_id)->first();

        // NEW 8 Aug 2026 per Chris: strict no-scroll rule — the message
        // panel used to just list every message and let the panel itself
        // scroll internally. Now paginated (3 messages/page, oldest-first
        // within a page) with the same bottom Prev/Next pattern every
        // other list screen uses. Defaults to the LAST page (most recent
        // messages) so opening a thread shows what just happened, same as
        // before.
        $hdMessagesPerPage = 3;
        $hdTotalMessages = DB::table('help_desk_messages')->where('thread_id', $threadId)->count();
        $hdLastPage = max(1, (int) ceil($hdTotalMessages / $hdMessagesPerPage));
        $hdPage = (int) request('page', $hdLastPage);
        if ($hdPage < 1) { $hdPage = 1; }
        if ($hdPage > $hdLastPage) { $hdPage = $hdLastPage; }

        $messages = DB::table('help_desk_messages as m')
            ->leftJoin('agents as a', 'm.sender_agent_id', '=', 'a.agent_id')
            ->where('m.thread_id', $threadId)
            ->orderBy('m.created_at')
            ->select('m.*', 'a.full_name', 'a.role')
            ->forPage($hdPage, $hdMessagesPerPage)
            ->get();

        $ccNames = DB::table('help_desk_cc as c')
            ->join('agents as a', 'c.agent_id', '=', 'a.agent_id')
            ->where('c.thread_id', $threadId)
            ->pluck('a.full_name');

        // Read-only for Cc'd viewers — the initiator, recipient, or (for
        // a Carolyn-logged ticket) any Admin can reply on a thread.
        $canReply = $isInitiator || $isRecipient || $isAdminQueue;
        $viewerAgentId = $agent->agent_id;

        // NEW 22 Jul 2026 — per Chris: only TL/Introducer get message
        // translation + "Fix Wording", each a paid, confirmed Claude
        // API call. viewerLanguage drives which language the Translate
        // button targets; already-cached translations for that message
        // + language are attached so no charge/confirm is needed to
        // just re-view something already paid for once.
        $languageService = app(LanguageService::class);
        $canUseLanguageFeatures = $languageService->isEligible($agent);
        $viewerLanguage = $languageService->effectiveLanguage($agent);
        $creditService = app(DocumentCreditService::class);
        $translationFee = $creditService->translationFee();
        $rephraseFee = $creditService->rephraseFee();

        $cachedTranslations = collect();
        if ($canUseLanguageFeatures) {
            $cachedTranslations = DB::table('help_desk_message_translations')
                ->whereIn('message_id', $messages->pluck('message_id'))
                ->where('target_language', $viewerLanguage)
                ->get()
                ->keyBy('message_id');
        }

        return view('help-desk.show', compact(
            'thread', 'initiator', 'recipient', 'messages', 'ccNames', 'canReply', 'viewerAgentId', 'isAdminQueue',
            'canUseLanguageFeatures', 'viewerLanguage', 'translationFee', 'rephraseFee', 'cachedTranslations',
            'hdPage', 'hdLastPage', 'hdTotalMessages'
        ));
    }

    /**
     * AJAX — translate one Help Desk message into the viewer's
     * preferred language. Two-step: without `confirm`, just reports
     * whether it's already cached (free) or needs the fee confirmed;
     * with `confirm=1`, actually charges (if not cached) and performs
     * the translation. TL/Introducer only.
     */
    public function translateMessage(Request $request, string $messageId, LanguageService $languageService, ClaudeTranslationService $translationService, DocumentCreditService $creditService)
    {
        $agent = Auth::guard('agent')->user();

        if (!$languageService->isEligible($agent)) {
            abort(403, 'Message translation is not available for your role.');
        }

        $message = DB::table('help_desk_messages')->where('message_id', $messageId)->firstOrFail();
        $thread = DB::table('help_desk_threads')->where('thread_id', $message->thread_id)->firstOrFail();

        $isInitiator = $thread->initiator_agent_id === $agent->agent_id;
        $isRecipient = $thread->recipient_agent_id === $agent->agent_id;
        $isCc = DB::table('help_desk_cc')->where('thread_id', $thread->thread_id)->where('agent_id', $agent->agent_id)->exists();
        if (!$isInitiator && !$isRecipient && !$isCc) {
            abort(403, 'You do not have access to this message.');
        }

        $targetLanguage = $languageService->effectiveLanguage($agent);

        $cached = DB::table('help_desk_message_translations')
            ->where('message_id', $messageId)
            ->where('target_language', $targetLanguage)
            ->first();

        if ($cached) {
            return response()->json(['status' => 'ok', 'charged' => false, 'text' => $cached->translated_body]);
        }

        $fee = $creditService->translationFee();

        if (!$request->boolean('confirm')) {
            return response()->json(['status' => 'confirm_required', 'fee' => $fee]);
        }

        if (!$creditService->chargeForAiFeature($agent->agent_id, 'TRANSLATION', $fee, 'Help Desk message translated to ' . $languageService->label($targetLanguage))) {
            return response()->json(['status' => 'error', 'message' => 'Insufficient Document Credit balance for this translation.'], 422);
        }

        $result = $translationService->translate($message->body, $targetLanguage);

        if ($result['status'] !== 'OK') {
            return response()->json(['status' => 'error', 'message' => $result['message'] ?? 'Translation failed.'], 500);
        }

        DB::table('help_desk_message_translations')->updateOrInsert(
            ['message_id' => $messageId, 'target_language' => $targetLanguage],
            ['translation_id' => (string) Str::uuid(), 'translated_body' => $result['text'], 'created_at' => now(), 'updated_at' => now()]
        );

        return response()->json(['status' => 'ok', 'charged' => true, 'text' => $result['text']]);
    }

    /**
     * AJAX — "Fix Wording": rephrase/correct text the agent is
     * currently typing, in their own preferred language. Same
     * confirm-then-charge two-step as translateMessage(), but never
     * cached (input text is different every time). TL/Introducer only.
     */
    public function rephrase(Request $request, LanguageService $languageService, ClaudeTranslationService $translationService, DocumentCreditService $creditService)
    {
        $agent = Auth::guard('agent')->user();

        if (!$languageService->isEligible($agent)) {
            abort(403, 'This feature is not available for your role.');
        }

        $request->validate(['text' => 'required|string|max:3000']);

        $fee = $creditService->rephraseFee();

        if (!$request->boolean('confirm')) {
            return response()->json(['status' => 'confirm_required', 'fee' => $fee]);
        }

        if (!$creditService->chargeForAiFeature($agent->agent_id, 'REPHRASE', $fee, 'Help Desk message wording suggestion')) {
            return response()->json(['status' => 'error', 'message' => 'Insufficient Document Credit balance for this feature.'], 422);
        }

        $sourceLanguage = $languageService->effectiveLanguage($agent);
        $result = $translationService->rephrase($request->input('text'), $sourceLanguage);

        if ($result['status'] !== 'OK') {
            return response()->json(['status' => 'error', 'message' => $result['message'] ?? 'Could not generate a suggestion.'], 500);
        }

        return response()->json(['status' => 'ok', 'text' => $result['text']]);
    }

    public function reply(Request $request, string $threadId)
    {
        $agent = Auth::guard('agent')->user();
        $thread = DB::table('help_desk_threads')->where('thread_id', $threadId)->firstOrFail();

        $isInitiator = $thread->initiator_agent_id === $agent->agent_id;
        $isRecipient = $thread->recipient_agent_id === $agent->agent_id;
        $isAdminQueue = $thread->created_by_type === 'CAROLYN_AI' && $agent->role === 'ADMIN';

        if (!$isInitiator && !$isRecipient && !$isAdminQueue) {
            abort(403, 'You cannot reply on this thread.');
        }

        if ($thread->status === 'CLOSED') {
            return back()->withErrors(['reply' => 'This thread is closed. Reopen it first if you still need to reply.']);
        }

        $request->validate([
            'body'       => ['required', 'string', 'max:3000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ]);

        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('help-desk-attachments', 'local');
            $attachmentName = $file->getClientOriginalName();
        }

        DB::table('help_desk_messages')->insert([
            'message_id'           => (string) Str::uuid(),
            'thread_id'            => $threadId,
            'sender_agent_id'      => $agent->agent_id,
            'body'                 => $request->input('body'),
            'attachment_file_name' => $attachmentName,
            'attachment_file_path' => $attachmentPath,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $viewedColumn = $isInitiator ? 'last_viewed_by_initiator_at' : 'last_viewed_by_recipient_at';
        DB::table('help_desk_threads')->where('thread_id', $threadId)->update([
            'last_message_at' => now(),
            $viewedColumn      => now(),
            'has_attachment'   => $thread->has_attachment || $attachmentPath !== null,
            'updated_at'       => now(),
        ]);

        // Notify the OTHER primary party, plus everyone Cc'd.
        $otherPartyId = $isInitiator ? $thread->recipient_agent_id : $thread->initiator_agent_id;
        $otherParty = DB::table('agents')->where('agent_id', $otherPartyId)->first();
        $recipients = collect([$otherParty])->filter();

        $ccAgents = DB::table('help_desk_cc as c')
            ->join('agents as a', 'c.agent_id', '=', 'a.agent_id')
            ->where('c.thread_id', $threadId)
            ->select('a.*')
            ->get();
        $recipients = $recipients->merge($ccAgents);

        app(NotificationService::class)->notify(
            $recipients,
            'HELP_DESK_REPLY',
            'Help Desk Reply: ' . $thread->subject,
            "{$agent->full_name} ({$agent->agent_code}) replied: \"{$thread->subject}\"."
        );

        return back()->with('success', 'Reply sent.');
    }

    public function toggleFlag(string $threadId)
    {
        $agent = Auth::guard('agent')->user();
        $thread = DB::table('help_desk_threads')->where('thread_id', $threadId)->firstOrFail();

        if ($thread->initiator_agent_id === $agent->agent_id) {
            DB::table('help_desk_threads')->where('thread_id', $threadId)->update(['flagged_by_initiator' => !$thread->flagged_by_initiator]);
        } elseif ($thread->recipient_agent_id === $agent->agent_id) {
            DB::table('help_desk_threads')->where('thread_id', $threadId)->update(['flagged_by_recipient' => !$thread->flagged_by_recipient]);
        } else {
            abort(403, 'You cannot flag this thread.');
        }

        return back();
    }

    public function close(string $threadId)
    {
        $agent = Auth::guard('agent')->user();
        $thread = DB::table('help_desk_threads')->where('thread_id', $threadId)->firstOrFail();
        $isAdminQueue = $thread->created_by_type === 'CAROLYN_AI' && $agent->role === 'ADMIN';

        if ($thread->initiator_agent_id !== $agent->agent_id && $thread->recipient_agent_id !== $agent->agent_id && !$isAdminQueue) {
            abort(403, 'You cannot close this thread.');
        }

        DB::table('help_desk_threads')->where('thread_id', $threadId)->update(['status' => 'CLOSED', 'updated_at' => now()]);
        return back()->with('success', 'Thread closed.');
    }

    public function reopen(string $threadId)
    {
        $agent = Auth::guard('agent')->user();
        $thread = DB::table('help_desk_threads')->where('thread_id', $threadId)->firstOrFail();
        $isAdminQueue = $thread->created_by_type === 'CAROLYN_AI' && $agent->role === 'ADMIN';

        if ($thread->initiator_agent_id !== $agent->agent_id && $thread->recipient_agent_id !== $agent->agent_id && !$isAdminQueue) {
            abort(403, 'You cannot reopen this thread.');
        }

        DB::table('help_desk_threads')->where('thread_id', $threadId)->update(['status' => 'OPEN', 'updated_at' => now()]);
        return back()->with('success', 'Thread reopened.');
    }

    public function attachment(string $messageId)
    {
        $agent = Auth::guard('agent')->user();
        $message = DB::table('help_desk_messages')->where('message_id', $messageId)->firstOrFail();
        $thread = DB::table('help_desk_threads')->where('thread_id', $message->thread_id)->firstOrFail();

        $isInitiator = $thread->initiator_agent_id === $agent->agent_id;
        $isRecipient = $thread->recipient_agent_id === $agent->agent_id;
        $isCc = DB::table('help_desk_cc')->where('thread_id', $thread->thread_id)->where('agent_id', $agent->agent_id)->exists();

        if (!$isInitiator && !$isRecipient && !$isCc) {
            abort(403, 'You do not have access to this attachment.');
        }

        if (!$message->attachment_file_path || !Storage::disk('local')->exists($message->attachment_file_path)) {
            abort(404, 'Attachment not found.');
        }

        return response()->file(Storage::disk('local')->path($message->attachment_file_path));
    }
}
