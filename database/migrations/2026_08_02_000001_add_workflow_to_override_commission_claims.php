<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// NEW 2 Aug 2026 — per Chris: the Vendor Override Member feature had a
// calculation engine (override:calculate, writes to
// override_commission_claims) and a data model, but no actual screen to
// review/submit/approve/pay those claims, and no real workflow — just a
// flat CALCULATED/SETTLED flag. This adds real stages: CALCULATED (auto-
// computed, not yet acted on) -> SUBMITTED (Admin has reviewed and put
// it forward) -> APPROVED (a DIFFERENT Admin approved it, via the same
// 4-eye ApprovalService already used for Withdrawal Approval etc.) ->
// PAID (money/settlement actually sent to the vendor-side override
// member). REJECTED covers a claim a second Admin declines at approval
// time. 'SETTLED' is kept in the enum for backward compatibility with
// the old 2-state model — no live rows exist yet (this feature was never
// actually reachable from the UI), but keeping it costs nothing and
// avoids a hard break if anyone already ran the command by hand.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE override_commission_claims MODIFY status ENUM('CALCULATED','SUBMITTED','APPROVED','REJECTED','PAID','SETTLED') NOT NULL DEFAULT 'CALCULATED'");

        Schema::table('override_commission_claims', function ($table) {
            if (!Schema::hasColumn('override_commission_claims', 'submitted_by')) {
                $table->uuid('submitted_by')->nullable()->after('status');
                $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            }
            if (!Schema::hasColumn('override_commission_claims', 'approval_id')) {
                // Links back to pending_approvals — the actual 4-eye
                // approve/reject decision lives there (same system as
                // Withdrawal Approval), this just points at it so the
                // claims screen can show current status without Admin
                // having to go dig through the Approvals queue separately.
                $table->uuid('approval_id')->nullable()->after('submitted_at');
            }
            if (!Schema::hasColumn('override_commission_claims', 'approved_by')) {
                $table->uuid('approved_by')->nullable()->after('approval_id');
                $table->timestamp('approved_at')->nullable()->after('approved_by');
                $table->text('rejection_reason')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('override_commission_claims', 'paid_by')) {
                $table->uuid('paid_by')->nullable()->after('rejection_reason');
                $table->string('payment_reference', 100)->nullable()->after('paid_by');
            }
        });

        Schema::table('override_commission_claims', function ($table) {
            $table->foreign('submitted_by')->references('agent_id')->on('agents')->nullOnDelete();
            $table->foreign('approved_by')->references('agent_id')->on('agents')->nullOnDelete();
            $table->foreign('paid_by')->references('agent_id')->on('agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('override_commission_claims', function ($table) {
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['paid_by']);
            $table->dropColumn([
                'submitted_by', 'submitted_at', 'approval_id',
                'approved_by', 'approved_at', 'rejection_reason',
                'paid_by', 'payment_reference',
            ]);
        });

        DB::statement("ALTER TABLE override_commission_claims MODIFY status ENUM('CALCULATED','SETTLED') NOT NULL DEFAULT 'CALCULATED'");
    }
};
