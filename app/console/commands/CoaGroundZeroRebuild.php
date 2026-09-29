<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CoaGroundZeroRebuild extends Command
{
    protected $signature = 'coa:ground-zero-rebuild';
    protected $description = 'One-off: wipe the Chart of Accounts (all orgs) + old sample journal lines, insert the new bilingual chart + account categories';

    public function handle()
    {
        DB::transaction(function () {
            $now = now();

            // 1. Remove the 2 known test journal entries (the bank-statement
            // trial import) + their 4 lines, since they point at accounts
            // about to be deleted.
            $testJournalIds = ['13975bdc-da55-4077-aba2-4eb39e9d6b29', 'cbe2ece9-277a-4d62-960f-c065d0016e82'];
            $linesDeleted = DB::table('cbe_journal_lines')->whereIn('journal_id', $testJournalIds)->delete();
            $entriesDeleted = DB::table('cbe_journal_entries')->whereIn('journal_id', $testJournalIds)->delete();
            $this->info("Removed $linesDeleted test journal line(s) and $entriesDeleted test journal entry(ies).");

            // 2. Wipe every existing Chart of Accounts row (confirmed sample
            // data, across every organisation). Null out self-referencing
            // parent_account_id first so the delete has nothing to trip on.
            DB::table('cbe_chart_of_accounts')->update(['parent_account_id' => null]);
            $oldCount = DB::table('cbe_chart_of_accounts')->count();
            DB::table('cbe_chart_of_accounts')->delete();
            $this->info("Deleted $oldCount old chart-of-accounts row(s).");

            // 3. Insert the new Account Categories (existing 10 generic ones
            // are left alone — this just adds the new coded list).
            $categories = require base_path('coa_categories_data.php');
            $categoryIdByCode = [];
            foreach ($categories as $i => $cat) {
                $id = (string) Str::uuid();
                $categoryIdByCode[$cat['code']] = $id;
                DB::table('cbe_account_categories')->insert([
                    'category_id' => $id,
                    'category_code' => $cat['code'],
                    'group_label_id' => null,
                    'category_name' => $cat['name_en'],
                    'category_name_zh' => $cat['name_zh'],
                    'is_active' => true,
                    'display_order' => $i,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $this->info('Inserted '.count($categoryIdByCode).' account categories.');

            // 4. Insert the new Chart of Accounts — headers first (so items
            // can link parent_account_id to their group's header), then items.
            $accounts = require base_path('coa_accounts_data.php');
            $idByCode = [];
            $headerIdByGroupKey = [];

            foreach ($accounts as $row) {
                if (! $row['is_header']) {
                    continue;
                }
                $id = (string) Str::uuid();
                $idByCode[$row['code']] = $id;
                $headerIdByGroupKey[$row['type'].'|'.$row['group_no']] = $id;
                DB::table('cbe_chart_of_accounts')->insert([
                    'account_id' => $id,
                    'group_label_id' => 'e3851037-933a-46f2-83d2-4c99d88bbdca', // Chris's real org (Persekutuan Pertubuhan Agama Tao Malaysia)
                    'cbe_node_id' => null,
                    'account_code' => $row['code'],
                    'account_name' => $row['name_en'],
                    'account_name_zh' => $row['name_zh'],
                    'account_type' => $row['type'],
                    'account_group_id' => null,
                    'account_category_id' => $categoryIdByCode[$row['category_code']] ?? null,
                    'normal_balance' => in_array($row['type'], ['ASSET', 'EXPENSE']) ? 'DEBIT' : 'CREDIT',
                    'is_posting_account' => false,
                    'is_control_account' => true,
                    'description' => null,
                    'created_by' => null,
                    'parent_account_id' => null,
                    'is_system' => true,
                    'is_active' => true,
                    'display_order' => (int) $row['code'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($accounts as $row) {
                if ($row['is_header']) {
                    continue;
                }
                $id = (string) Str::uuid();
                $idByCode[$row['code']] = $id;
                $parentId = $headerIdByGroupKey[$row['type'].'|'.$row['group_no']] ?? null;
                DB::table('cbe_chart_of_accounts')->insert([
                    'account_id' => $id,
                    'group_label_id' => 'e3851037-933a-46f2-83d2-4c99d88bbdca', // Chris's real org (Persekutuan Pertubuhan Agama Tao Malaysia)
                    'cbe_node_id' => null,
                    'account_code' => $row['code'],
                    'account_name' => $row['name_en'],
                    'account_name_zh' => $row['name_zh'],
                    'account_type' => $row['type'],
                    'account_group_id' => null,
                    'account_category_id' => $categoryIdByCode[$row['category_code']] ?? null,
                    'normal_balance' => in_array($row['type'], ['ASSET', 'EXPENSE']) ? 'DEBIT' : 'CREDIT',
                    'is_posting_account' => true,
                    'is_control_account' => str_contains($row['name_en'], '(Control)'),
                    'description' => null,
                    'created_by' => null,
                    'parent_account_id' => $parentId,
                    'is_system' => true,
                    'is_active' => true,
                    'display_order' => (int) $row['code'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->info('Inserted '.count($idByCode).' new chart-of-accounts rows.');

            file_put_contents(base_path('coa-new-ids.json'), json_encode($idByCode, JSON_PRETTY_PRINT));
        });

        $this->info('Done. Run: php artisan view:clear && php artisan cache:clear');

        return 0;
    }
}
