<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 31 Jul 2026 — Vendor Override Members, calculation output. Each
// row is one Override Member's calculated override commission for one
// period against one product (or the whole vendor, if the rule that
// fired had no specific product). This is the record Admin reviews and
// exports — NOT a wallet credit (Override Members aren't agents and
// never touch earning_wallets). Settlement tracking here just marks
// whether this particular calculated amount has been actioned yet,
// following whatever settlement_method that member had AT THE TIME —
// copied onto the claim row so a later change to the member's
// arrangement never rewrites history.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('override_commission_claims', function (Blueprint $table) {
            $table->uuid('claim_id')->primary();
            $table->uuid('override_member_id');
            $table->uuid('rule_id')->nullable();
            $table->uuid('product_id')->nullable();

            $table->date('period_start');
            $table->date('period_end');

            // The real sales figure the calculation was based on, so
            // the number is always explainable later, not just the
            // final amount.
            $table->decimal('sales_basis_amount', 15, 4)->nullable();
            $table->decimal('calculated_amount', 15, 4);

            // Copied from override_members.settlement_method at the
            // moment this claim was calculated.
            $table->enum('settlement_method', ['DEDUCT_FROM_CLAIM', 'CLAIM_BACK_REPORT']);
            $table->enum('status', ['CALCULATED', 'SETTLED'])->default('CALCULATED');
            $table->timestamp('settled_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['override_member_id', 'period_start', 'period_end'], 'occ_member_period_idx');
            $table->index('status');

            $table->foreign('override_member_id')->references('override_member_id')->on('override_members')->cascadeOnDelete();
            $table->foreign('rule_id')->references('rule_id')->on('override_member_eligibility_rules')->nullOnDelete();
            $table->foreign('product_id')->references('product_id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('override_commission_claims');
    }
};
