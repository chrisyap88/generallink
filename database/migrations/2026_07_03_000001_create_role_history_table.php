<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_history', function (Blueprint $table) {
            // Primary key
            $table->uuid('history_id')->primary();

            // The agent who changed role
            $table->uuid('agent_id');

            // Role change
            $table->enum('old_role', ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER', 'ADMIN']);
            $table->enum('new_role', ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER', 'ADMIN']);

            // Hierarchy change — nullable because e.g. a new GL's new_parent_id is NULL
            $table->uuid('old_parent_id')->nullable();
            $table->uuid('new_parent_id')->nullable();
            $table->uuid('old_group_id')->nullable();
            $table->uuid('new_group_id')->nullable();

            // When it took effect
            $table->timestamp('effective_date');

            // Why — e.g. AUTO_PROMOTED / AUTO_DEMOTED / ADMIN_DEMOTED
            $table->string('reason', 50);

            // System or Admin who triggered it (nullable = system-automatic)
            $table->uuid('performed_by')->nullable();

            // Decision 6 (03 Jul 2026): marks whether this specific demotion event's
            // displaced downline has already been reunited on a later re-promotion,
            // so the reunification logic never processes the same event twice.
            $table->timestamp('reunified_at')->nullable();

            // APPEND ONLY — never updated (except reunified_at) or deleted, per spec 25.4
            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index('agent_id');
            $table->index('old_parent_id');
            $table->index('new_parent_id');
            $table->index('reason');
            $table->index('effective_date');
        });

        // Foreign keys — nullOnDelete so historical rows survive if an agent record
        // is ever hard-deleted elsewhere (shouldn't happen per Decision 1, but this
        // keeps role_history safe as a permanent audit trail regardless).
        Schema::table('role_history', function (Blueprint $table) {
            $table->foreign('agent_id')->references('agent_id')->on('agents')->cascadeOnDelete();
            $table->foreign('old_parent_id')->references('agent_id')->on('agents')->nullOnDelete();
            $table->foreign('new_parent_id')->references('agent_id')->on('agents')->nullOnDelete();
            $table->foreign('performed_by')->references('agent_id')->on('agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('role_history', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropForeign(['old_parent_id']);
            $table->dropForeign(['new_parent_id']);
            $table->dropForeign(['performed_by']);
        });
        Schema::dropIfExists('role_history');
    }
};
