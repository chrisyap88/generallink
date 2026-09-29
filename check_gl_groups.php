<?php
$names = ['Chris Yap', 'Amy Tan', 'David Lim'];

foreach ($names as $name) {
    $agent = App\Models\Agent::where('full_name', 'like', '%' . $name . '%')
        ->where('role', 'GROUP_LEADER')
        ->first();

    if (!$agent) {
        echo "{$name}: NOT FOUND as a Group Leader" . PHP_EOL;
        continue;
    }

    $group = $agent->group_id
        ? DB::table('groups')->where('group_id', $agent->group_id)->first()
        : null;

    echo "{$agent->full_name} ({$agent->agent_code}) -> group_id: " . ($agent->group_id ?? 'NULL')
        . " | Group Name: " . ($group->group_name ?? '(no group record found)') . PHP_EOL;
}

echo PHP_EOL . 'Check complete.' . PHP_EOL;
