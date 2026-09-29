<?php
// Repurpose the existing Admin account into Admin Director.
$existingAdmin = App\Models\Agent::where('role', 'ADMIN')->whereNull('department')->first();

if ($existingAdmin) {
    $existingAdmin->update([
        'full_name'  => 'Admin Director',
        'department' => 'DIRECTOR',
    ]);
    echo "Repurposed existing admin ({$existingAdmin->email}) -> Admin Director" . PHP_EOL;
} else {
    echo "WARNING: No existing admin without a department found — skipping repurpose step." . PHP_EOL;
}

// Create Admin Finance
$financeId = Illuminate\Support\Str::uuid();
DB::table('agents')->insert([
    'agent_id'                 => $financeId,
    'full_name'                => 'Admin Finance',
    'email'                    => 'finance@generallink.my',
    'password_hash'            => Illuminate\Support\Facades\Hash::make('ChangeMe123!'),
    'phone'                    => '+60100000002',
    'role'                     => 'ADMIN',
    'department'               => 'FINANCE',
    'agent_code'               => 'ADMIN-FIN-001',
    'status'                   => 'ACTIVE',
    'qr_code_token'            => Illuminate\Support\Str::random(10),
    'email_verified_at'        => now(),
    'created_at'               => now(),
    'updated_at'               => now(),
]);
echo "Created Admin Finance (finance@generallink.my / temp password: ChangeMe123!)" . PHP_EOL;

// Create Admin Sales
$salesId = Illuminate\Support\Str::uuid();
DB::table('agents')->insert([
    'agent_id'                 => $salesId,
    'full_name'                => 'Admin Sales',
    'email'                    => 'sales@generallink.my',
    'password_hash'            => Illuminate\Support\Facades\Hash::make('ChangeMe123!'),
    'phone'                    => '+60100000003',
    'role'                     => 'ADMIN',
    'department'               => 'SALES',
    'agent_code'               => 'ADMIN-SAL-001',
    'status'                   => 'ACTIVE',
    'qr_code_token'            => Illuminate\Support\Str::random(10),
    'email_verified_at'        => now(),
    'created_at'               => now(),
    'updated_at'               => now(),
]);
echo "Created Admin Sales (sales@generallink.my / temp password: ChangeMe123!)" . PHP_EOL;

echo PHP_EOL . "--- Verify all 3 ---" . PHP_EOL;
$all = App\Models\Agent::where('role', 'ADMIN')->get(['full_name', 'email', 'department']);
foreach ($all as $a) {
    echo $a->full_name . ' | ' . $a->email . ' | ' . $a->department . PHP_EOL;
}

echo PHP_EOL . 'IMPORTANT: change these temporary passwords immediately after first login.' . PHP_EOL;
echo 'Done.' . PHP_EOL;
