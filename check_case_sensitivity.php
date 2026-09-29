<?php
$lower = App\Models\Agent::where('full_name', 'like', '%ali%')->count();
$upper = App\Models\Agent::where('full_name', 'like', '%Ali%')->count();

echo 'Lowercase "ali" matches: ' . $lower . PHP_EOL;
echo 'Capitalized "Ali" matches: ' . $upper . PHP_EOL;

$collation = DB::selectOne("SHOW FULL COLUMNS FROM agents WHERE Field = 'full_name'");
echo 'full_name column collation: ' . $collation->Collation . PHP_EOL;

echo PHP_EOL . 'Check complete.' . PHP_EOL;
