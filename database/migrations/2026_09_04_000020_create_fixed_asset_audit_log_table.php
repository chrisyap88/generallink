<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #395 Phase 4) — Fixed Asset Audit Trail (spec
// section 24/29). Exact same append-only event-log pattern as
// cbe_purchasing_audit_log (Task #394): one row per asset action,
// never updated or deleted, written alongside — never instead of —
// the asset's own status columns. asset_id is nullable because the
// very first event for an ACQUISITION that goes through Maker-Checker
// approval happens before an asset row exists yet (the request is
// still sitting in cbe_fixed_asset_requests).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_fixed_asset_audit_log')) {
            Schema::create('cbe_fixed_asset_audit_log', function (Blueprint $table) {
                $table->uuid('log_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('asset_id')->nullable();
                $table->string('asset_name', 150)->nullable();
                $table->string('action', 30); // CREATED / SUBMITTED_FOR_APPROVAL / APPROVED / REJECTED / EDITED / CAPITALISED / DEPRECIATED / IMPROVED / TRANSFERRED / DISPOSED
                $table->uuid('actor_id');
                $table->string('notes', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('actor_id')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'asset_id']);
                $table->index(['cbe_node_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_fixed_asset_audit_log');
    }
};
