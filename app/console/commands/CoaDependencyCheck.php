<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CoaDependencyCheck extends Command
{
    protected $signature = 'coa:dependency-check';
    protected $description = 'One-off: count rows in every table that has a FK to cbe_chart_of_accounts';

    public function handle()
    {
        $tables = [
            'cbe_transaction_categories' => 'chart_account_id',
            'cbe_bank_accounts' => 'gl_account_id',
            'cbe_petty_cash_funds' => 'gl_account_id',
            'cbe_recurring_journal_template_lines' => 'account_id',
            'cbe_suppliers' => 'default_ap_account_id',
            'cbe_purchase_budgets' => 'account_id',
            'cbe_asset_categories' => 'fixed_asset_account_id',
            'cbe_bank_reconciliation_rules' => 'default_gl_account_id',
        ];
        foreach ($tables as $table => $col) {
            if (! Schema::hasTable($table)) {
                $this->line("$table: TABLE DOES NOT EXIST");
                continue;
            }
            if (! Schema::hasColumn($table, $col)) {
                $this->line("$table.$col: COLUMN DOES NOT EXIST");
                continue;
            }
            $total = DB::table($table)->count();
            $withAccount = DB::table($table)->whereNotNull($col)->count();
            $this->line("$table: $total rows total, $withAccount with $col set");
        }
        return 0;
    }
}
