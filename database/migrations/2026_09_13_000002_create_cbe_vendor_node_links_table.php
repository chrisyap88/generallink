<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 13 Sep 2026 (Task #418) — per Chris: "a vendor can register once
// and offer to multiple CBE entities BUT will be multiple pick list
// options NOT default to ALL because Taoist prayer [items] cannot sell
// to Christian group." One row per (vendor, entity/node) the vendor has
// picked — never automatic, always an explicit choice, and never active
// until approved.
//
// "need approval process and use 4 eye policies to approve" — same
// maker-checker convention already used for GLADE Tier approvals
// (group_label_glade_tiers) and Bill Payment approvals: requested_by
// and approved_by must be two DIFFERENT agents. The app layer enforces
// requested_by <> approved_by; this table just stores both.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_vendor_node_links')) {
            Schema::create('cbe_vendor_node_links', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('vendor_id');
                $table->uuid('cbe_node_id');
                $table->enum('status', ['PENDING_APPROVAL', 'ACTIVE', 'REJECTED'])->default('PENDING_APPROVAL');
                $table->uuid('requested_by');
                $table->timestamp('requested_at')->nullable();
                $table->uuid('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->string('rejection_reason', 255)->nullable();
                $table->timestamps();

                // A vendor can only be linked to the same entity once —
                // re-requesting after a REJECTED link updates this same
                // row rather than creating a duplicate.
                $table->unique(['vendor_id', 'cbe_node_id']);

                $table->foreign('vendor_id')->references('vendor_id')->on('cbe_vendors')->onDelete('cascade');
                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('requested_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->foreign('approved_by')->references('agent_id')->on('agents')->nullOnDelete();
                $table->index(['cbe_node_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_vendor_node_links');
    }
};
