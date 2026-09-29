<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 22 Aug 2026 — per Chris: "full traceability, accountability, and
// financial transparency". When an Event is closed, its net result
// (total contributions received, total expenses) posts as summary
// entries into the Temple's own cbe_transactions ledger — the same
// ledger the regular Annual Income & Expenditure Report already reads
// from — so event money isn't a separate, disconnected set of books.
// event_id is nullable because most transactions are everyday temple
// income/expenses with no event behind them at all.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cbe_transactions', 'event_id')) {
            Schema::table('cbe_transactions', function (Blueprint $table) {
                $table->uuid('event_id')->nullable()->after('bank_statement_id');
                $table->foreign('event_id')->references('event_id')->on('cbe_events')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cbe_transactions', 'event_id')) {
            Schema::table('cbe_transactions', function (Blueprint $table) {
                $table->dropForeign(['event_id']);
                $table->dropColumn('event_id');
            });
        }
    }
};
