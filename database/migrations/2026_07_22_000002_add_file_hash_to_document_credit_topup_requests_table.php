<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 22 Jul 2026 — per Chris's fraud detection build (Checkpoint 2):
// Document Credit top-up bank-in slips had no duplicate-file check at
// all until now (unlike Sales Transaction documents, which already
// hashed their uploads). Same sha256 approach, reused via
// FraudDetectionService rather than duplicated logic.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_credit_topup_requests', function (Blueprint $table) {
            $table->string('file_hash', 64)->nullable()->after('bank_slip_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('document_credit_topup_requests', function (Blueprint $table) {
            $table->dropColumn('file_hash');
        });
    }
};
