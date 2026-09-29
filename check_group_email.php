<?php
// Diagnostic only — does not change any data.
print_r(DB::table('groups')->select('group_name', 'group_email')->get());
echo PHP_EOL . 'Check complete.' . PHP_EOL;
