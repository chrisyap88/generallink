<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 9 Aug 2026 — SSM Document Registration & Verification Module,
// per Chris: "the AI verification engine must automatically read and
// analyse the uploaded documents" + "the system should display: Uploaded
// / Missing / Invalid-Unable to Read / AI Verified / Verification Failed
// / Verification Pending." verification_status (existing column) stays
// the human/Admin decision (PENDING/VERIFIED/REJECTED). ai_check_status
// is a SEPARATE, honestly-scoped automatic first-pass signal
// (PENDING/VERIFIED/FAILED/UNREADABLE) — never a replacement for the
// human decision, always shown alongside it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_documents', function (Blueprint $table) {
            $table->string('ai_check_status', 20)->default('PENDING')->after('verification_status');
            $table->text('ai_check_note')->nullable()->after('ai_check_status');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_documents', function (Blueprint $table) {
            $table->dropColumn(['ai_check_status', 'ai_check_note']);
        });
    }
};
