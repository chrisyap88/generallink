<?php
$log = DB::table('audit_logs')
    ->where('action', 'PROFILE_UPDATE')
    ->whereDate('created_at', '2026-06-17')
    ->orderBy('created_at')
    ->first();

if (!$log) {
    echo 'No matching log entry found for that date.' . PHP_EOL;
} else {
    echo 'Log ID: ' . $log->log_id . PHP_EOL;
    echo 'Created At: ' . $log->created_at . PHP_EOL;
    echo PHP_EOL . '--- RAW before_value ---' . PHP_EOL;
    echo $log->before_value ?? '(NULL)';
    echo PHP_EOL . PHP_EOL . '--- RAW after_value ---' . PHP_EOL;
    echo $log->after_value ?? '(NULL)';
}

echo PHP_EOL . PHP_EOL . 'Check complete.' . PHP_EOL;
