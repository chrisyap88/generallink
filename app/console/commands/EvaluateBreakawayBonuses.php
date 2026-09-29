<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\HierarchyService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 25 Jul 2026 — Breakaway Bonus evaluation. Per Chris's example:
// Chris Yap earns nothing from Amy Tan's group (her breakaway link)
// until her ENTIRE group (her + her whole downline) hits the configured
// target within a period. This command checks every active breakaway
// link, and the moment a period's target is met, creates ONE claim
// voucher row for that period and notifies the original Group Leader
// (e.g. Chris Yap) — it never touches a wallet, since the real payment
// is claimed by Chris Yap separately from the insurance vendor.
//
// Deliberately kept fully separate from CommissionEngine/
// commission_transactions — per Chris's "i dont wants bugs" priority.
// Safe to run any time (e.g. daily via schedule): a period that's
// already produced a claim (bbc_link_period_unique) is never
// re-evaluated, and a period that hasn't hit target yet simply produces
// nothing until it does.
class EvaluateBreakawayBonuses extends Command
{
    protected $signature = 'breakaway:evaluate-bonuses';
    protected $description = 'Check every active Breakaway Bonus link and create a claim voucher the moment a period\'s target is reached';

    public function handle(HierarchyService $hierarchy, NotificationService $notifier): int
    {
        $links = DB::table('breakaway_links')->where('is_active', true)->get();

        $this->info("Checking {$links->count()} active breakaway link(s)...");
        $created = 0;

        foreach ($links as $link) {
            $promotedGl = Agent::find($link->promoted_gl_agent_id);
            if (! $promotedGl || $promotedGl->is_deleted || $promotedGl->status !== 'ACTIVE') {
                continue; // promoted GL no longer active — nothing to evaluate
            }
            if ($promotedGl->role !== 'GROUP_LEADER') {
                continue; // demoted back since — breakaway no longer applies
            }

            $rule = $this->resolveRule($promotedGl->group_label_id);
            if (! $rule) {
                continue; // nothing configured for this group (or System Default) — skip
            }

            $promotedAt = Carbon::parse($link->promoted_at);
            $periodMonths = (int) $rule->period_months;
            $monthsSince = $promotedAt->diffInMonths(now());
            $periodsElapsed = intdiv($monthsSince, $periodMonths);
            $periodStart = $promotedAt->copy()->addMonths($periodsElapsed * $periodMonths);

            // One claim per (link, period_start) — already handled this
            // cycle (whether it hit target or not) if a row exists.
            $exists = DB::table('breakaway_bonus_claims')
                ->where('link_id', $link->link_id)
                ->where('period_start', $periodStart->toDateString())
                ->exists();
            if ($exists) {
                continue;
            }

            $achieved = $hierarchy->teamVolumeBetween($promotedGl, $rule->target_metric, $periodStart, now());

            if ($achieved < (float) $rule->target_amount) {
                continue; // target not yet reached this period
            }

            $bonusAmount = round($achieved * ((float) $rule->bonus_pct / 100), 2);
            $claimId = (string) Str::uuid();

            DB::table('breakaway_bonus_claims')->insert([
                'claim_id'             => $claimId,
                'link_id'              => $link->link_id,
                'promoted_gl_agent_id' => $link->promoted_gl_agent_id,
                'original_gl_agent_id' => $link->original_gl_agent_id,
                'rule_id'              => $rule->rule_id,
                'period_start'         => $periodStart->toDateString(),
                'period_end'           => now()->toDateString(),
                'target_metric'        => $rule->target_metric,
                'target_amount'        => $rule->target_amount,
                'achieved_amount'      => $achieved,
                'bonus_pct'            => $rule->bonus_pct,
                'bonus_amount'         => $bonusAmount,
                'status'               => 'PENDING',
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            $originalGl = Agent::find($link->original_gl_agent_id);
            if ($originalGl) {
                $metricLabel = $rule->target_metric === 'PREMIUM' ? 'Premium' : 'Earning Income';
                $notifier->notify(
                    [$originalGl],
                    'BREAKAWAY_BONUS_ACHIEVED',
                    'Breakaway Bonus Target Achieved',
                    "{$promotedGl->full_name}'s group has reached RM" . number_format($achieved, 2)
                        . " in {$metricLabel}, meeting the breakaway target of RM" . number_format($rule->target_amount, 2)
                        . ". You are entitled to a bonus of RM" . number_format($bonusAmount, 2) . " ({$rule->bonus_pct}%)."
                        . ' A claim voucher is ready under Breakaway Bonus Claims — please file your claim with the insurance agency.',
                    $link->promoted_gl_agent_id
                );
            }

            $created++;
            $this->line("  -> Claim created: {$promotedGl->full_name} -> RM" . number_format($bonusAmount, 2) . ' for ' . ($originalGl->full_name ?? $link->original_gl_agent_id));
        }

        $this->info("Done. {$created} new claim(s) created.");

        return self::SUCCESS;
    }

    // Own group's active rule if it has one, else System Default's
    // active rule (group_label_id IS NULL) — same fallback pattern as
    // Promotion & Demotion Rules.
    private function resolveRule(?string $groupLabelId): ?object
    {
        $own = DB::table('breakaway_bonus_rules')
            ->where('group_label_id', $groupLabelId)
            ->where('is_active', true)
            ->first();
        if ($own) {
            return $own;
        }
        if ($groupLabelId === null) {
            return null; // System Default itself has nothing configured
        }

        return DB::table('breakaway_bonus_rules')
            ->whereNull('group_label_id')
            ->where('is_active', true)
            ->first();
    }
}
