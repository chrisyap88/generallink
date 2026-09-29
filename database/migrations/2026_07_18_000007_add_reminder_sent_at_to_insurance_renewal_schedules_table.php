<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 18 Jul 2026 — marks when the initial customer-facing renewal
// reminder (with the secure response link) was sent for this renewal
// cycle, so the daily job never sends it twice for the same policy.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_renewal_schedules', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('insurance_renewal_schedules', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
