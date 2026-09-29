<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 29 Jul 2026 — EspoCRM integration (task #251), part 2. Admin's
// company-wide Calendar events (briefings, training, news) are now also
// mirrored as Meetings in EspoCRM in the background, same pattern as
// personal_reminders -> Task. Remembers the EspoCRM Meeting id so
// edits/deletes here can keep the EspoCRM side in sync.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->string('espocrm_meeting_id', 100)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropColumn('espocrm_meeting_id');
        });
    }
};
