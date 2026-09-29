<?php
// Diagnostic only — does not change any data.
// Checks every agent's next_child_seq against their actual highest
// child agent_code, and prints any that are out of sync.

App\Models\Agent::whereNotNull('agent_code')->get()->each(function ($a) {
    $max = App\Models\Agent::where('parent_id', $a->agent_id)
        ->orderByRaw('LENGTH(agent_code) DESC, agent_code DESC')
        ->limit(1)
        ->value('agent_code');

    if ($max) {
        $n = (int) substr($max, strrpos($max, '-') + 1);
        if ($n > $a->next_child_seq) {
            echo $a->full_name . ' (' . $a->agent_code . ') seq=' . $a->next_child_seq . ' but highest child=' . $n . PHP_EOL;
        }
    }
});

echo 'Check complete.' . PHP_EOL;
