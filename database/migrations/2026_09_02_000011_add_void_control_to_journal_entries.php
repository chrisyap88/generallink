<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 2 Sep 2026 (Task #338) — Void/Reversal control. Per standard
// double-entry practice, a posted journal entry is NEVER edited or
// deleted (that would break the audit trail an AGM/ROS submission
// depends on). Instead, voiding an entry posts a brand-new REVERSING
// journal entry — same lines, debit and credit swapped, dated the day
// of the void (not backdated into a possibly-closed period) — and both
// entries are cross-linked so the history reads cleanly either
// direction: "this was voided, here's the reversal" / "this entry
// reverses that one". This is the one generic mechanism that works for
// every document type (transactions, bills, JVs, petty cash vouchers,
// disposals...) without touching each one's own posting method.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_journal_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_journal_entries', 'status')) {
                $table->string('status', 10)->default('POSTED')->after('source_id'); // POSTED, VOIDED
            }
            if (! Schema::hasColumn('cbe_journal_entries', 'voided_by')) {
                $table->uuid('voided_by')->nullable()->after('status');
            }
            if (! Schema::hasColumn('cbe_journal_entries', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('voided_by');
            }
            if (! Schema::hasColumn('cbe_journal_entries', 'void_reason')) {
                $table->string('void_reason', 255)->nullable()->after('voided_at');
            }
            // Set on the ORIGINAL entry once it has been voided — points
            // to the reversal entry that was created.
            if (! Schema::hasColumn('cbe_journal_entries', 'reversed_by_journal_id')) {
                $table->uuid('reversed_by_journal_id')->nullable()->after('void_reason');
            }
            // Set on the REVERSAL entry itself — points back to the
            // original entry it reverses.
            if (! Schema::hasColumn('cbe_journal_entries', 'reverses_journal_id')) {
                $table->uuid('reverses_journal_id')->nullable()->after('reversed_by_journal_id');
            }
        });

        if (! $this->fkExists('cbe_journal_entries', 'cbe_journal_entries_voided_by_foreign')) {
            Schema::table('cbe_journal_entries', function (Blueprint $table) {
                $table->foreign('voided_by')->references('agent_id')->on('agents')->onDelete('set null');
            });
        }
        if (! $this->fkExists('cbe_journal_entries', 'cbe_journal_entries_reversed_by_journal_id_foreign')) {
            Schema::table('cbe_journal_entries', function (Blueprint $table) {
                $table->foreign('reversed_by_journal_id')->references('journal_id')->on('cbe_journal_entries')->onDelete('set null');
            });
        }
        if (! $this->fkExists('cbe_journal_entries', 'cbe_journal_entries_reverses_journal_id_foreign')) {
            Schema::table('cbe_journal_entries', function (Blueprint $table) {
                $table->foreign('reverses_journal_id')->references('journal_id')->on('cbe_journal_entries')->onDelete('set null');
            });
        }
    }

    private function fkExists(string $table, string $constraintName): bool
    {
        $row = \Illuminate\Support\Facades\DB::selectOne(
            'SELECT COUNT(*) as cnt FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $constraintName]
        );
        return $row && (int) $row->cnt > 0;
    }

    public function down(): void
    {
        Schema::table('cbe_journal_entries', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropForeign(['reversed_by_journal_id']);
            $table->dropForeign(['reverses_journal_id']);
            $table->dropColumn(['status', 'voided_by', 'voided_at', 'void_reason', 'reversed_by_journal_id', 'reverses_journal_id']);
        });
    }
};
