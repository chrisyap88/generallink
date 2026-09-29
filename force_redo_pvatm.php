<?php
// TESTING ONLY — force-deletes regardless of verification status,
// overriding the normal safety rule, per explicit request to redo
// PVATM testing completely from scratch.
$email = 'cateow88@generallink.my';

$agent = App\Models\Agent::where('email', $email)->first();

if (!$agent) {
    echo "{$email}: NOT FOUND — nothing to delete." . PHP_EOL;
} else {
    if ($agent->group_id) {
        DB::table('groups')->where('group_id', $agent->group_id)->where('created_by', $agent->agent_id)->delete();
        echo "Deleted associated group." . PHP_EOL;
    }
    DB::table('agent_profiles')->where('agent_id', $agent->agent_id)->delete();
    DB::table('audit_logs')->where('record_id', $agent->agent_id)->delete();
    DB::table('earning_wallets')->where('agent_id', $agent->agent_id)->delete();
    DB::table('agents')->where('agent_id', $agent->agent_id)->delete();
    echo "{$email}: FORCE DELETED (agent, profile, group, wallet, audit logs)." . PHP_EOL;
}

echo PHP_EOL . 'Done — PVATM now has 0 agents, ready for a fresh test.' . PHP_EOL;
