<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// NEW 24 Jul 2026 — diagnostic only, no writes. Chris: the new group-label
// Organization Category dropdown shows "CA TEOW's Group" 6 times. This
// lists every row in `groups` (not just PVATM) with enough detail to tell
// whether that's 6 genuinely separate groups that all happen to share the
// same stale auto-generated name, or leftover/duplicate rows from earlier
// test data — and which one (if any) is the real PVATM group already
// fixed by pvatm:fix-group-name.
//
// Run via: php artisan groups:list
class ListGroups extends Command
{
    protected $signature = 'groups:list';
    protected $description = 'List every group record with its code, name, linked Group Leader, active status, and agent count (read-only, no changes)';

    public function handle(): int
    {
        $groups = DB::table('groups')->orderBy('group_name')->orderBy('created_at')->get();

        if ($groups->isEmpty()) {
            $this->info('No groups found.');
            return self::SUCCESS;
        }

        $this->info($groups->count() . ' group record(s) found:');
        $this->line('');

        foreach ($groups as $g) {
            $leader = DB::table('agents')
                ->where('group_id', $g->group_id)
                ->where('role', 'GROUP_LEADER')
                ->where('is_deleted', false)
                ->first(['full_name', 'email', 'status']);

            $memberCount = DB::table('agents')->where('group_id', $g->group_id)->where('is_deleted', false)->count();

            $this->line("group_id   : {$g->group_id}");
            $this->line("group_code : {$g->group_code}");
            $this->line("group_name : {$g->group_name}");
            $this->line('is_active  : ' . ($g->is_active ? 'yes' : 'no'));
            $this->line('created_at : ' . $g->created_at);
            $this->line('GL         : ' . ($leader ? "{$leader->full_name} ({$leader->email}) - {$leader->status}" : '— none linked —'));
            $this->line("members    : {$memberCount}");
            $this->line(str_repeat('-', 50));
        }

        return self::SUCCESS;
    }
}
