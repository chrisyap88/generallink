<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Aug 2026 — supplier master data for the new Accounts Payable
// module, scoped per cbe_node_id same as cbe_donors (each temple/branch
// keeps its own supplier list).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_suppliers')) {
            Schema::create('cbe_suppliers', function (Blueprint $table) {
                $table->uuid('supplier_id')->primary();
                $table->uuid('cbe_node_id');
                $table->string('supplier_name', 150);
                $table->string('contact_person', 100)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('address', 255)->nullable();
                $table->text('notes')->nullable();
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'supplier_name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_suppliers');
    }
};
