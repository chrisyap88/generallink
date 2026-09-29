<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 15 Sep 2026 — per Chris: a practitioner must be able to "declare
// off day or on leave or not available date" — one-off blocked dates
// on top of their regular weekly hours (public holiday, personal
// leave, fully booked elsewhere). A blocked date closes that whole day
// regardless of what the weekly hours say.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_practitioner_leave_dates')) {
            Schema::create('cbe_practitioner_leave_dates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('practitioner_profile_id');
                $table->date('leave_date');
                $table->string('reason', 150)->nullable();
                $table->timestamps();

                $table->foreign('practitioner_profile_id', 'fk_leave_profile')
                    ->references('id')->on('cbe_practitioner_profiles')->onDelete('cascade');
                $table->unique(['practitioner_profile_id', 'leave_date'], 'uniq_leave_per_profile_date');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_practitioner_leave_dates');
    }
};
