<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 16 Sep 2026 — per Chris: scanned/image bank statements (no text
// layer) now fall back to AI vision reading (Claude/OpenAI/Gemini via
// AiVisionBankStatementExtractionService) instead of always failing.
// This records which path actually read a given statement, so the
// batch/document screens can show it plainly (and so Chris always knows
// which documents spent a Document Credit vs which were read for free).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_ai_statement_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_ai_statement_documents', 'extraction_method')) {
                $table->string('extraction_method', 20)->default('TEXT_PARSE')->after('status'); // TEXT_PARSE | AI_VISION
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_ai_statement_documents', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_ai_statement_documents', 'extraction_method')) {
                $table->dropColumn('extraction_method');
            }
        });
    }
};
