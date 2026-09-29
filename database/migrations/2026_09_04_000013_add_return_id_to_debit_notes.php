<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #394 Phase 2) — links a Debit Note back to the
// Purchase Return it was raised for, when the user follows the "Raise
// Debit Note" button from a Purchase Return's screen rather than
// creating one from scratch. Nullable — a Debit Note raised for any
// other reason (pricing error, discount, etc.) has no return to link to.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_debit_notes', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_debit_notes', 'return_id')) {
                $table->uuid('return_id')->nullable()->after('bill_id');
                $table->foreign('return_id')->references('return_id')->on('cbe_purchase_returns')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_debit_notes', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_debit_notes', 'return_id')) {
                $table->dropForeign(['cbe_debit_notes_return_id_foreign']);
                $table->dropColumn('return_id');
            }
        });
    }
};
