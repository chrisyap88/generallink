<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 8 Aug 2026 (Task #92) — GLADE offer submission flow. Vendors have
// never had login access to GeneralLink before (only Admin manages them
// as reference data under Master File Maintenance). This adds a real
// login on the SAME vendors table (no separate table, no duplicate
// vendor_name/vendor_email etc.) — a vendor can now either self-register
// (login_status starts PENDING, needs Admin approval before they can log
// in) or have Admin create their login directly from the existing Vendor
// Maintenance screen (login_status set straight to ACTIVE, since Admin
// already vetted them by creating the record). is_active (already on
// this table) stays a separate, purely BUSINESS flag — login_status is
// the new, separate AUTHENTICATION flag; a vendor can be a fully active
// business partner with no login at all (login_status stays NONE
// forever), which is fine and expected for most existing vendor rows.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('password_hash', 255)->nullable()->after('pic_phone');
            $table->enum('login_status', ['NONE', 'PENDING', 'ACTIVE', 'REJECTED'])->default('NONE')->after('password_hash');
            $table->string('rejection_reason', 255)->nullable()->after('login_status');
            $table->string('remember_token', 100)->nullable()->after('rejection_reason');
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('remember_token');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');

            $table->index('login_status');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['password_hash', 'login_status', 'rejection_reason', 'remember_token', 'failed_login_attempts', 'locked_until']);
        });
    }
};
