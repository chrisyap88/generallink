<?php

// NEW 27 Sep 2026 — per Chris: District (daerah) for every post-office town
// in Malaysia, so CBE Entity Affiliation can pick a whole district at once
// (e.g. Klang = Klang, Kapar, Pelabuhan Klang, Pulau Indah, Pulau Ketam;
// Banting / Jenjarom / Tanjong Sepat / Telok Panglima Garang = Kuala Langat).
// Source: database/data/post_office_districts.csv (state, post_office, district).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('postcode_localities')) {
            return;
        }

        $file = database_path('data/post_office_districts.csv');
        $fh = fopen($file, 'r');
        fgetcsv($fh); // header
        while (($r = fgetcsv($fh)) !== false) {
            if (count($r) < 3) {
                continue;
            }
            DB::table('postcode_localities')
                ->where('state', $r[0])->where('post_office', $r[1])
                ->update(['district' => $r[2]]);
        }
        fclose($fh);

        $hasIndex = collect(DB::select("SHOW INDEX FROM postcode_localities WHERE Key_name = 'pl_district_idx'"))->isNotEmpty();
        if (! $hasIndex) {
            Schema::table('postcode_localities', function ($t) {
                $t->index(['district', 'state'], 'pl_district_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('postcode_localities')) {
            DB::table('postcode_localities')->update(['district' => null]);
        }
    }
};
