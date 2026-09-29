<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// NEW 27 Aug 2026 — per Chris: Outstanding Payables (Current Financial
// Snapshot, Box 5) must also cover staff/officer expense reimbursement
// claims, not just supplier invoices. Rather than a separate parallel
// system, Chris confirmed modeling a staff claim AS a record in this
// SAME AP ledger — the "payee" is the staff member instead of an
// outside supplier — so Outstanding Payables stays one single source of
// truth. supplier_id becomes nullable (only required when payee_type =
// SUPPLIER); payee_agent_id is used instead when payee_type = STAFF_CLAIM.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_purchase_bills', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_purchase_bills', 'payee_type')) {
                $table->enum('payee_type', ['SUPPLIER', 'STAFF_CLAIM'])->default('SUPPLIER')->after('supplier_id');
            }
            if (! Schema::hasColumn('cbe_purchase_bills', 'payee_agent_id')) {
                $table->uuid('payee_agent_id')->nullable()->after('payee_type');
                $table->foreign('payee_agent_id')->references('agent_id')->on('agents')->onDelete('restrict');
            }
        });

        // supplier_id was NOT NULL — relax it so STAFF_CLAIM rows can omit it.
        // Raw ALTER (not ->change()) since doctrine/dbal isn't installed in this project.
        DB::statement('ALTER TABLE cbe_purchase_bills MODIFY supplier_id CHAR(36) NULL');
    }

    public function down(): void
    {
        Schema::table('cbe_purchase_bills', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_purchase_bills', 'payee_agent_id')) {
                $table->dropForeign(['payee_agent_id']);
                $table->dropColumn('payee_agent_id');
            }
            if (Schema::hasColumn('cbe_purchase_bills', 'payee_type')) {
                $table->dropColumn('payee_type');
            }
        });
    }
};
