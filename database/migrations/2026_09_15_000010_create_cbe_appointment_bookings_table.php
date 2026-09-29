<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 15 Sep 2026 — the actual booked slots. A member picks an open
// slot for a specific practitioner on a specific date; status lets a
// cancelled slot free itself up again without losing the history of
// who booked it. member_agent_id is always an existing Agent/Member —
// there is no separate "customer" concept here.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_appointment_bookings')) {
            Schema::create('cbe_appointment_bookings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('practitioner_profile_id');
                $table->uuid('member_agent_id');
                $table->date('booking_date');
                $table->time('start_time');
                $table->time('end_time');
                $table->enum('status', ['CONFIRMED', 'CANCELLED'])->default('CONFIRMED');
                $table->string('notes', 255)->nullable();
                $table->timestamps();

                $table->foreign('practitioner_profile_id', 'fk_booking_profile')
                    ->references('id')->on('cbe_practitioner_profiles')->onDelete('cascade');
                $table->foreign('member_agent_id', 'fk_booking_member')
                    ->references('agent_id')->on('agents')->onDelete('cascade');
                $table->index(['practitioner_profile_id', 'booking_date', 'status'], 'idx_booking_lookup');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_appointment_bookings');
    }
};
