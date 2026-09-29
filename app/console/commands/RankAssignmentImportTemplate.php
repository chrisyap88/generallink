<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

// -------------------------------------------------------
// NEW 31 Jul 2026 — reads back RANK_ASSIGNMENT_TEMPLATE.xlsx (produced
// by rank:export-template) once Chris has typed rank names into the
// "New Rank" column, and bulk-updates agents.rank_id in one pass.
//
// Matching: for each filled-in row, looks up role_ranks by exact
// role + rank name (case-insensitive), preferring that agent's OWN
// Organization Rewards Group's rank if one exists under that name, else
// the System Default (group_label_id NULL) rank of the same name —
// same fallback convention already used elsewhere (RoleRankController,
// commission structures). A name that matches nothing is reported and
// skipped — never guessed. Typing NONE clears an agent's rank. A blank
// cell leaves that agent completely untouched.
//
// RESCOPED 31 Jul 2026 — column I is now the agent's group_label_id
// (Organization Rewards Group), not group_id (their own individual GL
// team), matching the same fix applied to the Rank Assignment screen.
// -------------------------------------------------------
class RankAssignmentImportTemplate extends Command
{
    protected $signature = 'rank:import-template {file=RANK_ASSIGNMENT_TEMPLATE.xlsx}';
    protected $description = 'Bulk-assign ranks to agents from the filled-in RANK_ASSIGNMENT_TEMPLATE.xlsx';

    public function handle(): int
    {
        $filePath = base_path($this->argument('file'));
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            $this->line('Run GENERATE_RANK_ASSIGNMENT_TEMPLATE.bat first, fill it in, then try again.');
            return self::FAILURE;
        }

        $spreadsheet = IOFactory::load($filePath);
        $ws = $spreadsheet->getSheetByName('Agents') ?? $spreadsheet->getActiveSheet();
        $highestRow = $ws->getHighestRow();

        // Cache role_ranks lookups so the same rank name isn't re-queried
        // for every agent who shares it.
        $rankCache = [];

        $updated = 0;
        $cleared = 0;
        $skippedBlank = 0;
        $skippedUnmatched = [];

        for ($row = 5; $row <= $highestRow; $row++) {
            $agentId = trim((string) $ws->getCell("G{$row}")->getValue());
            $roleCode = trim((string) $ws->getCell("H{$row}")->getValue());
            $groupLabelId = trim((string) $ws->getCell("I{$row}")->getValue());
            $newRankRaw = trim((string) $ws->getCell("F{$row}")->getValue());

            if ($agentId === '') {
                continue; // blank/decorative row
            }

            if ($newRankRaw === '') {
                $skippedBlank++;
                continue; // untouched — Chris didn't want to change this agent
            }

            if (strtoupper($newRankRaw) === 'NONE' || strtoupper($newRankRaw) === 'CLEAR') {
                DB::table('agents')->where('agent_id', $agentId)->update(['rank_id' => null, 'updated_at' => now()]);
                $cleared++;
                continue;
            }

            $groupLabelId = $groupLabelId !== '' ? $groupLabelId : null;
            $cacheKey = $roleCode . '|' . ($groupLabelId ?? 'default') . '|' . strtolower($newRankRaw);

            if (!array_key_exists($cacheKey, $rankCache)) {
                $match = null;
                if ($groupLabelId) {
                    $match = DB::table('role_ranks')
                        ->where('role', $roleCode)
                        ->where('group_label_id', $groupLabelId)
                        ->whereRaw('LOWER(rank_name) = ?', [strtolower($newRankRaw)])
                        ->where('is_active', true)
                        ->first();
                }
                if (!$match) {
                    $match = DB::table('role_ranks')
                        ->where('role', $roleCode)
                        ->whereNull('group_label_id')
                        ->whereRaw('LOWER(rank_name) = ?', [strtolower($newRankRaw)])
                        ->where('is_active', true)
                        ->first();
                }
                $rankCache[$cacheKey] = $match;
            }

            $match = $rankCache[$cacheKey];

            if (!$match) {
                $agentName = trim((string) $ws->getCell("A{$row}")->getValue());
                $skippedUnmatched[] = "{$agentName} (row {$row}) — no rank named \"{$newRankRaw}\" found for that role/group. Check the \"Available Ranks\" sheet for exact spelling.";
                continue;
            }

            $current = DB::table('agents')->where('agent_id', $agentId)->value('rank_id');
            if ($current === $match->rank_id) {
                continue; // already this rank, no change needed
            }

            DB::table('agents')->where('agent_id', $agentId)->update([
                'rank_id'    => $match->rank_id,
                'updated_at' => now(),
            ]);
            $updated++;
        }

        $this->line('');
        $this->info('========================================================');
        $this->info(' RESULT');
        $this->info('========================================================');
        $this->line("  Rank assigned/changed : {$updated}");
        $this->line("  Rank cleared (NONE)    : {$cleared}");
        $this->line("  Left unchanged (blank) : {$skippedBlank}");

        if (!empty($skippedUnmatched)) {
            $this->warn('  Skipped — rank name not found:');
            foreach ($skippedUnmatched as $m) {
                $this->line("    - {$m}");
            }
        } else {
            $this->line('  No unmatched rank names.');
        }

        return self::SUCCESS;
    }
}
