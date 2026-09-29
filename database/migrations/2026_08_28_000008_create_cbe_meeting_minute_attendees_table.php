<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 28 Aug 2026 — per Chris: attendee name list (attended / not
// attended) on every meeting minute. agent_id links to an existing
// member of the entity (pulled from cbe_group_memberships on the create
// screen, tick each Attended/Not Attended); guest_name is for a
// non-member visitor (external guest, invited speaker) typed in
// directly — exactly one of the two is ever set, never both.
//
// Also adds two columns to cbe_meeting_minutes itself: meeting_type_id
// (which kind of meeting — Committee/AGM/Membership/etc., picked from
// the admin-configurable cbe_meeting_types list) and content_sections
// (the numbered 1.0/1.1/2.0... minutes body, stored as ordered JSON so
// the detail screen and future exports can reprint it with numbering
// intact).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_meeting_minute_attendees')) {
            Schema::create('cbe_meeting_minute_attendees', function (Blueprint $table) {
                $table->uuid('attendee_id')->primary();
                $table->uuid('minute_id');
                $table->uuid('agent_id')->nullable();
                $table->string('guest_name', 150)->nullable();
                $table->boolean('attended')->default(false);
                $table->timestamps();

                $table->foreign('minute_id')->references('minute_id')->on('cbe_meeting_minutes')->onDelete('cascade');
                $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->index(['minute_id']);
            });
        }

        if (! Schema::hasColumn('cbe_meeting_minutes', 'meeting_type_id')) {
            Schema::table('cbe_meeting_minutes', function (Blueprint $table) {
                $table->uuid('meeting_type_id')->nullable()->after('title');
                $table->foreign('meeting_type_id')->references('meeting_type_id')->on('cbe_meeting_types')->onDelete('set null');
            });
        }

        if (! Schema::hasColumn('cbe_meeting_minutes', 'content_sections')) {
            Schema::table('cbe_meeting_minutes', function (Blueprint $table) {
                $table->json('content_sections')->nullable()->after('summary');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_meeting_minute_attendees');

        if (Schema::hasColumn('cbe_meeting_minutes', 'meeting_type_id')) {
            Schema::table('cbe_meeting_minutes', function (Blueprint $table) {
                $table->dropForeign(['meeting_type_id']);
                $table->dropColumn('meeting_type_id');
            });
        }
        if (Schema::hasColumn('cbe_meeting_minutes', 'content_sections')) {
            Schema::table('cbe_meeting_minutes', function (Blueprint $table) {
                $table->dropColumn('content_sections');
            });
        }
    }
};
