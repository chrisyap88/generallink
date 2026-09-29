<?php
echo "--- By Status ---" . PHP_EOL;
$byStatus = App\Models\Agent::where('role', 'INTRODUCER')
    ->where('is_deleted', false)
    ->selectRaw('status, count(*) as total')
    ->groupBy('status')
    ->get();
foreach ($byStatus as $row) {
    echo $row->status . ': ' . $row->total . PHP_EOL;
}

echo PHP_EOL . "--- By State (top 10) ---" . PHP_EOL;
$byState = App\Models\Agent::where('agents.role', 'INTRODUCER')
    ->where('agents.is_deleted', false)
    ->leftJoin('agent_profiles', 'agent_profiles.agent_id', '=', 'agents.agent_id')
    ->selectRaw('agent_profiles.state, count(*) as total')
    ->groupBy('agent_profiles.state')
    ->orderByDesc('total')
    ->limit(10)
    ->get();
foreach ($byState as $row) {
    echo ($row->state ?? '(blank)') . ': ' . $row->total . PHP_EOL;
}

echo PHP_EOL . "--- Total Introducers overall ---" . PHP_EOL;
echo App\Models\Agent::where('role', 'INTRODUCER')->where('is_deleted', false)->count() . PHP_EOL;

echo PHP_EOL . 'Check complete.' . PHP_EOL;
