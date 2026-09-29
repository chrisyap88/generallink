<?php

// Run this with: php artisan tinker --execute="require 'seed_customers.php';"
// Or just: php seed_customers.php (from project root)

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$agents = [
    'gl' => 'c660db88-4455-4324-a9d5-4eb5c235e30c',  // Chris Yap
    'tl' => '439fe1cd-d5c0-4624-a32a-5e4828d6954c',  // Ahmad Razif
    'in1' => '71356a27-1340-4c2c-a0cd-26f1b7d229f5', // Siti Nurhaliza
    'in2' => '10697b42-76b6-4d80-88f3-eeef42b49ad9', // Rajan Pillai
];

$customers = [
    ['full_name'=>'Lim Boon Keat',          'nric'=>'900101141234', 'phone'=>'012-3456789', 'email'=>'lim@email.com',    'city'=>'Klang',         'state'=>'Selangor',     'agent'=>$agents['in2']],
    ['full_name'=>'Ahmad Faizal bin Razak',  'nric'=>'850202105678', 'phone'=>'013-9876543', 'email'=>'faizal@email.com', 'city'=>'Shah Alam',      'state'=>'Selangor',     'agent'=>$agents['in1']],
    ['full_name'=>'Priya a/p Subramaniam',   'nric'=>'920303079012', 'phone'=>'011-2345678', 'email'=>'priya@email.com',  'city'=>'Petaling Jaya',  'state'=>'Selangor',     'agent'=>$agents['in1']],
    ['full_name'=>'Wong Chee Keong',         'nric'=>'780404143456', 'phone'=>'016-7654321', 'email'=>'wong@email.com',   'city'=>'Subang Jaya',    'state'=>'Selangor',     'agent'=>$agents['in2']],
    ['full_name'=>'Nurul Ain binti Hassan',  'nric'=>'950505037890', 'phone'=>'017-1234567', 'email'=>'nurul@email.com',  'city'=>'Kuala Lumpur',   'state'=>'Kuala Lumpur', 'agent'=>$agents['tl']],
    ['full_name'=>'Kavitha a/p Raj',         'nric'=>'881212085432', 'phone'=>'019-8765432', 'email'=>'kavitha@email.com','city'=>'Klang',          'state'=>'Selangor',     'agent'=>$agents['in2']],
    ['full_name'=>'Mohd Hafiz bin Ramli',    'nric'=>'910606101122', 'phone'=>'012-6543210', 'email'=>'hafiz@email.com',  'city'=>'Klang',          'state'=>'Selangor',     'agent'=>$agents['in1']],
    ['full_name'=>'Tan Siew Ling',           'nric'=>'870707143344', 'phone'=>'016-3344556', 'email'=>'tan@email.com',    'city'=>'Puchong',        'state'=>'Selangor',     'agent'=>$agents['gl']],
];

$count = 0;
foreach ($customers as $c) {
    DB::table('customers')->insert([
        'customer_id'      => (string) Str::uuid(),
        'nric_encrypted'   => encrypt($c['nric']),
        'nric_hash'        => hash('sha256', $c['nric']),
        'full_name'        => $c['full_name'],
        'email'            => $c['email'],
        'phone'            => $c['phone'],
        'city'             => $c['city'],
        'state'            => $c['state'],
        'owned_by_agent_id'=> $c['agent'],
        'is_deleted'       => 0,
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);
    $count++;
    echo "Added: {$c['full_name']}\n";
}

echo "\nDone! {$count} customers added.\n";
