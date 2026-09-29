<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------
        // calendar_events — Admin creates/manages (same centralized
        // pattern as Vendors/Products, per confirmed decision); GL/TL/
        // Introducer get read-only visibility. Covers briefing/training
        // session invitations for now; broadcast news could reuse this
        // later with a different event_type if wanted.
        // -----------------------------------------------------
        if (! Schema::hasTable('calendar_events')) {
            Schema::create('calendar_events', function (Blueprint $table) {
                $table->uuid('event_id')->primary();
                $table->string('title');
                $table->text('description')->nullable();
                $table->date('event_date');
                $table->time('event_time')->nullable();
                $table->string('event_type')->default('BRIEFING'); // BRIEFING, TRAINING, NEWS, OTHER
                $table->uuid('created_by')->nullable(); // agents.agent_id
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('event_date');
            });
        }

        // -----------------------------------------------------
        // reminder_logs — records every automated reminder actually
        // sent, so the daily scheduled check never sends the same
        // reminder twice (e.g. the "30 days before expiry" warning
        // should fire once, not once a day for 30 days straight).
        // -----------------------------------------------------
        if (! Schema::hasTable('reminder_logs')) {
            Schema::create('reminder_logs', function (Blueprint $table) {
                $table->uuid('log_id')->primary();
                $table->string('reminder_type'); // e.g. UNVERIFIED_REGISTRATION, BIRTHDAY, COMMISSION_EXPIRY_30DAY
                $table->uuid('related_id')->nullable(); // agent_id, structure_id, etc. depending on type
                $table->timestamp('sent_at');
                $table->timestamps();

                $table->index(['reminder_type', 'related_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
        Schema::dropIfExists('calendar_events');
    }
};
