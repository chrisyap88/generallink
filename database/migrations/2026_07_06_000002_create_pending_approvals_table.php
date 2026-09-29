<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pending_approvals')) {
            return; // already exists — avoids duplicate-table errors
        }

        Schema::create('pending_approvals', function (Blueprint $table) {
            $table->uuid('approval_id')->primary();

            // What kind of sensitive action this is — e.g.
            // UNDO_ROLE_CHANGE, ADMIN_ASSIGN_PLACEMENT, BANK_ACCOUNT_CHANGE,
            // VENDOR_DEACTIVATE, HARD_DELETE, REASON_CODE_EDIT, etc.
            // New action types just get a new case in ApprovalService — no
            // schema change needed to add more sensitive actions later.
            $table->string('action_type');

            // The agent this action is ABOUT, if applicable (e.g. whose
            // promotion is being undone) — nullable since some actions
            // (like editing the Reason Code list) aren't about one person.
            $table->uuid('target_agent_id')->nullable();

            // Everything ApprovalService needs to actually PERFORM the
            // action once approved — stored as JSON so the structure can
            // differ per action_type without needing new columns.
            $table->text('payload');

            $table->uuid('requested_by'); // agents.agent_id
            $table->uuid('request_reason_code_id')->nullable(); // reason_codes.reason_code_id
            $table->text('request_notes')->nullable();

            $table->string('status')->default('PENDING'); // PENDING, APPROVED, REJECTED

            $table->uuid('approved_by')->nullable(); // MUST differ from requested_by
            $table->text('approval_notes')->nullable(); // approver's own notes (esp. if rejecting)
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            $table->index(['action_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_approvals');
    }
};
