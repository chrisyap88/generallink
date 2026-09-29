<?php
$count = App\Models\Agent::where('role', 'INTRODUCER')
    ->where('is_deleted', false)
    ->where('full_name', 'like', '%Ali%')
    ->count();

echo 'Introducers with "Ali" in the name: ' . $count . PHP_EOL;

$list = App\Models\Agent::where('role', 'INTRODUCER')
    ->where('is_deleted', false)
    ->where('full_name', 'like', '%Ali%')
    ->get(['full_name', 'agent_code']);

foreach ($list as $a) {
    echo $a->agent_code . ' | ' . $a->full_name . PHP_EOL;
}

echo PHP_EOL . 'Check complete.' . PHP_EOL;
