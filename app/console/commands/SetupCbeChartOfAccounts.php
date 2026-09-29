<?php

namespace App\Console\Commands;

use App\Services\CbeAccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// NEW 25 Aug 2026 — one-time (but safe to re-run) setup: creates the
// standard Chart of Accounts (Cash, Accounts Payable, Fund Balance,
// Uncategorised Expense, plus one Income/Expense account per existing
// category) for every CBE community already in the system, and for the
// shared platform-wide default set (group_label_id = null). Run this
// once after migrating so the Finance dashboard has real numbers
// straight away instead of waiting for someone to first open the
// Accounting screens (which also auto-create it lazily, just later).
class SetupCbeChartOfAccounts extends Command
{
    protected $signature = 'cbe:setup-chart-of-accounts';
    protected $description = 'Create the standard Chart of Accounts for every CBE community (idempotent — safe to re-run).';

    public function handle(): int
    {
        CbeAccountingService::ensureChartOfAccounts(null);
        $this->info('Shared platform-wide default Chart of Accounts ready.');

        $groups = DB::table('group_labels')->where('group_type', 'CBE')->get();
        foreach ($groups as $group) {
            CbeAccountingService::ensureChartOfAccounts($group->group_label_id);
            $this->info('  ' . $group->group_name . ' — Chart of Accounts ready.');
        }

        $this->info('Done — ' . $groups->count() . ' CBE community/communities set up.');
        return self::SUCCESS;
    }
}
