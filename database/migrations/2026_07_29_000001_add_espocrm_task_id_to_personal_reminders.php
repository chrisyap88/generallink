<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 29 Jul 2026 — EspoCRM integration (task #251). Every personal
// (follow-up) reminder an agent saves is now mirrored as a Task in
// EspoCRM in the background — agents keep using this exact same
// GeneralLink screen, they never see EspoCRM's UI. This column just
// remembers which EspoCRM Task id belongs to which GeneralLink reminder,
// so marking it Done or deleting it here can also update/cancel the
// EspoCRM side and keep the two in sync.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_reminders', function (Blueprint $table) {
            $table->string('espocrm_task_id', 100)->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('personal_reminders', function (Blueprint $table) {
            $table->dropColumn('espocrm_task_id');
        });
    }
};
