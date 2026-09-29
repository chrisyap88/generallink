<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 10 Aug 2026 — per Chris: "study and modify the method to upload
// video for this entire programs...video is easier to understand all
// this marketing initiative." Two things bundled in one migration:
//
// 1) video_library gets a `feature_key` column so Admin can tag a
//    video as the "How This Works" explainer for one specific Growth &
//    Outreach screen (Referral Link, Rebate Offer Search, Recruitment
//    Contests, Public Profile, Survey Management, Send Survey,
//    Broadcast Campaigns, Channel Connections). Paired with a new
//    FEATURE_GUIDE video_type (added to VideoLibraryController::TYPES
//    in code, not schema).
//
// 2) broadcast_campaigns and recruitment_contests already had their
//    OWN separate video_url/video_file_path columns (built 25 Jul,
//    each campaign/contest re-uploads or re-links its own video file,
//    completely disconnected from Video Library — no reuse, no
//    provenance/source tracking, no central management). Adding
//    video_id lets Admin instead PICK an already-uploaded, already
//    audited video from Video Library as the campaign/contest's actual
//    outreach content, alongside the existing paste-link/upload-file
//    options (kept for flexibility, e.g. a plain YouTube link).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_library', function (Blueprint $table) {
            $table->string('feature_key', 40)->nullable()->after('source_help_desk_thread_id');
        });

        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            $table->uuid('video_id')->nullable()->after('video_file_path');
            $table->foreign('video_id')->references('video_id')->on('video_library')->nullOnDelete();
        });

        Schema::table('recruitment_contests', function (Blueprint $table) {
            $table->uuid('video_id')->nullable()->after('video_file_path');
            $table->foreign('video_id')->references('video_id')->on('video_library')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('video_library', function (Blueprint $table) {
            $table->dropColumn('feature_key');
        });
        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            $table->dropForeign(['video_id']);
            $table->dropColumn('video_id');
        });
        Schema::table('recruitment_contests', function (Blueprint $table) {
            $table->dropForeign(['video_id']);
            $table->dropColumn('video_id');
        });
    }
};
