<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// NEW 19 Sep 2026 -- "COA Chat", the first entity type wired into the
// new "AI Master Data Assistant" (per Chris's uploaded spec
// AI_Master_Data_and_Transaction_Assistant_Specification.docx, Part 1).
// Chris confirmed: build Chart of Accounts first as Phase 1, other
// master data types (Supplier, Customer, Bank, Fixed Asset, Cost
// Centre, Tax) come later one at a time; transaction auto-creation
// (Part 2 of the spec) is deliberately set aside for now.
//
// Per the spec's own rules (search-first, never invent, one decision
// per record, never bypass approval): given a plain free-text
// description, this (a) searches this CBE's OWN existing Chart of
// Accounts first to avoid ever creating a duplicate GL Code for the
// same thing, and only if nothing matches, (b) classifies a NEW
// account's Type/Group/Category/Name -- acting as the "30-year
// financial controller" so a non-accountant is never asked to make an
// accounting judgement call themselves.
//
// Anti-fabrication, same rule as TransactionClassificationService: the
// AI may only pick a Group/Category name from the list actually passed
// to it (this community's real, existing master data) -- it never
// invents a new Group/Category, and a claimed match must point at a
// real account_code we gave it, verified against the database before
// being trusted. The GL Code itself is NEVER decided by the AI -- it
// is always computed here, deterministically, from this community's
// own existing numbering (see nextAccountCode()) -- the spec's own
// Section 6 rule: "The AI must never invent a GL Code."
//
// This never saves anything by itself -- classify() only returns a
// proposal (spec Section 8: "Each Master Data record must have an
// individual decision... the system must not assume approval"). The
// COA Chat screen shows it to Chris as a preview, and the actual save
// happens through the existing, already-validated
// chart-of-accounts.store route when he taps Confirm -- same as the
// manual Add Account form.
class CoaChatAssistantService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-haiku-4-5-20251001';
    private const ANTHROPIC_VERSION = '2023-06-01';

    private const TYPE_BASE_CODE = [
        'ASSET' => 1000, 'LIABILITY' => 2000, 'EQUITY' => 3000, 'INCOME' => 4000, 'EXPENSE' => 5000,
    ];

    /**
     * @return array{status:string, message?:string, account?:array, proposal?:array}
     */
    public function classify(string $description, string $groupLabelId, string $nodeId): array
    {
        $description = trim($description);
        if ($description === '') {
            return ['status' => 'ERROR', 'message' => 'Please describe what the account is for first.'];
        }

        $apiKey = config('services.anthropic.key');
        if (empty($apiKey)) {
            return ['status' => 'ERROR', 'message' => 'ANTHROPIC_API_KEY is not set in .env — COA Chat is not configured yet.'];
        }

        $existingAccounts = DB::table('cbe_chart_of_accounts')
            ->where('cbe_chart_of_accounts.group_label_id', $groupLabelId)
            ->where(function ($q) use ($nodeId) { $q->whereNull('cbe_chart_of_accounts.cbe_node_id')->orWhere('cbe_chart_of_accounts.cbe_node_id', $nodeId); })
            ->where('is_active', true)
            ->leftJoin('cbe_account_groups', 'cbe_chart_of_accounts.account_group_id', '=', 'cbe_account_groups.group_id')
            ->leftJoin('cbe_account_categories', 'cbe_chart_of_accounts.account_category_id', '=', 'cbe_account_categories.category_id')
            ->orderBy('cbe_chart_of_accounts.account_code')
            ->get([
                'cbe_chart_of_accounts.account_id', 'cbe_chart_of_accounts.account_code', 'cbe_chart_of_accounts.account_name',
                'cbe_chart_of_accounts.account_name_zh', 'cbe_chart_of_accounts.account_type', 'cbe_chart_of_accounts.description',
                'cbe_account_groups.group_name', 'cbe_account_categories.category_name',
            ]);

        $groups = DB::table('cbe_account_groups')
            ->where(function ($q) use ($groupLabelId) { $q->whereNull('group_label_id')->orWhere('group_label_id', $groupLabelId); })
            ->where('is_active', true)->orderBy('account_type')->orderBy('display_order')
            ->get(['group_id', 'account_type', 'group_name', 'group_name_zh']);

        $categories = DB::table('cbe_account_categories')
            ->where(function ($q) use ($groupLabelId) { $q->whereNull('group_label_id')->orWhere('group_label_id', $groupLabelId); })
            ->where('is_active', true)->orderBy('display_order')
            ->get(['category_id', 'category_name', 'category_name_zh']);

        if ($existingAccounts->isEmpty()) {
            return ['status' => 'ERROR', 'message' => 'The Chart of Accounts has not been set up yet — please add at least one account manually first.'];
        }

        $accountsList = $existingAccounts->map(fn ($a) => "{$a->account_code} | {$a->account_type} | " . ($a->group_name ?? '-') . ' | ' . ($a->category_name ?? '-') . " | {$a->account_name}")->implode("\n");
        $groupsList = $groups->map(fn ($g) => "{$g->account_type}: {$g->group_name}")->implode("\n");
        $categoriesList = $categories->map(fn ($c) => $c->category_name)->implode("\n");

        $prompt = <<<PROMPT
You are a 30-year veteran financial controller helping a non-accountant volunteer or clerk at a Malaysian temple/NGO/SME set up their Chart of Accounts. They will describe, in plain everyday language, what they want to record (e.g. "hotel bills"). You must decide the correct accounting classification yourself — never ask them to choose Account Type, Group, Category, or Debit/Credit; they do not know these terms.

This community's EXISTING accounts (code | type | group | category | name):
{$accountsList}

This community's EXISTING Account Groups (only pick from this exact list, by exact name — never invent a new one):
{$groupsList}

This community's EXISTING Account Categories (only pick from this exact list, by exact name, or null if none genuinely fit):
{$categoriesList}

The user's description: "{$description}"

Step 1 — check for a duplicate: does one of the EXISTING accounts above already clearly cover this description? If yes, this is a MATCH — do not propose a new account.

Step 2 — if NOT a match, classify a NEW account:
- account_type: exactly one of ASSET, LIABILITY, EQUITY, INCOME, EXPENSE.
- group_name: the single best-fitting name from the EXISTING Account Groups list above, for that account_type. You MUST use one of the exact names given — if truly nothing fits well, use the group named "Other Assets"/"Other Liabilities"/"Other Reserves"/"Other Income"/"Other Expenses" (whichever matches the account_type).
- category_name: the single best-fitting name from the EXISTING Account Categories list above, or null if none genuinely fit.
- account_name: a short, professional English/Malay account name, Title Case (e.g. "Hotel & Accommodation Expenses").
- account_name_zh: the same account name translated into natural Simplified Chinese.
- description: one short plain-language sentence explaining what this account is for.

Reply with ONLY a JSON object, no markdown fences, no explanation, in ONE of these two exact shapes:
{"match": true, "matched_account_code": "<code from the existing accounts list above>"}
or
{"match": false, "account_type": "...", "group_name": "...", "category_name": "..." or null, "account_name": "...", "account_name_zh": "...", "description": "..."}
PROMPT;

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'content-type' => 'application/json',
            ])->timeout(30)->post(self::API_URL, [
                'model' => self::MODEL,
                'max_tokens' => 512,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('COA Chat classify request failed: ' . $e->getMessage());
            return ['status' => 'ERROR', 'message' => 'Could not reach the AI service right now — please try again in a moment.'];
        }

        if ($response->failed()) {
            Log::warning('COA Chat classify failed (' . $response->status() . '): ' . $response->body());
            if (in_array($response->status(), [401, 403])) {
                return ['status' => 'ERROR', 'message' => 'The API key was rejected, or the account has run out of credit.'];
            }
            return ['status' => 'ERROR', 'message' => 'The AI service could not respond right now — please try again.'];
        }

        $text = trim((string) $response->json('content.0.text'));
        $text = preg_replace('/^```(json)?/', '', $text);
        $text = preg_replace('/```$/', '', trim($text));
        $decoded = json_decode(trim($text), true);

        if (! is_array($decoded)) {
            return ['status' => 'ERROR', 'message' => 'The AI service returned an unexpected response — please try again.'];
        }

        // MATCH -- never trust the AI's claim on its own; the code it
        // named must actually exist in the list we gave it.
        if (! empty($decoded['match'])) {
            $matchedCode = trim((string) ($decoded['matched_account_code'] ?? ''));
            $matched = $existingAccounts->firstWhere('account_code', $matchedCode);
            if ($matched) {
                return ['status' => 'MATCH', 'account' => (array) $matched];
            }
            // AI claimed a match but named a code that doesn't exist --
            // fall through and treat as a fresh classification instead
            // of silently trusting an unverifiable claim.
            Log::warning("COA Chat: AI claimed a match on unverifiable code '{$matchedCode}' -- falling back to new-account classification.");
        }

        $accountType = strtoupper(trim((string) ($decoded['account_type'] ?? '')));
        if (! array_key_exists($accountType, self::TYPE_BASE_CODE)) {
            return ['status' => 'ERROR', 'message' => 'The AI service returned an unexpected response — please try again.'];
        }

        $groupName = trim((string) ($decoded['group_name'] ?? ''));
        $group = $groups->first(fn ($g) => $g->account_type === $accountType && mb_strtolower($g->group_name) === mb_strtolower($groupName));
        if (! $group) {
            // Anti-fabrication safety net: the AI must only use a real
            // group name, but if it slipped, fall back to this type's
            // seeded "Other ..." catch-all group rather than leaving it
            // blank or trusting an invented name.
            $fallbackNames = [
                'ASSET' => 'Other Assets', 'LIABILITY' => 'Other Liabilities', 'EQUITY' => 'Other Reserves',
                'INCOME' => 'Other Income', 'EXPENSE' => 'Other Expenses',
            ];
            $group = $groups->first(fn ($g) => $g->account_type === $accountType && $g->group_name === $fallbackNames[$accountType]);
        }

        $categoryName = trim((string) ($decoded['category_name'] ?? ''));
        $category = $categoryName !== '' && strtolower($categoryName) !== 'null'
            ? $categories->first(fn ($c) => mb_strtolower($c->category_name) === mb_strtolower($categoryName))
            : null;

        $accountName = trim((string) ($decoded['account_name'] ?? ''));
        $accountNameZh = trim((string) ($decoded['account_name_zh'] ?? ''));
        $desc = trim((string) ($decoded['description'] ?? ''));
        if ($accountName === '') {
            return ['status' => 'ERROR', 'message' => 'The AI service returned an unexpected response — please try again.'];
        }

        $accountCode = $this->nextAccountCode($existingAccounts, $accountType);

        return [
            'status' => 'NEW',
            'proposal' => [
                'account_type' => $accountType,
                'account_group_id' => $group->group_id ?? '',
                'account_group_name' => $group->group_name ?? '',
                'account_category_id' => $category->category_id ?? '',
                'account_category_name' => $category->category_name ?? '',
                'account_code' => $accountCode,
                'account_name' => $accountName,
                'account_name_zh' => $accountNameZh,
                'description' => $desc,
            ],
        ];
    }

    // Deterministic, non-AI code generation (spec Section 6: "The AI
    // must never invent a GL Code"): the next unused number within the
    // same account_type, one higher than the highest existing code of
    // that type (so it never collides even if some codes were skipped
    // or deleted), or this type's standard starting block
    // (1000/2000/3000/4000/5000, same convention already used by
    // CbeAccountingService::ensureChartOfAccounts) if this is the very
    // first account ever of that type.
    private function nextAccountCode($existingAccounts, string $accountType): string
    {
        $sameType = $existingAccounts->where('account_type', $accountType)
            ->pluck('account_code')
            ->filter(fn ($c) => ctype_digit((string) $c))
            ->map(fn ($c) => (int) $c);

        if ($sameType->isEmpty()) {
            return (string) (self::TYPE_BASE_CODE[$accountType] + 1);
        }

        return (string) ($sameType->max() + 1);
    }
}
