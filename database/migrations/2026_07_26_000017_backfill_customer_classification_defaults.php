<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 26 Jul 2026 — per Chris: the Customer KPI Dashboard's By Category/
// By Occupation/By Source charts were showing "Uncategorized"/
// "Unspecified" buckets — these are simply customers whose optional
// customer_category_id/occupation_group_id/source_id were left blank
// (some from real agent data entry, some from the CustomerKpiSampleData
// Seeder, which deliberately leaves 10-15% blank to simulate realistic
// incomplete data). Chris: "why there is a uncategorized, unspecified,
// undefined customer source... update db so that this is not display,
// i can show to my prospect with this confusing data."
//
// Rather than fabricating a specific guessed category for each customer
// (which would be dishonest — we don't actually know their real
// occupation/source), this adds a proper "Others" catch-all option to
// each of the 3 lookup tables (occupation_groups already has one) and
// backfills every NULL to that id. "Others" is a normal, permanent,
// editable option going forward too — any customer left blank in future
// will still show as NULL until an agent fills it in; this migration
// only cleans up what's blank RIGHT NOW.
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $this->ensureOthers('customer_categories', 'category_id', $now);
        $this->ensureOthers('occupation_groups', 'occupation_group_id', $now);
        $this->ensureOthers('customer_sources', 'source_id', $now);

        $categoryOthersId = DB::table('customer_categories')->where('code', 'OTHERS')->value('category_id');
        $occupationOthersId = DB::table('occupation_groups')->where('code', 'OTHERS')->value('occupation_group_id');
        $sourceOthersId = DB::table('customer_sources')->where('code', 'OTHERS')->value('source_id');

        DB::table('customers')->whereNull('customer_category_id')->update(['customer_category_id' => $categoryOthersId]);
        DB::table('customers')->whereNull('occupation_group_id')->update(['occupation_group_id' => $occupationOthersId]);
        DB::table('customers')->whereNull('source_id')->update(['source_id' => $sourceOthersId]);
    }

    public function down(): void
    {
        // Intentionally no-op — reversing would mean guessing which rows
        // were originally NULL vs genuinely "Others", which isn't
        // knowable. The 3 "Others" lookup rows are left in place (they're
        // harmless, normal, editable options either way).
    }

    private function ensureOthers(string $table, string $pkColumn, $now): void
    {
        if (DB::table($table)->where('code', 'OTHERS')->exists()) {
            return;
        }
        DB::table($table)->insert([
            $pkColumn     => (string) Str::uuid(),
            'code'        => 'OTHERS',
            'description' => 'Others',
            'is_active'   => true,
            'is_system'   => false,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }
};
