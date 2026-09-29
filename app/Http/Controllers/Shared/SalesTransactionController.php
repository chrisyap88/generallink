<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AuditService;
use App\Services\ClaudeDocumentExtractionService;
use App\Services\CommissionEngine;
use App\Services\DataScopeService;
use App\Services\DocumentCreditService;
use App\Services\EspoCrmService;
use App\Services\GeminiDocumentExtractionService;
use App\Services\HubVaultService;
use App\Services\OpenAiDocumentExtractionService;
use App\Services\PhoneNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// -------------------------------------------------------
// NEW 16 Jul 2026 — Sales Transaction Maintenance module.
// One shared controller reused across Admin/GL/TL/Introducer (and,
// via the same routes prefixed under each role, Public + Special
// Groups) — exactly the same pattern already used by
// MasterFileController for the Team Leader/Introducer maintenance
// screens. Authority scoping is handled per-request via
// DataScopeService (already used by TL/GL\TransactionController),
// never hard-coded per role.
//
// Design agreed with Chris:
//  - UPDATED 18 Jul 2026 — the uploaded photo/PDF is no longer
//    evidence-only. extractDocument() below sends it straight to the
//    Claude API (ClaudeDocumentExtractionService), which reads it
//    directly and returns field values the create screen uses to
//    auto-fill the form. The agent still reviews/corrects everything
//    before submitting — nothing is saved from extraction alone.
//  - UPDATED 5 Aug 2026 — an agent can now choose (My Profile >
//    Document Reading) to pay for this with their OWN OpenAI or Gemini
//    key instead of the company's Document Credit wallet. Only these
//    two providers are supported. extractDocument() re-checks the key
//    is actually CONNECTED and the agent's Integration Hub is unlocked
//    at the moment of every read (never trusts the saved preference
//    alone) and falls back to Company Credit if not.
//  - Only a true structural duplicate (same policy_number) is
//    hard-blocked (enforced by the DB unique constraint already on
//    sales_transactions.policy_number). Everything softer — same
//    customer+product with overlapping coverage dates, or a
//    receipt photo whose file_hash matches one already on file —
//    is flagged for Admin review (flagged_for_review/flag_reason)
//    but the submission still goes through.
//  - Commission is calculated immediately (CommissionEngine::calculate)
//    but stays PENDING — no wallet credit — until Admin confirms via
//    confirmPolicy(), matching the "real fraud check is vendor
//    payment reconciliation, not this form" design.
// -------------------------------------------------------
class SalesTransactionController extends Controller
{
    public function __construct(private CommissionEngine $commissionEngine) {}

    /**
     * List + search + wildcard + type-ahead-friendly index.
     */
    public function index(Request $request)
    {
        $agent = auth('agent')->user();
        $scope = new DataScopeService();

        $query = DB::table('sales_transactions as st')
            ->join('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
            ->where('st.is_deleted', false);

        $scope->applyToTransactions($query, 'st');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('st.document_reference_number', 'like', "%{$search}%")
                  ->orWhere('c.full_name', 'like', "%{$search}%")
                  ->orWhere('a.full_name', 'like', "%{$search}%")
                  ->orWhere('a.agent_code', 'like', "%{$search}%");
            });
        }
        if ($request->filled('status')) {
            $query->where('st.status', $request->status);
        }
        if ($request->filled('flagged')) {
            $query->where('st.flagged_for_review', true);
        }

        $transactions = $query->select(
                'st.policy_id', 'st.document_reference_number', 'st.premium_amount', 'st.status',
                'st.flagged_for_review', 'st.created_at',
                'c.full_name as customer_name', 'p.product_name', 'v.vendor_name',
                'a.full_name as agent_name', 'a.agent_code'
            )
            ->orderByDesc('st.created_at')
            ->paginate(15)
            ->withQueryString();

        $rolePrefix = $this->rolePrefix($agent);

        return view('sales-transactions.index', compact('transactions', 'rolePrefix', 'agent'));
    }

    /**
     * Live type-ahead: customers (by name/NRIC-tail/phone) — used by the
     * create form so the agent doesn't have to remember a customer_id.
     */
    public function customerTypeahead(Request $request)
    {
        $term = trim((string) $request->get('term'));
        if ($term === '') return response()->json([]);

        $results = DB::table('customers')
            ->where('is_deleted', false)
            ->where(function ($q) use ($term) {
                $q->where('full_name', 'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%");
            })
            ->select('customer_id', 'full_name', 'phone', 'city', 'state')
            ->limit(10)
            ->get();

        return response()->json($results);
    }

    /**
     * Live type-ahead: products, optionally scoped to a chosen vendor.
     */
    public function productTypeahead(Request $request)
    {
        $term = trim((string) $request->get('term'));

        $query = DB::table('products as p')
            ->join('vendors as v', 'p.vendor_id', '=', 'v.vendor_id')
            ->where('p.is_active', true);

        if ($term !== '') {
            $query->where('p.product_name', 'like', "%{$term}%");
        }
        if ($request->filled('vendor_id')) {
            $query->where('p.vendor_id', $request->vendor_id);
        }

        $results = $query->select('p.product_id', 'p.product_name', 'p.product_type', 'p.vendor_id', 'v.vendor_name')
            ->limit(15)
            ->get();

        return response()->json($results);
    }

    /**
     * NEW 18 Jul 2026 — AJAX: read an uploaded document (before final
     * submit) via the Claude API and hand back field values for the
     * create-screen JS to fill in. Nothing is saved here — same as the
     * old testExtraction()/previewText() endpoints on the Admin
     * calibration screens, this only returns what the form COULD be
     * filled with; the agent reviews and corrects before submitting.
     */
    public function extractDocument(
        Request $request,
        ClaudeDocumentExtractionService $extractor,
        DocumentCreditService $credit,
        OpenAiDocumentExtractionService $openAiExtractor,
        GeminiDocumentExtractionService $geminiExtractor,
        HubVaultService $vault
    ) {
        // NEW 19 Jul 2026 — per Chris: the customer's consent must be
        // confirmed before their document is sent anywhere for AI-assisted
        // processing. The create screen already disables the Read
        // Document button until this is ticked; this is the server-side
        // backstop in case that's ever bypassed.
        if (!$request->boolean('consent_declaration')) {
            return response()->json([
                'status'  => 'ERROR',
                'message' => 'Please confirm the consent declaration before uploading a document.',
            ], 422);
        }

        $agent = auth('agent')->user();

        // NEW 5 Aug 2026 — Document Reading preference (My Profile): an
        // agent can choose to read documents with their OWN OpenAI or
        // Gemini key (connected in the Integration Hub) instead of the
        // company's Document Credit wallet. Re-validated here, at the
        // moment of use — never trust the saved preference alone, since
        // the key could have been disconnected or failed a retest since
        // it was chosen, or the agent's Hub could simply be locked this
        // session.
        $preference = $agent->document_extraction_provider ?? 'COMPANY_CREDIT';
        $byokProvider = null; // 'OPENAI' or 'GEMINI' once confirmed usable below
        $byokFallbackReason = null; // set below if the preference couldn't be honoured, so the create screen can tell the agent plainly why they were still charged Document Credit

        if (in_array($preference, ['OPENAI', 'GEMINI'], true)) {
            $providerKey = strtolower($preference); // 'openai' / 'gemini'
            $row = DB::table('agent_integrations')
                ->where('agent_id', $agent->agent_id)
                ->where('category', 'ai_services')
                ->where('provider', $providerKey)
                ->first();

            if (!$row || $row->status !== 'CONNECTED') {
                $byokFallbackReason = 'NOT_CONNECTED';
            } elseif (!$vault->isUnlocked($agent->agent_id)) {
                $byokFallbackReason = 'HUB_LOCKED';
            } else {
                $byokProvider = $preference;
            }
            // Falls back to Company Credit below rather than blocking the
            // agent from reading the document at all — the create screen
            // shows exactly why via byok_fallback_reason in the response.
        }

        // NEW 21 Jul 2026 — Document Credit Wallet. Reading a document
        // costs real money via the Claude API, so this is blocked
        // outright if the agent's prepaid balance can't cover the
        // Admin-set flat cost per read — checked BEFORE calling the
        // API at all, so a blocked agent never causes an API call.
        // Skipped entirely when using the agent's own BYOK key, since no
        // company credit is spent in that case.
        if (!$byokProvider && !$credit->hasSufficientBalance($agent->agent_id)) {
            return response()->json([
                'status'  => 'ERROR',
                'message' => 'Your Document Credit balance (RM ' . number_format($credit->balance($agent->agent_id), 2) . ') is too low to read a document (costs RM ' . number_format($credit->deductionAmount(), 2) . ' per read). Please top up your Document Credit balance to continue — see My Account > Document Credit.',
            ], 422);
        }

        $request->validate([
            'document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ]);

        $file = $request->file('document');
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            // Fall back on the file extension if the browser sent a
            // generic/unexpected mime type.
            $mimeType = match (strtolower($file->getClientOriginalExtension())) {
                'pdf'   => 'application/pdf',
                'png'   => 'image/png',
                default => 'image/jpeg',
            };
        }

        // Field keys match this form's actual input names 1:1, so the
        // frontend can drop each returned value straight into the
        // matching field with no extra mapping step.
        $fields = [
            'new_customer_name'      => "The customer's / policyholder's / insured's full name.",
            'new_customer_nric'      => "The customer's NRIC or IC number.",
            'new_customer_phone'     => "The customer's phone/contact number, if shown anywhere on the document.",
            'new_customer_email'     => "The customer's email address, if shown anywhere on the document.",
            'new_customer_address'   => "The customer's full address exactly as printed, all lines combined into one string.",
            'new_customer_postcode'  => "The postcode (numeric part) from the customer's address.",
            'new_customer_city'      => "The city/town from the customer's address.",
            'new_customer_state'     => "The Malaysian state from the customer's address.",
            'document_reference_number' => 'The policy number, invoice number, or receipt number printed on the document.',
            // FIXED 19 Jul 2026 — per Chris: commission is calculated off
            // this field (see CommissionEngine), so for insurance
            // documents it MUST be the gross premium BEFORE SST/service
            // tax/stamp duty, never the final total amount payable that
            // includes tax — paying commission on tax the agency never
            // actually earned would overpay every insurance commission.
            // For non-insurance documents (plain receipts/invoices with
            // no such split), the total amount payable is used since
            // there's nothing else to go on.
            'premium_amount'         => 'The GROSS premium — the amount BEFORE SST/service tax/stamp duty is added — if this is an insurance document (look for a line literally labelled "Gross Premium"). Do NOT return the final total amount payable if it is inclusive of tax/duty. For non-insurance documents with no such distinction, use the total amount payable instead.',
            'sum_insured'            => 'The sum insured amount, if this is an insurance document, as a plain number.',
            // FIXED 19 Jul 2026 — explicitly says "no time" now, because
            // Claude was occasionally returning a trailing time (e.g.
            // "10-04-2026 05:30pm") which the create-screen JS's date
            // parser silently dropped, making these look "missing".
            'coverage_start'         => 'The insurance coverage start date, if this is an insurance policy. Return ONLY the date as DD-MM-YYYY, with no time of day attached.',
            'coverage_end'           => 'The insurance coverage end date / expiry date, if this is an insurance policy. Return ONLY the date as DD-MM-YYYY, with no time of day attached.',
            'coverage_type'          => 'The type/class of coverage (e.g. Comprehensive, Third Party), if this is an insurance policy.',
            'vehicle_number'         => 'The vehicle registration number, if this document is for motor insurance.',
            'vehicle_make_model'     => 'The vehicle make and model / type of body, if shown.',
            'cubic_capacity'         => 'The engine cubic capacity, if shown.',
            'year_of_manufacture'    => 'The vehicle year of manufacture, if shown.',
            'seating_capacity'       => 'The vehicle seating capacity, if shown.',
            'engine_number'          => 'The engine number, if shown.',
            'chassis_number'         => 'The chassis number, if shown.',
            'trailer_chassis_number' => 'The trailer chassis number, if this document mentions a trailer.',
            'named_drivers'          => 'The named/authorised driver(s), if shown.',
            'addons'                 => 'Any add-ons, extensions, or extra benefits listed (e.g. Windscreen, Flood, LLP to Passengers), each with its own amount if shown — one per line, as one string.',
            // NEW 19 Jul 2026 — requested by Chris: these appear on almost
            // every motor policy schedule alongside Sum Insured, and were
            // shown in the earlier demo but not yet captured by this form.
            'ncd_percentage'         => 'The No Claim Discount (NCD) percentage, if shown, as a plain number with no % sign (e.g. 55).',
            'excess_amount'          => 'The excess / excess amount, if shown, as a plain number with no currency symbol.',
            // FIXED 19 Jul 2026 — this field's role is swapped from its
            // original name: it now captures the TOTAL amount payable
            // (including SST/tax/duty), kept purely as a record of what
            // the customer actually paid. The gross (pre-tax) figure that
            // commission is based on now lives in premium_amount above —
            // the DB/form field is still literally named "gross_premium"
            // (renaming it touches many places for no functional gain),
            // but this instruction and its label on-screen reflect the
            // real, corrected meaning.
            'gross_premium'          => 'The final total amount payable BY the customer, including SST/service tax/stamp duty if shown separately from the gross premium — as a plain number with no currency symbol. (For record purposes only — NOT used for commission.)',
            // NEW 19 Jul 2026 — per Chris: letting the agent freely pick
            // ANY vendor/product regardless of what the document actually
            // says is a fraud/dispute risk (e.g. document says P&O
            // Insurance Berhad, agent picks a different insurer). This
            // reads the insurer's name off the document so it can be
            // matched against the vendors table and, if confident, the
            // Vendor/Product fields are locked to that match instead of
            // left freely editable.
            'vendor_name_on_document' => 'The name of the insurance company / underwriter / vendor issuing this document, exactly as printed (usually in the letterhead or "Insurer" field).',
            // NEW 19 Jul 2026 — per Chris: the product must be matched by
            // what the document actually calls it (e.g. "Private Car
            // Policy Schedule"), not guessed purely from vehicle fields
            // being present — a vendor can sell several motor products
            // (Private Car, Commercial Vehicle, Motorcycle, etc.), so
            // category alone isn't precise enough to auto-lock safely.
            'product_name_on_document' => 'The name/type of this policy or product exactly as printed on the document (e.g. "Private Car Policy Schedule", "Motor Insurance", "Fire Insurance", "Personal Accident"). Usually near the top of the document or in its title.',
        ];

        if ($byokProvider) {
            try {
                $apiKey = $vault->decryptSecret($agent->agent_id, $row->api_key_encrypted);
            } catch (\Throwable $e) {
                // Stored key couldn't be read back — treat exactly like
                // "not connected" and fall back to Company Credit rather
                // than fail the whole read.
                $byokProvider = null;
                $byokFallbackReason = 'KEY_UNREADABLE';
            }
        }

        if ($byokProvider === 'OPENAI') {
            $result = $openAiExtractor->extract($file->getRealPath(), $mimeType, $fields, $apiKey);
        } elseif ($byokProvider === 'GEMINI') {
            $result = $geminiExtractor->extract($file->getRealPath(), $mimeType, $fields, $apiKey);
        } else {
            $byokProvider = null; // make sure a failed decrypt above didn't leave this set
            $result = $extractor->extract($file->getRealPath(), $mimeType, $fields);
        }

        // NEW 5 Aug 2026 — a Connected badge in the Integration Hub is
        // only as fresh as the last Test Connection click. If the key
        // gets rejected during a REAL read (revoked, out of quota), that
        // badge is now a lie until someone happens to retest it — so
        // fix it immediately rather than leave it stale.
        if ($byokProvider && !empty($result['auth_error'])) {
            DB::table('agent_integrations')
                ->where('agent_id', $agent->agent_id)
                ->where('category', 'ai_services')
                ->where('provider', strtolower($byokProvider))
                ->update([
                    'status' => 'ERROR',
                    'last_tested_at' => now(),
                    'last_test_result' => substr($result['message'] ?? 'Key rejected during a real document read.', 0, 255),
                    'updated_at' => now(),
                ]);
        }

        // Tags which source actually read this document — shown on the
        // create screen, and useful later for comparing extraction
        // accuracy between providers per Chris's stated interest.
        $result['extraction_source'] = $byokProvider ?? 'COMPANY_CREDIT';
        if (!$byokProvider && $byokFallbackReason) {
            $result['byok_fallback_reason'] = $byokFallbackReason; // 'NOT_CONNECTED' | 'HUB_LOCKED' | 'KEY_UNREADABLE'
        }

        if ($result['status'] === 'OK') {
            $rawVendorName = $result['values']['vendor_name_on_document'] ?? null;
            $rawVendorName = $rawVendorName ? preg_replace('/^UNSURE:\s*/', '', $rawVendorName) : null;

            $result['vendor_match'] = ['matched' => false, 'raw_text' => $rawVendorName];
            $result['product_match'] = ['matched' => false];

            if ($rawVendorName) {
                $vendorMatch = $this->matchVendorFromText($rawVendorName);
                if ($vendorMatch) {
                    $result['vendor_match'] = [
                        'matched'     => true,
                        'vendor_id'   => $vendorMatch->vendor_id,
                        'vendor_name' => $vendorMatch->vendor_name,
                        'raw_text'    => $rawVendorName,
                    ];

                    // Only attempt a product match once we know the
                    // vendor — otherwise "MOTOR" alone could match any
                    // insurer's motor product.
                    $isMotor = !empty($result['values']['vehicle_number'])
                        || !empty($result['values']['chassis_number'])
                        || !empty($result['values']['engine_number']);

                    $rawProductName = $result['values']['product_name_on_document'] ?? null;
                    $rawProductName = $rawProductName ? preg_replace('/^UNSURE:\s*/', '', $rawProductName) : null;

                    // FIXED 19 Jul 2026 — per Chris: the frontend note that
                    // explains an unmatched product (as opposed to it just
                    // silently sitting empty, which looked broken) needs
                    // the raw document text — previously this only ever
                    // stayed at the outer default ['matched' => false] with
                    // no raw_text, since it's only overwritten below on a
                    // successful match.
                    $result['product_match'] = ['matched' => false, 'raw_text' => $rawProductName];

                    $vendorProducts = DB::table('products')
                        ->where('vendor_id', $vendorMatch->vendor_id)
                        ->where('is_active', true)
                        ->get();

                    // Try matching by the product name printed on the
                    // document FIRST — this is precise even when the
                    // vendor sells several products of the same category.
                    $productMatch = $rawProductName ? $this->matchProductFromText($rawProductName, $vendorProducts) : null;

                    if ($productMatch) {
                        $result['product_match'] = [
                            'matched'      => true,
                            'product_id'   => $productMatch->product_id,
                            'product_name' => $productMatch->product_name,
                        ];
                    } elseif ($isMotor) {
                        // Fallback for when the document didn't clearly
                        // name the product (or it didn't match anything):
                        // if this vendor has exactly ONE motor product,
                        // it's still a safe guess.
                        $motorProducts = $vendorProducts->where('product_type', 'MOTOR')->values();
                        if ($motorProducts->count() === 1) {
                            $result['product_match'] = [
                                'matched'      => true,
                                'product_id'   => $motorProducts[0]->product_id,
                                'product_name' => $motorProducts[0]->product_name,
                            ];
                        }
                    }
                }
            }
        }

        // NEW 21 Jul 2026 — the read succeeded, so the agent is now
        // charged the flat per-read amount. Deliberately only reached
        // when $result['status'] === 'OK' above — a failed/errored
        // read (bad file, API down, etc.) is never charged.
        // NEW 5 Aug 2026 — skipped entirely when the read used the
        // agent's own OpenAI/Gemini key: that costs the agent's own
        // account, never the company's Document Credit wallet.
        if ($result['status'] === 'OK' && !$byokProvider) {
            $credit->deduct($agent->agent_id, null, 'Document read on Sales Transaction Create screen: ' . $file->getClientOriginalName());
            $result['document_credit_balance'] = $credit->balance($agent->agent_id);
        }

        return response()->json($result);
    }

    /**
     * NEW 19 Jul 2026 — fuzzy-matches the insurer name printed on a
     * document against the vendors table, so the create screen can lock
     * Vendor/Product to what the document actually says (see comment on
     * extractDocument()). Deliberately conservative: only returns a match
     * when reasonably confident, otherwise the agent is left to pick
     * manually rather than risk locking to the WRONG vendor.
     */
    private function matchVendorFromText(string $rawText): ?object
    {
        $normalize = function (string $s) {
            $s = strtoupper($s);
            // Strip common corporate suffixes/punctuation that vary
            // between how a document prints a name and how it's stored
            // (e.g. "P&O Insurance Berhad" vs "P&O INSURANCE BHD").
            $s = str_replace(['BERHAD', 'BHD', '.', ',', "'"], ['', '', '', '', ''], $s);
            $s = preg_replace('/[^A-Z0-9& ]/', ' ', $s);
            return trim(preg_replace('/\s+/', ' ', $s));
        };

        // NEW 19 Jul 2026 — per Chris's real test case: the document said
        // "PACIFIC & ORIENT INSURANCE CO. BERHAD" but the vendor is
        // stored as "P & O Insurance Berhad" (code "P&O") — an
        // abbreviation, not a substring, so neither the exact/contains
        // check nor similar_text() would ever catch it. This builds an
        // initials form (dropping generic words like INSURANCE/CO first,
        // so only the distinctive words count) and compares that against
        // the vendor's own code and its own initials, which is how
        // Malaysian insurers' short names are actually derived.
        $initials = function (string $s) {
            $s = preg_replace('/\b(INSURANCE|CO|GROUP|MALAYSIA|TAKAFUL|BHD|SDN|LTD)\b/', '', $s);
            $s = trim(preg_replace('/\s+/', ' ', $s));
            $letters = '';
            foreach (explode(' ', $s) as $word) {
                if ($word === '') continue;
                $letters .= $word === '&' ? '&' : $word[0];
            }
            return $letters;
        };

        $needle = $normalize($rawText);
        if ($needle === '') return null;

        $vendors = DB::table('vendors')->where('is_active', true)->get();
        $needleInitials = $initials($needle);

        $best = null;
        $bestScore = 0;
        foreach ($vendors as $vendor) {
            $hay = $normalize($vendor->vendor_name);
            if ($hay === '') continue;

            if ($hay === $needle) {
                // Exact match after normalizing — full confidence.
                return $vendor;
            }

            if (str_contains($needle, $hay) || str_contains($hay, $needle)) {
                // One name fully contains the other (e.g. document prints
                // an abbreviated or extended form) — high confidence.
                $best = $vendor;
                $bestScore = 100;
                continue;
            }

            $vendorCode = strtoupper((string) ($vendor->vendor_code ?? ''));
            $hayInitials = $initials($hay);
            if ($needleInitials !== '' && ($needleInitials === $vendorCode || $needleInitials === $hayInitials)) {
                // e.g. "PACIFIC & ORIENT" -> "P&O" matches vendor_code
                // "P&O" or the vendor name's own initials.
                $best = $vendor;
                $bestScore = 100;
                continue;
            }

            similar_text($needle, $hay, $percent);
            if ($percent > $bestScore && $percent >= 70) {
                $best = $vendor;
                $bestScore = $percent;
            }
        }

        return $best;
    }

    /**
     * NEW 19 Jul 2026 — matches the product/policy name printed on a
     * document (e.g. "Private Car Policy Schedule") against a vendor's
     * own product list, so Product can be auto-locked precisely even when
     * the vendor sells several products of the same category (Private
     * Car, Commercial Vehicle, Motorcycle are all "MOTOR"). Same
     * conservative approach as matchVendorFromText() — no match found
     * just means the agent picks manually, never a wrong guess.
     */
    private function matchProductFromText(string $rawText, $vendorProducts): ?object
    {
        $normalize = function (string $s) {
            $s = strtoupper(trim($s));
            $s = preg_replace('/[^A-Z0-9 ]/', ' ', $s);
            return trim(preg_replace('/\s+/', ' ', $s));
        };

        $needle = $normalize($rawText);
        if ($needle === '') return null;

        $best = null;
        $bestScore = 0;
        foreach ($vendorProducts as $product) {
            $hay = $normalize($product->product_name);
            if ($hay === '') continue;

            if ($hay === $needle) {
                return $product;
            }

            if (str_contains($needle, $hay) || str_contains($hay, $needle)) {
                $best = $product;
                $bestScore = 100;
                continue;
            }

            similar_text($needle, $hay, $percent);
            if ($percent > $bestScore && $percent >= 70) {
                $best = $product;
                $bestScore = $percent;
            }
        }

        return $best;
    }

    public function create(Request $request)
    {
        $agent = auth('agent')->user();
        $rolePrefix = $this->rolePrefix($agent);

        $vendors = DB::table('vendors')->where('is_active', true)->orderBy('vendor_name')->get();

        return view('sales-transactions.create', compact('agent', 'rolePrefix', 'vendors'));
    }

    public function store(Request $request, EspoCrmService $espoCrm)
    {
        $agent = auth('agent')->user();
        $rolePrefix = $this->rolePrefix($agent);

        $validated = $request->validate([
            // NEW 19 Jul 2026 — per Chris: consent must be confirmed
            // before the document was ever uploaded/read, and this is
            // re-checked at final submission too — matches the
            // 'accepted' rule Laravel uses for checkbox agreements.
            'consent_declaration' => ['accepted'],

            // Customer — either an existing customer_id (from type-ahead) or
            // brand-new customer details typed in.
            'customer_id'      => ['nullable', 'exists:customers,customer_id'],
            'new_customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:200'],
            'new_customer_nric' => ['required_without:customer_id', 'nullable', 'string', 'max:20'],
            'new_customer_phone' => ['required_without:customer_id', 'nullable', 'string', PhoneNumberService::nullableRule()],
            // NEW 19 Jul 2026 — customers.email exists but was never
            // captured on this form; the daily renewals:send-reminders
            // job can't email a customer with no address on file, so
            // this is worth prompting for even though it's not required.
            'new_customer_email' => ['nullable', 'email', 'max:200'],
            'new_customer_address' => ['nullable', 'string'],
            // NEW 18 Jul 2026 — split out so manual entry populates the
            // same postcode/city/state columns the automated document
            // calibration engine's address parser will, instead of
            // everything sitting unsplit inside one Address field.
            'new_customer_postcode' => ['nullable', 'string', 'max:10'],
            'new_customer_city'     => ['nullable', 'string', 'max:100'],
            'new_customer_state'    => ['nullable', 'string', 'max:100'],

            'vendor_id'      => ['required', 'exists:vendors,vendor_id'],
            'product_id'     => ['required', 'exists:products,product_id'],
            // NEW 17 Jul 2026 — generic across every industry (policy
            // number for insurance, invoice number for a workshop, receipt
            // number for a restaurant, etc.). Still written into the old
            // policy_number column too (see below) since that column is
            // NOT NULL/unique on the table and hasn't been retired yet.
            'document_reference_number' => ['required', 'string', 'max:100', 'unique:sales_transactions,document_reference_number'],
            'premium_amount' => ['required', 'numeric', 'min:0.01'],
            'sum_insured'    => ['nullable', 'numeric', 'min:0'],
            // Coverage dates are insurance-only now — validated
            // conditionally below, not required for every industry.
            'coverage_start' => ['nullable', 'date'],
            'coverage_end'   => ['nullable', 'date', 'after:coverage_start'],
            // NEW 18 Jul 2026 — vehicle registration number, insurance-only
            // and optional even then (only motor policies have a vehicle).
            'vehicle_number' => ['nullable', 'string', 'max:20'],
            // NEW 18 Jul 2026 — full vehicle schedule detail, motor-only,
            // all optional. Stored as-is (strings), never parsed/computed.
            'vehicle_make_model'       => ['nullable', 'string', 'max:150'],
            'cubic_capacity'           => ['nullable', 'string', 'max:30'],
            'year_of_manufacture'      => ['nullable', 'string', 'max:4'],
            'seating_capacity'         => ['nullable', 'string', 'max:10'],
            'engine_number'            => ['nullable', 'string', 'max:50'],
            'chassis_number'           => ['nullable', 'string', 'max:50'],
            'trailer_chassis_number'   => ['nullable', 'string', 'max:50'],
            'named_drivers'            => ['nullable', 'string', 'max:255'],
            // NEW 18 Jul 2026 — coverage type + add-ons change every
            // renewal (unlike vehicle details), so these live per-policy
            // via the existing product-attributes table, not a fixed
            // column — a new insurer's wording never needs a migration.
            'coverage_type'  => ['nullable', 'string', 'max:150'],
            // FIXED 19 Jul 2026 — per Chris ("cannot change at all, it was
            // extracted from documents"): the Add-ons field on the create
            // screen is now two real, independently-editable boxes
            // (addons_part1/addons_part2) instead of one field kept in
            // sync via hidden-element JS trickery. Validated separately
            // here, then combined into the single "addons" string right
            // below — everything downstream (renewal schedule, stored
            // attributes) still just uses $validated['addons'] unchanged.
            'addons_part1'   => ['nullable', 'string', 'max:500'],
            'addons_part2'   => ['nullable', 'string', 'max:500'],
            // NEW 19 Jul 2026 — same flexible-attribute pattern as
            // coverage_type/addons above, requested by Chris.
            'ncd_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'excess_amount'  => ['nullable', 'numeric', 'min:0'],
            'gross_premium'  => ['nullable', 'numeric', 'min:0'],

            'document_type'  => ['required', 'in:RECEIPT,SALES_INVOICE,POLICY_DOCUMENT,OTHER'],
            'document'       => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ], [
            'document_reference_number.unique' => 'This document reference number already exists in the system — duplicate submissions are not allowed. If you believe this is an error, contact Admin.',
        ]);

        // FIXED 19 Jul 2026 — combine the two visible add-ons boxes back
        // into the single "addons" string every downstream use of
        // $validated['addons'] already expects (renewal schedule build,
        // stored attributes). Blank lines from either box are dropped so
        // an empty second box doesn't leave a stray blank line in the middle.
        $addonsCombined = trim(($validated['addons_part1'] ?? '') . "\n" . ($validated['addons_part2'] ?? ''));
        $addonsLines = array_filter(
            preg_split('/\r\n|\r|\n/', $addonsCombined),
            fn($line) => trim($line) !== ''
        );
        $validated['addons'] = implode("\n", $addonsLines);

        // Vendor industry drives whether this is an insurance sale (needs
        // coverage dates + a renewal record) or any other industry
        // (doesn't). Generic check, not hard-coded to a specific vendor.
        $vendor = DB::table('vendors')->where('vendor_id', $validated['vendor_id'])->first();
        $isInsurance = $vendor && $vendor->industry === 'INSURANCE';

        if ($isInsurance && (empty($validated['coverage_start']) || empty($validated['coverage_end']))) {
            return back()->withInput()->withErrors([
                'coverage_start' => 'Coverage Start and Coverage End are required for insurance products.',
            ]);
        }

        $flagReasons = [];
        // NEW 22 Jul 2026 — per Chris's scoped-down fraud detection
        // build: same checks as $flagReasons below, but shaped as
        // weighted anomalies for FraudDetectionService::flag(), which
        // both writes them to the new Risk Review Queue AND holds
        // HIGH/CRITICAL submissions from Admin's confirm() action
        // until cleared. $flagReasons itself is untouched — still
        // drives the existing flagged_for_review/flag_reason columns.
        $anomalies = [];

        // 1. Resolve or create the customer.
        // NEW 19 Jul 2026 — status_id is now a required FK into the
        // user-configurable customer_statuses table (per Chris — no
        // longer a hardcoded 2-value enum), looked up by code here.
        $activeStatusId = DB::table('customer_statuses')->where('code', 'ACTIVE')->value('status_id');
        abort_if(!$activeStatusId, 500, 'ACTIVE status is missing from customer_statuses — contact Admin.');

        if (!empty($validated['customer_id'])) {
            $customerId = $validated['customer_id'];
            // Per Chris: if the agent picked an existing Prospect (a
            // personal contact with no policy yet) from the typeahead,
            // submitting a real sale for them converts the record to
            // ACTIVE the moment this transaction is saved. NRIC capture
            // for this record still needs a follow-up edit (Customer
            // Edit, Admin-only) — not blocked here.
            $wasProspect = DB::table('customers')->where('customer_id', $customerId)
                ->whereIn('status_id', function ($q) {
                    $q->select('status_id')->from('customer_statuses')->where('code', 'PROSPECT');
                })->exists();
            if ($wasProspect) {
                DB::table('customers')->where('customer_id', $customerId)->update(['status_id' => $activeStatusId, 'updated_at' => now()]);
                AuditService::logChange('customers', $customerId, 'UPDATE', ['status' => 'PROSPECT'], ['status' => 'ACTIVE', 'reason' => 'Converted on first Sales Transaction'], $agent->agent_id);
            }
        } else {
            $nricRaw = $validated['new_customer_nric'];
            $nricHash = hash('sha256', $nricRaw);
            $existing = DB::table('customers')->where('nric_hash', $nricHash)->where('is_deleted', false)->first();

            if ($existing) {
                $customerId = $existing->customer_id;
            } else {
                $customerId = Str::uuid()->toString();
                DB::table('customers')->insert([
                    'customer_id'     => $customerId,
                    'status_id'       => $activeStatusId,
                    'nric_encrypted'  => encrypt($nricRaw),
                    'nric_hash'       => $nricHash,
                    'full_name'       => $validated['new_customer_name'],
                    'phone'           => PhoneNumberService::normalize($validated['new_customer_phone'] ?? null),
                    // NEW 19 Jul 2026 — was never captured before; without
                    // it renewals:send-reminders has no address to send to.
                    'email'           => $validated['new_customer_email'] ?? null,
                    'address'         => $validated['new_customer_address'] ?? null,
                    'postcode'        => $validated['new_customer_postcode'] ?? null,
                    'city'            => $validated['new_customer_city'] ?? null,
                    'state'           => $validated['new_customer_state'] ?? null,
                    'owned_by_agent_id' => $agent->agent_id,
                    'created_by'      => $agent->agent_id,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
                // NEW 19 Jul 2026 — per Chris: a customer found for the
                // first time via a Sales Transaction upload (not already
                // in the DB) defaults to ACTIVE status. Logged for the
                // Activity Log History tab.
                AuditService::logChange('customers', $customerId, 'CREATE', null, ['full_name' => $validated['new_customer_name'], 'status' => 'ACTIVE'], $agent->agent_id);

                // NEW 29 Jul 2026 — EspoCRM integration (task #252). This
                // is a second customer-creation path that doesn't go
                // through CustomerResolutionService, so it needs its own
                // Contact sync call.
                $newCustomerRow = DB::table('customers')->where('customer_id', $customerId)->first();
                $newContactId = $espoCrm->createContact($newCustomerRow);
                if ($newContactId) {
                    DB::table('customers')->where('customer_id', $customerId)->update(['espocrm_contact_id' => $newContactId]);
                }
            }
        }

        // 2. Soft duplicate check. WARN only, never blocks.
        // Insurance: same customer + product with overlapping coverage
        // dates (via the renewal schedule table, not the deprecated
        // columns). Other industries: same customer + product + amount
        // submitted again within the last 24 hours — a simple generic
        // fallback until per-industry duplicate rules are configurable.
        if ($isInsurance) {
            $overlap = DB::table('sales_transactions as st')
                ->join('insurance_renewal_schedules as irs', 'irs.policy_id', '=', 'st.policy_id')
                ->where('st.customer_id', $customerId)
                ->where('st.product_id', $validated['product_id'])
                ->where('st.is_deleted', false)
                ->where('irs.coverage_start', '<=', $validated['coverage_end'])
                ->where('irs.coverage_end', '>=', $validated['coverage_start'])
                ->exists();
            if ($overlap) {
                $flagReasons[] = 'Same customer already has an overlapping policy for this product — possible duplicate.';
                $anomalies[] = ['code' => 'SOFT_DUPLICATE', 'label' => 'Overlapping policy for same customer/product', 'detail' => 'This customer already has a policy for this product with overlapping coverage dates.'];
            }
        } else {
            $recentDuplicate = DB::table('sales_transactions')
                ->where('customer_id', $customerId)
                ->where('product_id', $validated['product_id'])
                ->where('premium_amount', $validated['premium_amount'])
                ->where('is_deleted', false)
                ->where('created_at', '>=', now()->subDay())
                ->exists();
            if ($recentDuplicate) {
                $flagReasons[] = 'Same customer, product, and amount was already submitted in the last 24 hours — possible duplicate.';
                $anomalies[] = ['code' => 'SOFT_DUPLICATE', 'label' => 'Same customer/product/amount submitted very recently', 'detail' => 'An identical submission (same customer, product, and amount) was made within the last 24 hours.'];
            }
        }

        // 3. Store the uploaded document + hash it (weak reused-photo signal).
        $file = $request->file('document');
        $fileHash = hash_file('sha256', $file->getRealPath());
        $filePath = $file->store('sales-transaction-documents', 'local');

        $reusedPhoto = DB::table('sales_transaction_documents')->where('file_hash', $fileHash)->exists();
        if ($reusedPhoto) {
            $flagReasons[] = 'This exact photo/file has already been submitted before for another transaction.';
            $anomalies[] = ['code' => 'DUPLICATE_FILE_HASH', 'label' => 'Identical file already on record', 'detail' => 'This exact document (matched by file hash) has already been submitted before for another transaction.'];
        }

        // NEW 22 Jul 2026 — date-range sanity check (fraud signal, not
        // a claim-deadline business rule): a document dated far in the
        // future, or unreasonably old, is worth a human look.
        if ($isInsurance && !empty($validated['coverage_start'])) {
            $dateAnomaly = app(\App\Services\FraudDetectionService::class)->dateRangeAnomaly(
                \Carbon\Carbon::parse($validated['coverage_start']), 365, false
            );
            if ($dateAnomaly) {
                $flagReasons[] = $dateAnomaly['detail'];
                $anomalies[] = $dateAnomaly;
            }
        }

        $policyId = Str::uuid()->toString();

        DB::transaction(function () use (
            $policyId, $validated, $customerId, $agent, $flagReasons,
            $file, $fileHash, $filePath, $isInsurance
        ) {
            DB::table('sales_transactions')->insert([
                'policy_id'      => $policyId,
                // Written to both columns for now — policy_number stays
                // NOT NULL/unique on the table and hasn't been retired;
                // document_reference_number is the new generic column
                // every industry (including future ones) reads/writes.
                'policy_number'  => $validated['document_reference_number'],
                'document_reference_number' => $validated['document_reference_number'],
                'vendor_id'      => $validated['vendor_id'],
                'product_id'     => $validated['product_id'],
                'customer_id'    => $customerId,
                'agent_id'       => $agent->agent_id,
                'premium_amount' => $validated['premium_amount'],
                'sum_insured'    => $validated['sum_insured'] ?? null,
                // Deprecated columns — only populated for insurance,
                // kept in sync purely for any old code path still
                // reading them until fully retired.
                'coverage_start' => $isInsurance ? $validated['coverage_start'] : null,
                'coverage_end'   => $isInsurance ? $validated['coverage_end'] : null,
                'status'         => 'SUBMITTED',
                'flagged_for_review' => count($flagReasons) > 0,
                'flag_reason'    => count($flagReasons) > 0 ? implode(' | ', $flagReasons) : null,
                'created_by'     => $agent->agent_id,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            // NEW 17 Jul 2026 — auto-create the insurance renewal record.
            // No manual step for the agent; only happens for insurance
            // vendors, keeping the core table industry-agnostic.
            if ($isInsurance) {
                // NEW 19 Jul 2026 — Chris asked for the Renewal Reminder
                // preview shown on the create screen to be stored the
                // moment Submit is clicked, not just computed later by
                // renewals:send-reminders. reminder_scheduled_date is the
                // same "coverage_end minus 30 days" that command already
                // defaults to; reminder_message is the exact wording it
                // sends, captured now as a permanent record. The secure
                // response link can't be generated yet (it's time-limited
                // from the moment it's actually sent, which may be months
                // from now), so it's left as a {{RENEWAL_LINK}} placeholder
                // that SendRenewalReminders.php swaps out at send time.
                $reminderScheduledDate = \Carbon\Carbon::parse($validated['coverage_end'])->subDays(30)->toDateString();
                $custFullNameForReminder = DB::table('customers')->where('customer_id', $customerId)->value('full_name');

                // NEW 19 Jul 2026 — per Chris: the reminder must remind the
                // customer WHAT they're renewing, not just that they need
                // to. Same fields shown on the Renewal Reminder preview
                // tab (Sum Insured, Coverage Type, Vehicle No., NCD,
                // Add-ons, etc.), so the live preview and what actually
                // gets stored/sent always match.
                //
                // FIXED 19 Jul 2026 — per Chris's exact mockup: labels
                // padded to the width of the longest label ("Vehicle
                // Registration No." — 24 chars) so every colon lines up in
                // one column, with that longest label's colon sitting
                // immediately after it (no gap). Keep PAD_WIDTH in sync
                // with the matching JS padLabel() in create.blade.php.
                $coverageEndDisplay = \Carbon\Carbon::parse($validated['coverage_end'])->format('d M Y');
                $padWidth = 24;
                $pad = fn (string $label) => str_pad($label, $padWidth);

                $policyDetailLines = [];
                $policyDetailLines[] = $pad('Vehicle Registration No.') . ': ' . ($validated['vehicle_number'] ?? '-');
                $policyDetailLines[] = $pad('Coverage Type') . ': ' . ($validated['coverage_type'] ?? '-');
                $policyDetailLines[] = $pad('Sum Insured') . ': RM ' . number_format((float) ($validated['sum_insured'] ?? 0), 2);
                $policyDetailLines[] = $pad('Coverage Period') . ': ' .
                    \Carbon\Carbon::parse($validated['coverage_start'])->format('d M Y') . ' to ' . $coverageEndDisplay;
                if (!empty($validated['ncd_percentage'])) {
                    $policyDetailLines[] = $pad('NCD') . ': ' . $validated['ncd_percentage'] . '%';
                }
                if (!empty($validated['excess_amount'])) {
                    $policyDetailLines[] = $pad('Excess') . ': RM ' . number_format((float) $validated['excess_amount'], 2);
                }
                if (!empty($validated['addons'])) {
                    // FIXED 19 Jul 2026 — per Chris: each add-on gets its
                    // own aligned row under the same colon column, split
                    // into a name column and a right-following RM amount
                    // column, instead of dumping the raw textarea text
                    // as-is. Each add-on line is expected as "Name Amount"
                    // (e.g. "Windscreen 75.00") — if a line has no trailing
                    // number, it's shown as-is with no amount.
                    $addonRows = [];
                    $addonNameWidth = 0;
                    foreach (preg_split('/\r\n|\r|\n/', trim($validated['addons'])) as $addonLine) {
                        $addonLine = trim($addonLine);
                        if ($addonLine === '') continue;
                        if (preg_match('/^(.*\S)\s+([\d,]+(?:\.\d{1,2})?)$/', $addonLine, $m)) {
                            $addonName = $m[1];
                            $addonAmount = 'RM ' . number_format((float) str_replace(',', '', $m[2]), 2);
                        } else {
                            $addonName = $addonLine;
                            $addonAmount = '';
                        }
                        $addonRows[] = [$addonName, $addonAmount];
                        $addonNameWidth = max($addonNameWidth, strlen($addonName));
                    }
                    $addonLines = [];
                    foreach ($addonRows as [$addonName, $addonAmount]) {
                        $addonLines[] = $pad('') . ': ' . str_pad($addonName, $addonNameWidth + 2) . $addonAmount;
                    }
                    $policyDetailLines[] = 'Add-ons:';
                    if (!empty($addonLines)) {
                        $policyDetailLines[] = implode("\n", $addonLines);
                    }
                }
                $policyDetailsBlock = implode("\n", $policyDetailLines);

                $reminderMessage = "Dear {$custFullNameForReminder},\n\n" .
                    "Your policy is due on {$coverageEndDisplay}. Here are your current policy details:\n\n" .
                    "{$policyDetailsBlock}\n\n" .
                    "Please review your current coverage and let us know how you'd like to proceed:\n\n" .
                    "{{RENEWAL_LINK}}\n\n" .
                    "Your agent, {$agent->full_name} ({$agent->phone}, {$agent->email}), is also available to help.\n\n" .
                    "GeneralLink";

                DB::table('insurance_renewal_schedules')->insert([
                    'renewal_id'     => Str::uuid()->toString(),
                    'policy_id'      => $policyId,
                    'vehicle_number' => $validated['vehicle_number'] ?? null,
                    'vehicle_make_model'      => $validated['vehicle_make_model'] ?? null,
                    'cubic_capacity'          => $validated['cubic_capacity'] ?? null,
                    'year_of_manufacture'     => $validated['year_of_manufacture'] ?? null,
                    'seating_capacity'        => $validated['seating_capacity'] ?? null,
                    'engine_number'           => $validated['engine_number'] ?? null,
                    'chassis_number'          => $validated['chassis_number'] ?? null,
                    'trailer_chassis_number'  => $validated['trailer_chassis_number'] ?? null,
                    'named_drivers'           => $validated['named_drivers'] ?? null,
                    'coverage_start' => $validated['coverage_start'],
                    'coverage_end'   => $validated['coverage_end'],
                    'reminder_scheduled_date' => $reminderScheduledDate,
                    'reminder_message'        => $reminderMessage,
                    'status'         => 'UPCOMING',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }

            // NEW 18 Jul 2026 — Coverage Type + Add-ons stored per-policy
            // in the flexible attributes table (same one used for any
            // other product-specific field), not a fixed column, since
            // wording and the list of add-ons varies by vendor/product
            // and by renewal year.
            foreach ([
                'COVERAGE_TYPE'  => $validated['coverage_type'] ?? null,
                'ADD_ONS'        => $validated['addons'] ?? null,
                // NEW 19 Jul 2026 — requested by Chris.
                'NCD_PERCENTAGE' => $validated['ncd_percentage'] ?? null,
                'EXCESS_AMOUNT'  => $validated['excess_amount'] ?? null,
                // FIXED 19 Jul 2026 — attribute renamed to match its
                // corrected meaning (tax-inclusive total, record only —
                // NOT the commission basis, which is premium_amount
                // above). Form field name stays "gross_premium" internally.
                'TOTAL_AMOUNT_PAYABLE_INCL_TAX' => $validated['gross_premium'] ?? null,
            ] as $attrName => $attrValue) {
                if (!empty($attrValue)) {
                    DB::table('sales_transaction_attributes')->insert([
                        'attr_id'         => Str::uuid()->toString(),
                        'policy_id'       => $policyId,
                        'product_id'      => $validated['product_id'],
                        'attribute_name'  => $attrName,
                        'attribute_value' => $attrValue,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }
            }

            DB::table('sales_transaction_documents')->insert([
                'document_id'  => Str::uuid()->toString(),
                'policy_id'    => $policyId,
                'document_type'=> $validated['document_type'],
                'file_name'    => $file->getClientOriginalName(),
                'file_path'    => $filePath,
                'file_size'    => $file->getSize(),
                'file_hash'    => $fileHash,
                'uploaded_by'  => $agent->agent_id,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            AuditService::logChange('sales_transactions', $policyId, 'CREATE', null, $validated, $agent->agent_id);
        });

        // NEW 22 Jul 2026 — writes to the Risk Review Queue (fraud_review_flags)
        // if any anomaly was found. HIGH/CRITICAL risk notifies Admin
        // immediately and blocks Admin's confirm() action below until
        // cleared — commission still calculates as PENDING either way
        // (that part never pays out on its own), but confirm() (the
        // actual wallet-crediting step) is where the hold is enforced.
        app(\App\Services\FraudDetectionService::class)->flag('SALES_TRANSACTION', $policyId, $agent->agent_id, $anomalies);

        // Commission calculated now but stays PENDING until Admin confirms.
        $this->commissionEngine->calculate($policyId);

        // NEW 18 Jul 2026 — notify the whole upline chain (direct
        // sponsor up through Team Leader, Group Leader, and Admin) that
        // a new sales transaction came in beneath them — bell + email,
        // via the same NotificationService already used elsewhere.
        $customerName = DB::table('customers')->where('customer_id', $customerId)->value('full_name');
        app(\App\Services\NotificationService::class)->notify(
            app(\App\Services\NotificationService::class)->recipientsForUplineChain($agent),
            'SALES_TRANSACTION_SUBMITTED',
            'New Sales Transaction Submitted',
            "{$agent->full_name} ({$agent->agent_code}) submitted a new sales transaction — Ref: {$validated['document_reference_number']}, Customer: {$customerName}, Amount: RM " . number_format($validated['premium_amount'], 2) . '.' . (count($flagReasons) > 0 ? ' This submission was flagged for review: ' . implode('; ', $flagReasons) : ''),
            $agent->agent_id
        );

        // NEW 29 Jul 2026 — EspoCRM integration (task #253). Mirror this
        // sale as an Opportunity, linked to the customer's Contact.
        // Stage starts at "Proposal/Price Quote" since Earning Income
        // stays PENDING until Admin confirms — confirm() below moves it
        // to "Closed Won". Never blocks/fails the sale submission itself
        // if EspoCRM is unreachable.
        $productName = DB::table('products')->where('product_id', $validated['product_id'])->value('product_name');
        $contactId = DB::table('customers')->where('customer_id', $customerId)->value('espocrm_contact_id');
        $opportunityId = $espoCrm->createOpportunity(
            trim(($productName ?: 'Policy') . ' — ' . $customerName),
            $contactId,
            (float) $validated['premium_amount'],
            'Proposal/Price Quote',
            now(),
            'Ref: ' . $validated['document_reference_number']
        );
        if ($opportunityId) {
            DB::table('sales_transactions')->where('policy_id', $policyId)->update(['espocrm_opportunity_id' => $opportunityId]);
        }

        $rolePrefix = $this->rolePrefix($agent);
        $message = count($flagReasons) > 0
            ? 'Sales transaction submitted. Note: this record was automatically flagged for Admin review (' . implode('; ', $flagReasons) . '), but your submission has been recorded and earning income calculated as pending.'
            : 'Sales transaction submitted successfully. Earning income calculated as pending — it will be released once Admin confirms the sale.';

        return redirect()->route($rolePrefix . '.sales-transactions.show', $policyId)->with('success', $message);
    }

    public function show(Request $request, string $policyId)
    {
        $agent = auth('agent')->user();
        $scope = new DataScopeService();
        $rolePrefix = $this->rolePrefix($agent);

        $query = DB::table('sales_transactions as st')
            ->join('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
            ->where('st.policy_id', $policyId)
            ->where('st.is_deleted', false);

        $scope->applyToTransactions($query, 'st');

        $txn = $query->select(
            'st.policy_id', 'st.document_reference_number', 'st.premium_amount', 'st.sum_insured',
            'st.status', 'st.flagged_for_review',
            'st.flag_reason', 'st.reviewed_by', 'st.reviewed_at', 'st.created_at',
            'c.customer_id', 'c.full_name as customer_name', 'c.phone as customer_phone',
            // ADDED 19 Jul 2026 — needed to populate the read-only Customer
            // tab on the new 3-tab View screen (Chris asked the View screen
            // to mirror the Submit Sales Transaction screen's tabs).
            'c.nric_encrypted as customer_nric_encrypted', 'c.email as customer_email',
            'c.address as customer_address', 'c.postcode as customer_postcode',
            'c.city as customer_city', 'c.state as customer_state',
            'p.product_name', 'p.product_type', 'v.vendor_name', 'v.industry as vendor_industry',
            'a.full_name as agent_name', 'a.agent_code', 'a.role as agent_role'
        )->first();

        abort_if(!$txn, 404);

        // Insurance-only renewal info — null for every other industry.
        $renewal = $txn->vendor_industry === 'INSURANCE'
            ? DB::table('insurance_renewal_schedules')->where('policy_id', $policyId)->first()
            : null;

        $commissions = DB::table('commission_transactions as ct')
            ->join('agents as a', 'ct.agent_id', '=', 'a.agent_id')
            ->where('ct.policy_id', $policyId)
            ->select('ct.txn_id', 'ct.agent_id', 'ct.commission_amount', 'ct.entitlement_pct',
                     'ct.role_at_transaction', 'ct.status', 'ct.is_breakage',
                     'a.full_name as agent_name', 'a.agent_code')
            ->get();

        $documents = DB::table('sales_transaction_documents')
            ->where('policy_id', $policyId)
            ->orderByDesc('created_at')
            ->get();

        // Coverage Type + Add-ons (and any other product-specific field)
        // — keyed by attribute_name so the view can look up individual
        // ones by key without caring what else might be stored here.
        $attributes = DB::table('sales_transaction_attributes')
            ->where('policy_id', $policyId)
            ->pluck('attribute_value', 'attribute_name');

        $isAdmin = $agent->role === 'ADMIN';

        return view('sales-transactions.show', compact('agent', 'txn', 'renewal', 'commissions', 'documents', 'attributes', 'rolePrefix', 'isAdmin'));
    }

    /**
     * Stream an uploaded document — scoped so an agent can only view
     * documents attached to transactions within their own authority.
     */
    public function document(Request $request, string $documentId)
    {
        $agent = auth('agent')->user();
        $scope = new DataScopeService();

        $doc = DB::table('sales_transaction_documents')->where('document_id', $documentId)->first();
        abort_if(!$doc, 404);

        $txnQuery = DB::table('sales_transactions')->where('policy_id', $doc->policy_id);
        $scope->applyToTransactions($txnQuery);
        abort_if(!$txnQuery->exists(), 403);

        abort_if(!Storage::disk('local')->exists($doc->file_path), 404);

        return Storage::disk('local')->response($doc->file_path, $doc->file_name);
    }

    /**
     * Admin-only — the actual "verification" step. Flips this policy's
     * PENDING commission to CONFIRMED and credits wallets. Today this is
     * a manual button; it is designed to later be called automatically
     * once the Vendor Payment Reconciliation module matches this sale
     * against a real vendor remittance line.
     */
    public function confirm(Request $request, string $policyId, EspoCrmService $espoCrm)
    {
        $agent = auth('agent')->user();
        abort_unless($agent->role === 'ADMIN', 403, 'Only Admin can confirm a sales transaction.');

        // NEW 22 Jul 2026 — per Chris: a HIGH/CRITICAL (or any
        // unresolved) risk flag must be cleared via the Risk Review
        // Queue BEFORE commission can be released — this is the actual
        // "hold" enforcement point, since calculate() above already
        // runs unconditionally but never pays out on its own.
        if (app(\App\Services\FraudDetectionService::class)->hasUnresolvedFlag('SALES_TRANSACTION', $policyId)) {
            return back()->withErrors(['confirm' => 'This transaction has an unresolved Risk Review flag. Please review and clear it under Risk Review Queue before confirming.']);
        }

        $this->commissionEngine->confirmPolicy($policyId);

        DB::table('sales_transactions')->where('policy_id', $policyId)->update([
            'reviewed_by' => $agent->agent_id,
            'reviewed_at' => now(),
        ]);

        AuditService::logChange('sales_transactions', $policyId, 'UPDATE', null, ['action' => 'confirmed'], $agent->agent_id);

        // NEW 29 Jul 2026 — EspoCRM integration (task #253). Confirming
        // is the "sale is real, earning income released" moment — move
        // the mirrored Opportunity to Closed Won to match.
        $espoOpportunityId = DB::table('sales_transactions')->where('policy_id', $policyId)->value('espocrm_opportunity_id');
        if (!empty($espoOpportunityId)) {
            $espoCrm->updateOpportunity($espoOpportunityId, ['stage' => 'Closed Won']);
        }

        // NEW 18 Jul 2026 — notify EVERY agent who actually earned a
        // share on this policy (Introducer, Team Leader, Group Leader —
        // whichever tiers were active), each with their OWN earning
        // income amount, so the submitter (and their upline) sees a real
        // "your submission was confirmed" reply instead of nothing.
        $policyRef = DB::table('sales_transactions')->where('policy_id', $policyId)->value('document_reference_number');
        $earners = DB::table('commission_transactions')
            ->where('policy_id', $policyId)
            ->where('status', 'CONFIRMED')
            ->where('is_breakage', false)
            ->get();

        $notificationService = app(\App\Services\NotificationService::class);
        foreach ($earners as $earn) {
            $earnerAgent = \App\Models\Agent::find($earn->agent_id);
            if (! $earnerAgent) continue;

            $notificationService->notify(
                [$earnerAgent],
                'SALES_TRANSACTION_CONFIRMED',
                'Sales Transaction Confirmed',
                "Your sales transaction (Ref: {$policyRef}) has been confirmed by Admin. Earning income of RM " . number_format($earn->commission_amount, 2) . ' has been released to your wallet.',
                $agent->agent_id
            );
        }

        $rolePrefix = $this->rolePrefix($agent);
        return redirect()->route($rolePrefix . '.sales-transactions.show', $policyId)
            ->with('success', 'Transaction confirmed. Earning income released to agent wallet(s).');
    }

    /**
     * Admin-only — clear a flagged_for_review record after manual
     * review, without necessarily confirming commission (e.g. Admin
     * decides the flag was a false positive but wants to review the
     * commission release separately).
     */
    public function clearFlag(Request $request, string $policyId)
    {
        $agent = auth('agent')->user();
        abort_unless($agent->role === 'ADMIN', 403);

        DB::table('sales_transactions')->where('policy_id', $policyId)->update([
            'flagged_for_review' => false,
            'reviewed_by' => $agent->agent_id,
            'reviewed_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Flag cleared.');
    }

    private function rolePrefix(Agent $agent): string
    {
        return match ($agent->role) {
            'ADMIN'        => 'admin',
            'GROUP_LEADER' => 'gl',
            'TEAM_LEADER'  => 'tl',
            'INTRODUCER'   => 'introducer',
            default        => 'admin',
        };
    }
}
