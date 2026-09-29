<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 19 Sep 2026 -- per Chris: "REFURNISH NEW SET OF COA WITH MORE
// COMPREHENSIVE GL CODE, TYPE, CATEGORY GROUP" -- the original starter
// Chart of Accounts (from the 3 Sep 2026 Task #381 migration) was too
// shallow: e.g. Entertainment had no split between customer/guest vs
// staff refreshment, and Fixed Assets had no breakdown at all (Land,
// Building, Machinery, Electrical Appliance, Backup Generator, Solar
// Panel etc. all had to share one generic "Fixed Asset" category).
// Chris reviewed a proposal document for this (Proposed-Comprehensive-
// Chart-of-Accounts-v2.docx) and then asked for a large list of
// additional items on top (specific temple prayer/blessing ceremony
// income types, extra operating expenses) -- ALL of it is included here.
//
// This is purely ADDITIVE, in 3 layers:
// 1. New Categories -> cbe_account_categories, group_label_id = null
//    (shared platform default, same as the original 10). Skipped if a
//    category with the same name already exists, so this is safe to
//    run more than once.
// 2. Two new Groups -> cbe_account_groups: "Entertainment & Hospitality"
//    (EXPENSE) and "Religious Ceremony & Prayer Income" (INCOME) --
//    neither existing EXPENSE/INCOME group family covered these.
// 3. 121 new starter GL accounts -> cbe_chart_of_accounts, inserted for
//    EVERY existing CBE community (group_labels.group_type = 'CBE'),
//    at cbe_node_id = null (shared community-wide, same as every other
//    core/default account). Per this system's own standing rule
//    ("never hardcode business data, generate 1-for-1 from the
//    community's existing categories" -- see CbeAccountingService),
//    these are only added for Chris's OWN real communities, by his own
//    explicit confirmed request -- NOT silently baked into new-community
//    onboarding for every future customer. GL Codes are never
//    hardcoded: each account gets the next free number in its Type's
//    block, exactly the same deterministic rule already used by
//    CoaChatAssistantService::nextAccountCode() and
//    CbeAccountingService::ensureChartOfAccounts(), so nothing can ever
//    clash with a code a community already has. Skipped per-account if
//    that community already has an account with the same name and type
//    (e.g. added manually before this ran), so this is also safe to run
//    more than once.
//
// Nothing existing is renamed, deleted, renumbered, or reclassified.
return new class extends Migration
{
    private const TYPE_BASE_CODE = [
        'ASSET' => 1000, 'LIABILITY' => 2000, 'EQUITY' => 3000, 'INCOME' => 4000, 'EXPENSE' => 5000,
    ];

    private const NORMAL_BALANCE = [
        'ASSET' => 'DEBIT', 'EXPENSE' => 'DEBIT',
        'LIABILITY' => 'CREDIT', 'EQUITY' => 'CREDIT', 'INCOME' => 'CREDIT',
    ];

    private const NEW_GROUPS = [
        ['EXPENSE', 'Entertainment & Hospitality'],
        ['INCOME', 'Religious Ceremony & Prayer Income'],
    ];

    // NEW 19 Sep 2026 -- per Chris: "is there any duplicate like
    // stationary?" -- an EXACT name match (below) won't catch it if his
    // existing account is worded differently (e.g. he already has
    // "Stationery" but this migration's new account is named "Office
    // Supplies & Stationery"). These are the common everyday expense/
    // income words most likely to already exist as one of his own
    // accounts (auto-generated over time from his own transaction
    // categories) -- if BOTH an existing account and a new one share one
    // of these words, treat it as already covered and skip the new one,
    // rather than create two accounts for the same real-world thing.
    private const COLLISION_KEYWORDS = [
        'stationery', 'printing', 'photocop', 'photostat', 'postage', 'courier',
        'electric', 'water', 'sewerage', 'telephone', 'internet',
        'salary', 'salaries', 'wage', 'epf', 'socso',
        'donation', 'rental', 'insurance', 'maintenance', 'cleaning', 'utilit',
        'parking', 'petrol', 'transport', 'audit', 'legal',
    ];

    // [group_name (as shown to the user), category_name, account_name, account_name_zh, description]
    // group_name here is only used to pick which cbe_account_groups row
    // this starter account's account_group_id points to -- for the 2
    // brand-new groups above it matches NEW_GROUPS exactly; for
    // everything else it matches one of the original 29 seeded group
    // names from the 3 Sep 2026 migration.
    private function starterAccounts(): array
    {
        return require __DIR__ . '/data/2026_09_19_coa_expansion_data.php';
    }

    public function up(): void
    {
        $data = $this->starterAccounts();

        // ---- 1. New Categories (shared, group_label_id = null) --------
        $allCategoryNames = collect($data)->flatMap(fn ($rows) => collect($rows)->pluck(1))->unique()->values();

        $existingCategoryNames = DB::table('cbe_account_categories')
            ->whereNull('group_label_id')
            ->pluck('category_name')
            ->map(fn ($n) => mb_strtolower(trim($n)))
            ->all();

        $nextCategoryOrder = 1 + (int) DB::table('cbe_account_categories')->whereNull('group_label_id')->max('display_order');

        foreach ($allCategoryNames as $categoryName) {
            if (in_array(mb_strtolower(trim($categoryName)), $existingCategoryNames, true)) {
                continue;
            }
            DB::table('cbe_account_categories')->insert([
                'category_id' => (string) Str::uuid(),
                'group_label_id' => null,
                'category_name' => $categoryName,
                'category_name_zh' => null,
                'is_active' => true,
                'display_order' => $nextCategoryOrder++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ---- 2. New Groups (shared, group_label_id = null) -------------
        $nextGroupOrder = 1 + (int) DB::table('cbe_account_groups')->whereNull('group_label_id')->max('display_order');

        foreach (self::NEW_GROUPS as [$accountType, $groupName]) {
            $exists = DB::table('cbe_account_groups')
                ->whereNull('group_label_id')
                ->where('account_type', $accountType)
                ->whereRaw('LOWER(group_name) = ?', [mb_strtolower($groupName)])
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('cbe_account_groups')->insert([
                'group_id' => (string) Str::uuid(),
                'group_label_id' => null,
                'account_type' => $accountType,
                'group_name' => $groupName,
                'group_name_zh' => null,
                'is_active' => true,
                'display_order' => $nextGroupOrder++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Re-read groups/categories once now that the new ones exist, so
        // we can resolve each starter account's group_id/category_id by
        // name (the "(NEW GROUP)" suffix used in the source data is only
        // a label for the review document -- strip it before matching).
        $groups = DB::table('cbe_account_groups')
            ->whereNull('group_label_id')->where('is_active', true)
            ->get(['group_id', 'account_type', 'group_name']);
        $categories = DB::table('cbe_account_categories')
            ->whereNull('group_label_id')->where('is_active', true)
            ->get(['category_id', 'category_name']);

        $resolveGroup = function (string $accountType, string $groupLabel) use ($groups) {
            $clean = trim(str_ireplace('(NEW GROUP)', '', $groupLabel));
            return $groups->first(fn ($g) => $g->account_type === $accountType && mb_strtolower($g->group_name) === mb_strtolower($clean));
        };
        $resolveCategory = function (string $categoryName) use ($categories) {
            return $categories->first(fn ($c) => mb_strtolower($c->category_name) === mb_strtolower(trim($categoryName)));
        };

        // ---- 3. Starter GL accounts, per existing CBE community -------
        $groupLabelIds = DB::table('group_labels')->where('group_type', 'CBE')->pluck('group_label_id');

        foreach ($groupLabelIds as $groupLabelId) {
            $existingAccounts = DB::table('cbe_chart_of_accounts')
                ->where('group_label_id', $groupLabelId)
                ->get(['account_type', 'account_code', 'account_name']);

            $existingNamesByType = [];
            foreach ($existingAccounts as $a) {
                $existingNamesByType[$a->account_type][] = mb_strtolower(trim($a->account_name));
            }

            $maxCodeByType = [];
            foreach ($existingAccounts as $a) {
                if (ctype_digit((string) $a->account_code)) {
                    $code = (int) $a->account_code;
                    $type = $a->account_type;
                    if (! isset($maxCodeByType[$type]) || $code > $maxCodeByType[$type]) {
                        $maxCodeByType[$type] = $code;
                    }
                }
            }

            foreach ($data as $accountType => $rows) {
                foreach ($rows as [$groupLabel, $categoryName, $accountName, $accountNameZh, $description]) {
                    $lowerName = mb_strtolower(trim($accountName));
                    $existingOfType = $existingNamesByType[$accountType] ?? [];

                    $already = in_array($lowerName, $existingOfType, true);

                    if (! $already) {
                        // Secondary, fuzzy check: same key word already
                        // covered by an existing account of this type,
                        // even if worded differently.
                        foreach (self::COLLISION_KEYWORDS as $keyword) {
                            if (str_contains($lowerName, $keyword)) {
                                foreach ($existingOfType as $existingName) {
                                    if (str_contains($existingName, $keyword)) {
                                        $already = true;
                                        break 2;
                                    }
                                }
                            }
                        }
                    }

                    if ($already) {
                        continue;
                    }

                    $nextCode = isset($maxCodeByType[$accountType])
                        ? ++$maxCodeByType[$accountType]
                        : ($maxCodeByType[$accountType] = self::TYPE_BASE_CODE[$accountType] + 1);

                    $group = $resolveGroup($accountType, $groupLabel);
                    $category = $resolveCategory($categoryName);

                    DB::table('cbe_chart_of_accounts')->insert([
                        'account_id' => (string) Str::uuid(),
                        'group_label_id' => $groupLabelId,
                        'cbe_node_id' => null,
                        'account_code' => (string) $nextCode,
                        'account_name' => $accountName,
                        'account_name_zh' => $accountNameZh,
                        'account_type' => $accountType,
                        'account_group_id' => $group->group_id ?? null,
                        'account_category_id' => $category->category_id ?? null,
                        'normal_balance' => self::NORMAL_BALANCE[$accountType],
                        'is_posting_account' => true,
                        'is_control_account' => false,
                        'description' => $description,
                        'is_system' => false,
                        'is_active' => true,
                        'display_order' => $nextCode,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $existingNamesByType[$accountType][] = mb_strtolower(trim($accountName));
                }
            }
        }
    }

    public function down(): void
    {
        // Deliberately not reversed automatically: by the time anyone
        // runs `migrate:rollback`, real transactions may already be
        // posted against one of these new accounts, and deleting it out
        // from under a posted transaction would corrupt the ledger. If
        // this ever genuinely needs undoing, do it by hand in Chart of
        // Accounts (deactivate the specific accounts you no longer want)
        // rather than a blanket rollback.
    }
};
