<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// NEW 31 Jul 2026 — fixes a specific mismatch Chris hit: migrations
// 2026_07_31_000003 through 000007 physically created their tables/
// columns in the database (confirmed working earlier, incl. the index-
// name-too-long fix), but the `migrations` bookkeeping table never got
// a row recorded for them — so `php artisan migrate` tries to re-run
// them and fails with "table already exists" (SQLSTATE 1050).
//
// This command does NOT create or alter any schema. For each of the 5
// migrations below, it checks — using Schema::hasTable()/hasColumn(),
// never assumed — whether the actual table/column that migration is
// responsible for really exists. Only if the check passes does it
// insert one row into `migrations` marking it done, so a future
// `php artisan migrate` correctly skips it and only runs what's
// genuinely still pending (000008 onward). Anything that fails its
// check is left alone and reported, never silently marked.
class BackfillMigrationRecords extends Command
{
    protected $signature = 'migrations:backfill-verified';
    protected $description = 'Safely mark 2026_07_31_000003-000007 as already run, but only after verifying each one\'s actual table/column exists';

    public function handle(): int
    {
        $checks = [
            '2026_07_31_000003_create_agent_commission_overrides' => fn () => Schema::hasTable('agent_commission_overrides'),
            '2026_07_31_000004_add_override_id_to_commission_transactions' => fn () => Schema::hasColumn('commission_transactions', 'override_id'),
            '2026_07_31_000005_add_is_rank_only_to_commission_structures' => fn () => Schema::hasColumn('commission_structures', 'is_rank_only'),
            '2026_07_31_000006_create_override_members' => fn () => Schema::hasTable('override_members') && Schema::hasTable('override_member_eligibility_rules'),
            '2026_07_31_000007_create_override_commission_claims' => fn () => Schema::hasTable('override_commission_claims'),
        ];

        $alreadyRecorded = DB::table('migrations')->pluck('migration')->all();
        $nextBatch = (int) DB::table('migrations')->max('batch') + 1;

        $this->info('Checking each migration against what actually exists in the database...');
        $this->line('');

        $marked = 0;
        $skippedAlready = 0;
        $failed = [];

        foreach ($checks as $migrationName => $check) {
            if (in_array($migrationName, $alreadyRecorded, true)) {
                $this->line("  [already recorded] {$migrationName}");
                $skippedAlready++;
                continue;
            }

            if ($check()) {
                DB::table('migrations')->insert([
                    'migration' => $migrationName,
                    'batch'     => $nextBatch,
                ]);
                $this->info("  [MARKED DONE — verified table/column exists] {$migrationName}");
                $marked++;
            } else {
                $this->error("  [NOT MARKED — table/column does NOT exist] {$migrationName}");
                $failed[] = $migrationName;
            }
        }

        $this->line('');
        $this->info('========================================================');
        $this->info(" RESULT: {$marked} marked done, {$skippedAlready} already recorded, " . count($failed) . ' failed verification.');
        $this->info('========================================================');

        if (!empty($failed)) {
            $this->warn('The following did NOT verify — their table/column is genuinely missing.');
            $this->warn('Do not run php artisan migrate yet; tell Chris\'s assistant which ones these are:');
            foreach ($failed as $f) {
                $this->line("  - {$f}");
            }
            return self::FAILURE;
        }

        $this->line('Safe to run RUN_RESCOPE_RANKS_MIGRATION.bat now — only 000008 and 000009 remain pending.');
        return self::SUCCESS;
    }
}
