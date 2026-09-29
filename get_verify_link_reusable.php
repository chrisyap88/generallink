<?php
// CHANGE THIS EMAIL to whichever new account you just created:
$email = 'cateow88@generallink.my';

$agent = App\Models\Agent::where('email', $email)->first();

if (!$agent) {
    echo 'Agent not found: ' . $email . PHP_EOL;
} else {
    echo 'Full Name: ' . $agent->full_name . PHP_EOL;
    echo 'Status: ' . $agent->status . PHP_EOL;
    echo 'Email Verified: ' . ($agent->email_verified_at ?? 'NOT YET') . PHP_EOL;
    echo PHP_EOL . '--- Direct verification link ---' . PHP_EOL;
    if ($agent->email_verification_token) {
        echo url('/verify-email/' . $agent->email_verification_token) . PHP_EOL;
    } else {
        echo 'No pending verification token — already verified, or something else is wrong.' . PHP_EOL;
    }
}

echo PHP_EOL . 'Done.' . PHP_EOL;
