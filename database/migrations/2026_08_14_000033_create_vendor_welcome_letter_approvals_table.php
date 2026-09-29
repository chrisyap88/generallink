<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 14 Aug 2026 — per Chris: "how you show the approval button for
// sales admin finance admin and director admin, tick box." Before the
// Welcome Letter can actually be sent, all THREE departments must sign
// off on this specific vendor — separate from (and in addition to) the
// existing 1-or-2-Admin approved_by_1/approved_by_2 counter on the
// vendors table itself, which stays exactly as-is. One row per vendor,
// three independent tick marks.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendor_welcome_letter_approvals')) {
            return; // already exists — avoids duplicate-table errors
        }

        Schema::create('vendor_welcome_letter_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_id')->unique();

            $table->uuid('sales_approved_by')->nullable();
            $table->timestamp('sales_approved_at')->nullable();

            $table->uuid('finance_approved_by')->nullable();
            $table->timestamp('finance_approved_at')->nullable();

            $table->uuid('director_approved_by')->nullable();
            $table->timestamp('director_approved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_welcome_letter_approvals');
    }
};
