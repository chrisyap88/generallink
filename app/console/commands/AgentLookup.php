<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// NEW 01 Aug 2026 — quick lookup so Chris can check exactly what Role,
// Rank, Group, and Organization Rewards Group any named agent currently
// has, instead of guessing — e.g. confirming whether his own login is
// tagged ADMIN (in which case it won't appear on Rank Assignment or
// Override Recipient Profile, which both deliberately exclude ADMIN).
class AgentLookup extends Command
{
    protected $signature = 'agents:lookup {name : full or partial name to search}';
    protected $description = 'Show Role, Rank, Group, and Organization Rewards Group for any agent by name';

    public function handle(): int
    {
        $name = $this->argument('name');

        $agents = DB::table('agents as a')
            ->leftJoin('role_ranks as rr', 'rr.rank_id', '=', 'a.rank_id')
            ->leftJoin('groups as g', 'g.group_id', '=', 'a.group_id')
            ->leftJoin('group_labels as gl', 'gl.group_label_id', '=', 'a.group_label_id')
            ->where('a.full_name', 'like', "%{$name}%")
            ->where('a.is_deleted', false)
            ->select('a.full_name', 'a.agent_code', 'a.role', 'a.status', 'rr.rank_name', 'g.group_name', 'gl.group_name as privilege_group')
            ->get();

        if ($agents->isEmpty()) {
            $this->warn("No agent found matching \"{$name}\".");
            return self::SUCCESS;
        }

        foreach ($agents as $a) {
            $this->line('');
            $this->info("{$a->full_name} ({$a->agent_code})");
            $this->line("  Role                    : {$a->role}");
            $this->line("  Status                  : {$a->status}");
            $this->line("  Rank                    : " . ($a->rank_name ?? '(none assigned)'));
            $this->line("  Organization Group       : " . ($a->group_name ?? '(none)'));
            $this->line("  Organization Rewards Group : " . ($a->privilege_group ?? '(none)'));

            if ($a->role === 'ADMIN') {
                $this->warn('  -> ADMIN accounts never appear on Rank Assignment or Override Recipient Profile — they sit outside the commission-earning hierarchy on purpose.');
            }
        }

        return self::SUCCESS;
    }
}
