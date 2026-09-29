<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #391) — per Chris: every document number (JV, PV,
// Receipt, Debit Note, Purchase Order, etc.) should sit in a fixed band
// per calendar month, the way a pre-printed physical receipt/voucher
// book is numbered — January 01000-01999, February 02000-02999, and so
// on through December 12000-12999 — rather than one long counter that
// runs continuously through the whole year. This also gives each month
// 1,000 numbers of headroom per document type per node, plenty for a
// temple's transaction volume, and it means "what month was this raised
// in" is readable straight off the number without checking the date.
//
// cbe_document_sequences already existed keyed by (cbe_node_id,
// doc_type, year) — this adds `month` to that key so each month gets
// its own independent counter, and a per-row `last_number` still means
// "how many of this doc type has this node issued in this month",
// exactly as before, just scoped one level finer.
//
// cbe_document_sequence_resets is new: Chris also asked for the ability
// to manually reset/set the starting number for a period. An unrestricted
// silent reset is a real internal-control risk (it could be used to
// quietly re-open a number range and issue a duplicate document number,
// which is exactly the kind of gap an auditor checks for) — so every
// reset is written here with who did it, when, the old and new value,
// and a mandatory reason, rather than just overwriting last_number with
// no trace. Restricted to Admin only in the controller, mirroring the
// existing reopenPeriod() pattern.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cbe_document_sequences', 'month')) {
            Schema::table('cbe_document_sequences', function (Blueprint $table) {
                $table->unsignedTinyInteger('month')->default(1)->after('year');
            });
        }

        try {
            Schema::table('cbe_document_sequences', function (Blueprint $table) {
                $table->dropUnique('cbe_doc_seq_node_type_year_unique');
            });
        } catch (\Throwable $e) {
            // Already dropped — safe to ignore.
        }

        if (! $this->indexExists('cbe_document_sequences', 'cbe_doc_seq_node_type_year_month_unique')) {
            Schema::table('cbe_document_sequences', function (Blueprint $table) {
                $table->unique(['cbe_node_id', 'doc_type', 'year', 'month'], 'cbe_doc_seq_node_type_year_month_unique');
            });
        }

        if (! Schema::hasTable('cbe_document_sequence_resets')) {
            Schema::create('cbe_document_sequence_resets', function (Blueprint $table) {
                $table->uuid('reset_id')->primary();
                $table->uuid('cbe_node_id');
                $table->string('doc_type', 20);
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->unsignedInteger('old_last_number');
                $table->unsignedInteger('new_last_number');
                $table->text('reason');
                $table->uuid('reset_by');
                $table->timestamps();

                $table->foreign('reset_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'doc_type', 'year', 'month'], 'cbe_doc_seq_resets_node_type_year_month_idx');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $conn = Schema::getConnection();
        $rows = $conn->select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$indexName]);
        return count($rows) > 0;
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_document_sequence_resets');
        Schema::table('cbe_document_sequences', function (Blueprint $table) {
            $table->dropUnique('cbe_doc_seq_node_type_year_month_unique');
        });
        Schema::table('cbe_document_sequences', function (Blueprint $table) {
            $table->unique(['cbe_node_id', 'doc_type', 'year'], 'cbe_doc_seq_node_type_year_unique');
            $table->dropColumn('month');
        });
    }
};
