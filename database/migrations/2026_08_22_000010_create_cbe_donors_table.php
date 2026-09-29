<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 22 Aug 2026 — per Chris: the Official Donation Register needs a
// proper donor/sponsor master list — a donor who gives every year
// shouldn't be re-typed from scratch on every event, the same "don't
// duplicate" principle already applied to agent profiles across CBE
// groups. Scoped to cbe_node_id (each Temple keeps its own donor
// register) and reused across every event that Temple runs.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_donors')) {
            Schema::create('cbe_donors', function (Blueprint $table) {
                $table->uuid('donor_id')->primary();
                $table->uuid('cbe_node_id');
                $table->string('donor_name', 255);
                $table->enum('donor_type', ['INDIVIDUAL', 'COMPANY', 'ORGANIZATION'])->default('INDIVIDUAL');
                $table->string('contact_person', 150)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('address', 500)->nullable();
                $table->text('notes')->nullable();
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'donor_name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_donors');
    }
};
