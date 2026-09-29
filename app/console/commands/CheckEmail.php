<?php

namespace App\Console\Commands;

use App\Models\Agent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckEmail extends Command
{
    protected $signature = 'check:email {email}';
    protected $description = 'Check if an email already exists in the agents table';

    public function handle()
    {
        $email = $this->argument('email');

        $agent = DB::table('agents')->whereRaw('LOWER(email) = ?', [strtolower($email)])->first();

        if (!$agent) {
            $this->info("'{$email}' does NOT exist — safe to use.");
            return;
        }

        $this->warn("'{$email}' ALREADY EXISTS:");
        $this->line('Full Name: ' . $agent->full_name);
        $this->line('Role: ' . $agent->role);
        $this->line('Status: ' . $agent->status);
        $this->line('Email Verified: ' . ($agent->email_verified_at ?? 'NOT YET'));
        $this->line('Is Deleted: ' . $agent->is_deleted);
    }
}
