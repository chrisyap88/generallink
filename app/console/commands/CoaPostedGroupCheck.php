<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CoaPostedGroupCheck extends Command
{
    protected $signature = 'coa:posted-group-check';
    protected $description = 'One-off: confirm group_label_id of the 3 posted accounts';

    public function handle()
    {
        $ids = ['dd81be68-1427-433e-bdc4-361cf806d43e', '506b6057-c8ea-4c06-a83f-96ba4e9847f8', '9e6dd8c2-3046-4380-9f15-1e30921c858b'];
        $rows = DB::table('cbe_chart_of_accounts')->whereIn('account_id', $ids)->get(['account_id', 'account_code', 'group_label_id', 'cbe_node_id']);
        foreach ($rows as $r) {
            $this->line($r->account_id.' | '.$r->account_code.' | group='.$r->group_label_id.' | node='.($r->cbe_node_id ?? 'NULL'));
        }
        return 0;
    }
}
