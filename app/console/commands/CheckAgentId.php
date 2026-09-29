<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckAgentId extends Command
{
    protected $signature = 'check:agent-id {id}';
    protected $description = 'Check if a specific agent_id still exists in the agents table';

    public function handle()
    {
        $id = $this->argument('id');
        $agent = DB::table('agents')->where('agent_id', $id)->first();

        if (!$agent) {
            $this->error("Agent ID '{$id}' does NOT exist — likely deleted.");
            return;
        }

        $this->info("Agent ID '{$id}' EXISTS:");
        $this->line('Full Name: ' . $agent->full_name);
        $this->line('Email: ' . $agent->email);
        $this->line('Role: ' . $agent->role);
        $this->line('Status: ' . $agent->status);
    }
}
