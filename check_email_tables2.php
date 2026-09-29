<?php
// Diagnostic only — does not change any data.
echo "--- batch_registration_records columns ---" . PHP_EOL;
print_r(Schema::getColumnListing('batch_registration_records'));

echo PHP_EOL . "--- groups columns ---" . PHP_EOL;
print_r(Schema::getColumnListing('groups'));

echo PHP_EOL . 'Check complete.' . PHP_EOL;
