<?php
echo "--- reason_codes columns ---" . PHP_EOL;
print_r(Schema::getColumnListing('reason_codes'));

echo PHP_EOL . "--- Sample existing rows ---" . PHP_EOL;
$rows = DB::table('reason_codes')->limit(5)->get();
foreach ($rows as $r) {
    print_r((array) $r);
}

echo PHP_EOL . 'Check complete.' . PHP_EOL;
