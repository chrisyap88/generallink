<?php
// Diagnostic only — does not change any data.
$groups = DB::table('groups')->select('group_id', 'group_name', 'group_email')->get();

foreach ($groups as $g) {
    $agent = App\Models\Agent::where('full_name', $g->group_name)->first();
    $agentEmail = $agent ? $agent->email : '(no matching agent found by name)';
    $match = ($agent && $agent->email === $g->group_email) ? 'MATCH' : 'DIFFERENT';
    echo $g->group_name . ' | group_email=' . $g->group_email . ' | agent.email=' . $agentEmail . ' | ' . $match . PHP_EOL;
}

echo PHP_EOL . 'Check complete.' . PHP_EOL;
