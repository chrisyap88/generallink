<?php
$prihatinId = DB::table('group_labels')->where('group_name', 'prihatin2u')->value('group_label_id');
$relaId = DB::table('group_labels')->where('group_name', 'rela2u')->value('group_label_id');

DB::table('agents')->where('full_name', 'Chris Yap')->where('role', 'GROUP_LEADER')->update(['group_label_id' => $prihatinId]);
DB::table('agents')->where('full_name', 'Amy Tan')->where('role', 'GROUP_LEADER')->update(['group_label_id' => $prihatinId]);
DB::table('agents')->where('full_name', 'David Lim')->where('role', 'GROUP_LEADER')->update(['group_label_id' => $relaId]);

echo "Chris Yap -> prihatin2u" . PHP_EOL;
echo "Amy Tan -> prihatin2u" . PHP_EOL;
echo "David Lim -> rela2u" . PHP_EOL;

echo PHP_EOL . "--- Verify ---" . PHP_EOL;
$agents = App\Models\Agent::whereIn('full_name', ['Chris Yap', 'Amy Tan', 'David Lim'])->where('role', 'GROUP_LEADER')->get();
foreach ($agents as $a) {
    echo $a->full_name . ' -> group_label_id: ' . ($a->group_label_id ?? 'STILL NULL') . PHP_EOL;
}

echo PHP_EOL . 'Done.' . PHP_EOL;
