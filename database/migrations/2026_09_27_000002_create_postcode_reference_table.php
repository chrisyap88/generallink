<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// NEW 27 Sep 2026 — per Chris (MANDATORY GUIDELINE): one national
// postcode reference for ALL districts and states, used by every CBE
// search (Entity, City, Branch, State, HQ — temple, Rotary club,
// cooperative, church, NGO, current and future). Loads the full Pos
// Malaysia list (about 57,000 rows: every area/street location, its
// post-office town, postcode and state) from
// database/data/pos_malaysia_postcodes.csv.
//
// `district` is included for the district (daerah) layer; Pos Malaysia's
// list does not carry districts, so it is filled separately.
return new class extends Migration
{
    public function up(): void
    {
        // An earlier Klang-only version of this table may exist — replace it.
        if (Schema::hasTable('postcode_localities') && ! Schema::hasColumn('postcode_localities', 'area_postcode')) {
            Schema::drop('postcode_localities');
        }

        if (! Schema::hasTable('postcode_localities')) {
            Schema::create('postcode_localities', function (Blueprint $table) {
                $table->id();
                $table->string('postcode', 5)->index();
                $table->string('location', 191)->index();
                $table->string('post_office', 100)->index();
                $table->string('district', 100)->nullable()->index();
                $table->string('state', 100)->index();
                // true = a normal delivery-area postcode (streets / housing
                // areas). false = a single-building postcode (government
                // office, hospital, company). P.O. Box / locked-bag rows are
                // not loaded at all. Pick lists use area postcodes only.
                $table->boolean('area_postcode')->default(true)->index();
                $table->timestamps();
            });
        }

        // Always (re)load from the file, so a run that stopped half-way
        // (e.g. on a bad line) is cleanly redone instead of skipped.
        DB::table('postcode_localities')->truncate();
        {
            $file = database_path('data/pos_malaysia_postcodes.csv');
            $fh = fopen($file, 'r');
            fgetcsv($fh); // header
            $batch = [];
            $now = now();
            while (($row = fgetcsv($fh)) !== false) {
                if (count($row) < 4 || ! preg_match('/^\d{5}$/', trim($row[0])) || trim($row[2]) === '' || trim($row[3]) === '') {
                    continue; // skip any malformed line (postcode must be exactly 5 digits)
                }
                // Per Chris: postcodes only, never P.O. Box / locked bag.
                if (preg_match('/peti\s*surat|beg\s*berkunci|p\.?\s*o\.?\s*box|locked\s*bag/i', $row[1])) {
                    continue;
                }
                $batch[] = [
                    'postcode' => str_pad(trim($row[0]), 5, '0', STR_PAD_LEFT),
                    'location' => mb_substr(trim($row[1]), 0, 191),
                    'post_office' => mb_substr(trim($row[2]), 0, 100),
                    'state' => mb_substr(trim($row[3]), 0, 100),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if (count($batch) >= 1000) {
                    DB::table('postcode_localities')->insert($batch);
                    $batch = [];
                }
            }
            if ($batch) {
                DB::table('postcode_localities')->insert($batch);
            }
            fclose($fh);

            // A postcode serving fewer than 3 areas is a single-building
            // postcode (e.g. 41506 Mahkamah Daerah, 40560 Ketua Polis
            // Negeri) — unless it is the only postcode of its town (e.g.
            // 42940 Pulau Ketam).
            $perPostcode = DB::table('postcode_localities')
                ->selectRaw('postcode, post_office, state, COUNT(*) as n')
                ->groupBy('postcode', 'post_office', 'state')->get();
            $perTown = $perPostcode->groupBy(fn ($r) => $r->post_office.'|'.$r->state)->map->count();
            foreach ($perPostcode as $r) {
                if ($r->n < 3 && ($perTown[$r->post_office.'|'.$r->state] ?? 0) > 1) {
                    DB::table('postcode_localities')
                        ->where('postcode', $r->postcode)->where('post_office', $r->post_office)->where('state', $r->state)
                        ->update(['area_postcode' => false]);
                }
            }
        }

        if (Schema::hasTable('malaysia_postcodes')) {
            // Undo the earlier wrong entry: 42920 is Pulau Indah (Pulau
            // Ketam is 42940) per Pos Malaysia.
            DB::table('malaysia_postcodes')->where('postcode', '42920')->where('city', 'Pulau Ketam')->update(['city' => 'Pulau Indah']);

            // Add any postcode the old list was missing.
            // Compared in PHP (not a SQL join): malaysia_postcodes and this
            // table can have different collations on some MySQL installs,
            // which made the join fail with "Illegal mix of collations".
            $have = array_flip(DB::table('malaysia_postcodes')->pluck('postcode')->all());
            $missing = DB::table('postcode_localities')
                ->selectRaw('postcode, MIN(post_office) as city, MIN(state) as state')
                ->groupBy('postcode')->get()
                ->filter(fn ($m) => ! isset($have[$m->postcode]));
            foreach ($missing as $m) {
                DB::table('malaysia_postcodes')->insertOrIgnore(['postcode' => $m->postcode, 'city' => $m->city, 'state' => $m->state]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('postcode_localities');
    }
};
