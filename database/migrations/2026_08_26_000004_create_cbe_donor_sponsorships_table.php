<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 26 Aug 2026, 15th pass — per Chris: "Sponsor/donor can participate
// many temple many places also ya, Penang donor / sponsor can be a
// Klang one of the temple as well as his hometown in Penang ya."
// cbe_donors.cbe_node_id only ever recorded ONE home temple per donor
// (by design, per that migration's own comment: "each Temple keeps its
// own donor register"). That's still correct as the donor's home/
// registering temple, but it can't represent the SAME donor also
// giving at other temples without re-typing a duplicate donor row —
// exactly the duplication problem donors were built to avoid, one
// level up. This pivot adds those EXTRA temple links on top of the
// existing home node, without changing cbe_donors itself.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_donor_sponsorships')) {
            Schema::create('cbe_donor_sponsorships', function (Blueprint $table) {
                $table->uuid('sponsorship_id')->primary();
                $table->uuid('donor_id');
                $table->uuid('cbe_node_id');
                $table->text('notes')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->foreign('donor_id')->references('donor_id')->on('cbe_donors')->onDelete('cascade');
                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('set null');
                $table->unique(['donor_id', 'cbe_node_id']);
                $table->index('cbe_node_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_donor_sponsorships');
    }
};
