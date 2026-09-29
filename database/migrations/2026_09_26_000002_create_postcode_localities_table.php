<?php

use Illuminate\Database\Migrations\Migration;

// SUPERSEDED 27 Sep 2026 — this migration first held a Klang-only town
// list. Per Chris: the reference must cover ALL districts for every CBE
// (temple, Rotary club, cooperative, church, NGO …), not only Klang.
// Replaced by 2026_09_27_000001_create_postcode_reference_table.php,
// which loads the full Pos Malaysia list. Left as a no-op so migration
// history stays consistent on machines where it already ran.
return new class extends Migration
{
    public function up(): void
    {
    }

    public function down(): void
    {
    }
};
