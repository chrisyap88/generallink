<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 27 Aug 2026 — per Chris: Meeting Calendar tab (Secretarial
// Overview) needs an agenda, time, venue, and a status distinguishing
// upcoming/scheduled meetings from completed ones with minutes on
// file — cbe_meeting_minutes previously only had date/title/summary/one
// attachment (past-minutes only). Feedback form: Chris confirmed reuse
// of the EXISTING Survey Management module — survey_id links a meeting
// to a survey instead of building a new bespoke feedback feature.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_meeting_minutes', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_meeting_minutes', 'agenda')) {
                $table->text('agenda')->nullable()->after('title');
            }
            if (! Schema::hasColumn('cbe_meeting_minutes', 'meeting_time')) {
                $table->time('meeting_time')->nullable()->after('meeting_date');
            }
            if (! Schema::hasColumn('cbe_meeting_minutes', 'venue')) {
                $table->string('venue', 255)->nullable()->after('meeting_time');
            }
            if (! Schema::hasColumn('cbe_meeting_minutes', 'status')) {
                $table->enum('status', ['SCHEDULED', 'COMPLETED'])->default('COMPLETED')->after('venue');
            }
            if (! Schema::hasColumn('cbe_meeting_minutes', 'survey_id')) {
                $table->uuid('survey_id')->nullable()->after('status');
                $table->foreign('survey_id')->references('survey_id')->on('surveys')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_meeting_minutes', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_meeting_minutes', 'survey_id')) {
                $table->dropForeign(['survey_id']);
                $table->dropColumn('survey_id');
            }
            foreach (['status', 'venue', 'meeting_time', 'agenda'] as $col) {
                if (Schema::hasColumn('cbe_meeting_minutes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
