<?php
// CHANGE THIS to whatever email you actually typed:
$email = 'cateow@generallink.my';

$agent = App\Models\Agent::where('email', $email)->first();
$agentAnyCase = DB::table('agents')->whereRaw('LOWER(email) = ?', [strtolower($email)])->first();

echo 'Exact match found: ' . ($agent ? 'YES' : 'NO') . PHP_EOL;
echo 'Case-insensitive match found: ' . ($agentAnyCase ? 'YES' : 'NO') . PHP_EOL;

if ($agentAnyCase) {
    echo 'Full Name: ' . $agentAnyCase->full_name . PHP_EOL;
    echo 'Actual stored email: "' . $agentAnyCase->email . '"' . PHP_EOL;
    echo 'is_deleted: ' . $agentAnyCase->is_deleted . PHP_EOL;
}

echo PHP_EOL . 'Done.' . PHP_EOL;
