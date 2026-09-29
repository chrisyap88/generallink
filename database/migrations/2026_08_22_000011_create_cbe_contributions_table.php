<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 22 Aug 2026 — per Chris: the Official Donation Register itself —
// one row per contribution, whatever form it takes. contribution_type is
// a fixed structural enum (not an admin-configurable list like
// cbe_transaction_categories) because each type genuinely behaves
// differently — an AUCTION_ITEM needs an item description and a winning
// bid, an IN_KIND_GIFT needs an estimated value instead of a cash
// amount, a CASH_DONATION is just an amount. Every field Chris asked
// for is captured directly: donor (donor_id), type, value
// (pledged/received/estimated), receipt (receipt_no/issued_at/
// attachment), status, and documents (receipt_attachment_path).
//
// status carries the outstanding-pledge / partial-payment lifecycle —
// PLEDGED (promised, nothing received yet) -> PARTIALLY_PAID ->
// FULLY_PAID, or RECEIVED (for in-kind gifts handed over in one go),
// or CANCELLED. received_amount is a running total kept in sync with
// cbe_contribution_payments (next migration) so the register always
// shows an accurate outstanding balance without summing payments live
// on every page load.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_contributions')) {
            Schema::create('cbe_contributions', function (Blueprint $table) {
                $table->uuid('contribution_id')->primary();
                $table->uuid('event_id');
                $table->uuid('donor_id');
                $table->enum('contribution_type', ['CASH_DONATION', 'SPONSORSHIP', 'IN_KIND_GIFT', 'SERVICE_SPONSORSHIP', 'AUCTION_ITEM']);
                $table->string('item_description', 500)->nullable();
                $table->decimal('pledged_amount', 12, 2)->nullable();
                $table->decimal('received_amount', 12, 2)->default(0);
                $table->decimal('estimated_value', 12, 2)->nullable();
                $table->enum('status', ['PLEDGED', 'PARTIALLY_PAID', 'FULLY_PAID', 'RECEIVED', 'CANCELLED'])->default('PLEDGED');
                $table->string('receipt_no', 50)->nullable();
                $table->timestamp('receipt_issued_at')->nullable();
                $table->string('receipt_attachment_path', 500)->nullable();
                $table->text('notes')->nullable();
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('event_id')->references('event_id')->on('cbe_events')->onDelete('cascade');
                $table->foreign('donor_id')->references('donor_id')->on('cbe_donors')->onDelete('restrict');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['event_id', 'status']);
                $table->index(['event_id', 'contribution_type']);
                $table->index('donor_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_contributions');
    }
};
