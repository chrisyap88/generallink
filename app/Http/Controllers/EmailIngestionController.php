<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Services\ClaudeDocumentExtractionService;
use App\Services\CommissionEngine;
use App\Services\CustomerResolutionService;
use App\Services\DocumentCreditService;
use App\Services\NotificationService;
use App\Services\SubmissionKeyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * NEW 18 Jul 2026 — the "submit by email" method Chris asked for.
 *
 * HOW IT WORKS: an agent emails a document (photo/PDF of a policy,
 * receipt, or invoice) to admin@generallink.my, putting their personal
 * submission key somewhere in the subject line, e.g.:
 *   Subject: New policy KEY:eyJpdiI6...
 *
 * Getting the actual email INTO this endpoint requires an inbound-mail
 * provider (Mailgun, Postmark, SendGrid, etc.) configured to forward
 * admin@generallink.my's incoming mail to this URL as a webhook — that
 * external service setup is a separate step Chris needs to do himself
 * (see the chat message this was built from for exact next steps). This
 * controller is the receiving end of that pipeline: once ANY provider
 * POSTs a parsed email here in roughly this shape (sender/subject/body/
 * attachment), it takes over from there — extraction, customer
 * matching, commission calculation, and notifying the agent back are
 * all fully built and working today.
 *
 * Every email-submitted transaction is ALWAYS flagged for Admin review
 * (flagged_for_review = true), regardless of how confident the reading
 * was — unlike the logged-in web form, nobody is sitting there to catch
 * a mistake before it saves, so a human check is mandatory here rather
 * than optional.
 */
class EmailIngestionController extends Controller
{
    public function __construct(
        private ClaudeDocumentExtractionService $extractor,
        private SubmissionKeyService $keyService,
        private CustomerResolutionService $customerResolver,
        private CommissionEngine $commissionEngine,
        private NotificationService $notificationService,
        private DocumentCreditService $credit,
    ) {}

    public function inbound(Request $request)
    {
        // Bare-minimum protection so this public URL can't be spammed
        // by just anyone who finds it — the real mail provider is
        // configured to include this same secret in the webhook URL
        // itself (see .env: EMAIL_INGESTION_WEBHOOK_SECRET).
        //
        // FIXED 22 Jul 2026 — security hardening: plain `!==` string
        // comparison leaks a tiny amount of timing information (it
        // returns as soon as the first mismatched byte is found),
        // letting an attacker guess the secret one byte at a time in
        // theory. hash_equals() always takes the same time regardless
        // of how much of the string matched, closing that off. Cast
        // both sides to string first — hash_equals() throws if either
        // argument isn't already a string, and the configured secret
        // could theoretically be null if unset.
        $providedToken = (string) $request->query('token', '');
        $expectedToken = (string) config('services.email_ingestion.webhook_secret', '');
        if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            abort(403, 'Invalid webhook token.');
        }

        $sender  = (string) $request->input('from', $request->input('sender', ''));
        $subject = (string) $request->input('subject', '');
        $body    = (string) $request->input('body-plain', $request->input('text', $request->input('body', '')));

        Log::info("Email ingestion received from={$sender} subject={$subject}");

        // 1. Identify the agent via their submission key.
        $key = $this->keyService->extractKeyFromText($subject) ?? $this->keyService->extractKeyFromText($body);
        $agentId = $this->keyService->resolveAgentId($key);

        if (! $agentId) {
            $this->alertAdmins("Unrecognised email submission — no valid key", "An email arrived from {$sender} (subject: \"{$subject}\") but it had no valid submission key, so it could not be attributed to any agent. Nothing was created.");
            return response()->json(['status' => 'REJECTED', 'reason' => 'no valid submission key found']);
        }

        $agent = Agent::find($agentId);
        if (! $agent || $agent->status !== 'ACTIVE' || $agent->is_deleted) {
            $this->alertAdmins("Email submission from inactive/unknown agent", "An email from {$sender} carried a submission key for an agent that is no longer active. Nothing was created.");
            return response()->json(['status' => 'REJECTED', 'reason' => 'agent not active']);
        }

        // 2. Find the attachment (Mailgun-style field naming: attachment-1, attachment-2, ...).
        $file = null;
        foreach ($request->allFiles() as $name => $uploaded) {
            if (str_starts_with($name, 'attachment')) {
                $file = $uploaded;
                break;
            }
        }
        if (! $file) {
            $this->notifyAgent($agent, 'Email Submission — No Document Found', "Your email (subject: \"{$subject}\") was received, but no document was attached, so nothing could be processed. Please resend with the document attached.");
            return response()->json(['status' => 'REJECTED', 'reason' => 'no attachment found']);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            $this->notifyAgent($agent, 'Email Submission — Unsupported File Type', "Your emailed document (subject: \"{$subject}\") was a .{$ext} file, which isn't supported. Please resend as a PDF, JPG, or PNG.");
            return response()->json(['status' => 'REJECTED', 'reason' => 'unsupported file type']);
        }
        $mimeType = match ($ext) {
            'pdf'   => 'application/pdf',
            'png'   => 'image/png',
            default => 'image/jpeg',
        };

        // 3. Read the document via the same Claude API extraction used
        // by the web form, plus vendor name (the web form already knows
        // the vendor from the dropdown; email has no dropdown, so it
        // has to be read off the document itself and matched below).
        $fields = [
            'vendor_name'            => "The vendor/insurance company's own printed name on the document (e.g. letterhead).",
            'new_customer_name'      => "The customer's / policyholder's / insured's full name.",
            'new_customer_nric'      => "The customer's NRIC or IC number.",
            'new_customer_phone'     => "The customer's phone/contact number, if shown.",
            'new_customer_address'   => "The customer's full address exactly as printed, all lines combined into one string.",
            'new_customer_postcode'  => "The postcode (numeric part) from the customer's address.",
            'new_customer_city'      => "The city/town from the customer's address.",
            'new_customer_state'     => "The Malaysian state from the customer's address.",
            'document_reference_number' => 'The policy number, invoice number, or receipt number printed on the document.',
            'premium_amount'         => 'The total amount payable / gross amount / total premium, as a plain number.',
            'sum_insured'            => 'The sum insured amount, if this is an insurance document, as a plain number.',
            'coverage_start'         => 'The insurance coverage start date, if this is an insurance policy.',
            'coverage_end'           => 'The insurance coverage end date / expiry date, if this is an insurance policy.',
            'coverage_type'          => 'The type/class of coverage, if this is an insurance policy.',
            'vehicle_number'         => 'The vehicle registration number, if this document is for motor insurance.',
        ];

        // NEW 21 Jul 2026 — Document Credit Wallet. Same rule as the
        // web form's Read Document button: blocked before the API call
        // if the agent's balance can't cover the Admin-set flat cost.
        if (!$this->credit->hasSufficientBalance($agent->agent_id)) {
            $this->notifyAgent($agent, 'Email Submission — Document Credit Balance Too Low', "Your emailed document (subject: \"{$subject}\") could not be read because your Document Credit balance (RM " . number_format($this->credit->balance($agent->agent_id), 2) . ") is too low. Please top up your Document Credit balance (My Account > Document Credit) and resend, or log in and submit it manually instead.");
            return response()->json(['status' => 'REJECTED', 'reason' => 'insufficient document credit balance']);
        }

        $result = $this->extractor->extract($file->getRealPath(), $mimeType, $fields);

        if ($result['status'] !== 'OK') {
            $this->notifyAgent($agent, 'Email Submission — Could Not Be Read', "Your emailed document (subject: \"{$subject}\") could not be read: {$result['message']}. Please log in and submit it manually instead.");
            return response()->json(['status' => 'ERROR', 'reason' => $result['message']]);
        }

        // Read succeeded — charge the flat per-read amount now.
        $this->credit->deduct($agent->agent_id, null, 'Document read via email submission: ' . $subject);

        $v = $result['values'];

        // 4. Match the vendor by printed name (best-effort). Product is
        // a known simplification for now — this picks the vendor's
        // first active product rather than truly identifying which one,
        // since that's a harder problem than can be solved tonight.
        // Always flagged for review below specifically BECAUSE of this.
        $vendor = null;
        if (!empty($v['vendor_name'])) {
            $vendor = DB::table('vendors')->where('vendor_name', 'like', '%' . $v['vendor_name'] . '%')->where('is_active', true)->first();
        }
        if (! $vendor) {
            $this->notifyAgent($agent, 'Email Submission — Vendor Not Recognised', "Your emailed document (subject: \"{$subject}\") was read, but the vendor" . (!empty($v['vendor_name']) ? " (\"{$v['vendor_name']}\")" : '') . " could not be matched to any vendor on file. Please log in and submit it manually instead.");
            return response()->json(['status' => 'REJECTED', 'reason' => 'vendor not matched']);
        }
        $product = DB::table('products')->where('vendor_id', $vendor->vendor_id)->where('is_active', true)->orderBy('product_name')->first();
        if (! $product) {
            $this->notifyAgent($agent, 'Email Submission — No Product Found', "Vendor \"{$vendor->vendor_name}\" was matched, but has no active product set up, so this submission could not be completed. Please log in and submit it manually, or ask Admin to set up a product for this vendor.");
            return response()->json(['status' => 'REJECTED', 'reason' => 'no active product for vendor']);
        }

        if (empty($v['new_customer_nric']) || empty($v['document_reference_number']) || empty($v['premium_amount'])) {
            $this->notifyAgent($agent, 'Email Submission — Missing Required Fields', "Your emailed document (subject: \"{$subject}\") was read, but a required field (customer NRIC, reference number, or amount) could not be found. Please log in and submit it manually instead.");
            return response()->json(['status' => 'REJECTED', 'reason' => 'missing required field(s)']);
        }

        if (DB::table('sales_transactions')->where('document_reference_number', $v['document_reference_number'])->exists()) {
            $this->notifyAgent($agent, 'Email Submission — Duplicate Reference Number', "Your emailed document's reference number ({$v['document_reference_number']}) already exists in the system, so this submission was rejected as a likely duplicate.");
            return response()->json(['status' => 'REJECTED', 'reason' => 'duplicate document_reference_number']);
        }

        // 5. Resolve/create the customer (reusing the same shared
        // service the manual form path is built on top of).
        $customerResult = $this->customerResolver->resolveOrCreate(
            [
                'nric'      => $v['new_customer_nric'],
                'full_name' => $v['new_customer_name'] ?? null,
                'phone'     => $v['new_customer_phone'] ?? null,
                'address'   => $v['new_customer_address'] ?? null,
                'postcode'  => $v['new_customer_postcode'] ?? null,
                'city'      => $v['new_customer_city'] ?? null,
                'state'     => $v['new_customer_state'] ?? null,
            ],
            $agent->agent_id,
            'EMAIL_INGESTION',
            $v['document_reference_number'],
            null
        );

        $isInsurance = $vendor->industry === 'INSURANCE';
        $policyId = Str::uuid()->toString();
        $filePath = $file->store('sales-transaction-documents', 'local');
        $fileHash = hash_file('sha256', $file->getRealPath());

        DB::transaction(function () use (
            $policyId, $v, $customerResult, $agent, $vendor, $product,
            $isInsurance, $file, $filePath, $fileHash, $subject
        ) {
            DB::table('sales_transactions')->insert([
                'policy_id'      => $policyId,
                'policy_number'  => $v['document_reference_number'],
                'document_reference_number' => $v['document_reference_number'],
                'vendor_id'      => $vendor->vendor_id,
                'product_id'     => $product->product_id,
                'customer_id'    => $customerResult['customer_id'],
                'agent_id'       => $agent->agent_id,
                'premium_amount' => $v['premium_amount'],
                'sum_insured'    => $v['sum_insured'] ?? null,
                'coverage_start' => $isInsurance ? ($v['coverage_start'] ?? null) : null,
                'coverage_end'   => $isInsurance ? ($v['coverage_end'] ?? null) : null,
                'status'         => 'SUBMITTED',
                'flagged_for_review' => true,
                'flag_reason'    => 'Submitted via email — auto-processed by Claude API, please verify every field against the attached document before confirming.',
                'created_by'     => $agent->agent_id,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            if ($isInsurance && !empty($v['coverage_start']) && !empty($v['coverage_end'])) {
                DB::table('insurance_renewal_schedules')->insert([
                    'renewal_id'     => Str::uuid()->toString(),
                    'policy_id'      => $policyId,
                    'vehicle_number' => $v['vehicle_number'] ?? null,
                    'coverage_start' => $v['coverage_start'],
                    'coverage_end'   => $v['coverage_end'],
                    'status'         => 'UPCOMING',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }

            if (!empty($v['coverage_type'])) {
                DB::table('sales_transaction_attributes')->insert([
                    'attr_id'         => Str::uuid()->toString(),
                    'policy_id'       => $policyId,
                    'product_id'      => $product->product_id,
                    'attribute_name'  => 'COVERAGE_TYPE',
                    'attribute_value' => $v['coverage_type'],
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

            DB::table('sales_transaction_documents')->insert([
                'document_id'  => Str::uuid()->toString(),
                'policy_id'    => $policyId,
                'document_type'=> stripos($subject, 'receipt') !== false ? 'RECEIPT' : (stripos($subject, 'invoice') !== false ? 'SALES_INVOICE' : 'POLICY_DOCUMENT'),
                'file_name'    => $file->getClientOriginalName(),
                'file_path'    => $filePath,
                'file_size'    => $file->getSize(),
                'file_hash'    => $fileHash,
                'uploaded_by'  => $agent->agent_id,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        });

        $this->commissionEngine->calculate($policyId);

        $this->notificationService->notify(
            $this->notificationService->recipientsForUplineChain($agent),
            'SALES_TRANSACTION_SUBMITTED',
            'New Sales Transaction Submitted (via Email)',
            "{$agent->full_name} ({$agent->agent_code}) submitted a new sales transaction by email — Ref: {$v['document_reference_number']}, Amount: RM " . number_format($v['premium_amount'], 2) . '. This was auto-processed and is flagged for review — please verify all fields before confirming.',
            $agent->agent_id
        );

        return response()->json(['status' => 'OK', 'policy_id' => $policyId]);
    }

    private function notifyAgent(Agent $agent, string $title, string $message): void
    {
        $this->notificationService->notify([$agent], 'EMAIL_SUBMISSION_ISSUE', $title, $message, $agent->agent_id);
    }

    private function alertAdmins(string $title, string $message): void
    {
        $admins = Agent::where('role', 'ADMIN')->where('is_deleted', false)->get()->all();
        $this->notificationService->notify($admins, 'EMAIL_SUBMISSION_ISSUE', $title, $message);
    }
}
