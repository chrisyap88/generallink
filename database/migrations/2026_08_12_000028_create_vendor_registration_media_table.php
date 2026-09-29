<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 12 Aug 2026 — per Chris: "for new vendor registration can he
// upload his video or slide show or you tube like or ppt, product
// flyer." Optional marketing attachments a vendor can add at
// registration time — separate from the mandatory SSM/company documents
// (vendor_documents table) and from the post-login Content Library
// submission flow (video_library table), since this is captured before
// the vendor even has a login. Feeds the new "Video/URL/Slideshow"
// folder tab on Admin's Pending Vendor Logins detail panel.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_registration_media', function (Blueprint $table) {
            $table->uuid('media_id')->primary();
            $table->uuid('vendor_id');
            $table->string('media_type', 20); // VIDEO, SLIDESHOW, PPT, FLYER, LINK
            $table->string('file_path', 255)->nullable(); // set for uploaded files
            $table->string('file_name', 255)->nullable();
            $table->string('external_url', 500)->nullable(); // set for LINK (e.g. YouTube)
            $table->string('label', 150)->nullable(); // vendor's own short description
            $table->timestamps();

            $table->foreign('vendor_id')->references('vendor_id')->on('vendors')->cascadeOnDelete();
            $table->index(['vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_registration_media');
    }
};
