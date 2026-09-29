<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 17 Jul 2026 — Document Template Library. Admin calibrates ONE
// template per Vendor + Product, one time, by telling the system what
// label to look for on that vendor's document for each field (e.g.
// "NRIC No" -> Customer NRIC, "Total Premium" -> Amount). Every future
// upload from that vendor+product is then read automatically using
// these rules. product_id is nullable so a template can optionally
// apply to every product from a vendor when the layout is identical.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->uuid('template_id')->primary();
            $table->uuid('vendor_id');
            $table->uuid('product_id')->nullable();
            $table->string('template_name', 150);
            $table->boolean('is_active')->default(true);

            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index('vendor_id');
            $table->index('product_id');
            $table->index('is_active');

            $table->foreign('vendor_id')->references('vendor_id')->on('vendors')->cascadeOnDelete();
            $table->foreign('product_id')->references('product_id')->on('products')->cascadeOnDelete();
            $table->foreign('created_by')->references('agent_id')->on('agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
