<?php
$realAgentIds = App\Models\Agent::where('is_deleted', false)
    ->whereIn('role', ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER'])
    ->pluck('agent_id')
    ->toArray();

$agents = App\Models\Agent::where('is_deleted', false)->get();
$reassigned = 0;

foreach ($agents as $agent) {
    if (!empty($realAgentIds)) {
        $randomCreator = $realAgentIds[array_rand($realAgentIds)];
        // Avoid setting someone as their own creator
        if ($randomCreator !== $agent->agent_id) {
            $agent->created_by = $randomCreator;
            $agent->save();
            $reassigned++;
        }
    }
}

echo "Reassigned Created By for {$reassigned} agents to random real Introducer/TL/GL names." . PHP_EOL;
echo 'Done.' . PHP_EOL;
