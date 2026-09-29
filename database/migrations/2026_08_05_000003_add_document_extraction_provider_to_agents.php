<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 5 Aug 2026 — lets an agent choose to read sales documents using
// their OWN OpenAI or Gemini key (connected in the Integration Hub)
// instead of the company's Document Credit wallet. Defaults to
// COMPANY_CREDIT for everyone so nothing changes unless the agent
// deliberately opts in from their Profile page.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('document_extraction_provider', 20)->default('COMPANY_CREDIT')->after('text_chat_api_key_encrypted');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('document_extraction_provider');
        });
    }
};
