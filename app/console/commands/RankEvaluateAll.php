<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

// RETIRED 31 Jul 2026 — per Chris: "REMOVE THIS RANK PROMOTION, IS
// CONFUSING." This command used to auto-assign each agent's rank_id
// individually (RankPromotionService), which conflicts with the
// bucket-level (Role + Organization Rewards Group) rank model Rank
// Assignment now uses. Kept as a harmless stub — rather than deleting
// the file — so RUN_RANK_EVALUATION.bat still runs without an error,
// it just explains the feature is gone and points to Rank Assignment.
class RankEvaluateAll extends Command
{
    protected $signature = 'rank:evaluate-all';
    protected $description = '[RETIRED] This automatic per-agent rank evaluation has been removed — use Rank Assignment instead';

    public function handle(): int
    {
        $this->warn('Rank Promotion Rules / automatic rank evaluation has been retired.');
        $this->line('Rank is now set at the Role + Organization Rewards Group level, not per individual agent.');
        $this->line('Go to Master File Maintenance -> Rank Assignment in the app to set ranks instead.');
        return self::SUCCESS;
    }
}
