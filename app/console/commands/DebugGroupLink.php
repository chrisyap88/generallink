<?php

namespace App\Console\Commands;

use App\Models\Agent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebugGroupLink extends Command
{
    protected $signature = 'debug:group-link {email}';
    protected $description = 'Check an agent group_label_id against the actual group_labels table';

    public function handle()
    {
        $email = $this->argument('email');
        $agent = DB::table('agents')->whereRaw('LOWER(email) = ?', [strtolower($email)])->first();

        if (!$agent) {
            $this->error('Agent not found.');
            return;
        }

        $this->line('Agent: ' . $agent->full_name);
        $this->line('Stored group_label_id: ' . ($agent->group_label_id ?? 'NULL'));
        $this->line('Stored group_id: ' . ($agent->group_id ?? 'NULL'));

        if ($agent->group_label_id) {
            $label = DB::table('group_labels')->where('group_label_id', $agent->group_label_id)->first();
            $this->line('Matching group_labels row: ' . ($label ? $label->group_name : 'NOT FOUND — orphaned reference!'));
        }

        $this->line('');
        $this->line('--- All group_labels currently in the system ---');
        $labels = DB::table('group_labels')->get();
        foreach ($labels as $l) {
            $this->line($l->group_name . ' -> ' . $l->group_label_id);
        }
    }
}
