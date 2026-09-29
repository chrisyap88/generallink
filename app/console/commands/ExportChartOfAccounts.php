<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportChartOfAccounts extends Command
{
    protected $signature = 'coa:export';
    protected $description = 'One-off: export the full Chart of Accounts to a CSV in the project folder for review';

    public function handle()
    {
        $rows = DB::table('cbe_chart_of_accounts')
            ->orderBy('account_code')
            ->get([
                'account_id', 'account_code', 'account_name', 'account_type',
                'parent_account_id', 'account_group_id', 'account_category_id',
                'cbe_node_id', 'is_posting_account', 'is_control_account',
                'is_active', 'is_system', 'display_order',
            ]);

        $path = base_path('coa-export.csv');
        $fh = fopen($path, 'w');
        fputcsv($fh, ['account_id', 'account_code', 'account_name', 'account_type', 'parent_account_id', 'account_group_id', 'account_category_id', 'cbe_node_id', 'is_posting_account', 'is_control_account', 'is_active', 'is_system', 'display_order']);
        foreach ($rows as $r) {
            fputcsv($fh, [
                $r->account_id, $r->account_code, $r->account_name, $r->account_type,
                $r->parent_account_id, $r->account_group_id, $r->account_category_id,
                $r->cbe_node_id, $r->is_posting_account, $r->is_control_account,
                $r->is_active, $r->is_system, $r->display_order,
            ]);
        }
        fclose($fh);

        $this->info('Exported '.count($rows)." accounts to $path");
        return 0;
    }
}
