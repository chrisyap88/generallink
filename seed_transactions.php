<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "Starting transaction seed...\n";

$agents   = DB::table('agents')->where('is_deleted',false)->where('status','ACTIVE')->where('role','!=','ADMIN')->get(['agent_id','role','group_id']);
$vendors  = DB::table('vendors')->where('is_active',true)->pluck('vendor_id')->toArray();
$products = DB::table('products')->where('is_active',true)->pluck('product_id')->toArray();
$customers= DB::table('customers')->pluck('customer_id')->toArray();
$statuses = ['ACTIVE','ACTIVE','ACTIVE','ACTIVE','PENDING_RENEWAL','SUBMITTED'];

// Get a default structure_id
$structureId = DB::table('commission_structures')->value('structure_id') ?? null;
if (!$structureId) {
    echo "No commission structure found - creating placeholder\n";
    $structureId = (string) Str::uuid();
}

$commRate = ['GROUP_LEADER'=>0.05,'TEAM_LEADER'=>0.08,'INTRODUCER'=>0.10];
$txnCount = 0; $commCount = 0;

foreach ($agents as $agent) {
    $numTxns = rand(3, 6);
    for ($i = 0; $i < $numTxns; $i++) {
        $monthsAgo  = rand(0, 5);
        $date       = now()->subMonths($monthsAgo)->subDays(rand(0, 25));
        $amount     = rand(500, 8000) + (rand(0,99)/100);
        $vendorId   = $vendors[array_rand($vendors)];
        $productId  = $products[array_rand($products)];
        $customerId = count($customers) ? $customers[array_rand($customers)] : null;
        $status     = $statuses[array_rand($statuses)];
        $policyId   = (string) Str::uuid();

        DB::table('sales_transactions')->insert([
            'policy_id'      => $policyId,
            'policy_number'  => 'POL-'.strtoupper(Str::random(4)).'-'.$date->format('Y').'-'.rand(1000,9999),
            'agent_id'       => $agent->agent_id,
            'vendor_id'      => $vendorId,
            'product_id'     => $productId,
            'customer_id'    => $customerId,
            'premium_amount' => $amount,
            'coverage_start' => $date->copy(),
            'coverage_end'   => $date->copy()->addYear(),
            'status'         => $status,
            'is_deleted'     => false,
            'created_at'     => $date,
            'updated_at'     => $date,
            'renewal_date'   => $status === 'PENDING_RENEWAL' ? $date->copy()->addYear() : null,
        ]);
        $txnCount++;

        $rate       = $commRate[$agent->role] ?? 0.05;
        $commission = round($amount * $rate, 2);
        $poolAmount = round($amount * 0.20, 2);

        DB::table('commission_transactions')->insert([
            'txn_id'               => (string) Str::uuid(),
            'policy_id'            => $policyId,
            'agent_id'             => $agent->agent_id,
            'structure_id'         => $structureId,
            'role_at_transaction'  => $agent->role,
            'policy_premium'       => $amount,
            'total_pool_amount'    => $poolAmount,
            'entitlement_pct'      => $rate * 100,
            'commission_amount'    => $commission,
            'status'               => 'CONFIRMED',
            'created_at'           => $date,
            'updated_at'           => $date,
        ]);
        $commCount++;
    }
}

echo "Done!\n";
echo "Sales transactions: $txnCount\n";
echo "Commission transactions: $commCount\n";
echo "Total sales: " . DB::table('sales_transactions')->count() . "\n";
echo "Total commissions: " . DB::table('commission_transactions')->count() . "\n";
