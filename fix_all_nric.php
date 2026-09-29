<?php
$agents = App\Models\Agent::where('is_deleted', false)->get();
$fixed = 0;
$alreadyOk = 0;
$usedNumbers = [];

function generateSampleNric(&$usedNumbers) {
    do {
        $year = str_pad(rand(60, 99), 2, '0', STR_PAD_LEFT);
        $month = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
        $day = str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
        $place = '14'; // Selangor — arbitrary, sample data only
        $serial = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $nric = "{$year}{$month}{$day}-{$place}-{$serial}";
    } while (in_array($nric, $usedNumbers));
    $usedNumbers[] = $nric;
    return $nric;
}

foreach ($agents as $agent) {
    $needsFix = false;

    if (!$agent->nric_encrypted) {
        $needsFix = true;
    } else {
        try {
            Illuminate\Support\Facades\Crypt::decryptString($agent->nric_encrypted);
            $alreadyOk++;
        } catch (\Exception $e) {
            $needsFix = true;
        }
    }

    if ($needsFix) {
        $sample = generateSampleNric($usedNumbers);
        $agent->nric_encrypted = Illuminate\Support\Facades\Crypt::encryptString($sample);
        $agent->save();
        echo $agent->full_name . ' (' . $agent->agent_code . ') -> ' . $sample . PHP_EOL;
        $fixed++;
    }
}

echo PHP_EOL . "Fixed: {$fixed}. Already had a valid encrypted NRIC: {$alreadyOk}." . PHP_EOL;
echo 'Done.' . PHP_EOL;
