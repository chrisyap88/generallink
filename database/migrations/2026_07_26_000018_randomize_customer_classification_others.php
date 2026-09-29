<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// NEW 26 Jul 2026 — per Chris: the previous migration
// (2026_07_26_000017) backfilled blank Category/Occupation/Source with
// a generic "Others" bucket so the KPI charts stopped showing raw
// Uncategorized/Unspecified. Chris then looked at the actual charts and
// asked instead: "update the DB for others by categories, by
// occupation, by source random update a real information dont use
// others" — i.e. every customer currently sitting on "Others" should
// instead get a real, specific, randomly-picked value from the normal
// list (Individual/SME/Corporate..., Banking & Finance/Military...,
// Walk-in/Referral/Google...), same spirit as the original
// CustomerKpiSampleDataSeeder's random assignment. This is cosmetic
// sample-style data, not a claim about the customer's real profile —
// exactly like the seeder already does for the 300 sample customers.
//
// The "Others" option itself is left in place in all 3 lookup tables
// (still perfectly valid for an agent to pick going forward) — this
// migration only moves existing customers OFF it onto a specific value.
return new class extends Migration
{
    public function up(): void
    {
        $this->randomizeAway('customer_categories', 'category_id', 'customer_category_id');
        $this->randomizeAway('occupation_groups', 'occupation_group_id', 'occupation_group_id');
        $this->randomizeAway('customer_sources', 'source_id', 'source_id');
    }

    public function down(): void
    {
        // Intentionally no-op — same reasoning as 000017: there's no way
        // to know which rows were originally on "Others" vs genuinely
        // picked that value, so there's nothing sensible to revert to.
    }

    private function randomizeAway(string $lookupTable, string $lookupPk, string $customerColumn): void
    {
        $othersId = DB::table($lookupTable)->where('code', 'OTHERS')->value($lookupPk);
        if (!$othersId) {
            return; // nothing to do if this install never got the "Others" row
        }

        $choices = DB::table($lookupTable)
            ->where('code', '!=', 'OTHERS')
            ->where('is_active', true)
            ->pluck($lookupPk)
            ->all();
        if (empty($choices)) {
            return; // no real options to randomize into — leave as-is
        }

        DB::table('customers')
            ->where($customerColumn, $othersId)
            ->select('customer_id')
            ->orderBy('customer_id')
            ->chunkById(200, function ($rows) use ($choices, $customerColumn) {
                foreach ($rows as $row) {
                    DB::table('customers')
                        ->where('customer_id', $row->customer_id)
                        ->update([$customerColumn => $choices[array_rand($choices)]]);
                }
            }, 'customer_id');
    }
};
