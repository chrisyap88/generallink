<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 13 Sep 2026 (Task #418) — per Chris: marketplace campaigns, one
// type being "Membership Recruitment Initiative" — targeting an
// entity's own non-member marketplace customers to convert them into
// members. type_id references the editable catalog (previous
// migration) rather than a hardcoded enum.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_marketplace_campaigns')) {
            Schema::create('cbe_marketplace_campaigns', function (Blueprint $table) {
                $table->uuid('campaign_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('type_id');
                $table->string('campaign_name', 200);
                $table->text('message')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->enum('status', ['DRAFT', 'ACTIVE', 'ENDED'])->default('DRAFT');
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('type_id')->references('type_id')->on('cbe_marketplace_campaign_types')->onDelete('restrict');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_marketplace_campaigns');
    }
};
