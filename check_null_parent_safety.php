<?php
echo "--- Is agents.parent_id nullable at the database level? ---" . PHP_EOL;
$columns = DB::select("SHOW COLUMNS FROM agents WHERE Field = 'parent_id'");
foreach ($columns as $col) {
    echo "Null allowed: " . $col->Null . PHP_EOL;
}

echo PHP_EOL . "--- How many EXISTING agents already have parent_id = NULL? ---" . PHP_EOL;
$nullCount = App\Models\Agent::whereNull('parent_id')->where('is_deleted', false)->count();
echo "Count: {$nullCount}" . PHP_EOL;

echo PHP_EOL . "--- Sample of existing agents with NULL parent_id (should be GLs) ---" . PHP_EOL;
$sample = App\Models\Agent::whereNull('parent_id')->where('is_deleted', false)->limit(5)->get(['full_name', 'agent_code', 'role']);
foreach ($sample as $a) {
    echo $a->full_name . ' | ' . $a->agent_code . ' | ' . $a->role . PHP_EOL;
}

echo PHP_EOL . 'Check complete.' . PHP_EOL;
