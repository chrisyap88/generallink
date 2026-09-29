<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 26 Aug 2026 — per Chris: "one of the function of accounting is to
// issue receipt upon a collection... i cannot be double standard one is
// upload one is key in." Every time money is actually collected — a
// donation/sponsorship payment, an event sale (CNY package etc.), or a
// sensei appointment fee — the system now issues an official receipt
// itself, at Temple/Branch/State/HQ level, instead of someone typing a
// receipt number and uploading a photo of one written by hand.
//
// source_type + source_id point back to whichever record the money came
// from (cbe_contributions, cbe_event_participants, or cbe_appointments)
// so the full paper trail is always traceable from either side.
//
// transaction_id is deliberately NULLABLE and left null for DONATION
// receipts: donation income already flows into the formal books in one
// aggregate line when the Event closes (see EventController::close() +
// CbeAccountingService header) — posting it again here per-receipt would
// double-count that income. EVENT_SALE and APPOINTMENT income had no
// existing posting path at all, so those DO get an individual
// cbe_transactions row (and therefore a journal entry) the moment the
// receipt is issued — see CbeReceiptService.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_receipts')) {
            Schema::create('cbe_receipts', function (Blueprint $table) {
                $table->uuid('receipt_id')->primary();
                $table->uuid('cbe_node_id');
                $table->string('receipt_no', 20);
                $table->enum('source_type', ['DONATION', 'EVENT_SALE', 'APPOINTMENT']);
                $table->uuid('source_id')->nullable();
                $table->string('payer_name', 200);
                $table->string('description', 500)->nullable();
                $table->decimal('amount', 12, 2);
                $table->uuid('transaction_id')->nullable();
                $table->uuid('issued_by')->nullable();
                $table->timestamp('issued_at');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('transaction_id')->references('transaction_id')->on('cbe_transactions')->onDelete('set null');
                $table->foreign('issued_by')->references('agent_id')->on('agents')->onDelete('set null');
                $table->unique(['cbe_node_id', 'receipt_no']);
                $table->index(['source_type', 'source_id']);
                $table->index(['cbe_node_id', 'issued_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_receipts');
    }
};
