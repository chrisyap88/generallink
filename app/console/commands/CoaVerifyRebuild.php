<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CoaVerifyRebuild extends Command
{
    protected $signature = 'coa:verify-rebuild';
    protected $description = 'One-off: sanity-check the new chart of accounts (counts, orphans, header/parent wiring)';

    public function handle()
    {
        $groupLabelId = 'e3851037-933a-46f2-83d2-4c99d88bbdca';

        $total = DB::table('cbe_chart_of_accounts')->where('group_label_id', $groupLabelId)->count();
        $this->line("Total accounts for the org: $total");

        $byType = DB::table('cbe_chart_of_accounts')->where('group_label_id', $groupLabelId)
            ->select('account_type', DB::raw('count(*) as c'))->groupBy('account_type')->get();
        foreach ($byType as $r) {
            $this->line("  $r->account_type: $r->c");
        }

        $headers = DB::table('cbe_chart_of_accounts')->where('group_label_id', $groupLabelId)->where('is_posting_account', false)->count();
        $items = DB::table('cbe_chart_of_accounts')->where('group_label_id', $groupLabelId)->where('is_posting_account', true)->count();
        $this->line("Headers (non-posting): $headers, Items (posting): $items");

        // Every non-header should have a parent that exists and is itself a header
        $orphanItems = DB::table('cbe_chart_of_accounts as a')
            ->where('a.group_label_id', $groupLabelId)
            ->where('a.is_posting_account', true)
            ->whereNotExists(function ($q) use ($groupLabelId) {
                $q->select(DB::raw(1))->from('cbe_chart_of_accounts as p')
                    ->whereColumn('p.account_id', 'a.parent_account_id')
                    ->where('p.group_label_id', $groupLabelId)
                    ->where('p.is_posting_account', false);
            })
            ->count();
        $this->line("Items with no valid header parent: $orphanItems");

        // Category tagging coverage
        $noCategory = DB::table('cbe_chart_of_accounts')->where('group_label_id', $groupLabelId)->whereNull('account_category_id')->count();
        $this->line("Rows with no account_category_id: $noCategory");

        // Duplicate code check
        $dupes = DB::table('cbe_chart_of_accounts')->where('group_label_id', $groupLabelId)
            ->select('account_code', DB::raw('count(*) as c'))->groupBy('account_code')->having('c', '>', 1)->get();
        $this->line('Duplicate account codes: '.$dupes->count());

        // The 13 constants should each resolve to exactly one live account
        $constants = [
            '101014' => 'CASH_CODE', '201005' => 'AP_CODE', '301005' => 'FUND_BALANCE_CODE',
            '515002' => 'UNCATEGORISED_EXPENSE_CODE', '102009' => 'AR_CODE', '105017' => 'FIXED_ASSET_CODE',
            '106008' => 'ACCUM_DEPRECIATION_CODE', '411003' => 'UNCATEGORISED_INCOME_CODE',
            '509008' => 'DEPRECIATION_EXPENSE_CODE', '509007' => 'DISPOSAL_GAIN_LOSS_CODE',
            '102001' => 'PLEDGE_RECEIVABLE_CODE', '507001' => 'BANK_CHARGES_EXPENSE_CODE', '410001' => 'BANK_INTEREST_INCOME_CODE',
        ];
        foreach ($constants as $code => $label) {
            $exists = DB::table('cbe_chart_of_accounts')->where('group_label_id', $groupLabelId)->where('account_code', $code)->exists();
            $this->line(($exists ? 'OK  ' : 'MISSING ').$label.' ('.$code.')');
        }

        return 0;
    }
}
