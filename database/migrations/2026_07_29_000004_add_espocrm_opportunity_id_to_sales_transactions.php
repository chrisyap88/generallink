<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 29 Jul 2026 — EspoCRM integration, expanded scope (task #253). Every
// sales transaction/policy now also gets a matching Opportunity in
// EspoCRM (linked to the customer's Contact), moved to "Closed Won" when
// Admin confirms the sale. Remembers the EspoCRM Opportunity id.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->string('espocrm_opportunity_id', 100)->nullable()->after('document_reference_number');
        });
    }

    public function down(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropColumn('espocrm_opportunity_id');
        });
    }
};
