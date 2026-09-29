<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 12 Aug 2026 — per Chris: "GL,TL,Introducer in their dashboard menu,
// they can write review on vendor and product the feedback on the vendor
// rebate programs, and they can search and review all reviews for this
// vendor and products." Star rating + comment, one row per review,
// scoped to a vendor (and optionally a specific product) so it can be
// browsed as a group either from the agent's Rebate Offer Search screen
// or from the vendor's/product's own record on the Admin side — Chris's
// "feedback review folder" concept.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_product_reviews', function (Blueprint $table) {
            $table->uuid('review_id')->primary();
            $table->uuid('vendor_id');
            $table->uuid('product_id')->nullable();
            $table->uuid('reviewer_agent_id');
            $table->unsignedTinyInteger('rating'); // 1-5, Facebook/star-rating style
            $table->text('comment');
            $table->timestamps();

            $table->foreign('vendor_id')->references('vendor_id')->on('vendors')->onDelete('cascade');
            $table->foreign('product_id')->references('product_id')->on('products')->onDelete('cascade');
            $table->foreign('reviewer_agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
            $table->index(['vendor_id', 'created_at']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_product_reviews');
    }
};
