<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 23 Jul 2026 — per Chris: rename the PVATM Special Privilege
// Group's GL record from "CA TEOW" to "PVATM HQ", and change the email
// to pvatmhq@generallink.my. Password is left completely untouched —
// only full_name and email are updated. Looks the agent up by the
// known current email (cateow@generallink.my)
// AND cross-checks it really is the PVATM group's GROUP_LEADER before
// touching anything, so this can never silently rename the wrong
// agent if the email was ever changed since.
//
// Run via: php artisan pvatm:rename-gl
// Shows the before/after and asks for a y/n confirmation before
// writing anything.
// -------------------------------------------------------
class RenamePvatmGL extends Command
{
    protected $signature = 'pvatm:rename-gl';
    protected $description = 'Rename the PVATM GL record from CA TEOW to PVATM HQ and change its email to pvatmhq@generallink.my (password untouched)';

    private const OLD_EMAIL = 'cateow@generallink.my';
    private const NEW_NAME = 'PVATM HQ';
    private const NEW_EMAIL = 'pvatmhq@generallink.my';

    public function handle(): int
    {
        $agent = DB::table('agents')->where('email', self::OLD_EMAIL)->where('is_deleted', false)->first();

        if (!$agent) {
            $this->error('No agent found with email ' . self::OLD_EMAIL . ' — nothing changed.');
            $this->line('If the email was already changed, tell me the current email and I\'ll adjust this command.');
            return self::FAILURE;
        }

        $label = DB::table('group_labels')->where('slug', 'pvatm')->first();
        if ($agent->role !== 'GROUP_LEADER' || !$label || $agent->group_label_id !== $label->group_label_id) {
            $this->error('That email does not belong to the PVATM Group Leader — refusing to touch it as a safety check.');
            $this->line("Found instead: {$agent->full_name} <{$agent->email}>, role={$agent->role}");
            return self::FAILURE;
        }

        $emailTaken = DB::table('agents')->where('email', self::NEW_EMAIL)->where('agent_id', '!=', $agent->agent_id)->where('is_deleted', false)->exists();
        if ($emailTaken) {
            $this->error(self::NEW_EMAIL . ' is already used by a different agent — refusing to create a duplicate.');
            return self::FAILURE;
        }

        $this->info('Found the PVATM GL record:');
        $this->line("  Current name : {$agent->full_name}");
        $this->line("  Current email: {$agent->email}");
        $this->line('');
        $this->info('Will change it to:');
        $this->line('  New name : ' . self::NEW_NAME);
        $this->line('  New email: ' . self::NEW_EMAIL);
        $this->line('  Password : unchanged');
        $this->line('');

        if (!$this->confirm('Apply this change now?', false)) {
            $this->warn('Cancelled — nothing changed.');
            return self::SUCCESS;
        }

        DB::table('agents')->where('agent_id', $agent->agent_id)->update([
            'full_name'  => self::NEW_NAME,
            'email'      => self::NEW_EMAIL,
            'updated_at' => now(),
        ]);

        $this->info('Done. The PVATM GL is now "' . self::NEW_NAME . '" <' . self::NEW_EMAIL . '> — password is unchanged, so it still logs in with the same password as before.');
        return self::SUCCESS;
    }
}
