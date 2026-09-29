<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// NEW 31 Jul 2026 — one-off diagnostic per Chris: migrate:status says the
// role_label_overrides rescope migration (2026_07_31_000012) already
// Ran, but the live site's error says the group_label_id column doesn't
// exist — a real contradiction. This prints exactly which database
// artisan is talking to and whether the column is actually there, so we
// stop guessing (cached config pointing somewhere else? a second
// database? something dropped the column after the fact?).
class DiagnoseRoleLabelOverridesSchema extends Command
{
    protected $signature = 'diagnose:role-label-overrides';
    protected $description = 'Print which database artisan is connected to and whether role_label_overrides.group_label_id actually exists there.';

    public function handle(): int
    {
        $this->info('--- Connection artisan is using ---');
        $this->line('Default connection: ' . config('database.default'));
        $this->line('Host: ' . config('database.connections.' . config('database.default') . '.host'));
        $this->line('Port: ' . config('database.connections.' . config('database.default') . '.port'));
        $this->line('Database name (from config): ' . config('database.connections.' . config('database.default') . '.database'));
        $this->line('Database name (actually connected to): ' . DB::connection()->getDatabaseName());

        $this->info('');
        $this->info('--- role_label_overrides table ---');
        $hasGroupLabelId = Schema::hasColumn('role_label_overrides', 'group_label_id');
        $hasOldGroupId = Schema::hasColumn('role_label_overrides', 'group_id');
        $this->line('Has group_label_id column: ' . ($hasGroupLabelId ? 'YES' : 'NO — this is the problem'));
        $this->line('Still has old group_id column: ' . ($hasOldGroupId ? 'YES (unexpected)' : 'NO (expected)'));

        $this->info('');
        $this->info('--- Last 8 migrations recorded as run (most recent first) ---');
        $rows = DB::table('migrations')->orderByDesc('id')->limit(8)->get();
        foreach ($rows as $row) {
            $this->line("batch {$row->batch} — {$row->migration}");
        }

        $this->info('');
        if (!$hasGroupLabelId) {
            $this->warn('The column is genuinely missing even though migrate:status says it ran. Most likely cause: a cached config (php artisan config:clear) or .env pointing artisan at a different database than the website uses. Run: php artisan config:clear — then re-check this diagnostic.');
        } else {
            $this->info('Column exists — if the website is still erroring, it may be reading a stale cached config too. Try: php artisan config:clear');
        }

        return self::SUCCESS;
    }
}
