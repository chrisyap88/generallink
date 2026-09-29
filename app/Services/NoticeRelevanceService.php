<?php

namespace App\Services;

// NEW 8 Aug 2026 — GLADE Ecosystem Engagement, Phase 2 (Task #89). Simple,
// explainable, rule-based relevance scoring — no AI involved (that's
// Phase 3). Two factors, per Chris's original requirement doc:
//   1. Category/interest match — does this notice's category match the
//      agent's own notification preference? (no preference row, or "all
//      categories" selected, counts as a match for everything.)
//   2. Recency — newer notices score higher, decaying to 0 after 50 days.
// Used to (a) rank the agent-facing Notice Board browse list by default
// (instead of pure recency), and (b) pick which single unread promotion
// Carolyn proactively names, rather than always just the most recent one.
// Deliberately a raw SQL expression (not scored in PHP after fetching) so
// it can be used directly in ORDER BY without pulling every row into PHP
// first — this table can grow large over time.
class NoticeRelevanceService
{
    /**
     * Builds a "n.category"/"n.created_at" relevance SQL expression plus
     * its bindings. Caller must alias the notices table as "n" (matches
     * both NoticeBoardController and ProactiveAlertService's existing
     * queries) and prepend the returned bindings before any other bindings
     * used later in the same select.
     *
     * @param array|null $categories agent's preferred categories, or null for "all"
     * @return array{0: string, 1: array}
     */
    public static function sqlExpression(?array $categories): array
    {
        if ($categories) {
            $placeholders = implode(',', array_fill(0, count($categories), '?'));
            $sql = "(CASE WHEN n.category IN ({$placeholders}) THEN 100 ELSE 0 END) + GREATEST(0, 50 - DATEDIFF(NOW(), n.created_at))";
            return [$sql, $categories];
        }

        // No preference set (or "all categories" selected) — every
        // category counts as a full match; recency is the only variable.
        return ['100 + GREATEST(0, 50 - DATEDIFF(NOW(), n.created_at))', []];
    }
}
