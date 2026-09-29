<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 27 Aug 2026 — per Chris: a CBE may have more than one bank
// account, and Box 3 (Financial Overview) needs each account shown as
// its own box with a real closing-balance figure — not just a scanned
// attachment. cbe_bank_statements previously stored ONLY the file, with
// no bank-account identifier and no balance number field. This adds a
// bank accounts table (one CBE node -> many accounts) and extends
// cbe_bank_statements with bank_account_id + closing_balance, populated
// via the document-extraction review flow (same pattern as
// SalesTransactionController::extractDocument()), not blind OCR.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_bank_accounts')) {
            Schema::create('cbe_bank_accounts', function (Blueprint $table) {
                $table->uuid('bank_account_id')->primary();
                $table->uuid('cbe_node_id');
                $table->string('bank_name', 150);
                $table->string('account_name', 150)->nullable();
                $table->string('account_number', 60);
                $table->boolean('is_active')->default(true);
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('set null');
                $table->index(['cbe_node_id', 'is_active']);
            });
        }

        Schema::table('cbe_bank_statements', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_bank_statements', 'bank_account_id')) {
                $table->uuid('bank_account_id')->nullable()->after('cbe_node_id');
                $table->foreign('bank_account_id')->references('bank_account_id')->on('cbe_bank_accounts')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_bank_statements', 'closing_balance')) {
                $table->decimal('closing_balance', 14, 2)->nullable()->after('statement_year');
            }
            if (! Schema::hasColumn('cbe_bank_statements', 'extraction_reviewed')) {
                $table->boolean('extraction_reviewed')->default(false)->after('closing_balance'); // true once officer confirmed the AI-extracted balance
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_bank_statements', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_bank_statements', 'bank_account_id')) {
                $table->dropForeign(['bank_account_id']);
                $table->dropColumn('bank_account_id');
            }
            foreach (['closing_balance', 'extraction_reviewed'] as $col) {
                if (Schema::hasColumn('cbe_bank_statements', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::dropIfExists('cbe_bank_accounts');
    }
};
