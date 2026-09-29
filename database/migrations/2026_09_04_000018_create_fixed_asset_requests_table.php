<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #395) — Fixed Asset Module upgrade, spec section
// 27 (Approval Control): Acquisition, Transfer, Disposal and Write-Off
// all need a Draft -> Submitted -> Approved -> Posted workflow when a
// temple has Maker-Checker switched on and the amount clears its
// threshold. Rather than one staging table per action type, this
// mirrors cbe_journal_voucher_drafts exactly (a JSON payload column
// replayed through the real posting method once approved) and plugs
// straight into the existing Pending Approvals screen/queue built for
// Task #334/#373 — same approve/reject actions, same self-approval
// block, same 3-tab (Pending/Approved/Rejected) history.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_fixed_asset_requests')) {
            Schema::create('cbe_fixed_asset_requests', function (Blueprint $table) {
                $table->uuid('request_id')->primary();
                $table->uuid('cbe_node_id');
                $table->string('action_type', 20); // ACQUISITION / DISPOSAL / TRANSFER / IMPROVEMENT
                $table->uuid('asset_id')->nullable(); // null for ACQUISITION until approved
                $table->string('description', 255);
                $table->date('entry_date');
                $table->decimal('amount', 12, 2);
                $table->json('payload');
                $table->uuid('prepared_by');
                $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
                $table->uuid('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->string('rejection_reason', 255)->nullable();
                $table->uuid('result_asset_id')->nullable(); // the asset actually created/affected once approved
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->index(['cbe_node_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_fixed_asset_requests');
    }
};
