<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 10 Aug 2026 — per Chris: "what admin can identify it is for the
// vendor vendor name, date submit and the purpose and the expired date
// for this video." Videos that come FROM a vendor (e.g. a marketing/
// promotion clip they emailed or WhatsApp'd to Admin) now carry:
// - vendor_id: which vendor this video is for/from. NULL means it's a
//   GeneralLink corporate video (e.g. the Introduction video, an Admin
//   announcement) — not every video belongs to a vendor.
// - purpose: free text explaining what the video is for (e.g. "August
//   2026 rebate campaign", "New agent onboarding explainer").
// - submitted_date: the date the vendor actually SENT the video to
//   Admin — separate from created_at (when Admin finished uploading it),
//   since those two dates are often different in practice.
// - expiry_date: when a marketing/promotion video's relevance ends.
//   Purely informational today (shown + flagged "Expired" in the list) —
//   does not auto-delete or auto-hide anything, since Admin should
//   always be able to see/reactivate past campaign videos if needed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_library', function (Blueprint $table) {
            $table->uuid('vendor_id')->nullable()->after('video_type');
            $table->string('purpose', 300)->nullable()->after('ownership');
            $table->date('submitted_date')->nullable()->after('purpose');
            $table->date('expiry_date')->nullable()->after('submitted_date');

            $table->index('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::table('video_library', function (Blueprint $table) {
            $table->dropColumn(['vendor_id', 'purpose', 'submitted_date', 'expiry_date']);
        });
    }
};
