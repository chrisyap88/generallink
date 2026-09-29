<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

// NEW 17 Sep 2026 — per Chris: the whole Notice Board page (header
// band) should switch look automatically for Christmas, Chinese New
// Year, etc, then revert — Admin-editable date ranges/colours/
// greeting text, same catalog pattern as everything else. Only one
// season is considered "current" at a time; if two ranges somehow
// overlap, the lower sort_order wins.
class CbeSeasonThemeService
{
    /** The season theme active today, or null if none. */
    public function current(?Carbon $on = null)
    {
        $on = $on ?: now();
        $month = (int) $on->format('n');
        $day = (int) $on->format('j');

        $themes = DB::table('cbe_season_themes')->where('is_active', true)->orderBy('sort_order')->get();

        foreach ($themes as $t) {
            if ($this->dateFallsWithin($month, $day, $t->start_month, $t->start_day, $t->end_month, $t->end_day)) {
                return $t;
            }
        }

        return null;
    }

    /** Handles ranges that wrap the new year (e.g. 20 Dec – 5 Jan). */
    private function dateFallsWithin(int $m, int $d, int $sm, int $sd, int $em, int $ed): bool
    {
        $val = $m * 100 + $d;
        $start = $sm * 100 + $sd;
        $end = $em * 100 + $ed;

        if ($start <= $end) {
            return $val >= $start && $val <= $end;
        }

        // Wraps across the year boundary.
        return $val >= $start || $val <= $end;
    }
}
