<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 14 Aug 2026 — per Chris: "Amendment auto-updating final
// registration copy Please build." Before this, resolving an amendment
// was just a status flip (REQUESTED/SUBMITTED -> RESOLVED) — it never
// touched the vendor's actual profile data. A document-type amendment
// already had a real resolution path (Admin Accepts the re-uploaded file
// in the SSM Documents tab, which becomes the current version), but a
// plain text-field correction (address, contact phone, etc.) had no
// equivalent — Admin had to separately go retype it in Master File >
// Vendors, easy to forget.
//
// target_field is optional and deliberately NOT free text: the view only
// offers a whitelisted dropdown of safe, non-sensitive vendors columns
// (see VendorOnboardingWorkflowController::AMENDABLE_FIELDS) — never the
// login email, password, or status columns — so this can never be used
// to silently move a vendor's identity or account state. When set,
// resolving the amendment can apply a typed-in corrected value directly
// to that vendor column in one click (applyAmendment()), logging the
// before/after via AuditService exactly like every other field edit in
// this app, instead of only marking the row resolved.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_onboarding_amendments', function (Blueprint $table) {
            $table->string('target_field', 60)->nullable()->after('request_note');
            $table->text('applied_value')->nullable()->after('target_field');
            $table->timestamp('applied_at')->nullable()->after('applied_value');
            $table->uuid('applied_by_admin_id')->nullable()->after('applied_at');

            $table->foreign('applied_by_admin_id')->references('agent_id')->on('agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_onboarding_amendments', function (Blueprint $table) {
            $table->dropForeign(['applied_by_admin_id']);
            $table->dropColumn(['target_field', 'applied_value', 'applied_at', 'applied_by_admin_id']);
        });
    }
};
