<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 9 Aug 2026 — per Chris: the SSM document requirement must change
// automatically based on the vendor's business entity type (Sdn. Bhd.,
// Sole Proprietorship, Partnership, Berhad, or Other). This column drives
// that logic — see App\Services\VendorDocumentChecklistService.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('entity_type', 20)->nullable()->after('vendor_type');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('entity_type');
        });
    }
};
