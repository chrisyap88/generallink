<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$amyGroupId = DB::table('agents')->where('full_name','like','%Amy Tan%')->where('role','GROUP_LEADER')->value('group_id');
$davidGroupId = DB::table('agents')->where('full_name','like','%David Lim%')->where('role','GROUP_LEADER')->value('group_id');

echo "Amy group: $amyGroupId\n";
echo "David group: $davidGroupId\n";

// Fix Amy Tan group agents - code starts with 1-1-
$amyFixed = DB::table('agents')
    ->where('agent_code', 'like', '1-1-%')
    ->where('role', '!=', 'GROUP_LEADER')
    ->update(['group_id' => $amyGroupId]);
echo "Amy Tan group agents fixed: $amyFixed\n";

// Fix David Lim group agents - code starts with 2-
$davidFixed = DB::table('agents')
    ->where('agent_code', 'like', '2-%')
    ->where('role', '!=', 'GROUP_LEADER')
    ->update(['group_id' => $davidGroupId]);
echo "David Lim group agents fixed: $davidFixed\n";

// Verify
echo "\nChris Yap TLs: " . DB::table('agents')->where('group_id', DB::table('agents')->where('full_name','like','%Chris Yap%')->where('role','GROUP_LEADER')->value('group_id'))->where('role','TEAM_LEADER')->where('is_deleted',false)->count() . "\n";
echo "Amy Tan TLs: " . DB::table('agents')->where('group_id', $amyGroupId)->where('role','TEAM_LEADER')->where('is_deleted',false)->count() . "\n";
echo "David Lim TLs: " . DB::table('agents')->where('group_id', $davidGroupId)->where('role','TEAM_LEADER')->where('is_deleted',false)->count() . "\n";

echo "Chris Yap Intros: " . DB::table('agents')->where('group_id', DB::table('agents')->where('full_name','like','%Chris Yap%')->where('role','GROUP_LEADER')->value('group_id'))->where('role','INTRODUCER')->where('is_deleted',false)->count() . "\n";
echo "Amy Tan Intros: " . DB::table('agents')->where('group_id', $amyGroupId)->where('role','INTRODUCER')->where('is_deleted',false)->count() . "\n";
echo "David Lim Intros: " . DB::table('agents')->where('group_id', $davidGroupId)->where('role','INTRODUCER')->where('is_deleted',false)->count() . "\n";
