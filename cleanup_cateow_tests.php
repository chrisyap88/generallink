<?php
$emails = ['cateow88@generallink.my', 'cateow888@generallink.my', 'cateow@generalink.my'];

foreach ($emails as $email) {
    $agent = App\Models\Agent::where('email', $email)->first();

    if (!$agent) {
        echo "{$email}: NOT FOUND — nothing to delete." . PHP_EOL;
        continue;
    }

    if ($agent->email_verified_at) {
        echo "{$email}: SKIPPED — this account is already verified, will not hard-delete per confirmed rule." . PHP_EOL;
        continue;
    }

    // If this agent created their own group (e.g. as a Special Privilege
    // GL), remove that too, so no orphaned empty group is left behind.
    if ($agent->group_id) {
        DB::table('groups')->where('group_id', $agent->group_id)->where('created_by', $agent->agent_id)->delete();
    }

    DB::table('agent_profiles')->where('agent_id', $agent->agent_id)->delete();
    DB::table('audit_logs')->where('record_id', $agent->agent_id)->delete();
    DB::table('agents')->where('agent_id', $agent->agent_id)->delete();

    echo "{$email}: DELETED (agent, profile, group, audit logs)." . PHP_EOL;
}

echo PHP_EOL . 'Done.' . PHP_EOL;
