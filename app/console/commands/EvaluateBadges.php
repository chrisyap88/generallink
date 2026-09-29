<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\HierarchyService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 25 Jul 2026 — Growth & Outreach Center (task #212). Checks every
// active agent's recruit count / TL promotions / GL promotions / total
// network size against the badge thresholds and awards+notifies the
// moment one is crossed. UPDATED 25 Jul 2026 — per Chris, Sales/Earning
// Income badges were dropped (duplicated the KPI Dashboard's own Top 3
// Sales/Earning views) and replaced with leadership/network badges that
// aren't shown anywhere else. Purely read-only against existing data —
// never writes to commission_transactions, sales_transactions, or
// touches CommissionEngine. Safe to run any time (e.g. daily via
// schedule); a badge already earned is never re-evaluated (unique
// agent_id+badge_code).
class EvaluateBadges extends Command
{
    protected $signature = 'badges:evaluate';
    protected $description = 'Award any milestone badges (recruit count / TL promotions / GL promotions / network size) newly crossed';

    public function handle(NotificationService $notifier, HierarchyService $hierarchy): int
    {
        $badges = DB::table('badge_definitions')->where('is_active', true)->orderBy('sort_order')->get();
        $agents = Agent::where('status', 'ACTIVE')->where('is_deleted', false)
            ->whereIn('role', ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER'])->get();

        $this->info("Checking {$agents->count()} active agent(s) against {$badges->count()} badge(s)...");
        $created = 0;

        foreach ($agents as $agent) {
            $already = DB::table('agent_badges')->where('agent_id', $agent->agent_id)->pluck('badge_code')->all();

            $recruitCount = null;
            $tlPromotions = null;
            $glPromotions = null;
            $networkSize = null;
            $subtreeIds = null;

            foreach ($badges as $badge) {
                if (in_array($badge->badge_code, $already, true)) {
                    continue;
                }

                if ($subtreeIds === null && in_array($badge->badge_type, ['TL_PROMOTIONS', 'GL_PROMOTIONS', 'NETWORK_SIZE'], true)) {
                    $subtreeIds = array_values(array_diff($hierarchy->subtreeAgentIds($agent), [$agent->agent_id]));
                }

                $value = match ($badge->badge_type) {
                    'RECRUIT_COUNT' => $recruitCount ??= (float) Agent::where('parent_id', $agent->agent_id)
                        ->where('status', 'ACTIVE')->where('is_deleted', false)->count(),
                    'TL_PROMOTIONS' => $tlPromotions ??= (float) DB::table('role_history')
                        ->where('new_role', 'TEAM_LEADER')->whereIn('agent_id', $subtreeIds)->count(),
                    'GL_PROMOTIONS' => $glPromotions ??= (float) DB::table('role_history')
                        ->where('new_role', 'GROUP_LEADER')->whereIn('agent_id', $subtreeIds)->count(),
                    'NETWORK_SIZE' => $networkSize ??= (float) Agent::whereIn('agent_id', $subtreeIds)
                        ->where('status', 'ACTIVE')->where('is_deleted', false)->count(),
                    default => 0.0,
                };

                if ($value < (float) $badge->threshold_value) {
                    continue;
                }

                DB::table('agent_badges')->insert([
                    'badge_award_id' => (string) Str::uuid(),
                    'agent_id'       => $agent->agent_id,
                    'badge_code'     => $badge->badge_code,
                    'achieved_at'    => now(),
                    'notified_at'    => now(),
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);

                $notifier->notify(
                    [$agent],
                    'BADGE_EARNED',
                    "New Badge: {$badge->badge_name}",
                    "Congratulations — you've earned the \"{$badge->badge_name}\" badge ({$badge->description}). Keep it up!"
                );

                $created++;
                $this->line("  -> {$agent->full_name}: {$badge->badge_name}");
            }
        }

        $this->info("Done. {$created} new badge(s) awarded.");

        return self::SUCCESS;
    }
}
