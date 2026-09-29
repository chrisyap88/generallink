<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Aug 2026 — a credit note reduces what a donor still owes on a
// pledge (e.g. a pledge written down, a goodwill adjustment). Donor
// contributions are deliberately kept OUTSIDE the formal journal (see
// CbeAccountingService header comment — avoids double-counting event
// income against the existing Event-close -> cbe_transactions posting
// built earlier), so this is a tracked adjustment record, not a journal
// entry: the Accounts Receivable dashboard figure is computed as
// pledged - received - credit_notes for that contribution.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_credit_notes')) {
            Schema::create('cbe_credit_notes', function (Blueprint $table) {
                $table->uuid('credit_note_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('donor_id');
                $table->uuid('contribution_id')->nullable();
                $table->date('note_date');
                $table->decimal('amount', 12, 2);
                $table->string('reason', 255)->nullable();
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('donor_id')->references('donor_id')->on('cbe_donors')->onDelete('restrict');
                $table->foreign('contribution_id')->references('contribution_id')->on('cbe_contributions')->onDelete('set null');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_credit_notes');
    }
};
