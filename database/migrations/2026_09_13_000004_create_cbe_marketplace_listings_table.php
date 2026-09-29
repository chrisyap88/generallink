<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 13 Sep 2026 (Task #418) — per Chris: "CBE entity itself can sell
// things like events talks, roadshow, prayer package etc" as well as
// registered vendors selling their own products/services. One listing
// table covers both — vendor_id is nullable: null means the entity
// itself is the seller (its own event/talk/package), a real vendor_id
// means that approved vendor is selling into this entity's marketplace.
//
// A listing can only exist under an entity a vendor is actually
// APPROVED for (enforced at the application layer against
// cbe_vendor_node_links, not by a DB constraint, since that check spans
// two tables) — this table only stores the listing itself.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_marketplace_listings')) {
            Schema::create('cbe_marketplace_listings', function (Blueprint $table) {
                $table->uuid('listing_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('vendor_id')->nullable();
                $table->string('title', 200);
                $table->string('title_zh', 200)->nullable();
                $table->text('description')->nullable();
                $table->decimal('price', 12, 2);
                $table->unsignedInteger('stock_quantity')->nullable();
                $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('vendor_id')->references('vendor_id')->on('cbe_vendors')->nullOnDelete();
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_marketplace_listings');
    }
};
