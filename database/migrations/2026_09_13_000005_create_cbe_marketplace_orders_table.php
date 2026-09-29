<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 13 Sep 2026 (Task #418) — per Chris: a marketplace buyer is NOT
// necessarily a member, and not even necessarily an existing Customer
// Master record — a walk-in/public buyer (his own example: "Chris Yap
// is a customer of Rotary Club" with no membership there) can place an
// order with just a name/phone/email, same as cbe_customers already
// allows a nullable agent_id. buyer_customer_id links to a real
// cbe_customers record when one exists/is created; buyer_name/phone/
// email cover a first-time walk-in buyer with no Customer Master record
// yet — one of the two must be present (enforced at the application
// layer, not the DB, since "either/or" isn't a clean column
// constraint).
//
// Payment: per Chris ("in future i will let you know the payment API
// gateway"), no live payment processing yet — payment_status starts at
// PENDING_PAYMENT and payment_reference is left empty until a real
// gateway is wired in later. Recording the order now must not be
// blocked on that.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_marketplace_orders')) {
            Schema::create('cbe_marketplace_orders', function (Blueprint $table) {
                $table->uuid('order_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('listing_id');
                $table->uuid('buyer_customer_id')->nullable();
                $table->string('buyer_name', 150)->nullable();
                $table->string('buyer_phone', 30)->nullable();
                $table->string('buyer_email', 150)->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('unit_price', 12, 2);
                $table->decimal('total_amount', 12, 2);
                $table->enum('payment_status', ['PENDING_PAYMENT', 'PAID', 'CANCELLED'])->default('PENDING_PAYMENT');
                $table->string('payment_reference', 100)->nullable();
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('listing_id')->references('listing_id')->on('cbe_marketplace_listings')->onDelete('restrict');
                $table->foreign('buyer_customer_id')->references('customer_id')->on('cbe_customers')->nullOnDelete();
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'payment_status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_marketplace_orders');
    }
};
