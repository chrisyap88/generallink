<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "Seeding June 2026 transactions for agents with zero sales...\n";

// Find agents with zero sales in June 2026
$agentsWithZero = DB::table('agents as a')
    ->where('a.is_deleted', false)
    ->where('a.status', 'ACTIVE')
    ->where('a.role', '!=', 'ADMIN')
    ->leftJoin('sales_transactions as st', function($j) {
        $j->on('st.agent_id', '=', 'a.agent_id')
          ->where('st.is_deleted', false)
          ->whereMonth('st.created_at', 6)
          ->whereYear('st.created_at', 2026);
    })
    ->select('a.agent_id', 'a.role', DB::raw('COALESCE(SUM(st.premium_amount),0) as june_sales'))
    ->groupBy('a.agent_id', 'a.role')
    ->having('june_sales', '=', 0)
    ->get();

echo "Agents with zero June sales: " . count($agentsWithZero) . "\n";

$vendors  = DB::table('vendors')->where('is_active',true)->pluck('vendor_id')->toArray();
$products = DB::table('products')->where('is_active',true)->pluck('product_id')->toArray();
$customers= DB::table('customers')->pluck('customer_id')->toArray();
$structureId = DB::table('commission_structures')->value('structure_id') ?? (string) Str::uuid();
$commRate = ['GROUP_LEADER'=>0.05,'TEAM_LEADER'=>0.08,'INTRODUCER'=>0.10];

$txnCount = 0;

foreach ($agentsWithZero as $agent) {
    $numTxns = rand(2, 5);
    for ($i = 0; $i < $numTxns; $i++) {
        $date       = now()->subDays(rand(0, 25)); // June 2026
        $amount     = rand(800, 8000) + (rand(0,99)/100);
        $vendorId   = $vendors[array_rand($vendors)];
        $productId  = $products[array_rand($products)];
        $customerId = count($customers) ? $customers[array_rand($customers)] : null;
        $policyId   = (string) Str::uuid();

        DB::table('sales_transactions')->insert([
            'policy_id'      => $policyId,
            'policy_number'  => 'POL-'.strtoupper(Str::random(4)).'-2026-'.rand(1000,9999),
            'agent_id'       => $agent->agent_id,
            'vendor_id'      => $vendorId,
            'product_id'     => $productId,
            'customer_id'    => $customerId,
            'premium_amount' => $amount,
            'coverage_start' => $date->copy(),
            'coverage_end'   => $date->copy()->addYear(),
            'status'         => 'ACTIVE',
            'is_deleted'     => false,
            'created_at'     => $date,
            'updated_at'     => $date,
        ]);

        $rate       = $commRate[$agent->role] ?? 0.05;
        $commission = round($amount * $rate, 2);

        DB::table('commission_transactions')->insert([
            'txn_id'              => (string) Str::uuid(),
            'policy_id'           => $policyId,
            'agent_id'            => $agent->agent_id,
            'structure_id'        => $structureId,
            'role_at_transaction' => $agent->role,
            'policy_premium'      => $amount,
            'total_pool_amount'   => round($amount * 0.20, 2),
            'entitlement_pct'     => $rate * 100,
            'commission_amount'   => $commission,
            'status'              => 'CONFIRMED',
            'created_at'          => $date,
            'updated_at'          => $date,
        ]);
        $txnCount++;
    }
}

echo "Done! Created $txnCount transactions for zero-sales agents.\n";
echo "Total sales transactions: " . DB::table('sales_transactions')->count() . "\n";
