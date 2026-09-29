<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CoaUsageCheck extends Command
{
    protected $signature = 'coa:usage-check';
    protected $description = 'One-off: check whether any GL postings already exist against chart of accounts rows';

    public function handle()
    {
        $out = [];
        if (Schema::hasTable('cbe_journal_lines')) {
            $count = DB::table('cbe_journal_lines')->count();
            $distinctAccounts = DB::table('cbe_journal_lines')->distinct('account_id')->count('account_id');
            $out[] = "cbe_journal_lines: $count rows, $distinctAccounts distinct accounts referenced";
        } else {
            $out[] = "cbe_journal_lines: table not found";
        }
        if (Schema::hasTable('cbe_opening_balances')) {
            $count = DB::table('cbe_opening_balances')->count();
            $out[] = "cbe_opening_balances: $count rows";
        }
        $path = base_path('coa-usage-check.txt');
        file_put_contents($path, implode("\n", $out));
        foreach ($out as $line) { $this->info($line); }
        return 0;
    }
}
