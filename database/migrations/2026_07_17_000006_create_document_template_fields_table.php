<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 17 Jul 2026 — one row per field the extraction engine should
// pull off a document, per template. anchor_text is the label Admin
// saw printed on the sample document (e.g. "Policy No", "NRIC No",
// "Total Premium Payable"). extraction_mode tells the engine how to
// read the value relative to that label; value_pattern is an optional
// regex to clean the raw captured text (e.g. keep digits only).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_template_fields', function (Blueprint $table) {
            $table->uuid('field_id')->primary();
            $table->uuid('template_id');

            // Generic field roles — never insurance-specific vocabulary,
            // so the same list works for any future vendor industry.
            $table->string('field_role', 40);
            $table->string('anchor_text', 150);
            $table->enum('extraction_mode', ['SAME_LINE', 'NEXT_LINE', 'REGEX'])->default('SAME_LINE');
            $table->string('value_pattern', 255)->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index('template_id');
            $table->index('field_role');

            $table->foreign('template_id')->references('template_id')->on('document_templates')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_template_fields');
    }
};
