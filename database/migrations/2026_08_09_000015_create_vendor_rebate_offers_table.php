<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 9 Aug 2026 — per Chris: agents need a searchable directory of
// vendor rebate offers, showing ONLY vendor name, product, and the
// rebate details — explicitly no address or contact info. Admin-managed
// (not vendor-submitted — that flow was the old Offer Request feature,
// which Chris deliberately scrapped 8 Aug 2026 for being confusing).
// Linked to a real product_id (existing `products` table) rather than a
// free-text product name, so results stay consistent with Product
// Maintenance.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_rebate_offers', function (Blueprint $table) {
            $table->uuid('rebate_offer_id')->primary();
            $table->uuid('vendor_id');
            $table->uuid('product_id')->nullable(); // nullable: Admin may post a vendor-wide rebate not tied to one specific product
            $table->text('rebate_details');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('vendor_id')->on('vendors')->cascadeOnDelete();
            $table->foreign('product_id')->references('product_id')->on('products')->nullOnDelete();
            $table->foreign('created_by')->references('agent_id')->on('agents')->nullOnDelete();
            $table->index(['vendor_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_rebate_offers');
    }
};
