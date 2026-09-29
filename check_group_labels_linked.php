<?php
echo "--- group_labels table ---" . PHP_EOL;
$labels = DB::table('group_labels')->get();
foreach ($labels as $l) {
    echo $l->group_label_id . ' | ' . $l->group_name . PHP_EOL;
}

echo PHP_EOL . "--- Chris Yap / Amy Tan / David Lim's group_label_id ---" . PHP_EOL;
$agents = App\Models\Agent::whereIn('full_name', ['Chris Yap', 'Amy Tan', 'David Lim'])->where('role', 'GROUP_LEADER')->get();
foreach ($agents as $a) {
    echo $a->full_name . ' -> group_label_id: ' . ($a->group_label_id ?? 'NULL') . PHP_EOL;
}

echo PHP_EOL . 'Check complete.' . PHP_EOL;
