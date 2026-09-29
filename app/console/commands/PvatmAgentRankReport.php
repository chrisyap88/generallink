<?php

namespace App\Console\Commands;

use App\Services\RoleLabelService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// NEW 1 Aug 2026 — per Chris: "check PVATM all the agent what is their
// rank and role list in excel file to me." One Excel file listing every
// non-deleted agent whose group_label is PVATM, with their role (using
// PVATM's own configured label, e.g. "Ali" not the hardcoded system
// default "Introducer"), and their currently assigned rank (agents.rank_id
// -> role_ranks, same column the Rank Assignment screen and the bulk
// rank:export-template command already read from).
class PvatmAgentRankReport extends Command
{
    protected $signature = 'report:pvatm-agent-ranks';
    protected $description = 'Export an Excel list of every PVATM agent with their role and rank';

    public function handle(): int
    {
        $group = DB::table('group_labels')->where('group_name', 'like', '%PVATM%')->first();

        if (!$group) {
            $this->error('No group_labels row found with "PVATM" in the name. Existing group labels:');
            foreach (DB::table('group_labels')->pluck('group_name') as $name) {
                $this->line("  - {$name}");
            }
            return self::FAILURE;
        }

        $agents = DB::table('agents as a')
            ->leftJoin('role_ranks as rr', 'rr.rank_id', '=', 'a.rank_id')
            ->where('a.group_label_id', $group->group_label_id)
            ->where('a.is_deleted', false)
            ->orderBy('a.role')
            ->orderBy('a.full_name')
            ->get([
                'a.agent_code', 'a.full_name', 'a.role', 'a.status',
                'rr.rank_no', 'rr.rank_name',
            ]);

        if ($agents->isEmpty()) {
            $this->warn("No agents found under {$group->group_name} (group_label_id={$group->group_label_id}).");
            return self::SUCCESS;
        }

        $spreadsheet = new Spreadsheet();
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('PVATM Agents');

        $ws->getCell('A1')->setValue("PVATM — Agent Role & Rank List ({$agents->count()} agent(s))");
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $headers = ['No.', 'Agent Code', 'Full Name', 'Role', 'Rank No', 'Rank Name', 'Status'];
        foreach ($headers as $i => $val) {
            $coord = Coordinate::stringFromColumnIndex($i + 1) . '3';
            $ws->getCell($coord)->setValue($val);
        }
        $ws->getStyle('A3:G3')->getFont()->setBold(true);
        $ws->getStyle('A3:G3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDDDDD');

        $rowIdx = 4;
        foreach ($agents as $i => $a) {
            $ws->getCell("A{$rowIdx}")->setValue($i + 1);
            $ws->getCell("B{$rowIdx}")->setValue($a->agent_code);
            $ws->getCell("C{$rowIdx}")->setValue($a->full_name);
            // PVATM's own configured role label (e.g. "Ali"), not the
            // hardcoded system default ("Introducer") — same lookup used
            // on the Rank Allocation / Earning Income Structure screens.
            $ws->getCell("D{$rowIdx}")->setValue(RoleLabelService::label($a->role, $group->group_label_id));
            $ws->getCell("E{$rowIdx}")->setValue($a->rank_no ?? '');
            $ws->getCell("F{$rowIdx}")->setValue($a->rank_name ?? 'No Rank Assigned');
            $ws->getCell("G{$rowIdx}")->setValue($a->status);
            $rowIdx++;
        }

        $lastRow = $rowIdx - 1;
        foreach (range('A', 'G') as $col) {
            $ws->getColumnDimension($col)->setAutoSize(true);
        }
        $ws->setAutoFilter("A3:G{$lastRow}");
        $ws->freezePane('A4');

        $filename = 'PVATM_Agent_Rank_Report.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save(base_path($filename));

        $this->info("Done. Saved as {$filename} in your GeneralLink folder.");
        $this->line("  {$agents->count()} agent(s) listed under {$group->group_name}.");
        return self::SUCCESS;
    }
}
