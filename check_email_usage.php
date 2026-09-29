<?php
// Diagnostic only — does not change any data.
// Finds every table that has an 'email' column, so we know exactly
// what (if anything) besides agents.email would be affected by an
// email change.

$results = DB::select("
    SELECT TABLE_NAME, COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND COLUMN_NAME LIKE '%email%'
    ORDER BY TABLE_NAME
");

foreach ($results as $r) {
    echo $r->TABLE_NAME . ' . ' . $r->COLUMN_NAME . PHP_EOL;
}

echo PHP_EOL . 'Check complete.' . PHP_EOL;
