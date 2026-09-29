<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteTestAgent extends Command
{
    protected $signature = 'delete:test-agent {email} {--force : Delete even if already verified — TESTING ONLY}';
    protected $description = 'Delete a test agent by email, respecting the never-delete-verified-accounts rule unless --force is used';

    public function handle()
    {
        $email = $this->argument('email');
        $force = $this->option('force');

        $agent = DB::table('agents')->whereRaw('LOWER(email) = ?', [strtolower($email)])->first();

        if (!$agent) {
            $this->info("'{$email}' not found — nothing to delete.");
            return;
        }

        if ($agent->email_verified_at && !$force) {
            $this->error("'{$email}' is already verified — will NOT delete (safety rule). Add --force to override for testing.");
            return;
        }

        if ($agent->group_id) {
            DB::table('groups')->where('group_id', $agent->group_id)->where('created_by', $agent->agent_id)->delete();
            $this->line('Deleted associated group.');
        }

        DB::table('agent_profiles')->where('agent_id', $agent->agent_id)->delete();
        DB::table('audit_logs')->where('record_id', $agent->agent_id)->delete();
        DB::table('earning_wallets')->where('agent_id', $agent->agent_id)->delete();
        DB::table('agents')->where('agent_id', $agent->agent_id)->delete();

        $this->info("'{$email}' DELETED (agent, profile, group, wallet, audit logs).");
    }
}
