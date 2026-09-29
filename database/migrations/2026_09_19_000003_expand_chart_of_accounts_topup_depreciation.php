<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 19 Sep 2026 -- TOP-UP for 2026_09_19_000002_expand_chart_of_
// accounts_categories_groups_accounts. That migration already RAN
// (confirmed via `php artisan migrate:status`) before Chris asked for
// full per-asset-type depreciation coverage -- Laravel remembers a
// migration by filename and never re-runs an already-applied one, so
// editing that file afterwards (adding 15 more depreciation accounts,
// and a stronger "same word already exists" duplicate check) had no
// effect on the live database. This migration is a separate file so it
// actually runs, and re-uses the exact same data file + same duplicate-
// safe logic, so it only inserts what's genuinely still missing.
//
// Net effect once this runs: every depreciable Fixed Asset type (Land is
// correctly excluded -- land isn't depreciated) has its own matching
// Accumulated Depreciation account and Depreciation Expense account --
// Building, Furniture & Fittings, Office Equipment, Computer & IT
// Equipment, Motor Vehicle, Machinery & Equipment, Electrical
// Installation & Appliance, Backup Power Generator, Solar Panel System,
// Religious & Ceremonial Assets.
//
// Same ADDITIVE guarantee as before: nothing existing is renamed,
// deleted, renumbered, or reclassified.
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

    // Same list as 000002, plus 'machinery'/'plant' added as an extra
    // safety net now that "Machinery & Equipment" wording is back in play.
    private const COLLISION_KEYWORDS = [
        'stationery', 'printing', 'photocop', 'photostat', 'postage', 'courier',
        'electric', 'water', 'sewerage', 'telephone', 'internet',
        'salary', 'salaries', 'wage', 'epf', 'socso',
        'donation', 'rental', 'insurance', 'maintenance', 'cleaning', 'utilit',
        'parking', 'petrol', 'transport', 'audit', 'legal', 'machinery', 'plant',
    ];

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
        // Deliberately not reversed automatically -- see 000002 for why.
    }
};
