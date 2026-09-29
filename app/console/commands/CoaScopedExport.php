<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CoaScopedExport extends Command
{
    protected $signature = 'coa:scoped-export';
    protected $description = 'One-off: export only the accounts belonging to the real CBE group + the NULL/platform-default bucket';

    public function handle()
    {
        $rows = DB::table('cbe_chart_of_accounts')
            ->where(function ($q) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', 'e3851037-933a-46f2-83d2-4c99d88bbdca');
            })
            ->orderBy('group_label_id')->orderBy('account_code')
            ->get(['account_id', 'account_code', 'account_name', 'account_type', 'parent_account_id', 'group_label_id', 'cbe_node_id', 'is_posting_account', 'is_active', 'is_system', 'display_order']);

        $path = base_path('coa-scoped-export.csv');
        $fh = fopen($path, 'w');
        fputcsv($fh, ['account_id', 'account_code', 'account_name', 'account_type', 'parent_account_id', 'group_label_id', 'cbe_node_id', 'is_posting_account', 'is_active', 'is_system', 'display_order']);
        foreach ($rows as $r) {
            fputcsv($fh, [$r->account_id, $r->account_code, $r->account_name, $r->account_type, $r->parent_account_id, $r->group_label_id, $r->cbe_node_id, $r->is_posting_account, $r->is_active, $r->is_system, $r->display_order]);
        }
        fclose($fh);
        $this->info('Exported '.count($rows)." rows to $path");
        return 0;
    }
}
