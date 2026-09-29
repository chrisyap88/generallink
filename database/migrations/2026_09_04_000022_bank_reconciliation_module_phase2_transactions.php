<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module upgrade,
// Phase 2: Bank Transaction Entry, spec section 2 ("Bank Statement
// Entry"). This is the real architectural fix flagged during the
// Phase 0 gap-check: the existing cbe_bank_reconciliation_lines are
// typed straight onto a reconciliation session and matched against
// cbe_transactions (the SIMPLE secretary/treasurer bookkeeping ledger
// — a completely separate, older module from the double-entry GL that
// Purchasing/AR/AP/Fixed Assets/Bank Transfers all post to). That means
// today's reconciliation never actually looks at the real GL bank
// movements at all.
//
// cbe_bank_transactions is the new, decoupled "bank side" record — a
// raw statement line (manual or imported), independent of any one
// reconciliation session, exactly like a real bank statement. Phase 3
// will rework matching to compare these against GL journal lines
// posted to the bank's own account (the "system side"), not against
// cbe_transactions.
//
// Amount convention matches cbe_bank_reconciliation_lines.amount:
// positive = money in (deposit/credit), negative = money out
// (withdrawal/debit) — one signed column, not separate debit/credit
// columns, so totals/sums work without a CASE statement everywhere.
//
// Tables are created in dependency order: import_batches first (no
// dependency on cbe_bank_transactions), then cbe_bank_transactions
// (FK's to import_batches), then import_rows (FK's to both).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_bank_transaction_import_batches')) {
            Schema::create('cbe_bank_transaction_import_batches', function (Blueprint $table) {
                $table->uuid('batch_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('bank_account_id');
                $table->string('filename', 255);
                $table->string('status', 20)->default('PREVIEW'); // PREVIEW / COMMITTED / CANCELLED
                $table->unsignedInteger('total_rows')->default(0);
                $table->unsignedInteger('valid_rows')->default(0);
                $table->unsignedInteger('invalid_rows')->default(0);
                $table->unsignedInteger('duplicate_rows')->default(0);
                $table->unsignedInteger('committed_rows')->default(0);
                $table->uuid('uploaded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('bank_account_id')->references('bank_account_id')->on('cbe_bank_accounts')->onDelete('cascade');
                $table->foreign('uploaded_by')->references('agent_id')->on('agents')->onDelete('restrict');
            });
        }

        if (! Schema::hasTable('cbe_bank_transactions')) {
            Schema::create('cbe_bank_transactions', function (Blueprint $table) {
                $table->uuid('transaction_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('bank_account_id');
                $table->date('transaction_date');
                $table->date('value_date')->nullable();
                $table->uuid('transaction_type_id')->nullable();
                $table->string('description', 255);
                $table->string('reference_no', 100)->nullable();
                $table->string('cheque_no', 50)->nullable();
                $table->decimal('amount', 12, 2); // positive = credit/deposit, negative = debit/withdrawal
                $table->string('bank_reference', 150)->nullable(); // bank's own statement line ref — duplicate-detection key on import
                $table->string('source', 20)->default('MANUAL'); // MANUAL / IMPORT
                $table->uuid('import_batch_id')->nullable();
                $table->string('status', 20)->default('UNRECONCILED'); // UNRECONCILED / MATCHED / RECONCILED
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('bank_account_id')->references('bank_account_id')->on('cbe_bank_accounts')->onDelete('cascade');
                $table->foreign('transaction_type_id')->references('type_id')->on('cbe_bank_transaction_types')->onDelete('set null');
                $table->foreign('import_batch_id')->references('batch_id')->on('cbe_bank_transaction_import_batches')->onDelete('set null');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['bank_account_id', 'transaction_date']);
                $table->index(['bank_account_id', 'status']);
            });
        }

        if (! Schema::hasTable('cbe_bank_transaction_import_rows')) {
            Schema::create('cbe_bank_transaction_import_rows', function (Blueprint $table) {
                $table->uuid('row_id')->primary();
                $table->uuid('batch_id');
                $table->unsignedInteger('row_number');
                $table->string('transaction_date_raw', 40)->nullable();
                $table->date('transaction_date')->nullable();
                $table->string('description', 255)->nullable();
                $table->string('reference_no', 100)->nullable();
                $table->string('cheque_no', 50)->nullable();
                $table->decimal('debit', 12, 2)->nullable();
                $table->decimal('credit', 12, 2)->nullable();
                $table->string('bank_reference', 150)->nullable();
                $table->string('status', 20)->default('VALID'); // VALID / INVALID / DUPLICATE
                $table->string('error_message', 255)->nullable();
                $table->uuid('committed_transaction_id')->nullable();
                $table->timestamps();

                $table->foreign('batch_id')->references('batch_id')->on('cbe_bank_transaction_import_batches')->onDelete('cascade');
                // Explicit short name — the auto-generated name (table +
                // column + "_foreign") is 65 characters, over MySQL's
                // 64-character identifier limit.
                $table->foreign('committed_transaction_id', 'cbe_bti_rows_committed_txn_foreign')->references('transaction_id')->on('cbe_bank_transactions')->onDelete('set null');
                $table->index('batch_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_bank_transaction_import_rows');
        Schema::table('cbe_bank_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_bank_transactions', 'import_batch_id')) {
                $table->dropForeign(['import_batch_id']);
            }
        });
        Schema::dropIfExists('cbe_bank_transactions');
        Schema::dropIfExists('cbe_bank_transaction_import_batches');
    }
};
