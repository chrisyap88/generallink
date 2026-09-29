<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 8 Aug 2026 — Vendor Management Phase 1 (per the "GeneralLink Digital
// Affiliate Ecosystem — Master Programming Specification" Chris supplied,
// design confirmed in vendor-management-phase1-design-v1.docx). Extends
// the existing vendor registration/login-approval system (built earlier
// the same day, Task #92/#93) rather than replacing it:
//   - vendor_type: classifies each vendor as Product / Service / Event /
//     Product+Service (spec Section 3), so the Phase 2 catalogue never
//     has to guess. Nullable — existing vendor rows are untyped until
//     someone opens and re-saves them.
//   - declaration_accepted_at / declaration_version: the immutable "I
//     confirm this information is accurate and genuine" acceptance record
//     from self-registration (spec Section 6). Admin-created vendors
//     don't self-declare — Admin is vouching for them directly — so this
//     stays null for those rows, which is expected, not a bug.
//   - approved_by_1/approved_at_1, approved_by_2/approved_at_2: makes the
//     existing single-click "Approve" in VendorLoginApprovalController
//     capable of genuine two-person approval (spec Section 2 "two-person
//     approval must be supported") without changing the login_status
//     enum — a vendor sits in login_status=PENDING throughout, and the
//     approval columns record how many of the required approvals have
//     been given and by whom. Whether 1 or 2 approvals are required is a
//     setting (system_settings key vendor_approval_required_count), not
//     hard-coded — Chris is the sole Admin today, so it defaults to 1
//     (identical behaviour to today); raising it to 2 once a second Admin
//     exists needs no further migration.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('vendor_type', 20)->nullable()->after('industry');
            $table->timestamp('declaration_accepted_at')->nullable()->after('fb_page_url');
            $table->string('declaration_version', 20)->nullable()->after('declaration_accepted_at');
            $table->uuid('approved_by_1')->nullable()->after('rejection_reason');
            $table->timestamp('approved_at_1')->nullable()->after('approved_by_1');
            $table->uuid('approved_by_2')->nullable()->after('approved_at_1');
            $table->timestamp('approved_at_2')->nullable()->after('approved_by_2');

            $table->foreign('approved_by_1')->references('agent_id')->on('agents')->nullOnDelete();
            $table->foreign('approved_by_2')->references('agent_id')->on('agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropForeign(['approved_by_1']);
            $table->dropForeign(['approved_by_2']);
            $table->dropColumn([
                'vendor_type', 'declaration_accepted_at', 'declaration_version',
                'approved_by_1', 'approved_at_1', 'approved_by_2', 'approved_at_2',
            ]);
        });
    }
};
