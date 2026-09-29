<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 2 Sep 2026 (Task #339) — ROS Submission Checklist. Distinct from
// Year-End Closing's own agm_held/ros_submitted pair (Task #332, a
// simple "did the year close" gate) — this is the fuller list of what a
// Malaysian ROS (Registrar of Societies) annual return actually needs
// gathered before it's submitted: the report pack itself, the current
// office-bearer list, verified financial statements, AGM minutes, an
// up-to-date membership register, and finally the submission reference.
// One row per node per fiscal year, same pattern as cbe_year_end_closings.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_ros_submission_checklists')) {
            Schema::create('cbe_ros_submission_checklists', function (Blueprint $table) {
                $table->uuid('checklist_id')->primary();
                $table->uuid('cbe_node_id');
                $table->unsignedSmallInteger('fiscal_year');
                $table->boolean('item_annual_report_pack')->default(false);
                $table->boolean('item_office_bearer_list')->default(false);
                $table->boolean('item_financial_statements')->default(false);
                $table->boolean('item_agm_minutes')->default(false);
                $table->boolean('item_membership_register')->default(false);
                $table->boolean('item_form_submitted')->default(false);
                $table->string('ros_reference_no', 100)->nullable();
                $table->date('submission_date')->nullable();
                $table->string('notes', 500)->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('updated_by')->references('agent_id')->on('agents')->onDelete('set null');
                $table->unique(['cbe_node_id', 'fiscal_year']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_ros_submission_checklists');
    }
};
