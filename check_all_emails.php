<?php
$total = App\Models\Agent::where('is_deleted', false)->count();
$missing = App\Models\Agent::where('is_deleted', false)
    ->where(function ($q) {
        $q->whereNull('email')->orWhere('email', '');
    })->count();
$notGenerallink = App\Models\Agent::where('is_deleted', false)
    ->whereNotNull('email')
    ->where('email', '!=', '')
    ->where('email', 'not like', '%@generallink.my')
    ->count();

echo "Total agents: {$total}" . PHP_EOL;
echo "Missing/blank email: {$missing}" . PHP_EOL;
echo "Has email but NOT @generallink.my: {$notGenerallink}" . PHP_EOL;

if ($notGenerallink > 0) {
    echo PHP_EOL . "--- Sample of non-generallink.my emails ---" . PHP_EOL;
    $sample = App\Models\Agent::where('is_deleted', false)
        ->whereNotNull('email')
        ->where('email', 'not like', '%@generallink.my')
        ->limit(5)
        ->get(['full_name', 'email']);
    foreach ($sample as $a) {
        echo $a->full_name . ' -> ' . $a->email . PHP_EOL;
    }
}

echo PHP_EOL . 'Check complete.' . PHP_EOL;
