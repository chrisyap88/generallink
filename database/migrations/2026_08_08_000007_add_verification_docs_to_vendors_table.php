<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// NEW 8 Aug 2026 — per Chris: vendor self-registration needs a real
// identity check before anyone gets a login, to stop scam registrations.
// Redesigns the flow: registration no longer collects a password at all
// — it collects an SSM certificate (required) plus a company profile
// document OR a Facebook page URL (at least one required) for Admin to
// manually verify. Only AFTER Admin approves the documents does the
// system email the vendor a link to verify their email and set their
// own password — mirrors the agent flow's email-verification-then-
// password pattern, but gated behind Admin's document review first.
// login_status gets a new middle state: PENDING (documents awaiting
// Admin review) -> AWAITING_PASSWORD (Admin approved, vendor hasn't set
// a password yet) -> ACTIVE. Same AWAITING_PASSWORD state is now also
// used by Admin's own "Create Login" action (Master File Maintenance),
// for the same reason — no temporary passwords change hands insecurely,
// every vendor sets their own password via a verified email link.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('ssm_document_path', 255)->nullable()->after('rejection_reason');
            $table->string('ssm_document_name', 200)->nullable()->after('ssm_document_path');
            $table->string('company_profile_document_path', 255)->nullable()->after('ssm_document_name');
            $table->string('company_profile_document_name', 200)->nullable()->after('company_profile_document_path');
            $table->string('fb_page_url', 255)->nullable()->after('company_profile_document_name');
            $table->string('email_verification_token', 100)->nullable()->after('fb_page_url');
        });

        DB::statement("ALTER TABLE vendors MODIFY login_status ENUM('NONE','PENDING','AWAITING_PASSWORD','ACTIVE','REJECTED') DEFAULT 'NONE'");
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['ssm_document_path', 'ssm_document_name', 'company_profile_document_path', 'company_profile_document_name', 'fb_page_url', 'email_verification_token']);
        });
        DB::statement("ALTER TABLE vendors MODIFY login_status ENUM('NONE','PENDING','ACTIVE','REJECTED') DEFAULT 'NONE'");
    }
};
