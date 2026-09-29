<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 23 Jul 2026 — per Chris: after renaming the PVATM GL to "PVATM
// HQ" (RenamePvatmGL), the "Group" field on its Edit screen still
// showed the OLD name — "CA TEOW's Group". That's because `groups`
// has its own `group_name` column, set ONCE to "{full_name}'s Group"
// at creation time (SpecialGroupController::createAgent()) and never
// kept in sync afterwards — renaming the agent does not rename the
// group. This is a one-off fix for that already-stale row.
//
// Run via: php artisan pvatm:fix-group-name
// Shows the before/after and asks for a y/n confirmation first.
// -------------------------------------------------------
class FixPvatmGroupName extends Command
{
    protected $signature = 'pvatm:fix-group-name';
    protected $description = 'Update the PVATM group\'s stale group_name ("CA TEOW\'s Group") to match the renamed GL ("PVATM HQ")';

    private const GL_EMAIL = 'pvatmhq@generallink.my';
    private const NEW_GROUP_NAME = "PVATM HQ's Group";

    public function handle(): int
    {
        $agent = DB::table('agents')->where('email', self::GL_EMAIL)->where('is_deleted', false)->first();

        if (!$agent) {
            $this->error('No agent found with email ' . self::GL_EMAIL . ' — nothing changed.');
            return self::FAILURE;
        }

        if (!$agent->group_id) {
            $this->error('This agent has no group_id set — nothing to fix.');
            return self::FAILURE;
        }

        $group = DB::table('groups')->where('group_id', $agent->group_id)->first();
        if (!$group) {
            $this->error('No matching row in groups table — nothing to fix.');
            return self::FAILURE;
        }

        $this->info("Found group record (group_id: {$group->group_id}):");
        $this->line('  Current group_name: ' . $group->group_name);
        $this->line('  New group_name    : ' . self::NEW_GROUP_NAME);
        $this->line('');

        if (!$this->confirm('Apply this change now?', false)) {
            $this->warn('Cancelled — nothing changed.');
            return self::SUCCESS;
        }

        DB::table('groups')->where('group_id', $group->group_id)->update([
            'group_name' => self::NEW_GROUP_NAME,
            'updated_at' => now(),
        ]);

        $this->info('Done — the Group field will now show "' . self::NEW_GROUP_NAME . '".');
        return self::SUCCESS;
    }
}
