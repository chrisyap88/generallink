<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use App\Services\AiVisionBankStatementExtractionService;
use App\Services\CbeAccountingService;
use App\Services\CbeReceiptService;
use App\Services\PartyMatchingService;
use App\Services\PdfPasswordRemovalService;
use App\Services\PdfStatementExtractionService;
use App\Services\StatementContinuityService;
use App\Services\TransactionClassificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// NEW 8 Sep 2026 (Task #397) — AI-Powered Accounting Automation
// Management Module, Phase 1: Document Upload + PDF Statement
// Extraction. Same nodeAndGroup()/ResolvesCbeActiveNode pattern as
// CbeAccountingController, kept in its own controller rather than
// folded into that already very large file, since this module's scope
// (upload, extraction review, classification, exceptions — Phases 1
// through 11) is substantial on its own.
class AiAccountingController extends Controller
{
    use ResolvesCbeActiveNode;

    private function nodeAndGroup(): array
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        $groupLabelId = $nodeId ? DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->value('group_label_id') : null;
        return [$agent, $nodeId, $groupLabelId];
    }

    public function index()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        // UPDATED 16 Sep 2026 — per Chris: "you should have delete
        // option" — a batch can now be deleted (see destroyBatch()
        // below), but only while none of its lines have been committed
        // into real bank transactions yet. This extra column tells the
        // view, per row, whether to show that Delete link at all.
        $batches = DB::table('cbe_ai_statement_batches as b')
            ->leftJoin('cbe_bank_accounts as a', 'a.bank_account_id', '=', 'b.bank_account_id')
            ->leftJoin('agents as u', 'u.agent_id', '=', 'b.uploaded_by')
            ->where('b.cbe_node_id', $nodeId)
            ->select('b.*', 'a.bank_name', 'a.account_name', 'u.full_name as uploaded_by_name')
            ->selectRaw('EXISTS(SELECT 1 FROM cbe_ai_extracted_transactions e INNER JOIN cbe_ai_statement_documents d ON d.document_id = e.document_id WHERE d.batch_id = b.batch_id AND e.status = ?) as has_committed_lines', ['COMMITTED'])
            ->orderByDesc('b.created_at')
            ->paginate(8);

        return view('cbe.ai-accounting.batches', compact('batches'));
    }

    // NEW 16 Sep 2026 — per Chris: "you should have delete option" — a
    // failed or unwanted batch (e.g. one uploaded before a password/
    // parsing issue was fixed) previously had no way to be removed at
    // all. Deletes the batch, its documents, and their extracted lines
    // (cascades via the existing foreign keys — see migration
    // 2026_09_08_000001), and the uploaded PDF files themselves.
    // Refuses outright if ANY line in the batch was already committed
    // into a real cbe_bank_transactions row — deleting that would
    // strand a posted transaction with no trace of the statement it
    // came from, and this never deletes the posted transaction itself,
    // only ever the upload/staging record.
    public function destroyBatch(string $batchId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $batch = DB::table('cbe_ai_statement_batches')->where('batch_id', $batchId)->where('cbe_node_id', $nodeId)->first();
        if (! $batch) {
            abort(404);
        }

        $hasCommittedLines = DB::table('cbe_ai_extracted_transactions as e')
            ->join('cbe_ai_statement_documents as d', 'd.document_id', '=', 'e.document_id')
            ->where('d.batch_id', $batchId)
            ->where('e.status', 'COMMITTED')
            ->exists();

        if ($hasCommittedLines) {
            return redirect()->route('cbe.ai-accounting.index')->with('warning', __('cbe_ai.delete_batch_blocked_committed'));
        }

        $storedPaths = DB::table('cbe_ai_statement_documents')->where('batch_id', $batchId)->pluck('stored_path');
        foreach ($storedPaths as $path) {
            Storage::disk('local')->delete($path);
        }

        DB::table('cbe_ai_statement_batches')->where('batch_id', $batchId)->delete();

        CbeAccountingService::logAiAudit($nodeId, null, null, 'BATCH_DELETED', $agent->agent_id, __('cbe_ai.audit_note_batch_deleted', ['label' => $batch->label]));

        return redirect()->route('cbe.ai-accounting.index')->with('success', __('cbe_ai.delete_batch_success'));
    }

    public function createBatchForm()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $bankAccounts = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('bank_name')->get();

        return view('cbe.ai-accounting.create-batch', compact('bankAccounts'));
    }

    public function storeBatch(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'label' => ['required', 'string', 'max:150'],
            'bank_account_id' => ['nullable', 'uuid', 'exists:cbe_bank_accounts,bank_account_id'],
            'statements' => ['required', 'array', 'min:1', 'max:12'],
            'statements.*' => ['required', 'file', 'mimes:pdf', 'max:8192'],
            'pdf_password' => ['nullable', 'string', 'max:100'],
        ]);

        $pdfPassword = $request->input('pdf_password');

        // NEW 16 Sep 2026 — per Chris: "Honestly all bank statement is
        // password protected in Malaysia" — he doesn't want to retype
        // the same password on every upload. Try the password(s)
        // already saved against the chosen bank account (or, if none
        // was chosen on this form, every bank account registered to
        // this node) — see decryptStatementPasswords() below.
        // FIXED 16 Sep 2026 — per Chris: he typed a password into this
        // field and every file in the batch failed with "Could not
        // unlock this PDF with the password given", even though the
        // correct password was already saved on the Bank Account master
        // file. Root cause: this used to be computed ONLY when the
        // manual field was left blank, so a manual password that didn't
        // work (mistyped, stale, or a browser silently autofilling the
        // wrong saved credential into the box) had nothing to fall back
        // to. Now always computed, and tried as a fallback below if the
        // manual password fails — never used INSTEAD of a manual
        // password, only as a second attempt after it.
        $savedPasswordCandidates = $this->decryptStatementPasswords($nodeId, $request->input('bank_account_id'));

        $batchId = (string) Str::uuid();
        DB::table('cbe_ai_statement_batches')->insert([
            'batch_id' => $batchId,
            'cbe_node_id' => $nodeId,
            'label' => $request->input('label'),
            'bank_account_id' => $request->input('bank_account_id'),
            'status' => 'UPLOADED',
            'uploaded_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $extractor = new PdfStatementExtractionService();
        $parsedCount = 0;
        $failedCount = 0;

        foreach ($request->file('statements') as $file) {
            $storedPath = $file->store('cbe-ai-statements', 'local');
            $documentId = (string) Str::uuid();

            DB::table('cbe_ai_statement_documents')->insert([
                'document_id' => $documentId,
                'batch_id' => $batchId,
                'cbe_node_id' => $nodeId,
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $storedPath,
                'status' => 'UPLOADED',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            CbeAccountingService::logAiAudit($nodeId, $documentId, null, 'UPLOADED', $agent->agent_id, __('cbe_ai.audit_note_uploaded', ['filename' => $file->getClientOriginalName()]));

            // NEW 16 Sep 2026 — per Chris: some real statements are
            // password-protected PDFs. Neither reader below can open an
            // encrypted file at all, so if a password was given on the
            // upload form, it's removed FIRST (via qpdf) into a
            // temporary unlocked copy — everything downstream reads
            // that copy, and it's always deleted before this function
            // returns, whatever happens.
            $readPath = Storage::disk('local')->path($storedPath);
            $unlockedTempPath = null;
            if (! empty($pdfPassword)) {
                $unlock = (new PdfPasswordRemovalService())->removePassword($readPath, $pdfPassword);
                if ($unlock['ok']) {
                    $readPath = $unlock['path'];
                    $unlockedTempPath = $unlock['path'];
                } elseif (! empty($savedPasswordCandidates)) {
                    // FIXED 16 Sep 2026 — per Chris: the password typed
                    // on the form didn't open this file. Before giving
                    // up, also try whatever password(s) are already
                    // saved against this bank account — the same
                    // fallback used when the field is left blank —
                    // since a typed password that fails is often a typo
                    // or a browser autofilling the wrong saved
                    // credential into the box, not proof the file itself
                    // can't be unlocked.
                    $autoUnlock = (new PdfPasswordRemovalService())->tryCandidatePasswords($readPath, $savedPasswordCandidates);
                    if ($autoUnlock['ok']) {
                        $readPath = $autoUnlock['path'];
                        $unlockedTempPath = $autoUnlock['path'];
                    } else {
                        DB::table('cbe_ai_statement_documents')->where('document_id', $documentId)->update([
                            'status' => 'PARSE_FAILED', 'parse_error_note' => $unlock['message'], 'updated_at' => now(),
                        ]);
                        CbeAccountingService::logAiAudit($nodeId, $documentId, null, 'PARSE_FAILED', $agent->agent_id, $unlock['message']);
                        $failedCount++;
                        continue;
                    }
                } else {
                    DB::table('cbe_ai_statement_documents')->where('document_id', $documentId)->update([
                        'status' => 'PARSE_FAILED', 'parse_error_note' => $unlock['message'], 'updated_at' => now(),
                    ]);
                    CbeAccountingService::logAiAudit($nodeId, $documentId, null, 'PARSE_FAILED', $agent->agent_id, $unlock['message']);
                    $failedCount++;
                    continue;
                }
            } elseif (! empty($savedPasswordCandidates)) {
                // No password typed on the form — quietly try this
                // node's saved bank-account password(s). If one of them
                // opens the file, use the unlocked copy same as above.
                // If none of them works (or this file simply isn't
                // encrypted at all), say nothing and carry on with the
                // original file exactly as before this feature existed
                // — never block an ordinary, unprotected upload.
                $autoUnlock = (new PdfPasswordRemovalService())->tryCandidatePasswords($readPath, $savedPasswordCandidates);
                if ($autoUnlock['ok']) {
                    $readPath = $autoUnlock['path'];
                    $unlockedTempPath = $autoUnlock['path'];
                }
            }

            $result = $extractor->parseFile($readPath);
            $extractionMethod = 'TEXT_PARSE';

            // NEW 16 Sep 2026 — per Chris: a scanned/photographed
            // statement (no text layer) previously always failed here.
            // Falls back to the same AI vision document-reading pipeline
            // already used by Sales Transaction "Read Document" (Company
            // Document Credit, or the agent's own OpenAI/Gemini key) —
            // a genuine digital-text PDF is still always read the free,
            // instant, no-AI way first; this only runs when that finds
            // no text at all.
            $visionResult = null;
            if (! $result['ok'] && ($result['reason'] ?? null) === 'no_text') {
                $visionResult = (new AiVisionBankStatementExtractionService())->extract(
                    $readPath,
                    $file->getMimeType() ?: 'application/pdf',
                    $agent->agent_id,
                    $documentId
                );
                if ($visionResult['ok']) {
                    $result = $visionResult;
                    $extractionMethod = 'AI_VISION';
                }
            }

            if (! $result['ok']) {
                $note = $visionResult['message'] ?? match ($result['reason'] ?? null) {
                    'no_text' => __('cbe_ai.parse_error_no_text'),
                    default => __('cbe_ai.parse_error_unreadable'),
                };
                DB::table('cbe_ai_statement_documents')->where('document_id', $documentId)->update([
                    'status' => 'PARSE_FAILED', 'parse_error_note' => $note, 'extraction_method' => $extractionMethod, 'updated_at' => now(),
                ]);
                CbeAccountingService::logAiAudit($nodeId, $documentId, null, 'PARSE_FAILED', $agent->agent_id, $note);
                (new PdfPasswordRemovalService())->cleanup($unlockedTempPath);
                $failedCount++;
                continue;
            }

            // Phase 12: identify which registered bank account this exact
            // statement belongs to from its own printed account number —
            // never require the uploader to pick it manually first. Falls
            // back to whatever default was chosen on the upload form
            // (optional now) only when the AI can't confidently tell.
            $detected = CbeAccountingService::detectBankAccount($nodeId, $groupLabelId, $result['account_number']);
            $docBankAccountId = $detected['bank_account_id'] ?: $request->input('bank_account_id');

            DB::table('cbe_ai_statement_documents')->where('document_id', $documentId)->update([
                'page_count' => $result['page_count'],
                'detected_bank_name' => $result['bank_name'],
                'detected_account_number' => $result['account_number'],
                'bank_account_id' => $docBankAccountId,
                'branch_detection_note' => $detected['note'],
                'statement_period_from' => $result['period'][0],
                'statement_period_to' => $result['period'][1],
                'detected_opening_balance' => $result['opening_balance'],
                'detected_closing_balance' => $result['closing_balance'],
                'extracted_transaction_count' => count($result['lines']),
                'status' => 'PARSED',
                'extraction_method' => $extractionMethod,
                'updated_at' => now(),
            ]);

            if ($detected['bank_account_id']) {
                CbeAccountingService::logAiAudit($nodeId, $documentId, null, 'BANK_ACCOUNT_DETECTED', $agent->agent_id, __('cbe_ai.audit_note_bank_detected'));
            } elseif ($detected['note']) {
                CbeAccountingService::logAiAudit($nodeId, $documentId, null, 'BANK_ACCOUNT_UNRESOLVED', $agent->agent_id, $detected['note']);
            }

            foreach ($result['lines'] as $line) {
                DB::table('cbe_ai_extracted_transactions')->insert([
                    'extraction_id' => (string) Str::uuid(),
                    'document_id' => $documentId,
                    'cbe_node_id' => $nodeId,
                    'line_no' => $line['line_no'],
                    'page_number' => $line['page'],
                    'transaction_date' => $line['date'],
                    'description' => $line['description'],
                    'reference_no' => $line['reference_no'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'running_balance' => $line['running_balance'],
                    'raw_line_text' => $line['raw_line'],
                    'extraction_confidence' => $line['confidence'],
                    'status' => 'PENDING',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            CbeAccountingService::logAiAudit($nodeId, $documentId, null, 'PARSED', $agent->agent_id, __('cbe_ai.audit_note_parsed', ['count' => count($result['lines']), 'pages' => $result['page_count']]));

            (new TransactionClassificationService())->classifyDocument($documentId, $nodeId);
            (new PartyMatchingService())->computeSuggestedNames($documentId);
            (new StatementContinuityService())->checkContinuity($documentId, $nodeId, $groupLabelId, $docBankAccountId);

            $continuityNote = DB::table('cbe_ai_statement_documents')->where('document_id', $documentId)->value('continuity_note');
            if ($continuityNote) {
                CbeAccountingService::logAiAudit($nodeId, $documentId, null, 'CONTINUITY_FLAGGED', $agent->agent_id, $continuityNote);
            }
            (new PdfPasswordRemovalService())->cleanup($unlockedTempPath);
            $parsedCount++;
        }

        DB::table('cbe_ai_statement_batches')->where('batch_id', $batchId)->update(['status' => 'REVIEWED', 'updated_at' => now()]);

        $message = $failedCount > 0
            ? __('cbe_ai.batch_uploaded_with_failures', ['parsed' => $parsedCount, 'failed' => $failedCount])
            : __('cbe_ai.batch_uploaded_success', ['count' => $parsedCount]);

        return redirect()->route('cbe.ai-accounting.batches.show', $batchId)->with($failedCount > 0 ? 'warning' : 'success', $message);
    }

    public function showBatch(string $batchId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $batch = DB::table('cbe_ai_statement_batches')->where('batch_id', $batchId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $documents = DB::table('cbe_ai_statement_documents')->where('batch_id', $batchId)->orderBy('original_filename')->get();

        return view('cbe.ai-accounting.show-batch', compact('batch', 'documents'));
    }

    public function showDocument(Request $request, string $documentId)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $document = DB::table('cbe_ai_statement_documents')->where('document_id', $documentId)->where('cbe_node_id', $nodeId)->firstOrFail();
        $batch = DB::table('cbe_ai_statement_batches')->where('batch_id', $document->batch_id)->first();

        $lines = DB::table('cbe_ai_extracted_transactions as t')
            ->leftJoin('cbe_suppliers as sup', 'sup.supplier_id', '=', 't.matched_supplier_id')
            ->leftJoin('cbe_customers as cus', 'cus.customer_id', '=', 't.matched_customer_id')
            ->leftJoin('cbe_donors as don', 'don.donor_id', '=', 't.matched_donor_id')
            ->leftJoin('cbe_journal_entries as je', 'je.journal_id', '=', 't.posted_journal_id')
            ->leftJoin('cbe_purchase_bills as bl', 'bl.bill_id', '=', 't.matched_bill_id')
            ->leftJoin('cbe_invoices as inv', 'inv.invoice_id', '=', 't.matched_invoice_id')
            ->leftJoin('cbe_fixed_assets as fa', 'fa.asset_id', '=', 't.matched_asset_id')
            // Phase 17: link the receipt number issued in Phase 14
            // (t.issued_receipt_no, plain text) back to the actual
            // cbe_receipts row so the UI can offer a View/Download PDF
            // link instead of just printing the number as text.
            ->leftJoin('cbe_receipts as rcpt', function ($join) use ($document) {
                $join->on('rcpt.receipt_no', '=', 't.issued_receipt_no')
                    ->where('rcpt.cbe_node_id', '=', $document->cbe_node_id);
            })
            ->where('t.document_id', $documentId)
            ->orderBy('t.line_no')
            ->select(
                't.*',
                DB::raw('COALESCE(sup.supplier_name, cus.customer_name, don.donor_name) as matched_party_name'),
                DB::raw('COALESCE(sup.is_draft, cus.is_draft, don.is_draft) as matched_party_is_draft'),
                'je.journal_no as posted_journal_no',
                // Phase 13: an AI-created Bill/Invoice has no supplier/
                // customer-issued bill_no/invoice_no (there wasn't one to
                // capture) — falls back to our own doc_ref_no, which is
                // always present, so a freshly created document is never
                // shown here as if nothing happened.
                DB::raw('COALESCE(bl.bill_no, bl.doc_ref_no, inv.invoice_no, inv.doc_ref_no, fa.asset_tag, fa.asset_name) as matched_doc_no'),
                'rcpt.receipt_id as issued_receipt_id'
            )
            ->paginate(8)
            ->withQueryString();

        $pendingCount = DB::table('cbe_ai_extracted_transactions')->where('document_id', $documentId)->where('status', 'PENDING')->count();
        $categories = TransactionClassificationService::CATEGORIES;

        // NEW 19 Sep 2026 -- per Chris: "why only category, it suppose
        // to allocate to the right GL code" -- the 10 broad categories
        // above (Bank Charge/Donation/Membership/Adjustment/Other) each
        // used to post to ONE fixed generic GL account regardless of
        // what the line actually is. This is the SAME chart_account_id
        // mapping already used on the manual Finance > Transactions
        // entry screen (cbe_transaction_categories), reused here so the
        // treasurer can point a line at the exact expense/income account
        // (e.g. "Entertainment Expenses > Meeting & Refreshment")
        // instead of the generic default.
        $glCategories = DB::table('cbe_transaction_categories')
            ->where(function ($q) use ($groupLabelId) { $q->whereNull('group_label_id')->orWhere('group_label_id', $groupLabelId); })
            ->where('is_active', true)->whereNotNull('chart_account_id')
            ->orderBy('type')->orderBy('display_order')->get();

        // NEW 19 Sep 2026 -- per Chris: "you should design an AI power
        // capability in capturing the right category and GL code rather
        // than the user have to find and allocate" -- reads each
        // PENDING line's description for common spending wording
        // (meal/electricity/rental/prayer items/event hall/etc.) and
        // pre-selects the matching GL category below, instead of
        // leaving the treasurer to browse the whole Chart of Accounts
        // blind. See TransactionClassificationService::suggestChartCategoryId().
        $glClassifier = new TransactionClassificationService();
        foreach ($lines as $l) {
            if ($l->status === 'PENDING') {
                $l->suggested_chart_category_id = $glClassifier->suggestChartCategoryId($l->description, $glCategories);
            }
        }

        // NEW 17 Sep 2026 — per Chris: "please present the header as
        // per the bank statement layout" (Category / No. of Transaction
        // / Balance, with Opening Balance / Total Debits / Total
        // Credits / Closing Balance rows). These totals must cover ALL
        // extracted lines on this document, not just the current page,
        // so this is a separate unpaginated aggregate query.
        $lineTotals = DB::table('cbe_ai_extracted_transactions')
            ->where('document_id', $documentId)
            ->selectRaw('COUNT(CASE WHEN debit > 0 THEN 1 END) as debit_count')
            ->selectRaw('COUNT(CASE WHEN credit > 0 THEN 1 END) as credit_count')
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debits')
            ->selectRaw('COALESCE(SUM(credit), 0) as total_credits')
            ->first();

        // NEW 17 Sep 2026 — per Chris: "why you didnt detect closing
        // balance? the statement clearly show closing balance". This
        // bank's own statement layout puts the printed closing-balance
        // value nowhere near its own label in the extracted PDF text,
        // so it can't always be found by reading the statement text.
        // As a reliable fallback we calculate it: Opening Balance +
        // Total Credits − Total Debits, from the lines we DID
        // correctly extract. We never hide that it's a calculation —
        // the view marks it with a footnote whenever this fallback is
        // used, instead of silently presenting it as if it were read
        // straight off the statement.
        $closingBalance = $document->detected_closing_balance;
        $closingBalanceCalculated = false;
        if ($closingBalance === null && $document->detected_opening_balance !== null) {
            $closingBalance = (float) $document->detected_opening_balance - (float) $lineTotals->total_debits + (float) $lineTotals->total_credits;
            $closingBalanceCalculated = true;
        }

        // NEW 17 Sep 2026 — per Chris: "YOU MUST apply next and
        // previous". The Prev/Next buttons at the bottom of this screen
        // page through TRANSACTION LINES within this one document (and
        // already work correctly — they're simply disabled when a
        // document has few enough lines to fit on one page, which is
        // correct, not a bug). What was missing is a way to move to the
        // next/previous STATEMENT (document) in the same batch without
        // going back to the batch list every time — added here, using
        // the same chronological order as the batch's own document
        // list (statement period, falling back to filename).
        $siblingDocumentIds = DB::table('cbe_ai_statement_documents')
            ->where('batch_id', $document->batch_id)
            ->orderByRaw('statement_period_from IS NULL, statement_period_from, original_filename')
            ->pluck('document_id');
        $currentPosition = $siblingDocumentIds->search($documentId);
        $prevDocumentId = ($currentPosition !== false && $currentPosition > 0) ? $siblingDocumentIds[$currentPosition - 1] : null;
        $nextDocumentId = ($currentPosition !== false && $currentPosition < $siblingDocumentIds->count() - 1) ? $siblingDocumentIds[$currentPosition + 1] : null;
        $statementPosition = $currentPosition !== false ? $currentPosition + 1 : null;
        $statementTotal = $siblingDocumentIds->count();

        return view('cbe.ai-accounting.show-document', compact(
            'document', 'batch', 'lines', 'pendingCount', 'categories', 'glCategories',
            'lineTotals', 'closingBalance', 'closingBalanceCalculated',
            'prevDocumentId', 'nextDocumentId', 'statementPosition', 'statementTotal'
        ));
    }

    public function downloadDocument(string $documentId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $document = DB::table('cbe_ai_statement_documents')->where('document_id', $documentId)->where('cbe_node_id', $nodeId)->firstOrFail();

        if (! Storage::disk('local')->exists($document->stored_path)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($document->stored_path));
    }

    public function commitDocument(Request $request, string $documentId)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $document = DB::table('cbe_ai_statement_documents')->where('document_id', $documentId)->where('cbe_node_id', $nodeId)->firstOrFail();
        $batch = DB::table('cbe_ai_statement_batches')->where('batch_id', $document->batch_id)->first();

        // Phase 12: commit always needs a confirmed bank account — this
        // is now resolved per document at upload time (auto-detected
        // from the statement itself wherever possible), so this only
        // trips when the AI genuinely couldn't identify or default one.
        if (! $document->bank_account_id) {
            return back()->with('error', __('cbe_ai.commit_needs_bank_account'));
        }

        $includeIds = array_values(array_filter((array) $request->input('include_ids', [])));
        if (empty($includeIds)) {
            return back()->with('error', __('cbe_ai.commit_no_selection_error'));
        }

        $categoryOverrides = (array) $request->input('category', []);
        // NEW 19 Sep 2026 -- per Chris: "why only category, it suppose
        // to allocate to the right GL code" -- optional, per-line
        // override of the GL account a Bank Charge/Donation/Membership/
        // Adjustment/Other line posts to (see postAiClassifiedBankTransaction).
        $chartCategoryOverrides = (array) $request->input('chart_category_id', []);
        $partyNameOverrides = (array) $request->input('party_name', []);
        // NEW 17 Sep 2026 — per Chris: allow the description to be
        // changed before committing (the AI-extracted text is not
        // always exactly how Chris wants it recorded on the books).
        $descriptionOverrides = (array) $request->input('description', []);
        $classifier = new TransactionClassificationService();
        $partyMatcher = new PartyMatchingService();
        $autoPostThreshold = CbeAccountingService::aiAutoPostThreshold($nodeId);

        $lines = DB::table('cbe_ai_extracted_transactions')
            ->where('document_id', $documentId)->where('status', 'PENDING')
            ->whereIn('extraction_id', $includeIds)->get();

        $committed = 0;
        foreach ($lines as $line) {
            if (! $line->transaction_date || ($line->debit === null && $line->credit === null)) {
                continue; // never fabricate a date or amount that wasn't actually extracted
            }

            // Duplicate check, same key as manual/CSV bank transaction
            // entry elsewhere in the Bank Reconciliation Module: bank
            // account + date + amount + reference, only when a reference
            // was actually captured.
            $amount = $line->credit !== null ? (float) $line->credit : -1 * (float) $line->debit;

            // Whatever description is showing on the review screen
            // (the AI-extracted text, or Chris's own edit in the
            // Description field) is what actually gets recorded —
            // used everywhere below instead of the raw $line->description.
            $descriptionOverride = trim((string) ($descriptionOverrides[$line->extraction_id] ?? ''));
            $description = $descriptionOverride !== '' ? $descriptionOverride : $line->description;

            if ($line->reference_no) {
                $dupe = DB::table('cbe_bank_transactions')
                    ->where('bank_account_id', $document->bank_account_id)
                    ->where('transaction_date', $line->transaction_date)
                    ->where('amount', $amount)
                    ->where('reference_no', $line->reference_no)
                    ->exists();
                if ($dupe) {
                    DB::table('cbe_ai_extracted_transactions')->where('extraction_id', $line->extraction_id)->update(['status' => 'DUPLICATE', 'updated_at' => now()]);
                    CbeAccountingService::logAiAudit($nodeId, $documentId, $line->extraction_id, 'DUPLICATE_SKIPPED', $agent->agent_id, __('cbe_ai.audit_note_duplicate', ['ref' => $line->reference_no]));
                    continue;
                }
            }

            // Phase 15 (task #397 follow-up): broader, cross-source check
            // — same bank account, same date, same amount, posted from
            // ANY module (manual Bill/Invoice payment, JV, Bank
            // Adjustment, or a prior AI commit), not just the narrower
            // reference-number check above, which manual AP/AR/JV entries
            // never populate. This alone can't prove it's the SAME
            // transaction (two separate ones could share a date and
            // amount), so it never auto-skips or auto-commits — held for
            // Chris to confirm one way or the other from the review screen.
            $glDupe = CbeAccountingService::findLikelyDuplicateJournal($document->bank_account_id, $nodeId, $groupLabelId, $line->transaction_date, $amount);
            if ($glDupe) {
                $dupNote = __('cbe_ai.possible_duplicate_note', ['journal' => $glDupe->journal_no, 'description' => $glDupe->description ?: __('cbe_ai.default_description')]);
                DB::table('cbe_ai_extracted_transactions')->where('extraction_id', $line->extraction_id)->update(['status' => 'POSSIBLE_DUPLICATE', 'possible_duplicate_note' => $dupNote, 'updated_at' => now()]);
                CbeAccountingService::logAiAudit($nodeId, $documentId, $line->extraction_id, 'POSSIBLE_DUPLICATE_FLAGGED', $agent->agent_id, $dupNote);
                continue;
            }

            // Whatever category is showing (suggested, or Chris's own
            // override in the dropdown) is treated as confirmed the
            // moment the line is committed — this is what reinforces or
            // creates the learned rule for next time (task #60).
            $confirmedCategory = $categoryOverrides[$line->extraction_id] ?? $line->suggested_ai_category;
            if ($confirmedCategory) {
                $classifier->confirmClassification($line->extraction_id, $confirmedCategory, $nodeId);
            }
            $transactionTypeId = $confirmedCategory ? $classifier->lookupTransactionTypeId($confirmedCategory, $groupLabelId) : null;

            // Link (or draft-create) the supplier/customer/donor master
            // this line belongs to, using whatever name is showing on
            // the review screen — never done silently before Chris has
            // seen and can edit it (task #61).
            $partyName = $partyNameOverrides[$line->extraction_id] ?? $line->suggested_party_name;
            $partyId = $partyMatcher->resolveOrCreateDraft($line->extraction_id, $confirmedCategory, $partyName, $nodeId, $agent->agent_id);

            // Phase 13: a SUPPLIER/CUSTOMER line commits into a real Bill/
            // Invoice no matter what (see allocateAiPaymentToBillOrInvoice
            // below) — so when the statement genuinely gave no name to
            // work with, fall back to one reusable "Cash Purchase"/"Cash
            // Sales" placeholder party rather than leaving it blank
            // (cbe_purchase_bills.supplier_id / cbe_invoices.customer_id
            // are both required columns). Phase 14: DONATION gets the
            // same treatment — a nameless bank credit that's clearly a
            // donation still needs a receipt, issued to "Anonymous
            // Donor" rather than skipped.
            if (! $partyId && in_array($confirmedCategory, ['SUPPLIER', 'CUSTOMER', 'DONATION'], true)) {
                $partyId = $partyMatcher->resolveOrCreateGenericParty($confirmedCategory, $nodeId, $agent->agent_id);
                $partyName = __('cbe_ai.generic_party_'.strtolower($confirmedCategory));
            }

            $transactionId = (string) Str::uuid();
            DB::table('cbe_bank_transactions')->insert([
                'transaction_id' => $transactionId,
                'cbe_node_id' => $nodeId,
                'bank_account_id' => $document->bank_account_id,
                'transaction_date' => $line->transaction_date,
                'transaction_type_id' => $transactionTypeId,
                'description' => $description ?: __('cbe_ai.default_description'),
                'reference_no' => $line->reference_no,
                'amount' => $amount,
                'source' => 'AI_EXTRACT',
                'status' => 'UNRECONCILED',
                'created_by' => $agent->agent_id,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            // GL Intelligence + Double-Entry Journal Engine (task #62):
            // reuses the same posting pipeline every other module posts
            // through. Only fires for categories/confidence levels safe
            // to auto-post without a subledger or a second unknowable
            // leg — see postAiClassifiedBankTransaction()'s own comment
            // for the full, disclosed list of what's deferred and why.
            $journalId = null;
            $postingNote = null;
            $matchedBillId = null;
            $matchedInvoiceId = null;
            $matchedAssetId = null;
            $apArDocumentCreated = false;
            $issuedReceiptNo = null;
            $wasOverridden = false;
            $postingConfidence = null;
            if ($confirmedCategory) {
                $wasOverridden = $confirmedCategory !== $line->suggested_ai_category;
                $postingConfidence = $wasOverridden ? 100 : $line->classification_confidence;

                if (in_array($confirmedCategory, ['SUPPLIER', 'CUSTOMER'], true)) {
                    // Phase 13 (task #397 follow-up): always creates the
                    // real original-source Bill/Invoice when no existing
                    // open one matches — see
                    // allocateAiPaymentToBillOrInvoice()'s own comment.
                    // journal_id being empty here now only ever means the
                    // payment itself is pending approval or the period is
                    // closed — the Bill/Invoice was still created either
                    // way, so matchedBillId/matchedInvoiceId are always
                    // set once a party was resolved.
                    $allocation = CbeAccountingService::allocateAiPaymentToBillOrInvoice(
                        $confirmedCategory, $partyId, $nodeId, $groupLabelId, $document->bank_account_id,
                        $line->transaction_date, $amount, $line->reference_no, $agent->agent_id,
                        $description
                    );
                    $journalId = $allocation['journal_id'];
                    $matchedBillId = $allocation['bill_id'];
                    $matchedInvoiceId = $allocation['invoice_id'];
                    $apArDocumentCreated = $allocation['created_new'];

                    if (! $journalId) {
                        $postingNote = match ($allocation['note']) {
                            'pending_approval' => __('cbe_ai.posting_pending_approval'),
                            'period_closed' => __('cbe_ai.posting_not_posted_other'),
                            default => __('cbe_ai.posting_deferred_ap_ar'),
                        };
                    }
                } elseif ($confirmedCategory === 'ASSET') {
                    // Phase 6 (task #64): capitalises a real Fixed Asset
                    // Register entry — only when the classification is
                    // trustworthy, same confidence gate as Phase 4, since
                    // creating a whole new depreciable asset record is a
                    // bigger action than a simple journal entry.
                    if ($postingConfidence >= $autoPostThreshold) {
                        $result = CbeAccountingService::postAiFixedAssetAcquisition(
                            $transactionId, $nodeId, $groupLabelId, $document->bank_account_id,
                            $description ?: __('cbe_ai.default_description'), $partyId,
                            $line->transaction_date, $amount, $agent->agent_id
                        );
                        $journalId = $result['journal_id'];
                        $matchedAssetId = $result['asset_id'];
                        if (! $journalId) {
                            $postingNote = __('cbe_ai.posting_not_posted_other');
                        }
                    } else {
                        $postingNote = __('cbe_ai.posting_low_confidence');
                    }
                } elseif ($confirmedCategory === 'RETURNED_CHEQUE') {
                    // Phase 16 (task #397 follow-up): never a direct GL
                    // posting — drafts the proper AP/AR Debit Note against
                    // the best-guess original payment (by amount, within
                    // 6 months) and leaves it NOT_POSTED for Chris to
                    // verify and post himself from the AP/AR Debit Notes
                    // screen. journal_id intentionally stays null here
                    // even on success — see draftReturnedChequeNote()'s
                    // own comment for why this never auto-posts.
                    $draft = CbeAccountingService::draftReturnedChequeNote(
                        $nodeId, $groupLabelId, $line->transaction_date, $amount, $partyName, $agent->agent_id
                    );
                    if ($draft['ok']) {
                        $matchedBillId = $draft['bill_id'];
                        $matchedInvoiceId = $draft['invoice_id'];
                        $postingNote = $draft['type'] === 'AP'
                            ? __('cbe_ai.returned_cheque_ap_drafted', ['doc' => $draft['doc_ref_no'], 'party' => $draft['party_name']])
                            : __('cbe_ai.returned_cheque_ar_drafted', ['doc' => $draft['doc_ref_no'], 'party' => $draft['party_name']]);
                    } else {
                        $postingNote = $draft['note'];
                    }
                } else {
                    // Phase 4 (task #62): direct GL posting for the
                    // categories that need no subledger.
                    $chartCategoryId = $chartCategoryOverrides[$line->extraction_id] ?? null;
                    $journalId = CbeAccountingService::postAiClassifiedBankTransaction(
                        $transactionId, $nodeId, $groupLabelId, $document->bank_account_id,
                        $confirmedCategory, $postingConfidence, $line->transaction_date,
                        $description ?: __('cbe_ai.default_description'), $amount, $agent->agent_id,
                        $chartCategoryId ?: null
                    );

                    if (! $journalId) {
                        $postingNote = match (true) {
                            in_array($confirmedCategory, ['TRANSFER', 'LOAN'], true) => __('cbe_ai.posting_manual_required'),
                            $postingConfidence < $autoPostThreshold => __('cbe_ai.posting_low_confidence'),
                            default => __('cbe_ai.posting_not_posted_other'),
                        };
                    }
                }

                // Phase 14 (task #397 follow-up): every donation gets its
                // own Official Receipt, independent of whether the GL
                // posting above succeeded — the money genuinely arrived
                // per the bank statement, so the receipt is owed to the
                // donor regardless of the AI's confidence in the GL side.
                // CbeReceiptService::issue() with sourceType='DONATION'
                // deliberately does NOT post its own journal entry (see
                // that service's own comment) — postAiClassifiedBankTransaction
                // above already did, so calling both never double-posts.
                if ($confirmedCategory === 'DONATION' && $amount > 0) {
                    $receipt = CbeReceiptService::issue(
                        $nodeId, 'DONATION', $transactionId,
                        $partyName ?: __('cbe_ai.generic_party_donation'),
                        $description ?: __('cbe_ai.default_description'),
                        $amount, $agent->agent_id
                    );
                    if ($receipt) {
                        $issuedReceiptNo = $receipt->receipt_no;
                        CbeAccountingService::logAiAudit($nodeId, $documentId, $line->extraction_id, 'RECEIPT_ISSUED', $agent->agent_id, __('cbe_ai.audit_note_receipt_issued', ['receipt' => $issuedReceiptNo]));
                    }
                }
            }

            DB::table('cbe_ai_extracted_transactions')->where('extraction_id', $line->extraction_id)->update([
                'status' => 'COMMITTED', 'committed_bank_transaction_id' => $transactionId,
                'description' => $description,
                'posted_journal_id' => $journalId, 'posting_note' => $postingNote,
                'matched_bill_id' => $matchedBillId, 'matched_invoice_id' => $matchedInvoiceId,
                'matched_asset_id' => $matchedAssetId, 'ap_ar_document_created' => $apArDocumentCreated,
                'issued_receipt_no' => $issuedReceiptNo,
                'updated_at' => now(),
            ]);

            // Phase 9 (task #67): one consolidated, honest "why" entry per
            // committed line — never fabricates a category, party, or
            // posting result that didn't actually happen; anything not
            // confidently resolved is written as "Unverified", not guessed.
            $categoryNote = $confirmedCategory
                ? __('cbe_ai.audit_note_category', ['category' => __('cbe_ai.category_'.strtolower($confirmedCategory)), 'source' => $wasOverridden ? __('cbe_ai.audit_source_manual') : __('cbe_ai.audit_source_'.strtolower($line->classification_source ?: 'none')), 'confidence' => (int) $postingConfidence])
                : __('cbe_ai.audit_note_category_unverified');
            $partyNote = $confirmedCategory && in_array($confirmedCategory, ['SUPPLIER', 'CUSTOMER', 'DONATION'], true)
                ? ($partyId ? __('cbe_ai.audit_note_party_resolved', ['name' => $partyName ?: '—']) : __('cbe_ai.audit_note_party_unverified'))
                : null;
            $postingAuditNote = $journalId
                ? __('cbe_ai.audit_note_posted')
                : __('cbe_ai.audit_note_not_posted', ['reason' => $postingNote ?: __('cbe_ai.audit_note_no_category')]);
            $createdDocNote = '';
            if ($apArDocumentCreated && $matchedBillId) {
                $docRefNo = DB::table('cbe_purchase_bills')->where('bill_id', $matchedBillId)->value('doc_ref_no');
                $createdDocNote = ' '.__('cbe_ai.audit_note_bill_created', ['bill' => $docRefNo]);
            } elseif ($apArDocumentCreated && $matchedInvoiceId) {
                $docRefNo = DB::table('cbe_invoices')->where('invoice_id', $matchedInvoiceId)->value('doc_ref_no');
                $createdDocNote = ' '.__('cbe_ai.audit_note_invoice_created', ['invoice' => $docRefNo]);
            }
            $auditNote = trim($categoryNote.' '.($partyNote ? $partyNote.' ' : '').$postingAuditNote.$createdDocNote);
            CbeAccountingService::logAiAudit($nodeId, $documentId, $line->extraction_id, 'COMMITTED', $agent->agent_id, $auditNote);

            $committed++;
        }

        $remainingPending = DB::table('cbe_ai_extracted_transactions')->where('document_id', $documentId)->where('status', 'PENDING')->count();
        if ($remainingPending === 0) {
            DB::table('cbe_ai_statement_documents')->where('document_id', $documentId)->update(['status' => 'CONFIRMED', 'updated_at' => now()]);
        }

        return redirect()->route('cbe.ai-accounting.documents.show', $documentId)->with('success', __('cbe_ai.commit_success', ['count' => $committed]));
    }

    public function rejectLine(string $extractionId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        // Phase 15: a POSSIBLE_DUPLICATE line can also be rejected —
        // that's how Chris confirms "yes, this really is the same
        // transaction, discard it" — same Reject button, no new UI.
        $line = DB::table('cbe_ai_extracted_transactions')->where('extraction_id', $extractionId)->where('cbe_node_id', $nodeId)->whereIn('status', ['PENDING', 'POSSIBLE_DUPLICATE'])->firstOrFail();

        DB::table('cbe_ai_extracted_transactions')->where('extraction_id', $extractionId)->update(['status' => 'REJECTED', 'updated_at' => now()]);
        CbeAccountingService::logAiAudit($nodeId, $line->document_id, $extractionId, 'REJECTED', $agent->agent_id, __('cbe_ai.audit_note_rejected'));

        return redirect()->route('cbe.ai-accounting.documents.show', $line->document_id)->with('success', __('cbe_ai.line_rejected_success'));
    }

    // NEW 9 Sep 2026 (Task #397 follow-up, Phase 15) — the other half of
    // resolving a POSSIBLE_DUPLICATE: Chris confirming "no, this is a
    // genuinely separate transaction, go ahead." Simply demotes the line
    // back to PENDING — it re-enters the normal review/commit flow
    // exactly as if it had never been flagged, no separate commit path
    // to maintain.
    public function confirmNotDuplicate(string $extractionId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $line = DB::table('cbe_ai_extracted_transactions')->where('extraction_id', $extractionId)->where('cbe_node_id', $nodeId)->where('status', 'POSSIBLE_DUPLICATE')->firstOrFail();

        DB::table('cbe_ai_extracted_transactions')->where('extraction_id', $extractionId)->update(['status' => 'PENDING', 'possible_duplicate_note' => null, 'updated_at' => now()]);
        CbeAccountingService::logAiAudit($nodeId, $line->document_id, $extractionId, 'NOT_DUPLICATE_CONFIRMED', $agent->agent_id, __('cbe_ai.audit_note_not_duplicate'));

        return redirect()->route('cbe.ai-accounting.documents.show', $line->document_id)->with('success', __('cbe_ai.not_duplicate_confirmed_success'));
    }

    // ---------- AI Learning Engine (task #66, Phase 8) — visibility and
    // control over the knowledge base Phase 2 already builds
    // automatically, plus the admin-configurable auto-post confidence
    // threshold that replaces the hardcoded "60" used through Phases
    // 4-6. ----------

    public function rules(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        // FIX 13 Sep 2026 (Task #418 follow-up) — same null-node crash
        // class as CbeAccountingController: aiAutoPostThreshold() below
        // requires a non-null string $nodeId.
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }
        $threshold = CbeAccountingService::aiAutoPostThreshold($nodeId);

        $rules = DB::table('cbe_ai_classification_rules')
            ->where('cbe_node_id', $nodeId)
            ->orderByDesc('is_active')
            ->orderByDesc('times_confirmed')
            ->paginate(12)
            ->withQueryString();

        return view('cbe.ai-accounting.rules', compact('threshold', 'rules'));
    }

    public function updateThreshold(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $request->validate(['auto_post_confidence_threshold' => ['required', 'integer', 'min:0', 'max:100']]);
        $oldThreshold = CbeAccountingService::aiAutoPostThreshold($nodeId);

        $existing = DB::table('cbe_ai_automation_settings')->where('cbe_node_id', $nodeId)->first();
        if ($existing) {
            DB::table('cbe_ai_automation_settings')->where('setting_id', $existing->setting_id)->update([
                'auto_post_confidence_threshold' => $request->input('auto_post_confidence_threshold'),
                'updated_by' => $agent->agent_id, 'updated_at' => now(),
            ]);
        } else {
            DB::table('cbe_ai_automation_settings')->insert([
                'setting_id' => (string) Str::uuid(),
                'cbe_node_id' => $nodeId,
                'auto_post_confidence_threshold' => $request->input('auto_post_confidence_threshold'),
                'updated_by' => $agent->agent_id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        CbeAccountingService::logAiAudit($nodeId, null, null, 'THRESHOLD_CHANGED', $agent->agent_id, __('cbe_ai.audit_note_threshold_changed', ['old' => $oldThreshold, 'new' => $request->input('auto_post_confidence_threshold')]));

        return redirect()->route('cbe.ai-accounting.rules')->with('success', __('cbe_ai.threshold_saved_success'));
    }

    public function toggleRule(string $ruleId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $rule = DB::table('cbe_ai_classification_rules')->where('rule_id', $ruleId)->where('cbe_node_id', $nodeId)->firstOrFail();

        DB::table('cbe_ai_classification_rules')->where('rule_id', $ruleId)->update(['is_active' => ! $rule->is_active, 'updated_at' => now()]);
        CbeAccountingService::logAiAudit($nodeId, null, null, $rule->is_active ? 'RULE_DEACTIVATED' : 'RULE_REACTIVATED', $agent->agent_id, __('cbe_ai.audit_note_rule_toggled', ['pattern' => $rule->match_pattern, 'category' => __('cbe_ai.category_'.strtolower($rule->ai_category))]));

        return redirect()->route('cbe.ai-accounting.rules')->with('success', $rule->is_active ? __('cbe_ai.rule_deactivated_success') : __('cbe_ai.rule_reactivated_success'));
    }

    // ---------- Exception Management Centre (task #66, Phase 8) — every
    // AI-related item across every batch that needs Chris's attention,
    // grouped by exception TYPE (his own explicit request: "put into
    // temporary ledger and I can go through row by row" rather than a
    // one-by-one prompt) instead of requiring him to open each batch and
    // document one at a time to discover what needs review. ----------

    public function exceptions(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        // FIX 13 Sep 2026 (Task #418 follow-up) — same null-node crash.
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }
        $threshold = CbeAccountingService::aiAutoPostThreshold($nodeId);
        $type = $request->input('type', 'unclassified');

        $baseExtracted = fn () => DB::table('cbe_ai_extracted_transactions as t')
            ->join('cbe_ai_statement_documents as d', 'd.document_id', '=', 't.document_id')
            ->where('t.cbe_node_id', $nodeId);

        $counts = [
            'unclassified' => $baseExtracted()->where('t.status', 'PENDING')->whereNull('t.suggested_ai_category')->count(),
            'low_confidence' => $baseExtracted()->where('t.status', 'PENDING')->whereNotNull('t.suggested_ai_category')->where('t.classification_confidence', '<', $threshold)->count(),
            'restricted_fund' => $baseExtracted()->where('t.status', 'PENDING')->whereNotNull('t.flag_note')->count(),
            'draft_masters' => DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->where('is_draft', true)->count()
                + DB::table('cbe_customers')->where('cbe_node_id', $nodeId)->where('is_draft', true)->count()
                + DB::table('cbe_donors')->where('cbe_node_id', $nodeId)->where('is_draft', true)->count(),
            'continuity' => DB::table('cbe_ai_statement_documents')->where('cbe_node_id', $nodeId)->whereNotNull('continuity_note')->count(),
            'unmatched_ap_ar' => $baseExtracted()->where('t.status', 'COMMITTED')->where('t.ap_ar_document_created', true)->count(),
            'possible_duplicate' => $baseExtracted()->where('t.status', 'POSSIBLE_DUPLICATE')->count(),
        ];

        $draftMasters = null;
        $items = null;

        if ($type === 'draft_masters') {
            $suppliers = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->where('is_draft', true)
                ->select('supplier_id as id', 'supplier_name as name', DB::raw("'SUPPLIER' as party_type"), 'created_at');
            $customers = DB::table('cbe_customers')->where('cbe_node_id', $nodeId)->where('is_draft', true)
                ->select('customer_id as id', 'customer_name as name', DB::raw("'CUSTOMER' as party_type"), 'created_at');
            $donors = DB::table('cbe_donors')->where('cbe_node_id', $nodeId)->where('is_draft', true)
                ->select('donor_id as id', 'donor_name as name', DB::raw("'DONOR' as party_type"), 'created_at');
            $draftMasters = $suppliers->unionAll($customers)->unionAll($donors)->orderByDesc('created_at')->paginate(12)->withQueryString();
        } elseif ($type === 'continuity') {
            $items = DB::table('cbe_ai_statement_documents as d')
                ->join('cbe_ai_statement_batches as b', 'b.batch_id', '=', 'd.batch_id')
                ->where('d.cbe_node_id', $nodeId)->whereNotNull('d.continuity_note')
                ->select('d.document_id', 'd.original_filename', 'd.continuity_note', 'd.statement_period_from', 'd.statement_period_to', 'b.label as batch_label')
                ->orderByDesc('d.created_at')->paginate(12)->withQueryString();
        } else {
            $query = $baseExtracted()->select('t.extraction_id', 't.document_id', 't.transaction_date', 't.description', 't.debit', 't.credit', 't.suggested_ai_category', 't.classification_confidence', 't.flag_note', 't.posting_note', 't.possible_duplicate_note', 'd.original_filename');
            $items = match ($type) {
                'low_confidence' => $query->where('t.status', 'PENDING')->whereNotNull('t.suggested_ai_category')->where('t.classification_confidence', '<', $threshold),
                'restricted_fund' => $query->where('t.status', 'PENDING')->whereNotNull('t.flag_note'),
                'unmatched_ap_ar' => $query->where('t.status', 'COMMITTED')->where('t.ap_ar_document_created', true),
                'possible_duplicate' => $query->where('t.status', 'POSSIBLE_DUPLICATE'),
                default => $query->where('t.status', 'PENDING')->whereNull('t.suggested_ai_category'),
            };
            $items = $items->orderBy('t.transaction_date')->paginate(12)->withQueryString();
        }

        return view('cbe.ai-accounting.exceptions', compact('counts', 'type', 'items', 'draftMasters'));
    }

    // ---------- Complete Audit Trail (task #67, Phase 9) — append-only,
    // read-only log tying source PDF -> extraction -> classification
    // reasoning -> master record -> journal -> posting -> amendment
    // together for every AI-touched line, same pattern as the existing
    // Purchasing / Fixed Asset / Bank Reconciliation audit trails. ----------

    public function auditLog(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $eventType = $request->input('event_type');

        $query = DB::table('cbe_ai_audit_log as l')
            ->leftJoin('agents as a', 'a.agent_id', '=', 'l.actor_id')
            ->leftJoin('cbe_ai_statement_documents as d', 'd.document_id', '=', 'l.document_id')
            ->where('l.cbe_node_id', $nodeId);

        if ($eventType) {
            $query->where('l.event_type', $eventType);
        }

        $logs = $query->select('l.*', 'a.full_name as actor_name', 'd.original_filename')
            ->orderByDesc('l.created_at')
            ->paginate(12, ['*'], 'auditPage')
            ->withQueryString();

        return view('cbe.ai-accounting.audit-log', compact('logs', 'eventType'));
    }

    // NEW 16 Sep 2026 — per Chris: "is there anyway you can store this
    // password in bank master and you retrieve the pre set up password
    // and automatically can open the pdf file?" Returns the decrypted
    // statement password(s) already saved on the Bank Account Number
    // master file (FinanceController::storeBankAccount/updateBankAccount),
    // for storeBatch() to try automatically. If a specific bank account
    // was chosen on the upload form, only that account's password is
    // tried; otherwise every registered account's saved password is
    // tried (there are rarely more than a handful per node). A password
    // that fails to decrypt (corrupted APP_KEY history, unlikely) is
    // silently skipped rather than blowing up the whole upload.
    private function decryptStatementPasswords(string $nodeId, ?string $onlyBankAccountId): array
    {
        $query = DB::table('cbe_bank_accounts')
            ->where('cbe_node_id', $nodeId)
            ->whereNotNull('statement_password_encrypted');

        if (! empty($onlyBankAccountId)) {
            $query->where('bank_account_id', $onlyBankAccountId);
        }

        $passwords = [];
        foreach ($query->pluck('statement_password_encrypted') as $encrypted) {
            try {
                $passwords[] = decrypt($encrypted);
            } catch (\Throwable $e) {
                // skip an unreadable saved password rather than fail the upload
            }
        }

        return array_values(array_unique($passwords));
    }
}
