<?php

namespace App\Console\Commands;

use App\Services\RoleLabelService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 31 Jul 2026 — fixes a real bug in migration 000012 (the
// role_label_overrides rescope). That migration dropped the OLD
// groups.group_id column but never deleted the rows that used to be
// scoped to a specific group — those rows just kept existing, and
// because the new group_label_id column defaults to NULL for existing
// rows, every one of those old per-group rows silently became an extra
// "System Default" row. RoleLabelService::all() picks up ALL
// group_label_id-IS-NULL rows, so whichever old group-specific label
// happened to load last "won" and leaked into every group's screen —
// this is why Chris saw prihatin2u showing labels that were actually
// meant for a different group.
//
// Run with no flags first to just SEE what's actually in the table
// (safe, read-only). Run with --reset to wipe everything back to a
// clean single System Default row per role (Group Leader / Team Leader
// / Introducer, no short label) — after that, System Default and every
// Organization Rewards Group's own labels need to be re-typed fresh via
// Organization Category Maintenance, same as the original migration
// note already asked for.
class CleanupRoleLabelOverrides extends Command
{
    protected $signature = 'role-labels:cleanup {--reset : Wipe all rows and start clean with the 3 hardcoded defaults}';
    protected $description = 'Show (or, with --reset, fix) the duplicate/orphaned rows left behind by the role_label_overrides rescope.';

    public function handle(): int
    {
        $rows = DB::table('role_label_overrides')->orderBy('role')->orderByRaw('group_label_id IS NULL DESC')->get();

        $this->info('--- Every row currently in role_label_overrides ---');
        foreach ($rows as $row) {
            $group = $row->group_label_id ? $row->group_label_id : 'NULL (System Default)';
            $this->line("role={$row->role}  label=\"{$row->label}\"  short=\"" . ($row->short_label ?? '') . "\"  group_label_id={$group}  override_id={$row->override_id}");
        }
        $this->line('');
        $this->line('Total rows: ' . $rows->count());

        if (!$this->option('reset')) {
            $this->warn('This was a read-only look — nothing changed. If you see more than one row per role with "NULL (System Default)", that confirms the bug. Re-run with --reset to wipe everything and start clean.');
            return self::SUCCESS;
        }

        DB::table('role_label_overrides')->delete();

        foreach (RoleLabelService::defaults() as $role => $label) {
            DB::table('role_label_overrides')->insert([
                'override_id'    => (string) Str::uuid(),
                'role'           => $role,
                'group_label_id' => null,
                'label'          => $label,
                'short_label'    => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        RoleLabelService::forgetCache();

        $this->info('Done — role_label_overrides reset to a clean System Default (Group Leader / Team Leader / Introducer, no short labels). Go to Organization Category Maintenance to re-type System Default and each group\'s own labels (PVATM, prihatin2u, rela2u) fresh.');

        return self::SUCCESS;
    }
}
