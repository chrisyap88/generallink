<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CoaCatPeek extends Command
{
    protected $signature = 'coa:cat-peek';
    protected $description = 'One-off: list transaction categories (name/type) grouped by group_label_id';

    public function handle()
    {
        $rows = DB::table('cbe_transaction_categories')->orderBy('group_label_id')->orderBy('type')->orderBy('category_name')->get(['category_name', 'category_name_zh', 'type', 'group_label_id', 'chart_account_id']);
        $path = base_path('coa-cat-peek.csv');
        $fh = fopen($path, 'w');
        fputcsv($fh, ['category_name', 'category_name_zh', 'type', 'group_label_id', 'chart_account_id']);
        foreach ($rows as $r) {
            fputcsv($fh, [$r->category_name, $r->category_name_zh, $r->type, $r->group_label_id, $r->chart_account_id]);
        }
        fclose($fh);
        $this->info('Wrote '.count($rows).' rows to coa-cat-peek.csv');
        return 0;
    }
}
