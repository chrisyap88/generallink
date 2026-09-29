<?php
// One-time bulk repair: sets next_child_seq for EVERY agent to match
// their actual highest existing child code, since the column is brand
// new and defaulted to 0 for all pre-existing agents.
//
// Safe to run multiple times (idempotent) — it only ever raises a
// counter to match reality, never lowers one, so it cannot cause a
// future collision.

$fixed = 0;
$skipped = 0;

App\Models\Agent::whereNotNull('agent_code')->get()->each(function ($a) use (&$fixed, &$skipped) {
    $max = App\Models\Agent::where('parent_id', $a->agent_id)
        ->orderByRaw('LENGTH(agent_code) DESC, agent_code DESC')
        ->limit(1)
        ->value('agent_code');

    if (!$max) {
        return; // no children at all — nothing to fix
    }

    $n = (int) substr($max, strrpos($max, '-') + 1);

    if ($n > $a->next_child_seq) {
        $a->update(['next_child_seq' => $n]);
        echo 'FIXED: ' . $a->full_name . ' (' . $a->agent_code . ') ' . $a->next_child_seq . ' -> ' . $n . PHP_EOL;
        $fixed++;
    } else {
        $skipped++;
    }
});

echo PHP_EOL . "Done. Fixed: {$fixed}. Already correct: {$skipped}." . PHP_EOL;
