<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 15 Sep 2026 — a practitioner's own recurring weekly working
// hours (e.g. "Mon-Fri 9am-5pm"). day_of_week: 0=Sunday .. 6=Saturday,
// same convention as PHP's date('w'). A day with no row is simply
// closed that day of the week — a practitioner can have more than one
// row per day (e.g. a morning block and an evening block).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_practitioner_weekly_hours')) {
            Schema::create('cbe_practitioner_weekly_hours', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('practitioner_profile_id');
                $table->unsignedTinyInteger('day_of_week');
                $table->time('start_time');
                $table->time('end_time');
                $table->timestamps();

                $table->foreign('practitioner_profile_id', 'fk_wkhours_profile')
                    ->references('id')->on('cbe_practitioner_profiles')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_practitioner_weekly_hours');
    }
};
