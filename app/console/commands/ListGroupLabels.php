<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// NEW 24 Jul 2026 — diagnostic only, no writes. Chris asked "how many
// type of group name beside public and special?" — there is no fixed
// list: "Direct Selling Group" just means an agent has no group_label_id
// at all, while "Special" covers however many Organization Rewards Group
// entries Admin has actually created in group_labels (each with its own
// Promotion/Demotion on/off setting). This prints the real count and
// list, plus how many agents currently sit in the Direct Selling bucket,
// so Chris can see exactly what exists without guessing.
//
// Run via: php artisan group-labels:list
class ListGroupLabels extends Command
{
    protected $signature = 'group-labels:list';
    protected $description = 'List every Organization Rewards Group (group_labels) with its promotion/demotion setting and linked agent count, plus how many agents are plain Direct Selling (read-only, no changes)';

    public function handle(): int
    {
        $labels = DB::table('group_labels')->orderBy('group_name')->get();

        $publicCount = DB::table('agents')->whereNull('group_label_id')->where('is_deleted', false)->count();

        $this->info('Direct Selling (no Organization Rewards Group at all): ' . $publicCount . ' agent(s).');
        $this->line('');

        if ($labels->isEmpty()) {
            $this->info('No Organization Rewards Groups have been created yet.');
            return self::SUCCESS;
        }

        $this->info($labels->count() . ' Organization Rewards Group(s) found:');
        $this->line('');

        foreach ($labels as $label) {
            $memberCount = DB::table('agents')
                ->where('group_label_id', $label->group_label_id)
                ->where('is_deleted', false)
                ->count();

            $leader = DB::table('agents')
                ->where('group_label_id', $label->group_label_id)
                ->where('role', 'GROUP_LEADER')
                ->where('is_deleted', false)
                ->first(['full_name', 'email']);

            $this->line("group_label_id            : {$label->group_label_id}");
            $this->line("name                      : {$label->group_name}");
            $this->line('promotion_demotion_enabled: ' . ($label->promotion_demotion_enabled ? 'yes (follows shared rules)' : 'no (fixed roles, special)'));
            $this->line('GL                        : ' . ($leader ? "{$leader->full_name} ({$leader->email})" : '— none linked —'));
            $this->line("agents linked             : {$memberCount}");
            $this->line(str_repeat('-', 50));
        }

        return self::SUCCESS;
    }
}
