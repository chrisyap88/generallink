<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\HierarchyService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 25 Jul 2026 — Growth & Outreach Center (task #211). Checks every
// currently-running contest and creates an award record the moment a
// participant hits the target — same "voucher only, never auto-credits
// a wallet" pattern as Breakaway Bonus. Safe to run any time (e.g.
// daily via schedule); a participant who already has an award row for a
// contest is never re-evaluated for it (unique contest_id+agent_id).
// Never touches commission_transactions or CommissionEngine.
class EvaluateRecruitmentContests extends Command
{
    protected $signature = 'contests:evaluate';
    protected $description = 'Check every currently-running Recruitment Contest and award anyone who has hit the target';

    public function handle(HierarchyService $hierarchy, NotificationService $notifier): int
    {
        $today = now()->toDateString();
        $created = 0;

        // THRESHOLD contests — unchanged behaviour: check continuously
        // while the contest is running, uncapped, everyone who crosses
        // the target wins immediately.
        $thresholdContests = DB::table('recruitment_contests')
            ->where('is_active', true)
            ->where('contest_mode', 'THRESHOLD')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->get();

        $this->info("Checking {$thresholdContests->count()} currently-running Threshold contest(s)...");

        foreach ($thresholdContests as $contest) {
            $participants = $this->eligibleParticipants($contest);
            [$since, $until] = $this->windowFor($contest);

            foreach ($participants as $agent) {
                $alreadyWon = DB::table('recruitment_contest_awards')
                    ->where('contest_id', $contest->contest_id)
                    ->where('agent_id', $agent->agent_id)
                    ->exists();
                if ($alreadyWon) {
                    continue;
                }

                $achieved = $this->achievedValue($hierarchy, $contest, $agent, $since, $until);
                if ($achieved < (float) $contest->target_value) {
                    continue;
                }

                $awardId = (string) Str::uuid();
                DB::table('recruitment_contest_awards')->insert([
                    'award_id'       => $awardId,
                    'contest_id'     => $contest->contest_id,
                    'agent_id'       => $agent->agent_id,
                    'achieved_value' => $achieved,
                    'placement'      => null,
                    'reward_type'    => $contest->reward_type,
                    'reward_value'   => $contest->reward_value,
                    'status'         => 'PENDING',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);

                $rewardLabel = $this->rewardLabel($contest->reward_type, $contest->reward_value);
                $notifier->notify(
                    [$agent],
                    'CONTEST_WON',
                    'You Won a Recruitment Contest!',
                    "Congratulations — you've hit the target for \"{$contest->title}\" and earned {$rewardLabel}. Check Growth & Outreach Center > Recruitment Contests for details."
                );

                $created++;
                $this->line("  -> Winner: {$agent->full_name} ({$contest->title})");
            }
        }

        // RANKED_TOP3 contests — finalized exactly ONCE, the first time
        // this command runs after end_date has passed (guarded by "no
        // awards exist yet for this contest" rather than a continuous
        // check, since ranking only makes sense once the window is
        // fully closed).
        $rankedContests = DB::table('recruitment_contests')
            ->where('is_active', true)
            ->where('contest_mode', 'RANKED_TOP3')
            ->where('end_date', '<', $today)
            ->whereNotIn('contest_id', DB::table('recruitment_contest_awards')->select('contest_id')->distinct())
            ->get();

        $this->info("Finalizing {$rankedContests->count()} just-ended Ranked Top-3 contest(s)...");

        foreach ($rankedContests as $contest) {
            $participants = $this->eligibleParticipants($contest);
            [$since, $until] = $this->windowFor($contest);

            $standings = collect();
            foreach ($participants as $agent) {
                $achieved = $this->achievedValue($hierarchy, $contest, $agent, $since, $until);
                if ($achieved < (float) $contest->target_value) {
                    continue; // didn't even qualify for ranking
                }
                $standings->push(['agent' => $agent, 'achieved' => $achieved]);
            }

            $top3 = $standings->sortByDesc('achieved')->take(3)->values();
            $placementRewards = [1 => $contest->reward_value, 2 => $contest->reward_value_2nd, 3 => $contest->reward_value_3rd];

            foreach ($top3 as $index => $row) {
                $placement = $index + 1;
                $rewardValue = $placementRewards[$placement] ?? null;
                if ($rewardValue === null) {
                    continue; // Admin left this placement's reward blank — no award for it
                }

                $awardId = (string) Str::uuid();
                DB::table('recruitment_contest_awards')->insert([
                    'award_id'       => $awardId,
                    'contest_id'     => $contest->contest_id,
                    'agent_id'       => $row['agent']->agent_id,
                    'achieved_value' => $row['achieved'],
                    'placement'      => $placement,
                    'reward_type'    => $contest->reward_type,
                    'reward_value'   => $rewardValue,
                    'status'         => 'PENDING',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);

                $placementLabel = match ($placement) { 1 => '1st place', 2 => '2nd place', 3 => '3rd place', default => "{$placement}th place" };
                $rewardLabel = $this->rewardLabel($contest->reward_type, $rewardValue);
                $notifier->notify(
                    [$row['agent']],
                    'CONTEST_WON',
                    "You Won {$placementLabel} in a Recruitment Contest!",
                    "Congratulations — you finished {$placementLabel} in \"{$contest->title}\" and earned {$rewardLabel}. Check Growth & Outreach Center > Recruitment Contests for details."
                );

                $created++;
                $this->line("  -> {$placementLabel}: {$row['agent']->full_name} ({$contest->title})");
            }
        }

        $this->info("Done. {$created} new award(s) created.");

        return self::SUCCESS;
    }

    private function eligibleParticipants(object $contest)
    {
        return Agent::where('status', 'ACTIVE')
            ->where('is_deleted', false)
            ->whereIn('role', ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER'])
            ->when($contest->group_label_id, fn ($q) => $q->where('group_label_id', $contest->group_label_id))
            ->get();
    }

    private function windowFor(object $contest): array
    {
        return [
            \Carbon\Carbon::parse($contest->start_date)->startOfDay(),
            \Carbon\Carbon::parse($contest->end_date)->endOfDay(),
        ];
    }

    private function achievedValue(HierarchyService $hierarchy, object $contest, Agent $agent, $since, $until): float
    {
        return match ($contest->metric) {
            'RECRUIT_COUNT'  => (float) Agent::where('parent_id', $agent->agent_id)
                ->where('status', 'ACTIVE')->where('is_deleted', false)
                ->whereBetween('created_at', [$since, $until])->count(),
            'SALES_VOLUME'   => $hierarchy->teamVolumeBetween($agent, 'PREMIUM', $since, $until),
            'EARNING_INCOME' => $hierarchy->teamVolumeBetween($agent, 'EARNING_INCOME', $since, $until),
            default          => 0.0,
        };
    }

    private function rewardLabel(string $rewardType, $rewardValue): string
    {
        return match ($rewardType) {
            'POINTS'          => number_format($rewardValue, 0) . ' reward points',
            'DOCUMENT_CREDIT' => number_format($rewardValue, 2) . ' document credits',
            default           => 'RM ' . number_format($rewardValue, 2),
        };
    }
}
