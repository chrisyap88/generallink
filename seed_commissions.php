<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Get all transactions
$transactions = DB::table('sales_transactions as st')
    ->join('products as p', 'st.product_id', '=', 'p.product_id')
    ->where('st.is_deleted', 0)
    ->select('st.*', 'p.product_id')
    ->get();

// Agents
$agents = [
    'gl'  => 'c660db88-4455-4324-a9d5-4eb5c235e30c',
    'tl'  => '439fe1cd-d5c0-4624-a32a-5e4828d6954c',
    'in1' => '71356a27-1340-4c2c-a0cd-26f1b7d229f5',
    'in2' => '10697b42-76b6-4d80-88f3-eeef42b49ad9',
];

$count = 0;

foreach ($transactions as $txn) {
    // Get commission structure for this product/vendor
    $structure = DB::table('commission_structures')
        ->where('vendor_id', $txn->vendor_id)
        ->where('product_id', $txn->product_id)
        ->where('is_active', 1)
        ->first();

    if (!$structure) {
        echo "No structure found for transaction: {$txn->policy_number}\n";
        continue;
    }

    $premium = $txn->premium_amount;
    $totalPool = $premium * ($structure->total_commission_pct / 100);

    // GL commission
    $glAmount = $totalPool * ($structure->group_leader_pct / 100);
    DB::table('commission_transactions')->insert([
        'txn_id'               => (string) Str::uuid(),
        'policy_id'            => $txn->policy_id,
        'agent_id'             => $agents['gl'],
        'structure_id'         => $structure->structure_id,
        'role_at_transaction'  => 'GROUP_LEADER',
        'policy_premium'       => $premium,
        'total_pool_amount'    => $totalPool,
        'entitlement_pct'      => $structure->group_leader_pct,
        'commission_amount'    => $glAmount,
        'is_breakage'          => 0,
        'reward_points_earned' => 0,
        'status'               => 'CONFIRMED',
        'created_at'           => now(),
        'updated_at'           => now(),
    ]);
    $count++;

    // TL commission
    $tlAmount = $totalPool * ($structure->team_leader_pct / 100);
    DB::table('commission_transactions')->insert([
        'txn_id'               => (string) Str::uuid(),
        'policy_id'            => $txn->policy_id,
        'agent_id'             => $agents['tl'],
        'structure_id'         => $structure->structure_id,
        'role_at_transaction'  => 'TEAM_LEADER',
        'policy_premium'       => $premium,
        'total_pool_amount'    => $totalPool,
        'entitlement_pct'      => $structure->team_leader_pct,
        'commission_amount'    => $tlAmount,
        'is_breakage'          => 0,
        'reward_points_earned' => 0,
        'status'               => 'CONFIRMED',
        'created_at'           => now(),
        'updated_at'           => now(),
    ]);
    $count++;

    // Introducer commission — use agent from transaction
    $introAmount = $totalPool * ($structure->introducer_pct / 100);
    DB::table('commission_transactions')->insert([
        'txn_id'               => (string) Str::uuid(),
        'policy_id'            => $txn->policy_id,
        'agent_id'             => $txn->agent_id,
        'structure_id'         => $structure->structure_id,
        'role_at_transaction'  => 'INTRODUCER',
        'policy_premium'       => $premium,
        'total_pool_amount'    => $totalPool,
        'entitlement_pct'      => $structure->introducer_pct,
        'commission_amount'    => $introAmount,
        'is_breakage'          => 0,
        'reward_points_earned' => 0,
        'status'               => 'CONFIRMED',
        'created_at'           => now(),
        'updated_at'           => now(),
    ]);
    $count++;

    echo "Added commissions for: {$txn->policy_number} — GL: RM ".number_format($glAmount,2)." | TL: RM ".number_format($tlAmount,2)." | Intro: RM ".number_format($introAmount,2)."\n";
}

echo "\nDone! {$count} commission records added.\n";
