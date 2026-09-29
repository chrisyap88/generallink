<?php

namespace App\Console\Commands;

use App\Services\CbeSeasonThemeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 17 Sep 2026 — per Chris: "some notice is NOT require admin to add
// like festival greeting notice, birthday notice to the cbe community."
// Runs daily (see routes/console.php). Two things it posts, both
// notice_source=SYSTEM and both de-duplicated via system_period_key so
// running this more than once a day never double-posts:
//
// 1. Festival greeting — one PUBLIC notice per CBE node, per active
//    cbe_season_themes row, for every day that season is "current"
//    (CbeSeasonThemeService), auto-expiring the day the season ends.
// 2. Birthday of the Month — one PUBLIC aggregate notice per CBE node
//    on the 1st of each month, naming only members who opted in
//    (agents.share_birthday_public) and whose date_of_birth falls in
//    that month. Individual private "Happy Birthday" cards (one per
//    opted-in member, on their own dashboard only) are posted on their
//    actual birthday.
class GenerateCbeSystemNotices extends Command
{
    protected $signature = 'cbe:generate-system-notices';
    protected $description = 'Post automatic CBE Notice Board entries: festival greetings and opt-in birthday notices.';

    public function handle(CbeSeasonThemeService $seasons): int
    {
        $this->generateFestivalGreetings($seasons);
        $this->generateBirthdayOfTheMonth();
        $this->generateIndividualBirthdayCards();

        return self::SUCCESS;
    }

    private function activeCbeNodeIds(): array
    {
        // Every node that has at least one active membership — no point
        // posting to an empty node.
        return DB::table('cbe_group_memberships')
            ->where('status', 'ACTIVE')->whereNotNull('cbe_node_id')
            ->distinct()->pluck('cbe_node_id')->all();
    }

    private function generateFestivalGreetings(CbeSeasonThemeService $seasons): void
    {
        $theme = $seasons->current();
        if (! $theme || ! $theme->post_greeting_notice) {
            return;
        }

        $periodKey = $theme->season_key.'-'.now()->year;
        $nodeIds = $this->activeCbeNodeIds();
        $expiresAt = $this->seasonEndDate($theme);

        foreach ($nodeIds as $nodeId) {
            $exists = DB::table('cbe_temple_notices')
                ->where('cbe_node_id', $nodeId)->where('notice_type', 'FESTIVAL_GREETING')
                ->where('system_period_key', $periodKey)->exists();
            if ($exists) {
                continue;
            }

            $anyManager = DB::table('cbe_group_memberships')->where('cbe_node_id', $nodeId)->where('status', 'ACTIVE')->value('agent_id');
            if (! $anyManager) {
                continue;
            }

            DB::table('cbe_temple_notices')->insert([
                'notice_id'          => (string) Str::uuid(),
                'cbe_node_id'        => $nodeId,
                'title'              => $theme->label.' Greetings',
                'body'               => $theme->greeting_text ?: ('Season\'s greetings for '.$theme->label.'!'),
                'category'           => 'ANNOUNCEMENT',
                'notice_source'      => 'SYSTEM',
                'notice_type'        => 'FESTIVAL_GREETING',
                'system_period_key'  => $periodKey,
                'style_key'          => 'CELEBRATION',
                'style_is_override'  => false,
                'visibility'         => 'PUBLIC',
                'expires_at'         => $expiresAt,
                'posted_by_agent_id' => $anyManager,
                'is_deleted'         => false,
                'created_at'         => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function seasonEndDate(object $theme): string
    {
        $now = now();
        $end = now()->setMonth($theme->end_month)->setDay($theme->end_day)->startOfDay();
        // Season wraps into next year (e.g. starts Dec, ends Jan).
        if ($theme->end_month < $theme->start_month && $now->month >= $theme->start_month) {
            $end = $end->addYear();
        }
        return $end->toDateString();
    }

    private function generateBirthdayOfTheMonth(): void
    {
        if (now()->day !== 1) {
            return;
        }

        $periodKey = now()->format('Y-m');
        $month = now()->month;

        foreach ($this->activeCbeNodeIds() as $nodeId) {
            $exists = DB::table('cbe_temple_notices')
                ->where('cbe_node_id', $nodeId)->where('notice_type', 'BIRTHDAY_MONTH')
                ->where('system_period_key', $periodKey)->exists();
            if ($exists) {
                continue;
            }

            $members = DB::table('cbe_group_memberships as m')
                ->join('agents as a', 'a.agent_id', '=', 'm.agent_id')
                ->where('m.cbe_node_id', $nodeId)->where('m.status', 'ACTIVE')
                ->where('a.share_birthday_public', true)
                ->whereNotNull('a.date_of_birth')
                ->whereMonth('a.date_of_birth', $month)
                ->orderByRaw('DAY(a.date_of_birth)')
                ->pluck('a.full_name');

            if ($members->isEmpty()) {
                continue;
            }

            $anyManager = DB::table('cbe_group_memberships')->where('cbe_node_id', $nodeId)->where('status', 'ACTIVE')->value('agent_id');

            DB::table('cbe_temple_notices')->insert([
                'notice_id'          => (string) Str::uuid(),
                'cbe_node_id'        => $nodeId,
                'title'              => 'Birthday Wishes This Month',
                'body'               => "Join us in wishing a happy birthday this month to: ".$members->implode(', ')."!",
                'category'           => 'ANNOUNCEMENT',
                'notice_source'      => 'SYSTEM',
                'notice_type'        => 'BIRTHDAY_MONTH',
                'system_period_key'  => $periodKey,
                'style_key'          => 'BIRTHDAY',
                'style_is_override'  => false,
                'visibility'         => 'PUBLIC',
                'expires_at'         => now()->endOfMonth()->toDateString(),
                'posted_by_agent_id' => $anyManager,
                'is_deleted'         => false,
                'created_at'         => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function generateIndividualBirthdayCards(): void
    {
        $today = now();
        $periodKey = (string) $today->year;

        $members = DB::table('agents')
            ->where('share_birthday_public', true)
            ->whereNotNull('date_of_birth')
            ->whereMonth('date_of_birth', $today->month)
            ->whereDay('date_of_birth', $today->day)
            ->get(['agent_id', 'full_name']);

        foreach ($members as $member) {
            $membership = DB::table('cbe_group_memberships')
                ->where('agent_id', $member->agent_id)->where('status', 'ACTIVE')
                ->whereNotNull('cbe_node_id')->first();
            if (! $membership) {
                continue;
            }

            $exists = DB::table('cbe_temple_notices')
                ->where('target_agent_id', $member->agent_id)->where('notice_type', 'BIRTHDAY_CARD')
                ->where('system_period_key', $periodKey)->exists();
            if ($exists) {
                continue;
            }

            DB::table('cbe_temple_notices')->insert([
                'notice_id'          => (string) Str::uuid(),
                'cbe_node_id'        => $membership->cbe_node_id,
                'title'              => 'Happy Birthday, '.$member->full_name.'!',
                'body'               => 'Wishing you a wonderful day and a great year ahead — from your whole community.',
                'category'           => 'GENERAL',
                'notice_source'      => 'SYSTEM',
                'notice_type'        => 'BIRTHDAY_CARD',
                'system_period_key'  => $periodKey,
                'style_key'          => 'BIRTHDAY',
                'style_is_override'  => false,
                'visibility'         => 'MEMBER_PRIVATE',
                'target_agent_id'    => $member->agent_id,
                'expires_at'         => $today->copy()->addDays(7)->toDateString(),
                'posted_by_agent_id' => $member->agent_id,
                'is_deleted'         => false,
                'created_at'         => now(), 'updated_at' => now(),
            ]);
        }
    }
}
