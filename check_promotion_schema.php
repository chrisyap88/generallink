<?php
echo "--- role_history columns ---" . PHP_EOL;
try {
    print_r(Schema::getColumnListing('role_history'));
} catch (\Exception $e) {
    echo 'Table not found: ' . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "--- agents columns relevant to promotion ---" . PHP_EOL;
$cols = Schema::getColumnListing('agents');
foreach (['origin_group_id', 'displaced_from_agent_id', 'group_id', 'parent_id', 'hierarchy_path', 'next_child_seq'] as $check) {
    echo $check . ': ' . (in_array($check, $cols) ? 'EXISTS' : 'MISSING') . PHP_EOL;
}

echo PHP_EOL . "--- groups columns ---" . PHP_EOL;
print_r(Schema::getColumnListing('groups'));

echo PHP_EOL . 'Check complete.' . PHP_EOL;
