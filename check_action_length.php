<?php
$columns = DB::select("SHOW COLUMNS FROM audit_logs WHERE Field = 'action'");
foreach ($columns as $col) {
    echo "Column type: " . $col->Type . PHP_EOL;
}
echo 'Check complete.' . PHP_EOL;
