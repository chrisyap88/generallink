<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CoaUsageDetail extends Command
{
    protected $signature = 'coa:usage-detail';
    protected $description = 'One-off: list which specific chart-of-accounts rows already have journal postings';

    public function handle()
    {
        $ids = DB::table('cbe_journal_lines')->distinct()->pluck('account_id');
        $accounts = DB::table('cbe_chart_of_accounts')->whereIn('account_id', $ids)->get(['account_id','account_code','account_name']);
        foreach ($accounts as $a) {
            $this->info("{$a->account_code} | {$a->account_name} | {$a->account_id}");
        }
        return 0;
    }
}
