<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 10 Aug 2026 — per Chris: "in a live environment it could be a lot
// of video, the admin wont remember when this video upload come from
// who...design the drill down the source of the video come from."
//
// GeneralLink has no automatic inbox for vendor emails/WhatsApp messages
// (vendors have no Help Desk access at all today — only agents do), so
// there's no real structured record to link to for those two channels.
// The honest, working design: Admin records WHERE a video came from as
// a short category (source_type) plus a free-text note with the actual
// identifying details (sender, date, reference) at upload time — same
// spirit as how the app already handles anything it can't fully
// automate (e.g. Due Diligence's "Manual Review Required"). WHO uploaded
// it and WHEN was already captured (created_by/created_at) but never
// shown anywhere — that's fixed in the view, not the schema.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_library', function (Blueprint $table) {
            $table->string('source_type', 30)->default('DIRECT_UPLOAD')->after('expiry_date'); // DIRECT_UPLOAD/EMAIL/WHATSAPP/HELP_DESK/OTHER
            $table->string('source_note', 300)->nullable()->after('source_type'); // e.g. "Emailed from vendor@company.com on 8 Aug 2026"
        });
    }

    public function down(): void
    {
        Schema::table('video_library', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'source_note']);
        });
    }
};
