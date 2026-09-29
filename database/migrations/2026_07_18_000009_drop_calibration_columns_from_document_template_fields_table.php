<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NEW 18 Jul 2026 — per Chris: "please remove because i dont want the
 * redundant field in the DB". These columns were only ever used by the
 * old local-OCR calibration pipeline (anchor-text matching and
 * position/coordinate bracket-labeling), which has been fully replaced
 * by direct Claude API document reading. Nothing in the codebase reads
 * or writes any of these columns anymore — DocumentTemplateController
 * was rewritten to only store field_role + sort_order per template.
 *
 * down() recreates the columns (all nullable, matching their state
 * right before this migration) so this can be reversed if ever needed,
 * but no data is preserved — these columns' old contents are gone
 * either way once this runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_template_fields', function (Blueprint $table) {
            $table->dropColumn([
                'anchor_text',
                'extraction_mode',
                'value_pattern',
                'box_page',
                'box_x',
                'box_y',
                'box_width',
                'box_height',
                'calibration_sample_value',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('document_template_fields', function (Blueprint $table) {
            $table->string('anchor_text', 150)->nullable();
            $table->enum('extraction_mode', ['SAME_LINE', 'NEXT_LINE', 'REGEX', 'COORDINATE'])->default('SAME_LINE');
            $table->string('value_pattern', 255)->nullable();
            $table->unsignedInteger('box_page')->nullable();
            $table->decimal('box_x', 10, 3)->nullable();
            $table->decimal('box_y', 10, 3)->nullable();
            $table->decimal('box_width', 10, 3)->nullable();
            $table->decimal('box_height', 10, 3)->nullable();
            $table->string('calibration_sample_value', 255)->nullable();
        });
    }
};
