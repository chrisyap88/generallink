<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 8 Sep 2026 (Task #397) — AI-Powered Accounting Automation
// Management Module, Phase 2: Rule-Based Transaction Understanding +
// Confidence.
//
// Two layers, in priority order:
//   1. Per-node LEARNED rules (cbe_ai_classification_rules) — built from
//      Chris's own confirmed/corrected classifications. These win over
//      the generic rules below because they reflect his actual
//      transactions, not a generic guess.
//   2. Built-in generic keyword heuristics (GENERIC_RULES below) — the
//      same "rule-based first, no external AI API" scope Chris chose
//      for Phase 1's PDF extraction. These are universal, low-risk
//      signals (bank charge wording, donation wording, transfer
//      wording) — never a business master list, so nothing here is
//      hardcoded per-organisation data.
//
// Anti-fabrication (spec section 21): if neither layer produces a
// confident match, the category is left null (classification_source =
// 'NONE') rather than guessing. This matters most for plain "Payment
// via Cheque" style bank lines — a real bank statement often gives no
// payee name at all, only a cheque number, so there is genuinely no
// signal to classify from until Chris confirms it once himself (which
// is exactly what teaches the LEARNED layer for next time).
class TransactionClassificationService
{
    public const CATEGORIES = [
        'SUPPLIER', 'CUSTOMER', 'DONATION', 'MEMBERSHIP', 'ASSET',
        'TRANSFER', 'BANK_CHARGE', 'LOAN', 'ADJUSTMENT', 'OTHER',
        'RETURNED_CHEQUE',
    ];

    // Category => the existing cbe_bank_transaction_types.type_name this
    // maps to, ONLY when that mapping is unambiguous. Left out entirely
    // for categories (SUPPLIER, CUSTOMER, DONATION, MEMBERSHIP, ASSET,
    // LOAN, ADJUSTMENT) where no matching default type exists — never
    // invents a GL/type mapping that isn't really configured.
    private const CATEGORY_TYPE_HINTS = [
        'BANK_CHARGE' => 'Bank Charge',
        'TRANSFER' => 'Bank Transfer',
    ];

    private const GENERIC_RULES = [
        // NEW 9 Sep 2026 (Task #397 follow-up, Phase 16) — a bounced/
        // returned cheque, either direction: our own cheque to a
        // supplier coming back (a CREDIT reversing the earlier debit) or
        // a customer's cheque we deposited bouncing (a DEBIT reversing
        // the earlier credit) — direction alone can't tell which, only
        // the wording can, so this fires on keyword regardless of
        // direction; AiAccountingController::commitDocument() decides AP
        // vs AR from the sign of the amount at commit time.
        ['keywords' => ['returned cheque', 'cheque returned', 'unpaid cheque', 'dishonour', 'dishonoured', 'dishonored', 'insufficient fund', 'nsf', 'r/c ', 'rtn chq', 'chq return', 'cek dikembalikan', 'cek tidak dapat ditunaikan', 'dana tidak cukup'], 'category' => 'RETURNED_CHEQUE', 'direction' => null, 'confidence' => 65],
        ['keywords' => ['bank charge', 'cheque processing fee', 'service charge', 'ledger fee', 'sms alert fee', 'account maintenance fee', 'caj bank', 'yuran bank'], 'category' => 'BANK_CHARGE', 'direction' => 'DEBIT', 'confidence' => 80],
        ['keywords' => ['interest credit', 'interest earned', 'faedah'], 'category' => 'OTHER', 'direction' => 'CREDIT', 'confidence' => 65],
        ['keywords' => ['donation', 'derma', 'sumbangan', 'contribution received', 'gift received'], 'category' => 'DONATION', 'direction' => 'CREDIT', 'confidence' => 70],
        ['keywords' => ['membership fee', 'yuran keahlian', 'yuran ahli', 'subscription fee'], 'category' => 'MEMBERSHIP', 'direction' => 'CREDIT', 'confidence' => 70],
        ['keywords' => ['ibg ', 'duitnow', 'interbank giro', 'fund transfer', 'pemindahan wang', 'instant transfer', 'online transfer', 'fpx'], 'category' => 'TRANSFER', 'direction' => null, 'confidence' => 60],
        ['keywords' => ['loan disbursement', 'pinjaman', 'loan repayment', 'installment payment', 'ansuran'], 'category' => 'LOAN', 'direction' => null, 'confidence' => 60],
        ['keywords' => ['purchase of equipment', 'asset purchase', 'furniture purchase', 'computer purchase', 'vehicle purchase', 'renovation', 'pembelian aset'], 'category' => 'ASSET', 'direction' => 'DEBIT', 'confidence' => 55],
        ['keywords' => ['salary', 'payroll', 'gaji', 'staff claim', 'staff reimbursement'], 'category' => 'OTHER', 'direction' => 'DEBIT', 'confidence' => 55],
    ];

    // NEW 19 Sep 2026 -- per Chris: "you should design an AI power
    // capability in capturing the right category and GL code rather
    // than the user have to find and allocate ... you should read the
    // description when you detect something like lunch, meal, food you
    // should filter for the user to select, same for electricity,
    // rental, prayer items, events hall." A treasurer who isn't the one
    // who set up the Chart of Accounts shouldn't have to already know
    // its structure to find the right account -- this reads the line's
    // description for common Malaysian NGO/temple spending wording and
    // narrows straight to the matching cbe_transaction_categories row
    // (which already carries its own chart_account_id -- see
    // suggestChartCategoryId() below), instead of leaving the treasurer
    // to browse the whole Chart of Accounts blind. Universal wording
    // only, same as GENERIC_RULES above -- never a hardcoded account
    // name/code, since every CBE community names its own categories
    // differently; this only narrows down to whichever of THEIR
    // categories' names contain a matching hint word.
    private const GL_CATEGORY_HINTS = [
        ['keywords' => ['lunch', 'dinner', 'breakfast', 'meal', 'makan', 'catering', 'restaurant', 'restoran', 'cafe', 'kopitiam', 'food', 'refreshment', 'tea break', 'snack'], 'hints' => ['refreshment', 'meeting', 'entertainment', 'meal', 'food', 'catering']],
        ['keywords' => ['tnb', 'electric', 'elektrik', 'electricity'], 'hints' => ['electric', 'utilit']],
        ['keywords' => ['syabas', 'water bill', 'air bill', 'indah water'], 'hints' => ['water']],
        ['keywords' => ['rental', 'rent ', 'sewa'], 'hints' => ['rent']],
        ['keywords' => ['incense', 'joss paper', 'joss stick', 'candle', 'prayer item', 'religious item', 'offering', 'colok', 'lilin', 'dupa'], 'hints' => ['religious', 'incense', 'joss', 'candle', 'offering', 'prayer']],
        ['keywords' => ['hall rental', 'event hall', 'venue rental', 'dewan', 'function room'], 'hints' => ['hall', 'event', 'venue']],
        ['keywords' => ['stationery', 'printing', 'photostat', 'alat tulis'], 'hints' => ['stationery', 'printing']],
        ['keywords' => ['internet', 'wifi', 'broadband', 'unifi', 'streamyx'], 'hints' => ['internet']],
        ['keywords' => ['telephone bill', 'phone bill', 'talian telefon'], 'hints' => ['telephone', 'communication']],
        ['keywords' => ['security services', 'pengawal keselamatan'], 'hints' => ['security']],
        ['keywords' => ['insurance premium', 'takaful', 'insurans'], 'hints' => ['insurance']],
        ['keywords' => ['salary', 'payroll', 'gaji', 'staff claim', 'staff reimbursement'], 'hints' => ['salary', 'wages', 'staff']],
        ['keywords' => ['audit fee', 'yuran audit'], 'hints' => ['audit']],
        ['keywords' => ['legal fee', 'yuran guaman'], 'hints' => ['legal']],
        ['keywords' => ['medical claim', 'medical bill', 'clinic bill', 'hospital bill', 'ubat', 'panel clinic'], 'hints' => ['medical']],
        ['keywords' => ['petrol claim', 'petrol', 'toll claim', 'toll fee', 'parking claim', 'parking fee', 'mileage claim', 'travel claim', 'transport claim', 'transportation claim'], 'hints' => ['travel', 'transport', 'petrol', 'mileage', 'toll', 'parking']],
    ];

    // Reads $description for the wording above and, on a hit, returns
    // the first of the CBE's OWN $glCategories (a Collection/array of
    // cbe_transaction_categories rows, each already carrying its own
    // chart_account_id) whose category_name / category_name_zh contains
    // one of that hit's hint words. Returns null on no match -- never
    // guesses a category that doesn't actually exist for this community,
    // same anti-fabrication rule as classifyLine() above; the treasurer
    // still picks manually from the (now much shorter, effectively
    // filtered) dropdown when nothing matches.
    public function suggestChartCategoryId(?string $description, iterable $glCategories): ?string
    {
        $text = mb_strtolower((string) $description);
        if ($text === '') {
            return null;
        }

        foreach (self::GL_CATEGORY_HINTS as $rule) {
            $matched = false;
            foreach ($rule['keywords'] as $kw) {
                if (str_contains($text, $kw)) { $matched = true; break; }
            }
            if (! $matched) { continue; }

            foreach ($glCategories as $gc) {
                $name = mb_strtolower(($gc->category_name ?? '').' '.($gc->category_name_zh ?? ''));
                foreach ($rule['hints'] as $hint) {
                    if (str_contains($name, $hint)) {
                        return $gc->category_id;
                    }
                }
            }
        }

        return null;
    }

    // Exposes GL_CATEGORY_HINTS to callers that need to run the same
    // matching client-side in JavaScript (live data-entry screens like
    // Invoices/Bills/Journal Vouchers, where there is no pre-extracted
    // description to match server-side at render time -- the treasurer
    // is still typing it in). The hint words themselves are universal
    // accounting wording, never a specific CBE's own account names, so
    // it is safe to send to the browser.
    public static function glCategoryHints(): array
    {
        return self::GL_CATEGORY_HINTS;
    }

    // Same matching as suggestChartCategoryId() above, but for the
    // screens (Journal Voucher/Adjustment Journal/Accrual Journal, Bank
    // Reconciliation adjustment) that pick a GL Account directly from
    // $accounts (cbe_chart_of_accounts rows) instead of going through
    // cbe_transaction_categories. Same anti-fabrication rule: returns
    // null on no match, never guesses an account that isn't in this
    // CBE's own Chart of Accounts.
    public function suggestChartAccountId(?string $description, iterable $accounts): ?string
    {
        $text = mb_strtolower((string) $description);
        if ($text === '') {
            return null;
        }

        foreach (self::GL_CATEGORY_HINTS as $rule) {
            $matched = false;
            foreach ($rule['keywords'] as $kw) {
                if (str_contains($text, $kw)) { $matched = true; break; }
            }
            if (! $matched) { continue; }

            foreach ($accounts as $a) {
                $name = mb_strtolower(($a->account_name ?? '').' '.($a->account_code ?? ''));
                foreach ($rule['hints'] as $hint) {
                    if (str_contains($name, $hint)) {
                        return $a->account_id;
                    }
                }
            }
        }

        return null;
    }

    // Keyword hints for the Fixed Asset screen's Asset Name box, mapped
    // to whatever Asset Category names Chris has actually set up (land,
    // building, machinery, computer, motor vehicle, van, aircond,
    // furniture, bike, etc. -- his own list from 19 Sep 2026). Same
    // anti-fabrication rule as GL_CATEGORY_HINTS: only ever narrows down
    // to a category that already exists in HIS cbe_asset_categories,
    // never invents one.
    private const ASSET_CATEGORY_HINTS = [
        ['keywords' => ['land', 'tanah'], 'hints' => ['land']],
        ['keywords' => ['building', 'bangunan', 'shop lot', 'shoplot', 'office lot'], 'hints' => ['building']],
        ['keywords' => ['machine', 'machinery', 'jentera', 'generator', 'genset'], 'hints' => ['machinery', 'machine']],
        ['keywords' => ['computer', 'laptop', 'notebook pc', 'desktop', 'printer', 'komputer', 'server'], 'hints' => ['computer', 'it equipment', 'it ']],
        ['keywords' => ['van', 'lori', 'lorry'], 'hints' => ['van']],
        ['keywords' => ['motor vehicle', 'car', 'kereta', 'pickup', 'truck'], 'hints' => ['motor vehicle', 'vehicle', 'car']],
        ['keywords' => ['motorcycle', 'motosikal', 'bike', 'bicycle', 'basikal'], 'hints' => ['bike', 'motorcycle', 'bicycle']],
        ['keywords' => ['aircond', 'air conditioner', 'air-con', 'air con', 'penghawa dingin'], 'hints' => ['aircond', 'air con', 'air-con']],
        ['keywords' => ['furniture', 'table', 'chair', 'cabinet', 'almari', 'meja', 'kerusi'], 'hints' => ['furniture']],
    ];

    // Exposes ASSET_CATEGORY_HINTS to the Fixed Asset create screen's
    // JavaScript, same reasoning as glCategoryHints() above.
    public static function assetCategoryHints(): array
    {
        return self::ASSET_CATEGORY_HINTS;
    }

    // Matches a typed Asset Name against this CBE's own
    // cbe_asset_categories rows. Returns null on no match -- the
    // treasurer still picks manually when nothing matches.
    public function suggestAssetCategoryId(?string $assetName, iterable $assetCategories): ?string
    {
        $text = mb_strtolower((string) $assetName);
        if ($text === '') {
            return null;
        }

        foreach (self::ASSET_CATEGORY_HINTS as $rule) {
            $matched = false;
            foreach ($rule['keywords'] as $kw) {
                if (str_contains($text, $kw)) { $matched = true; break; }
            }
            if (! $matched) { continue; }

            foreach ($assetCategories as $ac) {
                $name = mb_strtolower(($ac->category_name ?? '').' '.($ac->category_name_zh ?? ''));
                foreach ($rule['hints'] as $hint) {
                    if (str_contains($name, $hint)) {
                        return $ac->category_id;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Classify every not-yet-classified line in a document, then run the
     * restricted/designated-fund pairing pass across the whole document.
     */
    public function classifyDocument(string $documentId, string $nodeId): void
    {
        $groupLabelId = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->value('group_label_id');
        CbeAccountingService::ensureBankTransactionTypes($groupLabelId);

        $lines = DB::table('cbe_ai_extracted_transactions')
            ->where('document_id', $documentId)
            ->where('classification_source', 'NONE')
            ->get();

        foreach ($lines as $line) {
            $result = $this->classifyLine($line, $nodeId, $groupLabelId);
            DB::table('cbe_ai_extracted_transactions')->where('extraction_id', $line->extraction_id)->update([
                'suggested_ai_category' => $result['category'],
                'suggested_transaction_type_id' => $result['transaction_type_id'],
                'classification_confidence' => $result['confidence'],
                'classification_source' => $result['source'],
                'matched_rule_id' => $result['matched_rule_id'],
                'updated_at' => now(),
            ]);
        }

        $this->pairRestrictedFunds($documentId);
    }

    /**
     * Classify a single extracted line. Returns category/transaction
     * type/confidence/source/matched_rule_id — never writes to the DB
     * itself, so it can also be used for a re-classify-one-line action
     * later without touching the rest of the document.
     */
    public function classifyLine(object $line, string $nodeId, ?string $groupLabelId): array
    {
        $direction = $line->credit !== null ? 'CREDIT' : ($line->debit !== null ? 'DEBIT' : null);
        $pattern = $this->normalizePattern($line->description);

        if ($pattern) {
            $rule = DB::table('cbe_ai_classification_rules')
                ->where('cbe_node_id', $nodeId)
                ->where('is_active', true)
                ->where('match_field', 'DESCRIPTION')
                ->where(function ($q) use ($direction) {
                    $q->whereNull('direction');
                    if ($direction) {
                        $q->orWhere('direction', $direction);
                    }
                })
                ->orderByDesc('times_confirmed')
                ->get()
                ->first(fn ($r) => $pattern === $r->match_pattern || str_contains($pattern, $r->match_pattern) || str_contains($r->match_pattern, $pattern));

            if ($rule) {
                $confidence = $rule->confidence_base + min(20, $rule->times_confirmed * 3) - min(40, $rule->times_rejected * 8);
                $confidence = max(20, min(97, $confidence));

                return [
                    'category' => $rule->ai_category,
                    'transaction_type_id' => $rule->suggested_transaction_type_id,
                    'confidence' => $confidence,
                    'source' => 'LEARNED',
                    'matched_rule_id' => $rule->rule_id,
                ];
            }
        }

        $haystack = mb_strtolower(trim(($line->description ?? '').' '.($line->reference_no ?? '')));
        foreach (self::GENERIC_RULES as $genRule) {
            if ($genRule['direction'] && $direction && $genRule['direction'] !== $direction) {
                continue;
            }
            foreach ($genRule['keywords'] as $keyword) {
                if ($haystack !== '' && str_contains($haystack, $keyword)) {
                    return [
                        'category' => $genRule['category'],
                        'transaction_type_id' => $this->lookupTransactionTypeId($genRule['category'], $groupLabelId),
                        'confidence' => $genRule['confidence'],
                        'source' => 'RULE',
                        'matched_rule_id' => null,
                    ];
                }
            }
        }

        // No signal at all — mark unclassified rather than guess.
        return [
            'category' => null,
            'transaction_type_id' => null,
            'confidence' => 0,
            'source' => 'NONE',
            'matched_rule_id' => null,
        ];
    }

    /**
     * Cross-line pass over one document: pairs a credit with a same/
     * near-same-amount debit elsewhere in the same statement, flagging
     * both as a possible restricted/designated fund rather than letting
     * them blend silently into ordinary income/expense. Triggered by
     * the FTAM Klang reference example Chris shared (RM50,000 donation
     * received then RM50,000 disbursed for the same project a few days
     * later) — exactly the pattern that should surface for review, not
     * be posted through unnoticed.
     */
    public function pairRestrictedFunds(string $documentId): void
    {
        $lines = DB::table('cbe_ai_extracted_transactions')
            ->where('document_id', $documentId)
            ->whereNull('paired_extraction_id')
            ->orderBy('line_no')
            ->get();

        $credits = $lines->filter(fn ($l) => $l->credit !== null && (float) $l->credit > 0);
        $debitPool = $lines->filter(fn ($l) => $l->debit !== null && (float) $l->debit > 0)->values();

        foreach ($credits as $credit) {
            $creditAmount = (float) $credit->credit;
            $tolerance = max(1.0, $creditAmount * 0.01);

            $matchIndex = null;
            foreach ($debitPool as $idx => $debit) {
                if ($debit->extraction_id === $credit->extraction_id) {
                    continue;
                }
                if (abs((float) $debit->debit - $creditAmount) <= $tolerance) {
                    $matchIndex = $idx;
                    break;
                }
            }

            if ($matchIndex !== null) {
                $match = $debitPool[$matchIndex];
                $note = __('cbe_ai.flag_note_restricted_fund');
                DB::table('cbe_ai_extracted_transactions')->where('extraction_id', $credit->extraction_id)
                    ->update(['paired_extraction_id' => $match->extraction_id, 'flag_note' => $note, 'updated_at' => now()]);
                DB::table('cbe_ai_extracted_transactions')->where('extraction_id', $match->extraction_id)
                    ->update(['paired_extraction_id' => $credit->extraction_id, 'flag_note' => $note, 'updated_at' => now()]);
                $debitPool = $debitPool->reject(fn ($d, $i) => $i === $matchIndex)->values();
            }
        }
    }

    /**
     * Called when Chris confirms (or corrects) a line's category —
     * either explicitly, or implicitly by committing it with a category
     * showing in the dropdown. Reinforces or creates the per-node
     * learned rule so the same kind of line is recognised automatically
     * next time (task #60's core requirement).
     */
    public function confirmClassification(string $extractionId, ?string $confirmedCategory, string $nodeId): void
    {
        $line = DB::table('cbe_ai_extracted_transactions')->where('extraction_id', $extractionId)->first();
        if (! $line || ! $confirmedCategory) {
            return;
        }

        $pattern = $this->normalizePattern($line->description);
        $direction = $line->credit !== null ? 'CREDIT' : ($line->debit !== null ? 'DEBIT' : null);
        $oldRule = $line->matched_rule_id ? DB::table('cbe_ai_classification_rules')->where('rule_id', $line->matched_rule_id)->first() : null;
        $oldRuleMatched = $oldRule && $oldRule->ai_category === $confirmedCategory;

        if ($oldRule) {
            DB::table('cbe_ai_classification_rules')->where('rule_id', $oldRule->rule_id)->update(
                $oldRuleMatched
                    ? ['times_confirmed' => $oldRule->times_confirmed + 1, 'updated_at' => now()]
                    : ['times_rejected' => $oldRule->times_rejected + 1, 'updated_at' => now()]
            );
        }

        if ($pattern && ! $oldRuleMatched) {
            $existing = DB::table('cbe_ai_classification_rules')
                ->where('cbe_node_id', $nodeId)->where('match_field', 'DESCRIPTION')->where('match_pattern', $pattern)->first();

            if ($existing) {
                DB::table('cbe_ai_classification_rules')->where('rule_id', $existing->rule_id)->update([
                    'ai_category' => $confirmedCategory, 'direction' => $direction,
                    'times_confirmed' => $existing->times_confirmed + 1, 'is_active' => true, 'updated_at' => now(),
                ]);
            } else {
                DB::table('cbe_ai_classification_rules')->insert([
                    'rule_id' => (string) Str::uuid(), 'cbe_node_id' => $nodeId,
                    'match_field' => 'DESCRIPTION', 'match_pattern' => $pattern, 'direction' => $direction,
                    'ai_category' => $confirmedCategory, 'confidence_base' => 75,
                    'times_confirmed' => 1, 'times_rejected' => 0,
                    'created_from_extraction_id' => $extractionId, 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        DB::table('cbe_ai_extracted_transactions')->where('extraction_id', $extractionId)->update([
            'confirmed_ai_category' => $confirmedCategory, 'updated_at' => now(),
        ]);
    }

    /**
     * Normalizes a raw bank-line description into a learnable pattern:
     * lowercase, digit-runs stripped (cheque/reference numbers, dates —
     * the part that changes every time), whitespace collapsed. Two
     * lines that are the same kind of transaction but reference
     * different cheque numbers end up with the same pattern.
     */
    private function normalizePattern(?string $description): ?string
    {
        if (! $description) {
            return null;
        }
        $text = mb_strtolower(trim($description));
        $text = preg_replace('/\d{3,}/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        return $text !== '' ? mb_substr($text, 0, 255) : null;
    }

    public function lookupTransactionTypeId(string $category, ?string $groupLabelId): ?string
    {
        $hint = self::CATEGORY_TYPE_HINTS[$category] ?? null;
        if (! $hint) {
            return null;
        }

        return DB::table('cbe_bank_transaction_types')
            ->where(function ($q) use ($groupLabelId) {
                $q->where('group_label_id', $groupLabelId);
                if ($groupLabelId === null) {
                    $q->orWhereNull('group_label_id');
                }
            })
            ->where('type_name', $hint)->where('is_active', true)->value('type_id');
    }
}
