<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 26 Aug 2026 — per Chris: sensei prayer/counseling/blessing
// appointments can carry a fee (can be zero for a free visit). When an
// amount is entered, the system auto-issues a collection receipt for it
// (see CbeReceiptService) exactly the same way as donations and event
// sales — no separate "upload a photo of the receipt" step.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_appointments', 'fee_amount')) {
                $table->decimal('fee_amount', 10, 2)->nullable()->default(0)->after('appointment_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_appointments', function (Blueprint $table) {
            $table->dropColumn('fee_amount');
        });
    }
};
