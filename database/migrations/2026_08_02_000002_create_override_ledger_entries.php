<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 2 Aug 2026 — per Chris: a real accounting-style ledger per Override
// Member, Debit = amount submitted (claimed), Credit = amount actually
// paid/settled. Separate from override_commission_claims (which is the
// calculation/workflow record, one row per period) — this is the
// financial posting record, one row per actual Dr or Cr event, each
// with its own permanent voucher number. A claim normally produces two
// rows over its life (one Debit at submission, one Credit at payment);
// a rejected claim produces a third reversing Credit row so the ledger
// balance always reflects reality, never manually edited.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('override_ledger_entries', function (Blueprint $table) {
            $table->uuid('entry_id')->primary();
            $table->uuid('override_member_id');
            $table->uuid('claim_id')->nullable(); // which claim this posting belongs to

            $table->enum('entry_type', ['DEBIT', 'CREDIT']);
            $table->string('voucher_number', 30)->unique();
            $table->date('entry_date');
            $table->string('description', 255);

            // The real sales figure behind the calculation, shown on
            // Debit rows only — Credit (payment) rows don't have one.
            $table->decimal('sales_basis_amount', 15, 4)->nullable();
            $table->decimal('amount', 15, 4);

            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['override_member_id', 'entry_date'], 'ovle_member_date_idx');

            $table->foreign('override_member_id')->references('override_member_id')->on('override_members')->cascadeOnDelete();
            $table->foreign('claim_id')->references('claim_id')->on('override_commission_claims')->nullOnDelete();
            $table->foreign('created_by')->references('agent_id')->on('agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('override_ledger_entries');
    }
};
