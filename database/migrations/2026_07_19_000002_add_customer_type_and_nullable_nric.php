<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 19 Jul 2026 — per Chris: an agent (e.g. an Introducer like Murali)
// wants to track a personal contact he's trying to close a renewal/new
// sale with, BEFORE that contact is a real paying customer. The
// customers table previously required NRIC (identity key used by
// CustomerResolutionService to match/dedupe real policyholders), which
// a Prospect (personal contact/lead, no policy yet) doesn't have.
//
// REVISED 19 Jul 2026 — this migration originally also added a
// hardcoded 2-value customer_type enum (CUSTOMER/PROSPECT). Chris then
// asked for that to be fully configurable (unlimited categories like
// VIP, Expatriate, Government Servant, Army, Professional) AND
// clarified that "Prospect" is really a customer STATUS (Active /
// Prospect / Suspended / Withdrawn / ...), separate from customer TYPE
// (a demographic/segment tag). Both are now built as proper
// user-configurable lookup tables in
// 2026_07_19_000004_create_customer_status_and_type_tables.php — this
// migration now only handles making NRIC nullable.
return new class extends Migration
{
    public function up(): void
    {
        // Nullable NRIC — MySQL unique indexes allow multiple NULLs, so
        // this doesn't break the existing uniqueness guarantee for rows
        // that DO have an NRIC.
        Schema::table('customers', function (Blueprint $table) {
            $table->text('nric_encrypted')->nullable()->change();
            $table->string('nric_hash', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('nric_encrypted')->nullable(false)->change();
            $table->string('nric_hash', 64)->nullable(false)->change();
        });
    }
};
