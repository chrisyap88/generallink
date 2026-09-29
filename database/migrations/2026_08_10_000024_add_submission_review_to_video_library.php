<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 10 Aug 2026 — per Chris: "all agents including vendor is allow to
// send attachment...admin received and upload to master file and
// activate." Agents and vendors can now submit content directly (see
// ContentSubmissionController) instead of only sending it outside the
// app (email/WhatsApp) for Admin to manually re-upload. A submission
// lands in video_library with status='PENDING_REVIEW' (status column
// is a plain string, no schema change needed for that); these two
// columns record WHO reviewed it and WHY, if rejected. `created_by`
// (submitting agent) and `vendor_id` (submitting vendor) already exist
// and are reused as-is — no new "submitted by" column needed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_library', function (Blueprint $table) {
            $table->uuid('reviewed_by')->nullable()->after('created_by');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('rejection_reason', 300)->nullable()->after('reviewed_at');
            $table->foreign('reviewed_by')->references('agent_id')->on('agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('video_library', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['reviewed_by', 'reviewed_at', 'rejection_reason']);
        });
    }
};
