<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// -------------------------------------------------------
// NEW 3 Aug 2026 — AI Guided Navigation, part 2/4.
// Audit trail for every guided-navigation session: who asked for it, what
// goal, which workflow (or ad-hoc), which step it's on, and how it ended.
// Mirrors the same audit-first discipline already used elsewhere in this
// app (audit_logs, customer_change_logs).
// -------------------------------------------------------
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_navigation_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id')->nullable(); // null = guest (e.g. affiliate_registration)
            $table->string('mode', 20); // guest | agent
            $table->string('goal', 100); // e.g. affiliate_registration
            $table->string('workflow_key', 100)->nullable(); // matches config key if authored; null = ad-hoc
            $table->unsignedInteger('current_step')->default(0);
            $table->string('status', 20)->default('open'); // open | completed | abandoned
            $table->string('started_on_route', 150)->nullable();
            $table->timestamps();

            $table->index('agent_id');
            $table->index('goal');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_navigation_sessions');
    }
};
