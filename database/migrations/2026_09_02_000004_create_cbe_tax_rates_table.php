<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 2 Sep 2026 (Task #335) — per Chris: "what if the entity/temple/
// branch/state/hq not entitle for sales tax? and the per% is it user
// configurable?" Tax rates are scoped PER NODE (not per group_label_id
// like Transaction Categories), because tax registration is a
// per-legal-entity matter — one branch of a CBE community might be SST
// registered while another isn't, or a state office has a different
// rate to a temple. A node that configures zero rates here simply never
// sees anything but "No Tax" on its Bill/Invoice line dropdowns — no
// separate "not entitled" flag needed, the empty list IS the signal.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbe_tax_rates', function (Blueprint $table) {
            $table->uuid('rate_id')->primary();
            $table->uuid('cbe_node_id');
            $table->string('rate_name', 60);
            $table->decimal('rate_percent', 5, 2);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
            $table->index('cbe_node_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_tax_rates');
    }
};
