<?php
$adminId = App\Models\Agent::where('role', 'ADMIN')->value('agent_id');
$banks = ['Maybank', 'CIMB Bank', 'Public Bank', 'RHB Bank', 'Hong Leong Bank', 'AmBank', 'Bank Islam'];
$agents = App\Models\Agent::where('is_deleted', false)->get();

$profileInserted = 0;
$profileUpdated = 0;
$bankFixed = 0;
$createdByFixed = 0;

foreach ($agents as $agent) {
    // --- Address / Postcode / City / State ---
    $profile = DB::table('agent_profiles')->where('agent_id', $agent->agent_id)->first();

    if (!$profile) {
        // No profile row at all — insert a brand new one with a real
        // generated primary key.
        $pc = DB::table('malaysia_postcodes')->inRandomOrder()->first();
        $unitNo = rand(1, 88);
        DB::table('agent_profiles')->insert([
            'profile_id' => (string) Illuminate\Support\Str::uuid(),
            'agent_id'   => $agent->agent_id,
            'address'    => "No. {$unitNo}, Jalan Contoh",
            'postcode'   => $pc->postcode,
            'city'       => $pc->city,
            'state'      => $pc->state,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $profileInserted++;
    } elseif (empty($profile->address) || empty($profile->postcode) || empty($profile->city) || empty($profile->state)) {
        // Row exists but some fields are blank — fill only what's missing,
        // never touch profile_id.
        $pc = DB::table('malaysia_postcodes')->inRandomOrder()->first();
        $unitNo = rand(1, 88);
        DB::table('agent_profiles')->where('agent_id', $agent->agent_id)->update([
            'address'    => $profile->address ?: "No. {$unitNo}, Jalan Contoh",
            'postcode'   => $profile->postcode ?: $pc->postcode,
            'city'       => $profile->city ?: $pc->city,
            'state'      => $profile->state ?: $pc->state,
            'updated_at' => now(),
        ]);
        $profileUpdated++;
    }

    // --- Bank Name / Bank Account ---
    $needsBankFix = empty($agent->bank_name) || empty($agent->bank_account_encrypted);
    if ($needsBankFix) {
        $agent->bank_name = $banks[array_rand($banks)];
        $agent->bank_account_encrypted = Crypt::encryptString((string) rand(1000000000, 9999999999));
        $bankFixed++;
    }

    // --- Created By ---
    if (empty($agent->created_by) && $adminId) {
        $agent->created_by = $adminId;
        $createdByFixed++;
    }

    $agent->save();
}

echo "New profile rows inserted: {$profileInserted}" . PHP_EOL;
echo "Existing profile rows updated: {$profileUpdated}" . PHP_EOL;
echo "Bank details fixed: {$bankFixed}" . PHP_EOL;
echo "Created By fixed: {$createdByFixed}" . PHP_EOL;
echo 'Done.' . PHP_EOL;
