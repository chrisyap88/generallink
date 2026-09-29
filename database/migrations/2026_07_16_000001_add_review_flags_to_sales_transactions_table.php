<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 16 Jul 2026 — supports the Sales Transaction Maintenance module.
// Automated duplicate/sanity checks WARN rather than hard-block (only an
// exact duplicate policy_number is blocked, via the existing unique
// constraint) — anything softer (same customer+product+overlapping
// coverage dates, unusually high premium, reused receipt photo) gets
// flagged here for Admin to review, without ever stopping a genuinely
// real sale from being submitted.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->boolean('flagged_for_review')->default(false)->after('status');
            $table->text('flag_reason')->nullable()->after('flagged_for_review');
            $table->uuid('reviewed_by')->nullable()->after('flag_reason');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropColumn(['flagged_for_review', 'flag_reason', 'reviewed_by', 'reviewed_at']);
        });
    }
};
