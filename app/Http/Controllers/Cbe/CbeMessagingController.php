<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use App\Services\AiAssistantService;
use App\Services\ClaudeDocumentExtractionService;
use App\Services\CbeReceiptService;
use App\Services\HubVaultService;
use App\Services\Integrations\Communication\WhatsAppCloudConnector;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 17 Sep 2026 — per Chris: "in CBE it also need to have internal
// messaging, president message finance, finance reply, secretarial
// message the member/vendor ... only different is the logic dont have
// upline down line rule but messaging is a must among all cbe
// community." Per Chris's own answer: ANY member/officer/Secretary may
// message ANY other member/officer/Secretary within the SAME entity —
// no role restriction. Vendor messaging is deliberately out of scope
// for v1 (cbe_vendors has no login yet — see the migration comment).
class CbeMessagingController extends Controller
{
    use ResolvesCbeActiveNode;

    public function index(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);

        if (! $nodeId && $agent->role === 'ADMIN') {
            return $this->renderCbeNodePicker('cbe.messaging.index', __('cbe_records.messaging_page_title'), leafOnly: true, countResolver: function (array $nodeIds) {
                return DB::table('cbe_message_threads')
                    ->whereIn('cbe_node_id', $nodeIds)
                    ->selectRaw('cbe_node_id, COUNT(*) as cnt')
                    ->groupBy('cbe_node_id')
                    ->pluck('cnt', 'cbe_node_id')
                    ->all();
            });
        }

        $threads = collect();
        if ($nodeId) {
            $threads = DB::table('cbe_message_threads as t')
                ->where('t.cbe_node_id', $nodeId)
                ->where(function ($q) use ($agent) {
                    $q->where('t.initiator_agent_id', $agent->agent_id)
                      ->orWhere('t.recipient_agent_id', $agent->agent_id);
                })
                ->leftJoin('agents as ini', 't.initiator_agent_id', '=', 'ini.agent_id')
                ->leftJoin('agents as rec', 't.recipient_agent_id', '=', 'rec.agent_id')
                ->orderByDesc('t.last_message_at')
                ->select('t.*', 'ini.full_name as initiator_name', 'rec.full_name as recipient_name')
                ->paginate(8, ['*'], 'msgPage');
        }

        return view('cbe.messaging.index', [
            'threads' => $threads,
            'hasNode' => (bool) $nodeId,
            'agentId' => $agent->agent_id,
        ]);
    }

    // AJAX — "To" typeahead, restricted to active members of the SAME
    // entity only (via cbe_group_memberships), excluding the sender.
    public function recipientTypeahead(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);
        $term = trim((string) $request->get('q', ''));

        if (! $nodeId || $term === '') {
            return response()->json([]);
        }

        $results = DB::table('cbe_group_memberships as m')
            ->join('agents as a', 'a.agent_id', '=', 'm.agent_id')
            ->where('m.cbe_node_id', $nodeId)
            ->where('m.status', 'ACTIVE')
            ->where('a.agent_id', '<>', $agent->agent_id)
            ->where('a.full_name', 'like', "%{$term}%")
            ->orderBy('a.full_name')
            ->limit(15)
            ->select('a.agent_id', 'a.full_name')
            ->get();

        return response()->json($results);
    }

    public function create(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);
        abort_unless($nodeId, 404);

        return view('cbe.messaging.create', ['categories' => self::CATEGORIES]);
    }

    // CATEGORIES available on a new thread — per Chris: not just
    // payment notifications, also receipts, credit/debit notes, cheque
    // and TT slip references. OTHER covers plain internal chat, exactly
    // as this screen worked before this addition.
    public const CATEGORIES = ['PAYMENT_NOTIFICATION', 'RECEIPT_REQUEST', 'CREDIT_NOTE', 'DEBIT_NOTE', 'CHEQUE', 'TT_SLIP', 'OTHER'];

    public function store(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);
        abort_unless($nodeId, 404);

        $validated = $request->validate([
            'recipient_agent_id' => 'required|uuid',
            'subject' => 'required|string|max:150',
            'body' => 'required|string|max:3000',
            'category' => 'nullable|string|in:' . implode(',', self::CATEGORIES),
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:8192',
        ]);

        // The recipient must be an active member of THIS SAME entity —
        // never trust the posted agent_id alone (could be tampered with
        // to message someone outside this entity).
        $isMember = DB::table('cbe_group_memberships')
            ->where('cbe_node_id', $nodeId)
            ->where('agent_id', $validated['recipient_agent_id'])
            ->where('status', 'ACTIVE')
            ->exists();
        abort_unless($isMember && $validated['recipient_agent_id'] !== $agent->agent_id, 422, __('cbe_records.messaging_invalid_recipient'));

        $threadId = (string) Str::uuid();
        $messageId = (string) Str::uuid();
        $now = now();

        DB::table('cbe_message_threads')->insert([
            'thread_id' => $threadId,
            'cbe_node_id' => $nodeId,
            'subject' => $validated['subject'],
            'category' => $validated['category'] ?? 'OTHER',
            'status' => 'OPEN',
            'initiator_agent_id' => $agent->agent_id,
            'recipient_agent_id' => $validated['recipient_agent_id'],
            'initiator_read_at' => $now,
            'recipient_read_at' => null,
            'last_message_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('cbe-messaging-attachments', 'local');
            $attachmentName = $file->getClientOriginalName();
        }

        DB::table('cbe_messages')->insert([
            'message_id' => $messageId,
            'thread_id' => $threadId,
            'sender_agent_id' => $agent->agent_id,
            'body' => $validated['body'],
            'attachment_path' => $attachmentPath,
            'attachment_original_name' => $attachmentName,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $recipientAgent = \App\Models\Agent::find($validated['recipient_agent_id']);
        if ($recipientAgent) {
            app(NotificationService::class)->notify(
                [$recipientAgent],
                'CBE_MESSAGE',
                __('cbe_records.messaging_new_notif_title'),
                $validated['subject'],
                $agent->agent_id
            );
        }

        if ($attachmentPath) {
            $this->tryAiVerify($messageId, $attachmentPath, $file->getMimeType());
        }

        return redirect()->route('cbe.messaging.show', $threadId)->with('success', __('cbe_records.messaging_sent_success'));
    }

    public function show(Request $request, string $threadId)
    {
        $agent = Auth::guard('agent')->user();

        $thread = DB::table('cbe_message_threads as t')
            ->leftJoin('agents as ini', 't.initiator_agent_id', '=', 'ini.agent_id')
            ->leftJoin('agents as rec', 't.recipient_agent_id', '=', 'rec.agent_id')
            ->where('t.thread_id', $threadId)
            ->select('t.*', 'ini.full_name as initiator_name', 'rec.full_name as recipient_name')
            ->first();

        abort_if(! $thread, 404);
        $isInitiator = $thread->initiator_agent_id === $agent->agent_id;
        $isRecipient = $thread->recipient_agent_id === $agent->agent_id;
        abort_unless($isInitiator || $isRecipient, 403);

        // Mark read on MY side only.
        DB::table('cbe_message_threads')->where('thread_id', $threadId)->update(
            $isInitiator ? ['initiator_read_at' => now()] : ['recipient_read_at' => now()]
        );

        $messages = DB::table('cbe_messages as m')
            ->leftJoin('agents as a', 'a.agent_id', '=', 'm.sender_agent_id')
            ->where('m.thread_id', $threadId)
            ->orderBy('m.created_at')
            ->select('m.*', 'a.full_name as sender_name')
            ->get();

        $otherPartyName = $isInitiator ? $thread->recipient_name : $thread->initiator_name;

        // Only the RECIPIENT, and only if they're this node's Finance/
        // Director officer (or platform Admin), sees the "Confirm &
        // Issue" control — same gate confirmAndIssue() itself enforces,
        // shown here purely so the button doesn't appear to someone
        // who'd just get a 403 tapping it.
        $canIssueReceipt = false;
        if ($isRecipient) {
            $officerRole = DB::table('cbe_node_officers')
                ->where('node_id', $thread->cbe_node_id)
                ->where('agent_id', $agent->agent_id)
                ->where('is_active', true)
                ->value('role');
            $canIssueReceipt = $agent->role === 'ADMIN' || in_array($officerRole, ['DIRECTOR', 'FINANCE']);
        }

        return view('cbe.messaging.show', compact('thread', 'messages', 'otherPartyName', 'canIssueReceipt'));
    }

    public function reply(Request $request, string $threadId)
    {
        $agent = Auth::guard('agent')->user();

        $thread = DB::table('cbe_message_threads')->where('thread_id', $threadId)->first();
        abort_if(! $thread, 404);
        $isInitiator = $thread->initiator_agent_id === $agent->agent_id;
        $isRecipient = $thread->recipient_agent_id === $agent->agent_id;
        abort_unless($isInitiator || $isRecipient, 403);

        $validated = $request->validate([
            'body' => 'required|string|max:3000',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:8192',
            'mark_resolved' => 'nullable|boolean',
        ]);

        $now = now();
        $messageId = (string) Str::uuid();

        $attachmentPath = null;
        $attachmentName = null;
        $mimeType = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('cbe-messaging-attachments', 'local');
            $attachmentName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
        }

        DB::table('cbe_messages')->insert([
            'message_id' => $messageId,
            'thread_id' => $threadId,
            'sender_agent_id' => $agent->agent_id,
            'body' => $validated['body'],
            'attachment_path' => $attachmentPath,
            'attachment_original_name' => $attachmentName,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // A reply from the RECIPIENT side means someone actually
        // responded — clears Outstanding/Escalated. "Resolved" is an
        // explicit tick (e.g. Treasury saying "receipt issued, done"),
        // never inferred just from replying.
        $newStatus = $request->boolean('mark_resolved')
            ? 'RESOLVED'
            : ($isRecipient ? 'RESPONDED' : $thread->status);

        // My own reply marks MY side read; the other side goes unread
        // again so their unread badge picks it up.
        DB::table('cbe_message_threads')->where('thread_id', $threadId)->update([
            'last_message_at' => $now,
            'status' => $newStatus,
            'sla_notified_at' => null,
            'resolved_at' => $newStatus === 'RESOLVED' ? $now : null,
            $isInitiator ? 'initiator_read_at' : 'recipient_read_at' => $now,
            $isInitiator ? 'recipient_read_at' : 'initiator_read_at' => null,
        ]);

        $otherAgentId = $isInitiator ? $thread->recipient_agent_id : $thread->initiator_agent_id;
        $otherAgent = \App\Models\Agent::find($otherAgentId);
        if ($otherAgent) {
            app(NotificationService::class)->notify(
                [$otherAgent],
                'CBE_MESSAGE',
                __('cbe_records.messaging_reply_notif_title'),
                $thread->subject,
                $agent->agent_id
            );
        }

        if ($attachmentPath) {
            $this->tryAiVerify($messageId, $attachmentPath, $mimeType);
        }

        return redirect()->route('cbe.messaging.show', $threadId)->with('success', __('cbe_records.messaging_reply_success'));
    }

    // NEW 18 Sep 2026 — "Confirm & Issue" per Chris's two-speed design:
    // Carolyn already read and flagged the attachment (tryAiVerify
    // above); a Finance/Director officer taps this ONCE to actually
    // issue the Official Receipt and email it to the payer. Reuses
    // CbeReceiptService::issue() — the SAME engine already posting
    // Donation/Event Sale/Appointment receipts to the books — nothing
    // new invented for accounting itself, only a new "front door" into
    // it (source_type = MESSAGE_COLLECTION, source_id = the message).
    public function confirmAndIssue(Request $request, string $messageId, HubVaultService $vault, WhatsAppCloudConnector $whatsapp)
    {
        $agent = Auth::guard('agent')->user();

        $message = DB::table('cbe_messages')->where('message_id', $messageId)->first();
        abort_if(! $message, 404);
        $thread = DB::table('cbe_message_threads')->where('thread_id', $message->thread_id)->first();
        abort_if(! $thread, 404);
        abort_unless($thread->recipient_agent_id === $agent->agent_id, 403, __('cbe_records.messaging_only_recipient_can_issue'));

        // Only a Finance or Director officer of this CBE may actually
        // issue money-in documents — same role gate the rest of
        // Treasury/Accounting already uses (AdminCbeKpiController/
        // CbeExecDashboardController role-switch on DIRECTOR/FINANCE).
        $officerRole = DB::table('cbe_node_officers')
            ->where('node_id', $thread->cbe_node_id)
            ->where('agent_id', $agent->agent_id)
            ->where('is_active', true)
            ->value('role');
        $isPlatformAdmin = $agent->role === 'ADMIN';
        abort_unless($isPlatformAdmin || in_array($officerRole, ['DIRECTOR', 'FINANCE']), 403, __('cbe_records.messaging_finance_only'));

        $validated = $request->validate([
            'payer_name' => 'required|string|max:200',
            'amount' => 'required|numeric|min:0.01',
            'payer_email' => 'nullable|email|max:150',
            'payer_whatsapp' => 'nullable|string|max:20',
        ]);

        $receipt = CbeReceiptService::issue(
            $thread->cbe_node_id,
            'MESSAGE_COLLECTION',
            $messageId,
            $validated['payer_name'],
            $thread->subject,
            (float) $validated['amount'],
            $agent->agent_id
        );

        if (! $receipt) {
            return back()->with('error', __('cbe_records.messaging_issue_failed'));
        }

        DB::table('cbe_message_threads')->where('thread_id', $thread->thread_id)->update([
            'status' => 'RESOLVED',
            'resolved_at' => now(),
            'updated_at' => now(),
        ]);

        $emailSent = false;
        if (! empty($validated['payer_email'])) {
            $emailSent = $this->emailReceipt($receipt, $validated['payer_email']);
        }

        // NEW 19 Sep 2026 — WhatsApp delivery, per Chris's original
        // request ("receipt to the recipient is by email ya or whatapps
        // if available"). Uses the ISSUING OFFICER'S OWN connected
        // WhatsApp number (Integration Hub > Communication > WhatsApp —
        // same per-agent vault every other WhatsApp send in the app
        // uses), never a shared/global number. If the officer hasn't
        // connected WhatsApp, this is silently skipped (not an error —
        // email already went out, or the officer simply didn't set it
        // up) and the success message explains what to do next.
        $whatsappResult = null;
        if (! empty($validated['payer_whatsapp'])) {
            $whatsappResult = $this->whatsappReceipt($receipt, $validated['payer_whatsapp'], $agent->agent_id, $vault, $whatsapp);
        }

        $successKey = 'cbe_records.messaging_issued_success';
        if ($emailSent && ($whatsappResult['success'] ?? false)) {
            $successKey = 'cbe_records.messaging_issued_email_whatsapp_success';
        } elseif ($emailSent) {
            $successKey = 'cbe_records.messaging_issued_and_emailed_success';
        } elseif ($whatsappResult['success'] ?? false) {
            $successKey = 'cbe_records.messaging_issued_and_whatsapped_success';
        }

        $redirect = redirect()->route('cbe.messaging.show', $thread->thread_id)
            ->with('success', __($successKey, ['no' => $receipt->receipt_no]));

        if ($whatsappResult && ! $whatsappResult['success']) {
            $redirect = $redirect->with('whatsapp_warning', __('cbe_records.messaging_whatsapp_not_sent', ['reason' => $whatsappResult['message']]));
        }

        return $redirect;
    }

    // Sends the Official Receipt PDF as a real WhatsApp document
    // attachment (see WhatsAppCloudConnector::sendDocument()), using
    // the issuing officer's own connected WhatsApp number. Mirrors
    // emailReceipt() below — best-effort, never blocks the receipt
    // itself, and the outcome is recorded on the receipt row for basic
    // traceability (cbe_receipts had no delivery record at all before
    // this — see migration 2026_09_19_000001).
    private function whatsappReceipt(object $receipt, string $toPhone, string $issuingAgentId, HubVaultService $vault, WhatsAppCloudConnector $whatsapp): array
    {
        $row = DB::table('agent_integrations')
            ->where('agent_id', $issuingAgentId)
            ->where('category', 'communication')
            ->where('provider', 'whatsapp')
            ->where('status', 'CONNECTED')
            ->first();

        if (! $row) {
            $result = ['success' => false, 'message' => __('cbe_records.messaging_whatsapp_not_connected')];
            $this->recordReceiptDelivery($receipt->receipt_id, null, $toPhone, $result['message']);
            return $result;
        }

        if (! $vault->isUnlocked($issuingAgentId)) {
            $result = ['success' => false, 'message' => __('cbe_records.messaging_whatsapp_vault_locked')];
            $this->recordReceiptDelivery($receipt->receipt_id, null, $toPhone, $result['message']);
            return $result;
        }

        try {
            $credentials = [
                'client_id' => $vault->decryptSecret($issuingAgentId, $row->client_id_encrypted),
                'client_secret' => $vault->decryptSecret($issuingAgentId, $row->client_secret_encrypted),
            ];
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message' => __('cbe_records.messaging_whatsapp_not_connected')];
            $this->recordReceiptDelivery($receipt->receipt_id, null, $toPhone, $result['message']);
            return $result;
        }

        try {
            $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $receipt->cbe_node_id)->first();
            $amountWords = \App\Services\NumberToWordsService::ringgit((float) $receipt->amount);
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.cbe-kpi.receipts.pdf', [
                'receipt' => $receipt,
                'nodeName' => $node->node_name ?? '',
                'amountWords' => $amountWords,
            ])->setPaper('a5')->output();

            $result = $whatsapp->sendDocument(
                $credentials,
                $toPhone,
                $pdf,
                'Receipt-' . $receipt->receipt_no . '.pdf',
                'Official Receipt ' . $receipt->receipt_no . ' — RM ' . number_format($receipt->amount, 2)
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('CBE messaging: receipt WhatsApp send failed — ' . $e->getMessage());
            $result = ['success' => false, 'message' => __('cbe_records.messaging_whatsapp_send_error')];
        }

        $this->recordReceiptDelivery($receipt->receipt_id, $result['success'] ? $toPhone : null, $toPhone, $result['message']);

        return $result;
    }

    private function recordReceiptDelivery(string $receiptId, ?string $sentToIfSuccess, string $attemptedPhone, string $note): void
    {
        try {
            DB::table('cbe_receipts')->where('receipt_id', $receiptId)->update([
                'whatsapp_sent_to' => $sentToIfSuccess,
                'whatsapp_sent_at' => $sentToIfSuccess ? now() : null,
                'whatsapp_note' => $sentToIfSuccess ? null : ('Tried ' . $attemptedPhone . ': ' . $note),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('CBE messaging: could not record receipt WhatsApp delivery — ' . $e->getMessage());
        }
    }

    // Emails the Official Receipt PDF straight to the payer — per
    // Chris: "receipt to the recipient is by email ya... that will show
    // the cbe is efficient." Reuses AdminCbeReceiptController's own PDF
    // template so the emailed copy is identical to the one printed from
    // the receipt screen. A failed send never blocks the receipt itself
    // from being issued — same "bell notification always saves, email
    // is best-effort on top" rule as NotificationService::notify().
    private function emailReceipt(object $receipt, string $toEmail): bool
    {
        try {
            $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $receipt->cbe_node_id)->first();
            $amountWords = \App\Services\NumberToWordsService::ringgit((float) $receipt->amount);

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.cbe-kpi.receipts.pdf', [
                'receipt' => $receipt,
                'nodeName' => $node->node_name ?? '',
                'amountWords' => $amountWords,
            ])->setPaper('a5')->output();

            $html = '<p>Dear ' . e($receipt->payer_name) . ',</p><p>Thank you — please find your Official Receipt ' . e($receipt->receipt_no) . ' (RM ' . number_format($receipt->amount, 2) . ') attached.</p>';
            Mail::html($html, function ($mail) use ($toEmail, $receipt, $pdf) {
                $mail->to($toEmail)
                    ->subject('Official Receipt ' . $receipt->receipt_no)
                    ->attachData($pdf, 'Receipt-' . $receipt->receipt_no . '.pdf', ['mime' => 'application/pdf']);
            });
            DB::table('cbe_receipts')->where('receipt_id', $receipt->receipt_id)->update([
                'emailed_to' => $toEmail,
                'emailed_at' => now(),
            ]);
            return true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('CBE messaging: receipt email failed — ' . $e->getMessage());
            return false;
        }
    }

    // Serves an attachment to either party on the thread only — same
    // access check as show(). Mirrors HelpDeskController::attachment().
    public function attachment(Request $request, string $messageId)
    {
        $agent = Auth::guard('agent')->user();

        $message = DB::table('cbe_messages')->where('message_id', $messageId)->first();
        abort_if(! $message || ! $message->attachment_path, 404);

        $thread = DB::table('cbe_message_threads')->where('thread_id', $message->thread_id)->first();
        abort_if(! $thread, 404);
        abort_unless($thread->initiator_agent_id === $agent->agent_id || $thread->recipient_agent_id === $agent->agent_id, 403);

        abort_unless(Storage::disk('local')->exists($message->attachment_path), 404);

        return response()->file(Storage::disk('local')->path($message->attachment_path));
    }

    // AI-reads a payment/receipt attachment (bank-in slip, cheque
    // photo, TT slip) — per Chris: "if you read the attachment and
    // verified is a valid document" — reuses the SAME
    // ClaudeDocumentExtractionService already built for policy/receipt
    // extraction, no new AI pipeline. Two-speed design per Chris's own
    // agreement: this only READS and flags confidence — it never
    // auto-issues a receipt by itself; a Treasury/Secretarial officer
    // still taps to confirm (see the "confirm and send" step, built as
    // part of the Payment Completion engine).
    private function tryAiVerify(string $messageId, string $attachmentPath, ?string $mimeType): void
    {
        if (! $mimeType || ! in_array($mimeType, ['image/jpeg', 'image/png', 'application/pdf'])) {
            return;
        }

        $result = app(ClaudeDocumentExtractionService::class)->extract(
            Storage::disk('local')->path($attachmentPath),
            $mimeType,
            [
                'amount' => 'the payment amount shown (bank-in slip, cheque, TT slip, or receipt)',
                'payer_name' => 'the name of the person or company who paid/is paying',
                'date' => 'the date on the document',
                'document_type' => 'what kind of document this looks like — one of: PAYMENT_SLIP, CHEQUE, TT_SLIP, RECEIPT, OTHER',
            ]
        );

        if ($result['status'] !== 'OK') {
            DB::table('cbe_messages')->where('message_id', $messageId)->update([
                'ai_notes' => $result['message'] ?? 'Could not read this attachment automatically.',
                'ai_confidence' => null,
            ]);
            return;
        }

        $values = $result['values'];
        $amount = $values['amount'] ?? null;
        $unsure = false;
        foreach ($values as $v) {
            if (is_string($v) && str_starts_with($v, 'UNSURE:')) {
                $unsure = true;
            }
        }

        $confidence = 'HIGH';
        if ($unsure || empty($amount)) {
            $confidence = empty($amount) ? 'LOW' : 'MEDIUM';
        }

        DB::table('cbe_messages')->where('message_id', $messageId)->update([
            'ai_extracted_amount' => is_numeric($amount) ? $amount : (is_numeric(str_replace(['UNSURE:', ' '], '', (string) $amount)) ? str_replace(['UNSURE:', ' '], '', (string) $amount) : null),
            'ai_extracted_payer' => $values['payer_name'] ?? null,
            'ai_extracted_date' => $this->parseAiDate($values['date'] ?? null),
            'ai_confidence' => $confidence,
            'ai_notes' => 'Carolyn read this as a ' . ($values['document_type'] ?? 'document') . '.',
        ]);
    }

    private function parseAiDate(?string $raw): ?string
    {
        if (! $raw || str_starts_with($raw, 'UNSURE:')) {
            return null;
        }
        try {
            return \Carbon\Carbon::createFromFormat('d-m-Y', trim($raw))->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    // NEW 17 Sep 2026 — per Chris: "use back ask carolyn help to
    // rephrase" applies to messaging too, not just the Notice Board.
    // Reuses the existing 'help_desk_message' Carolyn content type —
    // it already covers a message with an optional subject (compose)
    // or without one (reply) — see AiAssistantService::WRITE_ASSIST_TYPES.
    public function aiAssist(Request $request, AiAssistantService $ai)
    {
        $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'body'  => ['required', 'string', 'max:3000'],
        ]);

        $result = $ai->improveWrittenText('help_desk_message', (string) $request->input('body'), $request->input('title'), app()->getLocale());

        if ($result['status'] !== 'OK') {
            return response()->json(['status' => 'ERROR', 'message' => $result['message'] ?? "Sorry, Carolyn couldn't rewrite that just now — please try again."], 200);
        }

        return response()->json(['status' => 'OK', 'title' => $result['title'], 'body' => $result['body']]);
    }
}
