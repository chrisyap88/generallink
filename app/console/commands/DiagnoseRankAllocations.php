<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// NEW 1 Aug 2026 — one-off diagnostic per Chris: "after set up allocation
// and click save did you save? if yes where to retrieve it back to
// edit?" — the Edit Earning Income Structure screen was showing "Rank %
// currently allocated: 0.00%" for PVATM and Chris couldn't tell if that
// meant his save never went through, or if the readout itself was wrong.
// This prints exactly what's actually sitting in commission_rank_allocations
// for a structure, broken down by which group's rank ladder each row
// belongs to, so we stop guessing and just look at the real data.
class DiagnoseRankAllocations extends Command
{
    protected $signature = 'diagnose:rank-allocations {search? : Vendor or product name to filter by (optional — omit to list every structure)}';
    protected $description = 'Show every commission_rank_allocations row for a structure, grouped by which Group Label the rank belongs to.';

    public function handle(): int
    {
        $search = $this->argument('search');

        $query = DB::table('commission_structures as cs')
            ->join('vendors as v', 'v.vendor_id', '=', 'cs.vendor_id')
            ->join('products as p', 'p.product_id', '=', 'cs.product_id')
            ->select('cs.*', 'v.vendor_name', 'p.product_name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('v.vendor_name', 'like', "%{$search}%")
                  ->orWhere('p.product_name', 'like', "%{$search}%");
            });
        }

        $structures = $query->orderBy('v.vendor_name')->orderBy('p.product_name')->get();

        if ($structures->isEmpty()) {
            $this->warn('No matching Earning Income Structures found.');
            return self::SUCCESS;
        }

        foreach ($structures as $s) {
            $this->info("=== {$s->vendor_name} / {$s->product_name}  (structure_id={$s->structure_id}) ===");
            $this->line("Total Earning Income %: {$s->total_commission_pct}%  |  is_rank_only: " . ($s->is_rank_only ? 'YES' : 'NO') . "  |  HQ/Cwg/Introducer on file: {$s->group_leader_pct}% / {$s->team_leader_pct}% / {$s->introducer_pct}%");

            $rows = DB::table('commission_rank_allocations as cra')
                ->join('role_ranks as rr', 'rr.rank_id', '=', 'cra.rank_id')
                ->leftJoin('group_labels as gl', 'gl.group_label_id', '=', 'rr.group_label_id')
                ->where('cra.structure_id', $s->structure_id)
                ->orderByRaw('rr.group_label_id IS NULL DESC')
                ->orderBy('gl.group_name')
                ->orderBy('rr.role')
                ->orderBy('rr.rank_no')
                ->get(['rr.rank_name', 'rr.role', 'rr.group_label_id', 'gl.group_name', 'cra.rank_pct']);

            if ($rows->isEmpty()) {
                $this->warn('  No rank allocation rows saved for this structure at all, in any group.');
            } else {
                $currentGroup = '__none__';
                $groupSum = 0.0;
                foreach ($rows as $r) {
                    $groupLabel = $r->group_name ?? 'System Default';
                    if ($groupLabel !== $currentGroup) {
                        if ($currentGroup !== '__none__') {
                            $this->line("    -- group total so far: {$groupSum}% --");
                        }
                        $this->line("  Group: {$groupLabel}");
                        $currentGroup = $groupLabel;
                        $groupSum = 0.0;
                    }
                    $groupSum += (float) $r->rank_pct;
                    $this->line("    {$r->rank_name} ({$r->role}): {$r->rank_pct}%");
                }
                $this->line("    -- group total so far: {$groupSum}% --");
            }
            $this->line('');
        }

        return self::SUCCESS;
    }
}
