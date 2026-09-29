<?php

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// NEW 9 Aug 2026 — per Chris's spec, run automatically once a vendor's
// registration documents pass initial (upload) validation. Built
// deliberately HONEST: GeneralLink has no paid sanctions/bankruptcy/
// court-record/credit-bureau data source connected. This service only
// ever reports what it can genuinely check:
//   1. Document identity cross-check — does the company name ON the
//      uploaded document actually match what the vendor typed? Uses the
//      same Claude document-reading capability already used elsewhere in
//      GeneralLink (ClaudeDocumentExtractionService) — a real read of the
//      real file, not a guess.
//   2. Sanctions screening — the free, public UN Security Council
//      Consolidated Sanctions List (no subscription, no login needed).
//   3. AI negative-news scan — Claude's native web search tool, used only
//      when it's actually available on the connected API key; if it
//      isn't, this is reported as unavailable, never faked.
// Anything that can't be genuinely checked is recorded as
// "UNAVAILABLE"/"Manual Review Required" — never a made-up percentage.
//
// EXTENDED 12 Aug 2026 — per Chris: "what type of dilligent check status
// (legal case, scammer, social media complaints news, illegal offences,
// court case, director and shareholder bankruptcy check etc)? risk
// score and detail of your report and comments?" Four more categories
// added (legal/court case, scammer/fraud, social media & news
// complaints, illegal offences/regulatory action) — all still AI web
// search best-effort, same honesty rule as above, run together in ONE
// combined Claude call (runExtendedRiskScan) to keep cost/latency down
// instead of four separate API calls. Director & Shareholder Bankruptcy
// is stored but ALWAYS reported UNAVAILABLE — see runBankruptcyCheck()
// — GeneralLink has no connection to any bankruptcy/insolvency registry,
// and faking a "clear" here would be actively dangerous.
//
// REDESIGNED 13 Aug 2026 — per Chris: "when your recommendation is 100%
// you are cheating because the bankruptcy report is not upload and the
// ctos report upload how you can score 100% that is cheating further
// more your individual assessment carry 0% that is very obvious
// cheating, you should design the scoring mark per due diligent check
// and the final score is the total of each 9 diligent divide by 9." The
// OLD model was demerit-only: points were only ever SUBTRACTED when a
// concern was actually found, so an UNCHECKED category (bankruptcy/CTOS
// not yet uploaded) scored identically to a genuinely CLEAR one — a
// vendor with 2 of 9 checks never even run could show a perfect 100%.
// The NEW model gives every one of the 9 categories a real mark: 100 if
// that category is genuinely CLEAR (actually checked, nothing found),
// 0 if it is UNAVAILABLE (not yet checked/uploaded) OR a concern was
// found. Final score = sum of all 9 marks / 9 — an incomplete
// assessment can never read as a perfect one again. See
// scoreBreakdown() for the full working and the 5-tier colour-coded
// recommendation (green Approve down to dark-red Critical) that Chris
// asked for.
class VendorDueDiligenceService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-haiku-4-5-20251001';
    private const ANTHROPIC_VERSION = '2023-06-01';
    private const UN_SANCTIONS_LIST_URL = 'https://scsanctions.un.org/resources/xml/en/consolidated.xml';

    public function __construct(private ClaudeDocumentExtractionService $extractor) {}

    public function runAssessment(Vendor $vendor): array
    {
        $assessmentId = (string) Str::uuid();
        $result = [
            'assessment_id' => $assessmentId,
            'vendor_id' => $vendor->vendor_id,
            'status' => 'COMPLETED',
            'identity_match_score' => null,
            'identity_match_note' => null,
            'sanctions_status' => null,
            'sanctions_note' => null,
            'negative_news_status' => null,
            'negative_news_note' => null,
            'legal_case_status' => null,
            'legal_case_note' => null,
            'scammer_status' => null,
            'scammer_note' => null,
            'social_media_status' => null,
            'social_media_note' => null,
            'illegal_offences_status' => null,
            'illegal_offences_note' => null,
            'bankruptcy_status' => null,
            'bankruptcy_note' => null,
            'ctos_status' => null,
            'ctos_note' => null,
            'risk_score' => null,
            'risk_band' => null,
            'overall_recommendation' => null,
            'error_message' => null,
            'run_at' => now(),
        ];

        try {
            $this->runIdentityCrossCheck($vendor, $result);
            $this->runSanctionsScreening($vendor, $result);
            $this->runNegativeNewsScan($vendor, $result);
            $this->runExtendedRiskScan($vendor, $result);
            $this->runBankruptcyCheck($vendor, $result);
            $this->runCtosCheck($vendor, $result);
            $risk = self::scoreBreakdown((object) $result);
            $result['risk_score'] = $risk['score'];
            $result['risk_band'] = $risk['band'];
            $result['overall_recommendation'] = $risk['recommendation'];
        } catch (\Throwable $e) {
            Log::warning('VendorDueDiligenceService: assessment failed for vendor ' . $vendor->vendor_id . ': ' . $e->getMessage());
            $result['status'] = 'ERROR';
            $result['error_message'] = 'Assessment could not complete — see Laravel log. Treat as Manual Review Required.';
            $result['overall_recommendation'] = 'MANUAL_REVIEW_REQUIRED';
        }

        DB::table('vendor_due_diligence_assessments')->insert([
            'assessment_id' => $assessmentId,
            'vendor_id' => $vendor->vendor_id,
            'status' => $result['status'],
            'identity_match_score' => $result['identity_match_score'],
            'identity_match_note' => $result['identity_match_note'],
            'sanctions_status' => $result['sanctions_status'],
            'sanctions_note' => $result['sanctions_note'],
            'negative_news_status' => $result['negative_news_status'],
            'negative_news_note' => $result['negative_news_note'],
            'legal_case_status' => $result['legal_case_status'],
            'legal_case_note' => $result['legal_case_note'],
            'scammer_status' => $result['scammer_status'],
            'scammer_note' => $result['scammer_note'],
            'social_media_status' => $result['social_media_status'],
            'social_media_note' => $result['social_media_note'],
            'illegal_offences_status' => $result['illegal_offences_status'],
            'illegal_offences_note' => $result['illegal_offences_note'],
            'bankruptcy_status' => $result['bankruptcy_status'],
            'bankruptcy_note' => $result['bankruptcy_note'],
            'ctos_status' => $result['ctos_status'],
            'ctos_note' => $result['ctos_note'],
            'risk_score' => $result['risk_score'],
            'risk_band' => $result['risk_band'],
            'overall_recommendation' => $result['overall_recommendation'],
            'error_message' => $result['error_message'],
            'run_at' => $result['run_at'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $result;
    }

    /** 1. Document identity cross-check — reads the vendor's own uploaded document and compares its stated company name to what they typed. */
    private function runIdentityCrossCheck(Vendor $vendor, array &$result): void
    {
        // CHANGED 9 Aug 2026 — the SSM Document Registration &
        // Verification Module rebuild replaced the old fixed document
        // keys ('constitutional_doc'/'business_reg_cert') with a full,
        // per-entity-type checklist (Form 9/Section 14/Form D/etc.), so
        // this can no longer hardcode which key to look for. Instead: use
        // whichever document is the first MANDATORY checklist item for
        // this vendor's entity type — that's always the core SSM
        // registration/incorporation proof, regardless of entity type —
        // falling back to the earliest-uploaded document if that specific
        // one somehow isn't on file.
        $primaryKey = null;
        foreach (VendorDocumentChecklistService::checklist($vendor->entity_type ?? '') as $item) {
            if ($item['tier'] === VendorDocumentChecklistService::TIER_MANDATORY) {
                $primaryKey = $item['key'];
                break;
            }
        }
        $doc = $primaryKey
            ? DB::table('vendor_documents')->where('vendor_id', $vendor->vendor_id)->where('document_key', $primaryKey)->first()
            : null;
        if (!$doc) {
            $doc = DB::table('vendor_documents')->where('vendor_id', $vendor->vendor_id)->orderBy('created_at')->first();
        }

        if (!$doc || !Storage::disk('local')->exists($doc->file_path)) {
            $result['identity_match_note'] = 'No SSM registration document was available to cross-check — Manual Review Required.';
            return;
        }

        $ext = strtolower(pathinfo($doc->file_path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => null,
        };
        if (!$mime) {
            $result['identity_match_note'] = 'Uploaded document format could not be read automatically — Manual Review Required.';
            return;
        }

        $extraction = $this->extractor->extract(Storage::disk('local')->path($doc->file_path), $mime, [
            'company_name' => 'The exact registered company or business name as printed on this SSM document',
            'registration_number' => 'The company/business registration number printed on this document',
        ]);

        if ($extraction['status'] !== 'OK') {
            $result['identity_match_note'] = 'Document could not be read automatically (' . ($extraction['message'] ?? 'unknown error') . ') — Manual Review Required.';
            return;
        }

        $docName = trim((string) ($extraction['values']['company_name'] ?? ''));
        if ($docName === '' || $docName === null) {
            $result['identity_match_note'] = 'Could not find a company name on the document — Manual Review Required.';
            return;
        }

        // Real text-similarity score (0-100), not an invented number.
        similar_text(strtoupper($vendor->vendor_name), strtoupper($docName), $percent);
        $score = (int) round($percent);
        $result['identity_match_score'] = $score;
        $result['identity_match_note'] = $score >= 80
            ? "Document states \"{$docName}\" — matches the registered name closely."
            : "Document states \"{$docName}\" — this does not closely match the typed name \"{$vendor->vendor_name}\". Please check manually.";
    }

    /** 2. Free public UN Consolidated Sanctions List name screening (cached 24h to avoid re-downloading on every registration). */
    private function runSanctionsScreening(Vendor $vendor, array &$result): void
    {
        $names = array_filter(array_unique(array_map('strtoupper', array_filter([
            $vendor->vendor_name,
            $vendor->pic_name,
            $vendor->contact2_name,
            $vendor->contact3_name,
        ]))));

        $listNames = \Illuminate\Support\Facades\Cache::remember('un_sanctions_list_names', now()->addDay(), function () {
            try {
                $response = Http::timeout(20)->get(self::UN_SANCTIONS_LIST_URL);
                if ($response->failed()) {
                    return null;
                }
                $xml = @simplexml_load_string($response->body());
                if ($xml === false) {
                    return null;
                }
                $names = [];
                foreach (($xml->INDIVIDUALS->INDIVIDUAL ?? []) as $ind) {
                    $parts = array_filter([(string) $ind->FIRST_NAME, (string) $ind->SECOND_NAME, (string) $ind->THIRD_NAME, (string) $ind->FOURTH_NAME]);
                    if ($parts) {
                        $names[] = strtoupper(trim(implode(' ', $parts)));
                    }
                }
                foreach (($xml->ENTITIES->ENTITY ?? []) as $ent) {
                    if ((string) $ent->FIRST_NAME !== '') {
                        $names[] = strtoupper(trim((string) $ent->FIRST_NAME));
                    }
                }
                return $names;
            } catch (\Throwable $e) {
                Log::warning('VendorDueDiligenceService: UN sanctions list fetch failed: ' . $e->getMessage());
                return null;
            }
        });

        if ($listNames === null) {
            $result['sanctions_status'] = 'UNAVAILABLE';
            $result['sanctions_note'] = 'The public UN sanctions list could not be reached — Manual Review Required.';
            return;
        }

        $matches = [];
        foreach ($names as $name) {
            foreach ($listNames as $listed) {
                // Deliberately conservative: only flag a close/exact name
                // match, never a loose partial-word match, to avoid
                // scaring Admin with false positives on common words.
                similar_text($name, $listed, $percent);
                if ($percent >= 90) {
                    $matches[] = "{$name} ~ {$listed}";
                }
            }
        }

        if ($matches) {
            $result['sanctions_status'] = 'POTENTIAL_MATCH';
            $result['sanctions_note'] = 'Possible name match on the UN Consolidated Sanctions List: ' . implode('; ', array_slice($matches, 0, 3)) . '. This is a NAME match only, not confirmed identity — verify manually before acting on it.';
        } else {
            $result['sanctions_status'] = 'CLEAR';
            $result['sanctions_note'] = 'No name match found on the UN Consolidated Sanctions List.';
        }
    }

    /** 3. AI negative-news scan — only claims a real result if Claude's web search tool actually returned search results; otherwise honestly reports unavailable. */
    private function runNegativeNewsScan(Vendor $vendor, array &$result): void
    {
        $apiKey = config('services.anthropic.key');
        if (empty($apiKey)) {
            $result['negative_news_status'] = 'UNAVAILABLE';
            $result['negative_news_note'] = 'AI service is not configured — Manual Review Required.';
            return;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'anthropic-beta' => 'web-search-2025-03-05',
                'content-type' => 'application/json',
            ])->timeout(45)->post(self::API_URL, [
                'model' => self::MODEL,
                'max_tokens' => 700,
                'tools' => [['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 3]],
                'messages' => [[
                    'role' => 'user',
                    'content' => "Search for major negative news about the Malaysian business \"{$vendor->vendor_name}\" — things like fraud, court judgments, insolvency/winding-up, regulatory action, or serious public complaints. Only report something if your search actually found a real, specific, named source. If your search finds nothing concerning, or you are not confident real search results back up a claim, say exactly: NO_VERIFIABLE_CONCERNS. Otherwise, summarize the concern in 2 sentences and name the source. Do not guess or speculate — only state what your search results actually show.",
                ]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('VendorDueDiligenceService: negative news scan request failed: ' . $e->getMessage());
            $result['negative_news_status'] = 'UNAVAILABLE';
            $result['negative_news_note'] = 'Could not reach the AI news scan service — Manual Review Required.';
            return;
        }

        if ($response->failed()) {
            // A 4xx here very often means web search isn't enabled on this
            // API key/plan — honestly say so rather than pretending a scan
            // ran.
            Log::warning('VendorDueDiligenceService: negative news scan failed (' . $response->status() . '): ' . $response->body());
            $result['negative_news_status'] = 'UNAVAILABLE';
            $result['negative_news_note'] = 'AI negative-news scan is not available on this account\'s API plan right now — Manual Review Required.';
            return;
        }

        $json = $response->json();
        $usedSearch = false;
        foreach (($json['content'] ?? []) as $block) {
            if (($block['type'] ?? null) === 'server_tool_use' || ($block['type'] ?? null) === 'web_search_tool_result') {
                $usedSearch = true;
            }
        }
        $text = '';
        foreach (($json['content'] ?? []) as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'];
            }
        }
        $text = trim($text);

        if (!$usedSearch || $text === '') {
            $result['negative_news_status'] = 'UNAVAILABLE';
            $result['negative_news_note'] = 'AI scan did not return verifiable search results — Manual Review Required.';
            return;
        }

        if (str_contains($text, 'NO_VERIFIABLE_CONCERNS')) {
            $result['negative_news_status'] = 'CLEAR';
            $result['negative_news_note'] = 'No verifiable negative news found in an AI web search.';
        } else {
            $result['negative_news_status'] = 'CONCERNS_FOUND';
            $result['negative_news_note'] = $text;
        }
    }

    /** 4-in-1 AI web search — legal/court case, scammer/fraud, social media & news complaints, illegal offences/regulatory action. One combined call (not four) to keep cost/latency down; each category is honestly UNAVAILABLE if search wasn't actually used or its section couldn't be parsed. */
    private function runExtendedRiskScan(Vendor $vendor, array &$result): void
    {
        $categories = ['legal_case', 'scammer', 'social_media', 'illegal_offences'];

        $apiKey = config('services.anthropic.key');
        if (empty($apiKey)) {
            foreach ($categories as $cat) {
                $result[$cat . '_status'] = 'UNAVAILABLE';
                $result[$cat . '_note'] = 'AI service is not configured — Manual Review Required.';
            }
            return;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'anthropic-beta' => 'web-search-2025-03-05',
                'content-type' => 'application/json',
            ])->timeout(60)->post(self::API_URL, [
                'model' => self::MODEL,
                'max_tokens' => 1200,
                'tools' => [['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 6]],
                'messages' => [[
                    'role' => 'user',
                    'content' => "Search the public web for real, named, specific information about the Malaysian business \"{$vendor->vendor_name}\" across these FOUR separate categories. Answer using EXACTLY this format (including the markers), nothing else:\n\n===LEGAL===\n<NO_VERIFIABLE_CONCERNS, or a 2-sentence summary naming the specific court case/judgment/lawsuit and its source>\n===SCAMMER===\n<NO_VERIFIABLE_CONCERNS, or a 2-sentence summary naming the specific scam/fraud report and its source>\n===SOCIAL===\n<NO_VERIFIABLE_CONCERNS, or a 2-sentence summary naming the specific social media/news complaint and its source>\n===ILLEGAL===\n<NO_VERIFIABLE_CONCERNS, or a 2-sentence summary naming the specific regulatory action/illegal-operation finding and its source>\n\nLEGAL = court judgments, lawsuits, active litigation. SCAMMER = online scam/fraud reports or consumer fraud warnings. SOCIAL = negative social media mentions or news consumer complaints. ILLEGAL = regulatory sanctions, illegal business operation, licence revocation. Only report something under a category if your search actually found a real, specific, named source — never guess or speculate. If you are not confident real search results back up a claim, use NO_VERIFIABLE_CONCERNS for that category.",
                ]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('VendorDueDiligenceService: extended risk scan request failed: ' . $e->getMessage());
            foreach ($categories as $cat) {
                $result[$cat . '_status'] = 'UNAVAILABLE';
                $result[$cat . '_note'] = 'Could not reach the AI scan service — Manual Review Required.';
            }
            return;
        }

        if ($response->failed()) {
            Log::warning('VendorDueDiligenceService: extended risk scan failed (' . $response->status() . '): ' . $response->body());
            foreach ($categories as $cat) {
                $result[$cat . '_status'] = 'UNAVAILABLE';
                $result[$cat . '_note'] = 'AI scan is not available on this account\'s API plan right now — Manual Review Required.';
            }
            return;
        }

        $json = $response->json();
        $usedSearch = false;
        foreach (($json['content'] ?? []) as $block) {
            if (($block['type'] ?? null) === 'server_tool_use' || ($block['type'] ?? null) === 'web_search_tool_result') {
                $usedSearch = true;
            }
        }
        $text = '';
        foreach (($json['content'] ?? []) as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'];
            }
        }
        $text = trim($text);

        if (!$usedSearch || $text === '') {
            foreach ($categories as $cat) {
                $result[$cat . '_status'] = 'UNAVAILABLE';
                $result[$cat . '_note'] = 'AI scan did not return verifiable search results — Manual Review Required.';
            }
            return;
        }

        $markers = ['legal_case' => 'LEGAL', 'scammer' => 'SCAMMER', 'social_media' => 'SOCIAL', 'illegal_offences' => 'ILLEGAL'];
        foreach ($markers as $resultKey => $marker) {
            if (preg_match('/===' . $marker . '===\s*(.*?)(?====[A-Z]+===|$)/s', $text, $m)) {
                $section = trim($m[1]);
                if ($section === '' || str_contains($section, 'NO_VERIFIABLE_CONCERNS')) {
                    $result[$resultKey . '_status'] = 'CLEAR';
                    $result[$resultKey . '_note'] = 'No verifiable concerns found in an AI web search.';
                } else {
                    $result[$resultKey . '_status'] = 'CONCERNS_FOUND';
                    $result[$resultKey . '_note'] = $section;
                }
            } else {
                $result[$resultKey . '_status'] = 'UNAVAILABLE';
                $result[$resultKey . '_note'] = 'AI scan response could not be parsed for this category — Manual Review Required.';
            }
        }
    }

    /** Deliberately hardcoded, never a real check — GeneralLink has no connection to Malaysia's Jabatan Insolvensi Malaysia (JIM) e-Insolvency registry or any paid bankruptcy/court-record data source. Reporting a fake "clear" here would be actively dangerous, not just unhelpful — it stays UNAVAILABLE until a real data source is connected (or until Admin uploads their own search report — see analyzeBankruptcyDocument()/refreshBankruptcyAssessment() below). */
    private function runBankruptcyCheck(Vendor $vendor, array &$result): void
    {
        $result['bankruptcy_status'] = 'UNAVAILABLE';
        $result['bankruptcy_note'] = 'Not checked — GeneralLink has no connection to a bankruptcy/insolvency registry. Upload a JIM e-Insolvency (or similar) search report for each director/shareholder in the Risk Assessment Results tab to complete this check.';
    }

    /**
     * NEW 12 Aug 2026 — per Chris: "if cannot check then you allow the
     * admin upload the report separately... you have to prepare your own
     * review and comments your assessment risk score." Reads ONE
     * admin-uploaded bankruptcy/insolvency search report the same way
     * runIdentityCrossCheck() reads an SSM document — a real Claude read
     * of the real file, never a guess. Returns a genuine finding, not a
     * template.
     */
    public function analyzeBankruptcyDocument(string $absolutePath, string $mimeType): array
    {
        $extraction = $this->extractor->extract($absolutePath, $mimeType, [
            'person_name' => 'The full name of the individual (director or shareholder) this bankruptcy/insolvency search result is for',
            'conclusion'  => 'Exactly one word: CLEAR if the document shows no bankruptcy or winding-up record for this person, CONCERN if it shows an active or past bankruptcy/winding-up record, or UNCLEAR if the document does not clearly show a search result either way',
            'finding'     => 'A 2-sentence plain-English summary of exactly what this document shows',
        ]);

        if ($extraction['status'] !== 'OK') {
            return ['status' => 'ERROR', 'message' => $extraction['message'] ?? 'Could not read this document automatically — review it manually.'];
        }

        $conclusion = strtoupper(trim((string) ($extraction['values']['conclusion'] ?? '')));
        if (!in_array($conclusion, ['CLEAR', 'CONCERN', 'UNCLEAR'], true)) {
            $conclusion = 'UNCLEAR';
        }

        return [
            'status'      => 'OK',
            'person_name' => trim((string) ($extraction['values']['person_name'] ?? '')) ?: null,
            'conclusion'  => $conclusion,
            'finding'     => trim((string) ($extraction['values']['finding'] ?? '')) ?: null,
        ];
    }

    /**
     * NEW 12 Aug 2026 — recomputes the vendor's bankruptcy_status/note
     * from every uploaded+AI-reviewed vendor_bankruptcy_documents row
     * (worst finding wins across however many directors/shareholders
     * were uploaded), then recomputes the overall risk score/band/
     * recommendation the same way runAssessment() does, and saves both
     * onto the vendor's latest assessment row. Called right after every
     * upload so the Risk Assessment Results tab is always showing a
     * current picture.
     */
    public function refreshBankruptcyAssessment(Vendor $vendor): void
    {
        $docs = DB::table('vendor_bankruptcy_documents')->where('vendor_id', $vendor->vendor_id)->orderBy('created_at')->get();
        $assessment = DB::table('vendor_due_diligence_assessments')->where('vendor_id', $vendor->vendor_id)->orderByDesc('created_at')->first();
        if (!$assessment) {
            return;
        }

        if ($docs->isEmpty()) {
            $status = 'UNAVAILABLE';
            $note = 'Not checked — GeneralLink has no connection to a bankruptcy/insolvency registry. Upload a JIM e-Insolvency (or similar) search report for each director/shareholder to complete this check.';
        } else {
            $lines = [];
            $worst = 'CLEAR';
            foreach ($docs as $d) {
                $who = $d->ai_person_name ?: $d->file_name;
                $lines[] = $who . ': ' . ($d->ai_finding ?: 'Could not be read automatically — review the uploaded file manually.');
                $concl = $d->ai_conclusion ?: 'UNCLEAR';
                if ($concl === 'CONCERN') {
                    $worst = 'CONCERN';
                } elseif ($concl === 'UNCLEAR' && $worst !== 'CONCERN') {
                    $worst = 'UNCLEAR';
                }
            }
            $status = match ($worst) {
                'CONCERN' => 'CONCERNS_FOUND',
                'UNCLEAR' => 'UNAVAILABLE',
                default => 'CLEAR',
            };
            $note = implode(' | ', $lines);
        }

        DB::table('vendor_due_diligence_assessments')->where('assessment_id', $assessment->assessment_id)->update([
            'bankruptcy_status' => $status,
            'bankruptcy_note' => $note,
            'updated_at' => now(),
        ]);

        $refreshed = DB::table('vendor_due_diligence_assessments')->where('assessment_id', $assessment->assessment_id)->first();
        $risk = self::scoreBreakdown($refreshed);

        DB::table('vendor_due_diligence_assessments')->where('assessment_id', $assessment->assessment_id)->update([
            'risk_score' => $risk['score'],
            'risk_band' => $risk['band'],
            'overall_recommendation' => $risk['recommendation'],
            'updated_at' => now(),
        ]);
    }

    /** Deliberately hardcoded, never a real check — GeneralLink has no connection to CTOS (Malaysia's main credit reporting agency) or any paid credit-bureau data source. Stays UNAVAILABLE until Admin uploads their own CTOS report — see analyzeCtosDocument()/refreshCtosAssessment() below. */
    private function runCtosCheck(Vendor $vendor, array &$result): void
    {
        $result['ctos_status'] = 'UNAVAILABLE';
        $result['ctos_note'] = 'Not checked — GeneralLink has no connection to CTOS or any credit bureau. Upload a CTOS report in the Risk Assessment Results tab to complete this check.';
    }

    /**
     * NEW 12 Aug 2026 — per Chris: "add the 9th folder call Ctos report,
     * same as bankruptcy, choose file method." Reads ONE admin-uploaded
     * CTOS credit report the same way analyzeBankruptcyDocument() reads a
     * bankruptcy search result — a real Claude read of the real file.
     */
    public function analyzeCtosDocument(string $absolutePath, string $mimeType): array
    {
        $extraction = $this->extractor->extract($absolutePath, $mimeType, [
            'entity_name' => 'The full name of the company or individual this CTOS credit report is for',
            'conclusion'  => 'Exactly one word: CLEAR if the report shows no adverse credit findings (no defaults, legal suits, negative trade references, or poor credit score), CONCERN if it shows any adverse credit finding, or UNCLEAR if the report does not clearly show a result either way',
            'finding'     => 'A 2-sentence plain-English summary of exactly what this CTOS report shows, including the credit score/rating if one is stated',
        ]);

        if ($extraction['status'] !== 'OK') {
            return ['status' => 'ERROR', 'message' => $extraction['message'] ?? 'Could not read this document automatically — review it manually.'];
        }

        $conclusion = strtoupper(trim((string) ($extraction['values']['conclusion'] ?? '')));
        if (!in_array($conclusion, ['CLEAR', 'CONCERN', 'UNCLEAR'], true)) {
            $conclusion = 'UNCLEAR';
        }

        return [
            'status'      => 'OK',
            'entity_name' => trim((string) ($extraction['values']['entity_name'] ?? '')) ?: null,
            'conclusion'  => $conclusion,
            'finding'     => trim((string) ($extraction['values']['finding'] ?? '')) ?: null,
        ];
    }

    /** NEW 12 Aug 2026 — mirrors refreshBankruptcyAssessment() exactly, for CTOS reports. */
    public function refreshCtosAssessment(Vendor $vendor): void
    {
        $docs = DB::table('vendor_ctos_documents')->where('vendor_id', $vendor->vendor_id)->orderBy('created_at')->get();
        $assessment = DB::table('vendor_due_diligence_assessments')->where('vendor_id', $vendor->vendor_id)->orderByDesc('created_at')->first();
        if (!$assessment) {
            return;
        }

        if ($docs->isEmpty()) {
            $status = 'UNAVAILABLE';
            $note = 'Not checked — GeneralLink has no connection to CTOS or any credit bureau. Upload a CTOS report to complete this check.';
        } else {
            $lines = [];
            $worst = 'CLEAR';
            foreach ($docs as $d) {
                $who = $d->ai_entity_name ?: $d->file_name;
                $lines[] = $who . ': ' . ($d->ai_finding ?: 'Could not be read automatically — review the uploaded file manually.');
                $concl = $d->ai_conclusion ?: 'UNCLEAR';
                if ($concl === 'CONCERN') {
                    $worst = 'CONCERN';
                } elseif ($concl === 'UNCLEAR' && $worst !== 'CONCERN') {
                    $worst = 'UNCLEAR';
                }
            }
            $status = match ($worst) {
                'CONCERN' => 'CONCERNS_FOUND',
                'UNCLEAR' => 'UNAVAILABLE',
                default => 'CLEAR',
            };
            $note = implode(' | ', $lines);
        }

        DB::table('vendor_due_diligence_assessments')->where('assessment_id', $assessment->assessment_id)->update([
            'ctos_status' => $status,
            'ctos_note' => $note,
            'updated_at' => now(),
        ]);

        $refreshed = DB::table('vendor_due_diligence_assessments')->where('assessment_id', $assessment->assessment_id)->first();
        $risk = self::scoreBreakdown($refreshed);

        DB::table('vendor_due_diligence_assessments')->where('assessment_id', $assessment->assessment_id)->update([
            'risk_score' => $risk['score'],
            'risk_band' => $risk['band'],
            'overall_recommendation' => $risk['recommendation'],
            'updated_at' => now(),
        ]);
    }

    /**
     * NEW 12 Aug 2026 — per Chris: "there is no drill down on each due
     * diligence... it is a new screen when click from pending approval
     * screen." This 9-row array used to be hand-built once, inline,
     * inside pending-logins.blade.php's per-vendor @php block, purely to
     * feed the (now-removed) in-modal Risk Assessment tab. Centralized
     * here as the single source of truth so the new dedicated
     * risk-assessment.blade.php screen (and anything else that ever
     * needs it) builds the exact same 9 rows from the exact same
     * numbers — never two copies that could drift apart.
     */
    public static function riskCategoryRows(?object $dd): array
    {
        if (!$dd) {
            return [];
        }
        $idScore = $dd->identity_match_score ?? null;
        $idStatus = $idScore === null ? 'UNAVAILABLE' : ($idScore >= 80 ? 'CLEAR' : 'CONCERNS_FOUND');

        $rows = [
            ['key' => 'identity', 'num' => 1, 'label' => 'Document Identity Cross-Check', 'status' => $idStatus, 'note' => $dd->identity_match_note ?? 'Not run.', 'extra' => $idScore !== null ? $idScore . '% name-text match' : null],
            ['key' => 'sanctions', 'num' => 2, 'label' => 'Sanctions Screening', 'status' => $dd->sanctions_status ?? 'UNAVAILABLE', 'note' => $dd->sanctions_note ?? 'Not run.'],
            ['key' => 'legal', 'num' => 3, 'label' => 'Legal / Court Case Check', 'status' => $dd->legal_case_status ?? 'UNAVAILABLE', 'note' => $dd->legal_case_note ?? 'Not run.'],
            ['key' => 'scammer', 'num' => 4, 'label' => 'Scammer / Fraud Reports Check', 'status' => $dd->scammer_status ?? 'UNAVAILABLE', 'note' => $dd->scammer_note ?? 'Not run.'],
            ['key' => 'social', 'num' => 5, 'label' => 'Social Media & News Complaints Check', 'status' => $dd->social_media_status ?? 'UNAVAILABLE', 'note' => $dd->social_media_note ?? 'Not run.'],
            ['key' => 'illegal', 'num' => 6, 'label' => 'Illegal Offences / Regulatory Action Check', 'status' => $dd->illegal_offences_status ?? 'UNAVAILABLE', 'note' => $dd->illegal_offences_note ?? 'Not run.'],
            ['key' => 'news', 'num' => 7, 'label' => 'AI Negative-News Scan', 'status' => $dd->negative_news_status ?? 'UNAVAILABLE', 'note' => $dd->negative_news_note ?? 'Not run.'],
            ['key' => 'bankruptcy', 'num' => 8, 'label' => 'Director & Shareholder Bankruptcy Check', 'status' => $dd->bankruptcy_status ?? 'UNAVAILABLE', 'note' => $dd->bankruptcy_note ?? 'Not run.'],
            ['key' => 'ctos', 'num' => 9, 'label' => 'CTOS Credit Report', 'status' => $dd->ctos_status ?? 'UNAVAILABLE', 'note' => $dd->ctos_note ?? 'Not run.'],
        ];

        // Every category gets a real mark: 100 only if it was genuinely
        // CLEAR (actually checked, nothing found). UNAVAILABLE (not yet
        // checked/uploaded) and CONCERNS_FOUND/POTENTIAL_MATCH both score
        // 0 — an unchecked category must never look the same as a clean
        // one. See scoreBreakdown() for how these 9 marks combine.
        foreach ($rows as &$row) {
            $row['mark'] = $row['status'] === 'CLEAR' ? 100 : 0;
        }
        unset($row);

        return $rows;
    }

    /**
     * REDESIGNED 13 Aug 2026 — per Chris: "you should design the scoring
     * mark per due diligent check and the final score is the total of
     * each 9 diligent divide by 9." Final score = sum of the 9 category
     * marks (100 each if CLEAR, 0 otherwise) / 9 — an assessment with
     * bankruptcy/CTOS not yet uploaded can no longer read as a perfect
     * score, because those 2 categories contribute 0, not nothing.
     *
     * Also builds the 5-tier, colour-coded recommendation Chris asked
     * for ("green is recommend to approve... propose different grade
     * different colour and different text"):
     *   Approve                              — green   (#166534) — score 100, nothing unresolved
     *   Approve With Minor Review             — blue    (#0369a1) — score 70-99, no concern found, just 1-2 checks outstanding
     *   Moderate Risk — Review Required       — amber   (#b45309) — score 40-69, several checks still outstanding
     *   High Risk — Detailed Review Required  — orange  (#c2410c) — score below 40, most checks outstanding
     *   Critical Risk — Escalate to Director  — dark red(#b71c1c) — ANY category actually found a real concern, overrides the score entirely
     * Static + accepts any object with the right properties (a DB row or
     * a cast array) so the exact same logic runs at assessment time AND
     * when re-displaying an already-saved report — one source of truth.
     */
    public static function scoreBreakdown(object $dd): array
    {
        $rows = self::riskCategoryRows($dd);
        $marks = array_column($rows, 'mark');
        $score = count($marks) ? (int) round(array_sum($marks) / count($marks)) : 0;

        $anyConcern = collect($rows)->contains(fn ($r) => in_array($r['status'], ['CONCERNS_FOUND', 'POTENTIAL_MATCH'], true));

        if ($anyConcern) {
            $band = 'CRITICAL';
            $recommendation = 'CRITICAL_RISK_ESCALATE';
            $recLabel = 'Critical Risk — Escalate to Director';
            $recColor = '#b71c1c';
        } elseif ($score >= 90) {
            $band = 'LOW';
            $recommendation = 'APPROVE';
            $recLabel = 'Approve';
            $recColor = '#166534';
        } elseif ($score >= 70) {
            $band = 'FAIR';
            $recommendation = 'APPROVE_WITH_REVIEW';
            $recLabel = 'Approve With Minor Review';
            $recColor = '#0369a1';
        } elseif ($score >= 40) {
            $band = 'MODERATE';
            $recommendation = 'MODERATE_RISK_REVIEW';
            $recLabel = 'Moderate Risk — Review Required';
            $recColor = '#b45309';
        } else {
            $band = 'HIGH';
            $recommendation = 'HIGH_RISK_REVIEW';
            $recLabel = 'High Risk — Detailed Review Required';
            $recColor = '#c2410c';
        }

        $lines = array_map(fn ($r) => ['label' => $r['label'], 'status' => $r['status'], 'mark' => $r['mark']], $rows);

        return [
            'score' => $score,
            'band' => $band,
            'lines' => $lines,
            'recommendation' => $recommendation,
            'recommendation_label' => $recLabel,
            'recommendation_color' => $recColor,
        ];
    }
}
