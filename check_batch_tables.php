<?php
echo "--- batch_registration_staging columns ---" . PHP_EOL;
print_r(Schema::getColumnListing('batch_registration_staging'));

echo PHP_EOL . "--- batch_registration_records columns ---" . PHP_EOL;
print_r(Schema::getColumnListing('batch_registration_records'));

echo PHP_EOL . 'Check complete.' . PHP_EOL;
