<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #394) — per Chris's Purchasing Management module
// spec, section 5: Purchase Quotation management, sitting between
// Purchase Requisition and Purchase Order in the procurement chain. One
// Request for Quotation (RFQ) header can invite several suppliers — all
// drawn from the existing common cbe_suppliers master, never a separate
// list — each supplier's quoted amount is recorded, then one is marked
// selected after evaluation/approval. The RFQ can then be converted
// straight into a Purchase Order for the selected supplier, carrying the
// quoted amount across so it doesn't need re-entry.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_purchase_rfqs')) {
            Schema::create('cbe_purchase_rfqs', function (Blueprint $table) {
                $table->uuid('rfq_id')->primary();
                $table->string('doc_ref_no', 40)->nullable();
                $table->uuid('cbe_node_id');
                $table->uuid('request_id')->nullable();
                $table->uuid('cost_centre_id')->nullable();
                $table->uuid('fund_id')->nullable();
                $table->date('rfq_date');
                $table->string('description', 255)->nullable();
                // DRAFT: being prepared. SENT: quotations requested from
                // suppliers. QUOTED: at least one supplier quote entered.
                // EVALUATED: quotes compared, one marked selected.
                // AWARDED: converted to a Purchase Order. CANCELLED: called off.
                $table->enum('status', ['DRAFT', 'SENT', 'QUOTED', 'EVALUATED', 'AWARDED', 'CANCELLED'])->default('DRAFT');
                $table->uuid('selected_supplier_id')->nullable();
                $table->uuid('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->uuid('converted_po_id')->nullable();
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('request_id')->references('request_id')->on('cbe_purchase_requests')->onDelete('set null');
                $table->foreign('cost_centre_id')->references('centre_id')->on('cbe_cost_centres')->onDelete('set null');
                $table->foreign('fund_id')->references('fund_id')->on('cbe_funds')->onDelete('set null');
                $table->foreign('selected_supplier_id')->references('supplier_id')->on('cbe_suppliers')->onDelete('set null');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'status']);
            });
        }

        if (! Schema::hasTable('cbe_purchase_rfq_suppliers')) {
            Schema::create('cbe_purchase_rfq_suppliers', function (Blueprint $table) {
                $table->uuid('rfq_supplier_id')->primary();
                $table->uuid('rfq_id');
                $table->uuid('supplier_id');
                $table->string('quotation_ref', 60)->nullable();
                $table->date('quotation_date')->nullable();
                $table->decimal('quoted_amount', 12, 2)->nullable();
                $table->string('remarks', 255)->nullable();
                $table->string('attachment_path')->nullable();
                $table->string('attachment_original_name')->nullable();
                $table->boolean('is_selected')->default(false);
                $table->timestamps();

                $table->foreign('rfq_id')->references('rfq_id')->on('cbe_purchase_rfqs')->onDelete('cascade');
                $table->foreign('supplier_id')->references('supplier_id')->on('cbe_suppliers')->onDelete('restrict');
                $table->index('rfq_id');
                $table->unique(['rfq_id', 'supplier_id'], 'cbe_rfq_supplier_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_purchase_rfq_suppliers');
        Schema::dropIfExists('cbe_purchase_rfqs');
    }
};
