<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 31 Jul 2026 — one-off helper per Chris: "similar as per your preview
// screen" — seeds a starter set of sample ranks into System Default
// (role_ranks.group_label_id = NULL) so the Organization Rank Hierarchy
// Structure screen isn't empty, using the same example names shown in the
// preview mockup: CEO + Regional Director under Mgt, Store Manager under
// Ops, Customer Services under Aff. These are just a starting point —
// Chris can rename, delete, or add more directly on the live screen using
// the +/-/pencil/up-down icons. Safe to run once; skips if System Default
// already has ranks (pass --force to add these on top anyway).
class SeedSampleSystemDefaultRanks extends Command
{
    protected $signature = 'ranks:seed-sample-default {--force : Add sample ranks even if System Default already has some}';
    protected $description = 'Seed a starter set of sample ranks into System Default (Mgt/Ops/Aff), matching the preview example.';

    public function handle(): int
    {
        $existing = DB::table('role_ranks')->whereNull('group_label_id')->count();

        if ($existing > 0 && !$this->option('force')) {
            $this->warn("System Default already has {$existing} rank(s) — nothing added, to avoid duplicates.");
            $this->line('Re-run with --force to add these sample ranks on top anyway, or just use the + button on the screen to add your own.');
            return self::SUCCESS;
        }

        $samples = [
            ['role' => 'GROUP_LEADER', 'rank_no' => '1', 'rank_name' => 'CEO'],
            ['role' => 'GROUP_LEADER', 'rank_no' => '2', 'rank_name' => 'Regional Director'],
            ['role' => 'TEAM_LEADER',  'rank_no' => '1', 'rank_name' => 'Store Manager'],
            ['role' => 'INTRODUCER',   'rank_no' => '1', 'rank_name' => 'Customer Services'],
        ];

        foreach ($samples as $s) {
            DB::table('role_ranks')->insert([
                'rank_id'        => (string) Str::uuid(),
                'role'           => $s['role'],
                'group_label_id' => null,
                'rank_no'        => $s['rank_no'],
                'rank_name'      => $s['rank_name'],
                'display_order'  => 0,
                'is_active'      => true,
                'created_by'     => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $this->line("Added: {$s['rank_name']} ({$s['role']})");
        }

        $this->info('Done. Open Organization Rank Hierarchy Structure (System Default) to see them.');
        return self::SUCCESS;
    }
}
