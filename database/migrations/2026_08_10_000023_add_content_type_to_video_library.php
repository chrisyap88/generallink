<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 10 Aug 2026 — per Chris: "do you allow upload slide show and
// marketing flyer, google link form?" Rather than building 3 separate
// dedicated modules (each with its own storage, provenance tracking,
// and "pick from library" reuse), this widens the SAME Video Library
// to hold any of 4 content types:
//   VIDEO     — existing behaviour, unchanged (mp4/mov/webm/etc.)
//   SLIDESHOW — PDF (browsers preview PDF inline; PPTX has no built-in
//               in-browser preview, so PDF is the supported format)
//   FLYER     — image (JPG/PNG) or PDF
//   LINK      — no file at all — an external URL (Google Form,
//               YouTube, Facebook post, etc.) stored in external_url
// `content_type` defaults to VIDEO so every existing row (already-
// uploaded videos) keeps working with zero data migration needed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_library', function (Blueprint $table) {
            $table->string('content_type', 20)->default('VIDEO')->after('video_type');
            $table->string('external_url', 500)->nullable()->after('original_file_name');
        });
    }

    public function down(): void
    {
        Schema::table('video_library', function (Blueprint $table) {
            $table->dropColumn(['content_type', 'external_url']);
        });
    }
};
