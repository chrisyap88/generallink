<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 9 Aug 2026 — Video Library, per Chris: "create a library video
// library for the admin to add and categories video type, video name,
// created date, ownership and status... this group of video is not
// store inside the DB to avoid occupied te DB size. it will store at
// destinate hardisc folder to browse and upload. the folder director
// path is store in this video maintenance."
//
// Only METADATA lives here — the actual video file bytes are never
// written to the database. Each row points to a file that lives on disk
// inside the folder path configured via system_settings key
// 'video_library_folder_path' (same updateOrInsert pattern used
// elsewhere in the app, e.g. director email setting).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_library', function (Blueprint $table) {
            $table->uuid('video_id')->primary();
            $table->string('video_name', 200);
            $table->string('video_type', 40); // INTRODUCTION/ANNOUNCEMENT/MARKETING_PROMOTION/PRODUCT_EXPLANATION/OTHER
            $table->string('stored_file_name', 255); // actual file name on disk inside the configured folder
            $table->string('original_file_name', 255)->nullable();
            $table->string('ownership', 150)->nullable();
            $table->string('status', 20)->default('ACTIVE'); // ACTIVE/INACTIVE
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index('video_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_library');
    }
};
