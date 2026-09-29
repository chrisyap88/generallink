<?php
$agent = App\Models\Agent::where('full_name', 'like', '%Rohana Kassim%')->first();

if (!$agent) {
    echo 'Agent not found.' . PHP_EOL;
} else {
    $agent->nric_encrypted = Illuminate\Support\Facades\Crypt::encryptString('850615-14-5678');
    $agent->save();
    echo 'Updated. New NRIC set to sample: 850615-14-5678 (properly encrypted this time).' . PHP_EOL;
}

echo 'Done.' . PHP_EOL;
