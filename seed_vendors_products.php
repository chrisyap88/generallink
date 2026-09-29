<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "Starting seed...\n";

// ── Vendors ─────────────────────────────────────────────────────
$newVendors = [
    'Allianz Malaysia','Zurich Insurance','Great Eastern Life',
    'AIA Bhd','Prudential BSN Takaful','Sun Life Malaysia',
    'MSIG Insurance','Etiqa Insurance','Hong Leong Assurance',
    'Tune Protect','Berjaya Sompo','AmMetLife',
    'Manulife Insurance','RHB Insurance','Tokio Marine',
];

foreach ($newVendors as $name) {
    if (!DB::table('vendors')->where('vendor_name', $name)->exists()) {
        $id = (string) Str::uuid();
        DB::table('vendors')->insert([
            'vendor_id'   => $id,
            'vendor_name' => $name,
            'vendor_code' => strtoupper(substr(str_replace(' ','',$name),0,4)),
            'is_active'   => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        echo "Vendor: $name\n";
    }
}

$allVendorIds = DB::table('vendors')->pluck('vendor_id')->toArray();
$firstVendorId = $allVendorIds[0];

// ── Products ─────────────────────────────────────────────────────
$newProducts = [
    ['Travel Insurance','TRVL','OTHER'],
    ['Home Content Insurance','HOME','FIRE'],
    ['Critical Illness','CI','OTHER'],
    ['Whole Life Plan','WLP','OTHER'],
    ['Term Life','TERM','OTHER'],
    ['Investment Linked','ILP','OTHER'],
    ['Endowment Plan','END','OTHER'],
    ['Medical Card','MED','OTHER'],
    ['Personal Accident','PA','PERSONAL_ACCIDENT'],
    ['Fire Insurance','FIRE','FIRE'],
    ['Motorcycle Insurance','MOTO','MOTOR'],
    ['Commercial Vehicle','COMM','MOTOR'],
    ['Marine Cargo','MAR','OTHER'],
    ['Contractor All Risk','CAR','OTHER'],
    ['Public Liability','PL','OTHER'],
    ['Workmen Compensation','WC','OTHER'],
    ['Group Medical','GM','OTHER'],
];

foreach ($newProducts as [$name, $code, $type]) {
    if (!DB::table('products')->where('product_name', $name)->exists()) {
        DB::table('products')->insert([
            'product_id'   => (string) Str::uuid(),
            'vendor_id'    => $firstVendorId,
            'product_name' => $name,
            'product_code' => $code,
            'product_type' => $type,
            'is_active'    => true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        echo "Product: $name\n";
    }
}

$allVendorIds  = DB::table('vendors')->pluck('vendor_id')->toArray();
$allProductIds = DB::table('products')->pluck('product_id')->toArray();
$agents        = DB::table('agents')->where('is_deleted',false)->where('status','ACTIVE')->where('role','!=','ADMIN')->get(['agent_id']);
$customers     = DB::table('customers')->pluck('customer_id')->toArray();
$statuses      = ['ACTIVE','ACTIVE','ACTIVE','PENDING_RENEWAL','SUBMITTED'];
$count         = 0;

echo "\nCreating 300 transactions...\n";

for ($i = 0; $i < 300; $i++) {
    $agent      = $agents->random();
    $vendorId   = $allVendorIds[array_rand($allVendorIds)];
    $productId  = $allProductIds[array_rand($allProductIds)];
    $customerId = count($customers) ? $customers[array_rand($customers)] : null;
    $amount     = rand(500, 15000) + (rand(0,99)/100);
    $monthsAgo  = rand(0, 5);
    $date       = now()->subMonths($monthsAgo)->subDays(rand(0,27));
    $status     = $statuses[array_rand($statuses)];
    $coverStart = $date->copy();
    $coverEnd   = $date->copy()->addYear();

    DB::table('sales_transactions')->insert([
        'policy_id'      => (string) Str::uuid(),
        'policy_number'  => 'POL-'.strtoupper(Str::random(4)).'-'.$date->format('Y').'-'.rand(1000,9999),
        'agent_id'       => $agent->agent_id,
        'vendor_id'      => $vendorId,
        'product_id'     => $productId,
        'customer_id'    => $customerId,
        'premium_amount' => $amount,
        'coverage_start' => $coverStart,
        'coverage_end'   => $coverEnd,
        'status'         => $status,
        'is_deleted'     => false,
        'created_at'     => $date,
        'updated_at'     => $date,
        'renewal_date'   => $status === 'PENDING_RENEWAL' ? $coverEnd : null,
    ]);
    $count++;
}

echo "Done! $count transactions created.\n";
echo "Total vendors: ".DB::table('vendors')->count()."\n";
echo "Total products: ".DB::table('products')->count()."\n";
echo "Total transactions: ".DB::table('sales_transactions')->count()."\n";
