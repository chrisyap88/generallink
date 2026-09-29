<?php
$missing = App\Models\Agent::where('is_deleted', false)
    ->where(function ($q) {
        $q->whereNull('qr_code_token')->orWhere('qr_code_token', '');
    })->get();

echo "Agents missing a QR token: {$missing->count()}" . PHP_EOL;

$generated = 0;
foreach ($missing as $agent) {
    $token = Illuminate\Support\Str::random(10);
    // Ensure uniqueness against existing tokens
    while (App\Models\Agent::where('qr_code_token', $token)->exists()) {
        $token = Illuminate\Support\Str::random(10);
    }
    $agent->qr_code_token = $token;
    $agent->save();
    $generated++;
}

echo "Generated new tokens for: {$generated}" . PHP_EOL;
echo 'Done.' . PHP_EOL;
