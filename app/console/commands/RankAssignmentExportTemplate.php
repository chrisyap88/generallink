<?php

namespace App\Console\Commands;

use App\Services\RoleLabelService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// -------------------------------------------------------
// NEW 31 Jul 2026 — per Chris: "what if there are millions of them, I
// need to tag one by one, is there a better logic?" Same bulk pattern
// already proven for the commission structure setup: one Excel file,
// one row per agent, type the rank name into one column, one import
// applies everything at once instead of clicking through the Rank
// Assignment web screen agent-by-agent.
//
// Sheet 1 lists every non-deleted agent (Full Name, Code, Role, Group,
// current rank as reference, and one editable "New Rank" column).
// Sheet 2 lists every available rank name, grouped by role+group, so
// Chris can see exactly what's valid to type into Sheet 1 without
// guessing spelling.
//
// Pairs with RankAssignmentImportTemplate below.
// -------------------------------------------------------
class RankAssignmentExportTemplate extends Command
{
    protected $signature = 'rank:export-template';
    protected $description = 'Export an editable Excel template to bulk-assign every agent a rank';

    public function handle(): int
    {
        // RESCOPED 31 Jul 2026, then again same day — per Chris: even
        // reference/display columns on a rank screen must speak only in
        // Organization Rewards Group terms, never an individual GL's own
        // team — so the "Group" column here is now the agent's
        // group_label (System Default / prihatin2u / rela2u / PVATM),
        // not their personal groups.group_name.
        $agents = DB::table('agents as a')
            ->leftJoin('group_labels as gl', 'gl.group_label_id', '=', 'a.group_label_id')
            ->leftJoin('role_ranks as rr', 'rr.rank_id', '=', 'a.rank_id')
            ->where('a.is_deleted', false)
            ->where('a.role', '!=', 'ADMIN')
            ->orderBy('a.role')
            ->orderBy('gl.group_name')
            ->orderBy('a.full_name')
            ->get([
                'a.agent_id', 'a.full_name', 'a.agent_code', 'a.role', 'a.group_label_id',
                'gl.group_name', 'rr.rank_name as current_rank_name',
            ]);

        // NEW 31 Jul 2026 — grouped by role first (so the reference sheet
        // stays role-grouped), then natural-sorted by the admin-typed
        // Rank No within each role (instead of the old plain-integer
        // display_order).
        $allRanks = DB::table('role_ranks')->where('is_active', true)->get()
            ->sort(fn($a, $b) => $a->role <=> $b->role ?: strnatcmp($a->rank_no ?? '', $b->rank_no ?? ''))
            ->values();
        $groupLabels = DB::table('group_labels')->pluck('group_name', 'group_label_id');

        $spreadsheet = new Spreadsheet();

        // ---- Sheet 1: Agents ----
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('Agents');

        $ws->getCell('A1')->setValue('GeneralLink — Bulk Rank Assignment');
        $ws->getCell('A2')->setValue('Type the exact rank name into the "New Rank" column (see the "Available Ranks" sheet for valid spellings). Leave blank to leave that agent unchanged. Type NONE to remove a rank.');
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $ws->getStyle('A2')->getFont()->setItalic(true);

        $headers = ['Full Name', 'Agent Code', 'Role', 'Group', 'Current Rank', 'New Rank', 'Agent ID (do not edit)', 'Role Code (do not edit)', 'Group Label ID (do not edit)'];
        foreach ($headers as $i => $val) {
            $coord = Coordinate::stringFromColumnIndex($i + 1) . '4';
            $ws->getCell($coord)->setValue($val);
        }
        $ws->getStyle('A4:I4')->getFont()->setBold(true);
        $ws->getStyle('A4:I4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDDDDD');

        $rowIdx = 5;
        foreach ($agents as $a) {
            $groupLabelId = $a->group_label_id;
            $roleLabel = RoleLabelService::label($a->role);

            $ws->getCell("A{$rowIdx}")->setValue($a->full_name);
            $ws->getCell("B{$rowIdx}")->setValue($a->agent_code);
            $ws->getCell("C{$rowIdx}")->setValue($roleLabel);
            $ws->getCell("D{$rowIdx}")->setValue($a->group_name ?? 'System Default');
            $ws->getCell("E{$rowIdx}")->setValue($a->current_rank_name ?? 'No Rank');
            // F left blank for Chris to fill in
            $ws->getCell("G{$rowIdx}")->setValue($a->agent_id);
            $ws->getCell("H{$rowIdx}")->setValue($a->role);
            $ws->getCell("I{$rowIdx}")->setValue($groupLabelId ?? '');

            $ws->getStyle("F{$rowIdx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF9C4');

            $rowIdx++;
        }

        $lastRow = $rowIdx - 1;
        foreach (range('A', 'I') as $col) {
            $ws->getColumnDimension($col)->setAutoSize(true);
        }
        $ws->getStyle("G5:I{$lastRow}")->getFont()->getColor()->setRGB('999999');
        $ws->setAutoFilter("A4:I{$lastRow}");
        $ws->freezePane('A5');

        // ---- Sheet 2: Available Ranks (reference only) ----
        $refSheet = $spreadsheet->createSheet();
        $refSheet->setTitle('Available Ranks');
        $refSheet->getCell('A1')->setValue('Available Rank Names (copy the exact spelling into the New Rank column on the Agents sheet)');
        $refSheet->getStyle('A1')->getFont()->setBold(true);

        $refHeaders = ['Role', 'Group', 'Rank Name', 'Rank No'];
        foreach ($refHeaders as $i => $val) {
            $coord = Coordinate::stringFromColumnIndex($i + 1) . '3';
            $refSheet->getCell($coord)->setValue($val);
        }
        $refSheet->getStyle('A3:D3')->getFont()->setBold(true);
        $refSheet->getStyle('A3:D3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDDDDD');

        $refRow = 4;
        foreach ($allRanks as $r) {
            $roleLabel = RoleLabelService::label($r->role);
            $groupLabelName = $r->group_label_id ? ($groupLabels[$r->group_label_id] ?? 'Unknown Group') : 'System Default';
            $refSheet->getCell("A{$refRow}")->setValue($roleLabel);
            $refSheet->getCell("B{$refRow}")->setValue($groupLabelName);
            $refSheet->getCell("C{$refRow}")->setValue($r->rank_name);
            $refSheet->getCell("D{$refRow}")->setValue($r->rank_no);
            $refRow++;
        }
        foreach (range('A', 'D') as $col) {
            $refSheet->getColumnDimension($col)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $writer->save(base_path('RANK_ASSIGNMENT_TEMPLATE.xlsx'));

        $this->info('Done. Saved as RANK_ASSIGNMENT_TEMPLATE.xlsx in your GeneralLink folder.');
        $this->line("  {$agents->count()} agent(s) listed across " . $allRanks->count() . ' available rank(s).');
        return self::SUCCESS;
    }
}
