<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$chrisGroupId = DB::table('agents')->where('full_name','like','%Chris Yap%')->where('role','GROUP_LEADER')->value('group_id');

// Find old seed Introducers in Chris Yap group with non-hierarchy codes
$old = DB::table('agents')
    ->where('group_id', $chrisGroupId)
    ->where('role', 'INTRODUCER')
    ->where('is_deleted', false)
    ->whereNotLike('agent_code', '1-%')
    ->get(["full_name","agent_code"]);

echo "Old seed Introducers in Chris Yap group:\n";
foreach ($old as $a) {
    echo "  {$a->full_name} ({$a->agent_code})\n";
}
echo "Count: " . count($old) . "\n";

// Soft delete them
$deleted = DB::table('agents')
    ->where('group_id', $chrisGroupId)
    ->where('role', 'INTRODUCER')
    ->where('is_deleted', false)
    ->whereNotLike('agent_code', '1-%')
    ->update(['is_deleted' => true]);
echo "Soft deleted: $deleted\n";

echo "\nChris Yap Intros after fix: " . DB::table('agents')->where('group_id', $chrisGroupId)->where('role','INTRODUCER')->where('is_deleted',false)->count() . "\n";
