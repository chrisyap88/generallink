<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CoaGroupCheck extends Command
{
    protected $signature = 'coa:group-check';
    protected $description = 'One-off: check group_label_id / cbe_node_id distribution on the existing Chart of Accounts';

    public function handle()
    {
        $rows = DB::table('cbe_chart_of_accounts')
            ->select('group_label_id', 'cbe_node_id', DB::raw('count(*) as c'))
            ->groupBy('group_label_id', 'cbe_node_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = ($r->group_label_id ?? 'NULL').' | '.($r->cbe_node_id ?? 'NULL').' | '.$r->c;
        }

        $labels = DB::table('group_labels')->get(['group_label_id', 'group_name']);
        foreach ($labels as $l) {
            $out[] = 'LABEL: '.$l->group_label_id.' = '.$l->group_name;
        }

        file_put_contents(base_path('coa-group-check.txt'), implode("\n", $out));
        $this->info('Wrote coa-group-check.txt');
        foreach ($out as $line) { $this->line($line); }
        return 0;
    }
}
