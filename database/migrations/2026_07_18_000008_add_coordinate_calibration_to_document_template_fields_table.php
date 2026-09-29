<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// NEW 18 Jul 2026 — position/coordinate-based calibration, per Chris's
// explicit decision after the anchor-text engine proved unreliable on
// real bilingual/2-column documents (repeated wrong matches). Instead
// of "find this label text, read what follows it," a field can now be
// calibrated by an exact bounding box on the page (in PDF point units,
// resolution-independent) — filled in by running Chris's labeled
// sample image through OCR (see CoordinateCalibrationService) rather
// than typed in by hand.
//
// anchor_text is now nullable — COORDINATE-mode fields don't have one
// at all; SAME_LINE/NEXT_LINE/REGEX fields keep using it exactly as
// before. Both modes coexist per field, so existing calibrated
// templates keep working untouched.
return new class extends Migration
{
    public function up(): void
    {
        // Widen the enum to add COORDINATE without dropping the column
        // (keeps existing SAME_LINE/NEXT_LINE/REGEX rows intact).
        DB::statement("ALTER TABLE document_template_fields MODIFY extraction_mode ENUM('SAME_LINE','NEXT_LINE','REGEX','COORDINATE') DEFAULT 'SAME_LINE'");

        Schema::table('document_template_fields', function (Blueprint $table) {
            $table->string('anchor_text', 150)->nullable()->change();

            // Which page of the document this box sits on (1-indexed).
            $table->unsignedInteger('box_page')->nullable()->after('value_pattern');
            // Bounding box in PDF point units (1/72in), matching
            // pdftotext -bbox's coordinate system directly — resolution
            // independent, so it doesn't matter what DPI Chris's sample
            // image happened to be rendered at.
            $table->decimal('box_x', 10, 3)->nullable()->after('box_page');
            $table->decimal('box_y', 10, 3)->nullable()->after('box_x');
            $table->decimal('box_width', 10, 3)->nullable()->after('box_y');
            $table->decimal('box_height', 10, 3)->nullable()->after('box_width');
            // What the calibration step actually read at that box, for
            // Admin's own reference when reviewing/re-calibrating later.
            $table->string('calibration_sample_value', 255)->nullable()->after('box_height');
        });
    }

    public function down(): void
    {
        Schema::table('document_template_fields', function (Blueprint $table) {
            $table->dropColumn(['box_page', 'box_x', 'box_y', 'box_width', 'box_height', 'calibration_sample_value']);
        });
        DB::statement("ALTER TABLE document_template_fields MODIFY extraction_mode ENUM('SAME_LINE','NEXT_LINE','REGEX') DEFAULT 'SAME_LINE'");
    }
};
