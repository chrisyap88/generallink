<?php
$agent = App\Models\Agent::where('full_name', 'like', '%Sarah%')->first();

if (!$agent) {
    echo 'No agent found with "Sarah" in the name at all.' . PHP_EOL;
} else {
    echo 'full_name: ' . $agent->full_name . PHP_EOL;
    echo 'role: ' . $agent->role . PHP_EOL;
    echo 'is_deleted: ' . ($agent->is_deleted ? 'true' : 'false') . PHP_EOL;
    echo 'status: ' . $agent->status . PHP_EOL;
    echo 'agent_code: ' . $agent->agent_code . PHP_EOL;
}

echo PHP_EOL . 'Check complete.' . PHP_EOL;
