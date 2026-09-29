<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 13 Sep 2026 (Task #418) — per Chris: CBE needs its own marketplace
// and vendor registration, completely separate from GeneralLink's
// existing MLM Vendor Management (that one is built around insurance/
// distributor onboarding with due diligence, agreements, commission
// rebates — none of which apply here). A CBE vendor is simply someone
// registered to sell into one or more CBE entities' marketplaces
// (temple gift shop items, event caterers, etc.).
//
// This table holds the vendor's own identity only. WHICH entities they
// can sell to lives in cbe_vendor_node_links (next migration) — a
// vendor registering here has no marketplace access anywhere until at
// least one node link is requested and approved.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_vendors')) {
            Schema::create('cbe_vendors', function (Blueprint $table) {
                $table->uuid('vendor_id')->primary();
                $table->string('vendor_name', 150);
                $table->string('contact_person', 100)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('address', 255)->nullable();
                $table->text('notes')->nullable();
                $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index('vendor_name');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_vendors');
    }
};
