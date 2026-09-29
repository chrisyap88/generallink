<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Jul 2026 — per Chris: "why dont you include to attached of
// video clip if any to support all marketing events, display
// professional header banner etc." Adds an optional banner image and
// an optional video (either a pasted link OR an uploaded file — Chris
// wants both options available) to all three outward-facing Growth &
// Outreach features: Recruitment Contests, Broadcast Campaigns, and the
// Public Agent Profile. Purely additive/nullable — nothing existing
// changes if left blank.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_contests', function (Blueprint $table) {
            $table->string('banner_image_path')->nullable()->after('rules_text');
            $table->string('video_url')->nullable()->after('banner_image_path');
            $table->string('video_file_path')->nullable()->after('video_url');
        });

        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            $table->string('banner_image_path')->nullable()->after('message_body');
            $table->string('video_url')->nullable()->after('banner_image_path');
            $table->string('video_file_path')->nullable()->after('video_url');
        });

        Schema::table('agent_profiles', function (Blueprint $table) {
            $table->string('banner_image_path')->nullable()->after('bio');
            $table->string('video_url')->nullable()->after('banner_image_path');
            $table->string('video_file_path')->nullable()->after('video_url');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_contests', function (Blueprint $table) {
            $table->dropColumn(['banner_image_path', 'video_url', 'video_file_path']);
        });
        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            $table->dropColumn(['banner_image_path', 'video_url', 'video_file_path']);
        });
        Schema::table('agent_profiles', function (Blueprint $table) {
            $table->dropColumn(['banner_image_path', 'video_url', 'video_file_path']);
        });
    }
};
