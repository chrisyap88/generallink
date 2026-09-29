<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "Updating commission amounts for ALL agents...\n";

// Different earn rates by agent code to ensure earn rank differs from sales rank
// Pattern: some high-sales agents have low earn rate, some low-sales have high earn rate
$rateByCode = [
    // Chris Yap group
    '1'    => 0.05, // Chris Yap GL
    '1-2'  => 0.06, // high sales, low earn
    '1-3'  => 0.15, // medium sales, HIGH earn
    '1-4'  => 0.12, // medium-high sales, high earn
    '1-5'  => 0.10,
    '1-6'  => 0.08,
    '1-7'  => 0.09,
    '1-8'  => 0.14, // low sales, HIGH earn
    // Amy Tan group
    '1-1'    => 0.05,
    '1-1-1'  => 0.14,
    '1-1-2'  => 0.06,
    '1-1-3'  => 0.12,
    '1-1-4'  => 0.09,
    '1-1-5'  => 0.15,
    '1-1-6'  => 0.07,
    '1-1-7'  => 0.11,
    '1-1-8'  => 0.08,
    '1-1-9'  => 0.13,
    '1-1-10' => 0.10,
    '1-1-11' => 0.06,
    // David Lim group
    '2'    => 0.05,
    '2-1'  => 0.13,
    '2-2'  => 0.07,
    '2-3'  => 0.15,
    '2-4'  => 0.08,
    '2-5'  => 0.11,
    '2-6'  => 0.06,
];

// Default rates by role for agents not in the map
$defaultRates = [
    'GROUP_LEADER' => 0.05,
    'TEAM_LEADER'  => 0.08,
    'INTRODUCER'   => 0.10,
];

// Get all agents
$agents = DB::table('agents')
    ->where('is_deleted', false)
    ->where('status', 'ACTIVE')
    ->where('role', '!=', 'ADMIN')
    ->get(['agent_id', 'full_name', 'agent_code', 'role']);

$count = 0;
foreach ($agents as $agent) {
    $rate = $rateByCode[$agent->agent_code] ?? $defaultRates[$agent->role] ?? 0.08;

    // Update all commission transactions for this agent
    $commissions = DB::table('commission_transactions')
        ->where('agent_id', $agent->agent_id)
        ->get(['txn_id', 'policy_premium']);

    foreach ($commissions as $comm) {
        DB::table('commission_transactions')
            ->where('txn_id', $comm->txn_id)
            ->update([
                'commission_amount' => round($comm->policy_premium * $rate, 2),
                'entitlement_pct'   => $rate * 100,
            ]);
        $count++;
    }
}

echo "Updated $count commission transactions.\n\n";

// Show summary for all TLs
echo "=== Chris Yap TLs ===\n";
$chrisGroupId = DB::table('agents')->where('full_name','like','%Chris Yap%')->where('role','GROUP_LEADER')->value('group_id');
$tls = DB::table('agents')->where('group_id',$chrisGroupId)->where('role','TEAM_LEADER')->where('is_deleted',false)->orderBy('agent_code')->get(['agent_id','full_name','agent_code']);
foreach ($tls as $tl) {
    $sales = DB::table('sales_transactions')->where('agent_id',$tl->agent_id)->where('is_deleted',false)->whereMonth('created_at',6)->whereYear('created_at',2026)->sum('premium_amount');
    $earn  = DB::table('commission_transactions')->where('agent_id',$tl->agent_id)->whereMonth('created_at',6)->whereYear('created_at',2026)->sum('commission_amount');
    echo "{$tl->agent_code} {$tl->full_name}: Sales=RM ".number_format($sales,2)." Earn=RM ".number_format($earn,2)."\n";
}

echo "\n=== Amy Tan TLs ===\n";
$amyGroupId = DB::table('agents')->where('full_name','like','%Amy Tan%')->where('role','GROUP_LEADER')->value('group_id');
$tls = DB::table('agents')->where('group_id',$amyGroupId)->where('role','TEAM_LEADER')->where('is_deleted',false)->orderBy('agent_code')->get(['agent_id','full_name','agent_code']);
foreach ($tls as $tl) {
    $sales = DB::table('sales_transactions')->where('agent_id',$tl->agent_id)->where('is_deleted',false)->whereMonth('created_at',6)->whereYear('created_at',2026)->sum('premium_amount');
    $earn  = DB::table('commission_transactions')->where('agent_id',$tl->agent_id)->whereMonth('created_at',6)->whereYear('created_at',2026)->sum('commission_amount');
    echo "{$tl->agent_code} {$tl->full_name}: Sales=RM ".number_format($sales,2)." Earn=RM ".number_format($earn,2)."\n";
}

echo "\n=== David Lim TLs ===\n";
$davidGroupId = DB::table('agents')->where('full_name','like','%David Lim%')->where('role','GROUP_LEADER')->value('group_id');
$tls = DB::table('agents')->where('group_id',$davidGroupId)->where('role','TEAM_LEADER')->where('is_deleted',false)->orderBy('agent_code')->get(['agent_id','full_name','agent_code']);
foreach ($tls as $tl) {
    $sales = DB::table('sales_transactions')->where('agent_id',$tl->agent_id)->where('is_deleted',false)->whereMonth('created_at',6)->whereYear('created_at',2026)->sum('premium_amount');
    $earn  = DB::table('commission_transactions')->where('agent_id',$tl->agent_id)->whereMonth('created_at',6)->whereYear('created_at',2026)->sum('commission_amount');
    echo "{$tl->agent_code} {$tl->full_name}: Sales=RM ".number_format($sales,2)." Earn=RM ".number_format($earn,2)."\n";
}

echo "\nDone!\n";
