<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

// NEW 12 Aug 2026 — per Chris: application submissions and approved
// rebate programs both need a real, auto-generated, human-readable
// number — never a raw UUID shown on screen. Two separate series so an
// application number (a proposal, may never be approved) is never
// confused with a program number (a live, approved offer):
//   RBA-2026-0001  — application/submission number (vendor_rebate_applications)
//   RB-2026-0001   — rebate program number (vendor_rebate_offers, issued on approval)
// Wrapped in a transaction with lockForUpdate so two admins/vendors
// acting at the same moment can't both be handed the same number.
class RebateNumberingService
{
    public static function nextApplicationNumber(): string
    {
        return self::next('vendor_rebate_applications', 'application_number', 'RBA');
    }

    public static function nextProgramNumber(): string
    {
        return self::next('vendor_rebate_offers', 'rebate_program_number', 'RB');
    }

    private static function next(string $table, string $column, string $prefix): string
    {
        return DB::transaction(function () use ($table, $column, $prefix) {
            $year = now()->format('Y');
            $like = $prefix . '-' . $year . '-%';
            $max = DB::table($table)->where($column, 'like', $like)->lockForUpdate()->max($column);
            $nextSeq = $max ? ((int) substr($max, -4)) + 1 : 1;
            return $prefix . '-' . $year . '-' . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
        });
    }
}
