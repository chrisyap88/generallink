<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 22 Aug 2026 — per Chris: a couple of temples in the real source
// file have more than one phone number crammed into a single
// Telephone 1/2 cell (e.g. "0379821457, 0123336968 (苏),0126637086
// (苏）" — three numbers, two tagged with a contact's surname). Rather
// than widen telephone_1/telephone_2 again and hit the same wall the
// next time a temple has 3+ numbers, this normalizes phone numbers into
// their own table — one row per real number, with an optional note
// (e.g. "苏") carried over from any "(name)" annotation next to it in
// the source. Replaces telephone_1/telephone_2 entirely (see next
// migration) since nothing has been imported into those columns yet —
// no data to migrate, just wiring this in before the first real import.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_hierarchy_node_phones')) {
            Schema::create('cbe_hierarchy_node_phones', function (Blueprint $table) {
                $table->uuid('phone_id')->primary();
                $table->uuid('node_id');
                $table->string('phone_number', 30);
                $table->string('contact_note', 50)->nullable();
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();

                $table->foreign('node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->index(['node_id', 'display_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_hierarchy_node_phones');
    }
};
