<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

// NEW 16 Sep 2026 — per Chris: a bank statement saved as a scanned
// photo (e.g. via CamScanner, or any phone photo of a printed
// statement) has no text layer, so PdfStatementExtractionService's
// rule-based reader (deliberately no-OCR, per the original AI
// Accounting Module scope) always returned 'no_text' and the upload
// failed outright — even though this app already has a working AI
// document-reading pipeline (ClaudeDocumentExtractionService and its
// OpenAI/Gemini BYOK siblings), already used by the Sales Transaction
// "Read Document" button.
//
// This service is the bridge: when PdfStatementExtractionService can't
// find any text, AiAccountingController falls back to calling THIS,
// which reuses those exact same three extraction services (same
// Document Credit wallet / same BYOK-preference-then-fallback logic as
// SalesTransactionController::extractDocument()) instead of writing a
// brand new AI integration. A digital-text PDF is still always read the
// free, instant, no-AI way first — this only ever runs for a statement
// that genuinely has no text to read directly, so it only spends a
// Document Credit (or the agent's own key) when it actually has to.
//
// Returns the EXACT SAME SHAPE as PdfStatementExtractionService::parseFile()
// (ok/page_count/bank_name/account_number/period/opening_balance/
// closing_balance/lines) so the rest of AiAccountingController's
// pipeline (classification, party matching, continuity check, DB
// inserts) runs completely unchanged regardless of which extractor
// produced the result.
class AiVisionBankStatementExtractionService
{
    // Field map handed to the existing Claude/OpenAI/Gemini document
    // extractors — those services only understand a flat field_key =>
    // value contract, so every transaction line is asked for as ONE
    // field containing a JSON-encoded array STRING, decoded back out
    // below, rather than changing that shared contract for this one
    // caller.
    private function fields(): array
    {
        return [
            'bank_name' => 'The name of the bank that issued this statement (e.g. Maybank, CIMB, AmBank, Public Bank).',
            'account_number' => 'The bank account number printed on the statement, digits only (strip spaces/dashes).',
            'period_from' => 'The statement period START date, as DD-MM-YYYY.',
            'period_to' => 'The statement period END date, as DD-MM-YYYY.',
            'opening_balance' => 'The opening / balance brought forward amount, as a plain number with no currency symbol or thousands separator (e.g. 34126.11).',
            'closing_balance' => 'The closing / ending balance amount, as a plain number with no currency symbol or thousands separator.',
            'transactions_json' => 'EVERY transaction line shown in the statement (every page), as a single JSON-encoded array STRING (this field\'s value must literally be a JSON array wrapped in a string, e.g. "[{\"date\":\"12-01-2024\",\"description\":\"CHEQUE PROCESSING FEE\",\"reference_no\":null,\"debit\":0.50,\"credit\":null}]"). Each item: "date" (DD-MM-YYYY), "description" (the transaction description text as printed), "reference_no" (cheque/reference number if shown, else null), "debit" (plain number if this line reduced the balance, else null), "credit" (plain number if this line increased the balance, else null). Never invent a transaction that is not actually printed on the statement.',
        ];
    }

    /**
     * @return array{ok:bool, reason?:string, message?:string, page_count?:int|null, bank_name?:?string, account_number?:?string, period?:array, opening_balance?:?float, closing_balance?:?float, lines?:array}
     */
    public function extract(string $filePath, string $mimeType, string $agentId, ?string $referenceDocumentId = null): array
    {
        $agent = DB::table('agents')->where('agent_id', $agentId)->first();
        $preference = $agent->document_extraction_provider ?? 'COMPANY_CREDIT';
        $byokProvider = null;
        $apiKey = null;
        $vault = new HubVaultService();

        if (in_array($preference, ['OPENAI', 'GEMINI'], true)) {
            $providerKey = strtolower($preference);
            $row = DB::table('agent_integrations')->where('agent_id', $agentId)->where('category', 'ai_services')->where('provider', $providerKey)->first();
            if ($row && $row->status === 'CONNECTED' && $vault->isUnlocked($agentId)) {
                try {
                    $apiKey = $vault->decryptSecret($agentId, $row->api_key_encrypted);
                    $byokProvider = $preference;
                } catch (\Throwable $e) {
                    $byokProvider = null; // stored key couldn't be read back — fall back to Company Credit, same as SalesTransactionController
                }
            }
            // Falls back to Company Credit below, same as SalesTransactionController — never blocks the read outright over a BYOK hiccup.
        }

        $credit = new DocumentCreditService();
        if (! $byokProvider && ! $credit->hasSufficientBalance($agentId)) {
            return [
                'ok' => false,
                'reason' => 'insufficient_credit',
                'message' => 'This is a scanned/image statement (no readable text), so it needs AI to read it — but your Document Credit balance (RM '.number_format($credit->balance($agentId), 2).') is too low (costs RM '.number_format($credit->deductionAmount(), 2).' per statement). Top up Document Credit, or connect your own OpenAI/Gemini key in My Integrations, or upload a real digital PDF statement instead.',
            ];
        }

        $fields = $this->fields();

        if ($byokProvider === 'OPENAI') {
            $result = (new OpenAiDocumentExtractionService())->extract($filePath, $mimeType, $fields, $apiKey);
        } elseif ($byokProvider === 'GEMINI') {
            $result = (new GeminiDocumentExtractionService())->extract($filePath, $mimeType, $fields, $apiKey);
        } else {
            $result = (new ClaudeDocumentExtractionService())->extract($filePath, $mimeType, $fields);
        }

        if (($result['status'] ?? null) !== 'OK') {
            return ['ok' => false, 'reason' => 'ai_vision_failed', 'message' => $result['message'] ?? 'The AI document reading service could not read this scanned statement.'];
        }

        $values = $result['values'];
        $lines = $this->parseTransactionLines($values['transactions_json'] ?? null);

        if (! $byokProvider) {
            $credit->deduct($agentId, $referenceDocumentId, 'Bank statement AI vision read (scanned/image file, no text layer)');
        }

        return [
            'ok' => true,
            'page_count' => null,
            'bank_name' => $this->nullIfEmpty($values['bank_name'] ?? null),
            'account_number' => $this->digitsOnly($values['account_number'] ?? null),
            'period' => [$this->toIsoDate($values['period_from'] ?? null), $this->toIsoDate($values['period_to'] ?? null)],
            'opening_balance' => $this->toFloat($values['opening_balance'] ?? null),
            'closing_balance' => $this->toFloat($values['closing_balance'] ?? null),
            'lines' => $lines,
        ];
    }

    private function parseTransactionLines(mixed $raw): array
    {
        if (is_array($raw)) {
            $decoded = $raw; // some providers may already return a real array despite being asked for a string
        } else {
            $decoded = json_decode((string) $raw, true);
        }

        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        $lineNo = 0;
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $date = $this->toIsoDate($row['date'] ?? null);
            if (! $date) {
                continue; // never fabricate a date that wasn't actually extracted
            }
            $debit = $this->toFloat($row['debit'] ?? null);
            $credit = $this->toFloat($row['credit'] ?? null);
            if ($debit === null && $credit === null) {
                continue;
            }
            $lineNo++;
            $out[] = [
                'line_no' => $lineNo,
                'page' => null,
                'date' => $date,
                'description' => $this->nullIfEmpty($row['description'] ?? null),
                'reference_no' => $this->nullIfEmpty($row['reference_no'] ?? null),
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => null,
                'raw_line' => json_encode($row),
                'confidence' => 70, // AI-vision-read, on a scan — reviewed by a human before commit either way, same as a low-confidence rule-based line
            ];
        }

        return $out;
    }

    private function nullIfEmpty(?string $v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;

        return ($v === '' || $v === null || strtoupper((string) $v) === 'NULL') ? null : $v;
    }

    private function digitsOnly(?string $v): ?string
    {
        $v = $this->nullIfEmpty($v);

        return $v !== null ? preg_replace('/[^0-9]/', '', $v) : null;
    }

    private function toFloat(mixed $v): ?float
    {
        if ($v === null || $v === '' || (is_string($v) && strtoupper($v) === 'NULL')) {
            return null;
        }

        return is_numeric($v) ? (float) $v : null;
    }

    private function toIsoDate(?string $v): ?string
    {
        $v = $this->nullIfEmpty($v);
        if (! $v) {
            return null;
        }
        // Prompt asks for DD-MM-YYYY, but tolerate a slash variant too.
        foreach (['d-m-Y', 'd/m/Y'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $v);
            if ($d instanceof \DateTime) {
                $errors = \DateTime::getLastErrors();
                if (! $errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                    return $d->format('Y-m-d');
                }
            }
        }

        return null;
    }
}
