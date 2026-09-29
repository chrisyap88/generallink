<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 8 Aug 2026 (Task #95) — GLADE offer submission flow. Deliberately a
// SEPARATE table from `notices`, not a "draft" status bolted onto it —
// notices semantics are "this is a live broadcast", and every existing
// query (agent browse, delivery, relevance scoring, AI Insight,
// analytics) assumes that. Keeping submissions here means none of that
// existing code needs to change at all; APPROVING a request simply
// INSERTs one new row into notices (see NoticeBoardController@store-style
// insert in OfferRequestApprovalController) and everything downstream —
// Phases 1-4 — picks it up automatically, unchanged.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_requests', function (Blueprint $table) {
            $table->uuid('request_id')->primary();
            $table->uuid('vendor_id'); // which vendor this offer is about — always required
            $table->enum('submitted_by_type', ['VENDOR', 'AGENT']);
            $table->uuid('submitted_by_vendor_id')->nullable(); // set when a vendor submits their own
            $table->uuid('submitted_by_agent_id')->nullable();  // set when an agent submits on a vendor's behalf

            $table->string('title', 200);
            $table->text('body');
            $table->date('expiry_date'); // required at submission — no indefinite offers
            $table->string('attachment_file_path', 255)->nullable();
            $table->string('attachment_file_name', 200)->nullable();

            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->string('rejection_reason', 255)->nullable();
            $table->uuid('reviewed_by_agent_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('notice_id')->nullable(); // filled in once approved — links to the live notices row

            $table->timestamps();

            $table->foreign('vendor_id')->references('vendor_id')->on('vendors')->onDelete('cascade');
            $table->foreign('submitted_by_vendor_id')->references('vendor_id')->on('vendors')->nullOnDelete();
            $table->foreign('submitted_by_agent_id')->references('agent_id')->on('agents')->nullOnDelete();
            $table->foreign('reviewed_by_agent_id')->references('agent_id')->on('agents')->nullOnDelete();
            $table->foreign('notice_id')->references('notice_id')->on('notices')->nullOnDelete();

            $table->index(['status', 'created_at']);
            $table->index('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_requests');
    }
};
