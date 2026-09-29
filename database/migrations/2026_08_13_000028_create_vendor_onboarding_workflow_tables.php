<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 13 Aug 2026 — per Chris: "create a program after the pending
// approval menu dashboard called Vendor Onboarding and Communication
// Workflow... this communication tools allow vendor to upload new file
// if require or ask for changes... and all this amendment must have
// keep track in row form when admin click this program because in
// actual environment it may incur a series of communication."
//
// Reuses the existing vendor_pending_messages thread (built 12 Aug 2026
// for pre-approval Q&A) as the single communication backbone — rather
// than a second, parallel messaging system — per the recommendation
// given to Chris: one audit trail, not two. This migration only ADDS
// what that thread was missing for this new program:
//   1. File attachments on a message (vendor or admin can now attach a
//      document/photo to what they send, not just plain text).
//   2. A message "type" so an amendment REQUEST can be told apart from
//      an ordinary chat message and from the vendor's SUBMISSION back.
//   3. A separate, structured vendor_onboarding_amendments table — the
//      "row form" tracking Chris asked for — one row per requested
//      change/document, with its own status (REQUESTED / SUBMITTED /
//      RESOLVED), so the admin's workflow screen can show a clean
//      table of open items instead of admin having to re-read the
//      whole chat history to find what's still outstanding.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_pending_messages', function (Blueprint $table) {
            $table->string('message_type', 20)->default('MESSAGE')->after('sender_admin_id'); // MESSAGE, AMENDMENT_REQUEST, AMENDMENT_SUBMITTED
            $table->string('attachment_path', 500)->nullable()->after('message');
            $table->string('attachment_file_name', 255)->nullable()->after('attachment_path');
        });

        Schema::create('vendor_onboarding_amendments', function (Blueprint $table) {
            $table->uuid('amendment_id')->primary();
            $table->uuid('vendor_id');
            $table->uuid('message_id')->nullable(); // the thread message that raised this request, if any
            $table->uuid('requested_by_admin_id')->nullable();
            $table->string('item_label', 150); // e.g. "Business Registration Certificate", "Company Address", "Bank Details"
            $table->text('request_note');
            $table->string('status', 20)->default('REQUESTED'); // REQUESTED, SUBMITTED, RESOLVED
            $table->uuid('resolved_by_admin_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('vendor_id')->on('vendors')->cascadeOnDelete();
            $table->foreign('message_id')->references('message_id')->on('vendor_pending_messages')->nullOnDelete();
            $table->foreign('requested_by_admin_id')->references('agent_id')->on('agents')->nullOnDelete();
            $table->foreign('resolved_by_admin_id')->references('agent_id')->on('agents')->nullOnDelete();
            $table->index(['vendor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_onboarding_amendments');
        Schema::table('vendor_pending_messages', function (Blueprint $table) {
            $table->dropColumn(['message_type', 'attachment_path', 'attachment_file_name']);
        });
    }
};
