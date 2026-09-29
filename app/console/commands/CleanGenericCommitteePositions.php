<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// NEW 25 Sep 2026 -- per Chris: after cbe:import-klang-committee ran,
// he saw the generic "President" placeholder still showing on the
// Committee/Management Positions screen for "Persekutuan Pertubuhan
// Agama Tao Malaysia" -- this group was created BEFORE the 25 Sep
// per-group split (migration 2026_09_25_000002), so it was cloned the
// original 6 generic English seed positions (President/Deputy
// President/Secretary/Treasurer/CEO/Finance Director) at that time,
// same as every other pre-existing CBE group. Those 6 sit alongside
// the 9 real Malay positions the import created and were never
// actually used by anyone -- exactly the "it never have such position
// in CBE goup Persatuan Tao" problem from before, just re-appearing
// here because this group already existed when that fix ran.
//
// Deliberately SAFE: only deactivates a generic position if it has
// ZERO people currently assigned to it in this group -- never touches
// one that's actually in use. Deactivate (not delete) so the change is
// fully reversible from the normal Edit screen if ever needed.
class CleanGenericCommitteePositions extends Command
{
    protected $signature = 'cbe:clean-generic-committee-positions {group_name=Persekutuan Pertubuhan Agama Tao Malaysia}';

    protected $description = 'Deactivate unused generic English committee positions (President/Deputy President/Secretary/Treasurer/CEO/Finance Director) for one CBE group, leaving any position that already has a real person assigned untouched';

    private const GENERIC_CODES = ['PRESIDENT', 'DEPUTY_PRESIDENT', 'SECRETARY', 'TREASURER', 'CEO', 'FINANCE_DIRECTOR'];

    public function handle(): int
    {
        $groupName = $this->argument('group_name');
        $group = DB::table('group_labels')->where('group_name', $groupName)->where('group_type', 'CBE')->first();
        if (! $group) {
            $this->error("No CBE group found named \"$groupName\" -- nothing changed.");

            return self::FAILURE;
        }

        $rows = DB::table('cbe_committee_position_types')
            ->where('group_label_id', $group->group_label_id)
            ->whereIn('code', self::GENERIC_CODES)
            ->where('is_active', true)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Nothing to clean up -- no active generic positions found for this group.');

            return self::SUCCESS;
        }

        $deactivated = 0;
        $skipped = [];

        foreach ($rows as $row) {
            $assignedCount = DB::table('group_committee_members')->where('position_type_id', $row->id)->count();
            if ($assignedCount > 0) {
                $skipped[] = $row->position_label." ($assignedCount person(s) assigned -- left active)";

                continue;
            }

            DB::table('cbe_committee_position_types')->where('id', $row->id)->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
            $deactivated++;
            $this->info('Deactivated (unused): '.$row->position_label);
        }

        $this->newLine();
        $this->line("Group: $groupName");
        $this->line("Deactivated: $deactivated generic position(s) that had nobody assigned.");
        if ($skipped) {
            $this->warn('Left active (already has someone assigned, not touched):');
            foreach ($skipped as $s) {
                $this->warn('  - '.$s);
            }
        }

        return self::SUCCESS;
    }
}
