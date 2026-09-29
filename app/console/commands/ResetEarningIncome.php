<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 29 Jul 2026 — per Chris, step 3 of reorganizing sales
// transactions for a clean earning income simulation: wipe every
// commission_transactions row and zero every agent's commission
// wallet, so earning income can be recalculated cleanly once the
// Earning Income Structures (%) are properly set up per vendor+product.
//
// Deliberately requires typing "RESET" to confirm — this deletes real
// data and cannot be undone. sales_transactions themselves (the real
// customer policies) are NEVER touched by this command, only the
// commission_transactions table and agents.commission_balance.
// -------------------------------------------------------
class ResetEarningIncome extends Command
{
    protected $signature = 'commission:reset';
    protected $description = 'Delete all commission_transactions rows and zero every agent wallet balance — irreversible, requires confirmation';

    public function handle(): int
    {
        $rowCount = DB::table('commission_transactions')->count();
        $totalAmount = DB::table('commission_transactions')->sum('commission_amount');
        $walletTotal = DB::table('agents')->where('is_deleted', false)->sum('commission_balance');
        $walletAgents = DB::table('agents')->where('is_deleted', false)->where('commission_balance', '>', 0)->count();

        $this->warn('========================================================');
        $this->warn(' THIS WILL PERMANENTLY DELETE EARNING INCOME DATA');
        $this->warn('========================================================');
        $this->line("  commission_transactions rows to be deleted : {$rowCount} (totalling RM " . number_format($totalAmount, 2) . ')');
        $this->line("  Agent wallet balances to be zeroed          : {$walletAgents} agent(s), currently summing to RM " . number_format($walletTotal, 2));
        $this->line('  sales_transactions (the actual customer policies) will NOT be touched.');
        $this->line('');

        if ($rowCount === 0 && $walletAgents === 0) {
            $this->info('Nothing to reset — there is no earning income data on file.');
            return self::SUCCESS;
        }

        $confirm = $this->ask('Type RESET (in capitals) to proceed, or anything else to cancel');
        if ($confirm !== 'RESET') {
            $this->line('Cancelled — nothing was changed.');
            return self::SUCCESS;
        }

        DB::transaction(function () {
            DB::table('commission_transactions')->delete();
            DB::table('agents')->where('is_deleted', false)->update(['commission_balance' => 0]);
        });

        $this->info('Done.');
        $this->line("  Deleted {$rowCount} commission_transactions row(s).");
        $this->line("  Reset {$walletAgents} agent wallet(s) to RM 0.");
        $this->line('');
        $this->line('Next steps: make sure every vendor+product combination has a correct Earning Income');
        $this->line('Structure (%) set up, then re-confirm each sales transaction to recalculate earning income cleanly.');

        return self::SUCCESS;
    }
}
