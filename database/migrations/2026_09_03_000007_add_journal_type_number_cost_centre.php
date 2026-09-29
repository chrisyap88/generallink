<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// NEW 3 Sep 2026 (Task #382) — per Chris's Temple/NGO General Ledger
// spec, sections 1.4-1.6: every journal entry needs a human-readable
// sequential Journal Number (separate from source_type's own reference,
// e.g. a bill's bill_no) and a Journal Type classification (General,
// Adjustment, Accrual, Reversal, Opening, Closing, Recurring, Imported),
// and every journal LINE needs an optional Cost Centre / Department /
// Project / Event tag for departmental/fund reporting. One flexible
// cbe_cost_centres table covers all four dimensions via centre_type,
// same "one table, a type column" pattern as cbe_account_groups.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_journal_types')) {
            Schema::create('cbe_journal_types', function (Blueprint $table) {
                $table->uuid('type_id')->primary();
                $table->uuid('group_label_id')->nullable();
                $table->string('type_code', 20);
                $table->string('type_name', 60);
                $table->string('type_name_zh', 60)->nullable();
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();

                $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->onDelete('cascade');
                $table->index(['group_label_id', 'is_active']);
            });

            $types = [
                ['GENERAL', 'General', 0], ['ADJUSTMENT', 'Adjustment', 1],
                ['ACCRUAL', 'Accrual', 2], ['REVERSAL', 'Reversal', 3],
                ['OPENING', 'Opening', 4], ['CLOSING', 'Closing', 5],
                ['RECURRING', 'Recurring', 6], ['IMPORTED', 'Imported', 7],
            ];
            foreach ($types as [$code, $name, $order]) {
                DB::table('cbe_journal_types')->insert([
                    'type_id' => (string) Str::uuid(), 'group_label_id' => null,
                    'type_code' => $code, 'type_name' => $name, 'type_name_zh' => null,
                    'is_system' => true, 'is_active' => true, 'display_order' => $order,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        if (! Schema::hasTable('cbe_cost_centres')) {
            Schema::create('cbe_cost_centres', function (Blueprint $table) {
                $table->uuid('centre_id')->primary();
                $table->uuid('group_label_id')->nullable();
                $table->enum('centre_type', ['COST_CENTRE', 'DEPARTMENT', 'PROJECT', 'EVENT']);
                $table->string('centre_code', 20)->nullable();
                $table->string('centre_name', 100);
                $table->string('centre_name_zh', 100)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();

                $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->onDelete('cascade');
                $table->index(['group_label_id', 'centre_type', 'is_active']);
            });
        }

        Schema::table('cbe_journal_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_journal_entries', 'journal_no')) {
                $table->string('journal_no', 30)->nullable()->after('reference_no');
            }
            if (! Schema::hasColumn('cbe_journal_entries', 'journal_type_id')) {
                $table->uuid('journal_type_id')->nullable()->after('source_id');
                $table->foreign('journal_type_id')->references('type_id')->on('cbe_journal_types')->onDelete('set null');
            }
        });

        Schema::table('cbe_journal_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_journal_lines', 'cost_centre_id')) {
                $table->uuid('cost_centre_id')->nullable()->after('account_id');
                $table->foreign('cost_centre_id')->references('centre_id')->on('cbe_cost_centres')->onDelete('set null');
            }
        });

        // The maker-checker draft (cbe_journal_voucher_drafts) needs its
        // own journal_type_id too, so an Adjustment/Accrual JV that's
        // above the approval threshold doesn't lose its type on the way
        // to being approved and posted (Task #383 wires the selector in).
        if (Schema::hasTable('cbe_journal_voucher_drafts') && ! Schema::hasColumn('cbe_journal_voucher_drafts', 'journal_type_id')) {
            Schema::table('cbe_journal_voucher_drafts', function (Blueprint $table) {
                $table->uuid('journal_type_id')->nullable()->after('description');
                $table->foreign('journal_type_id')->references('type_id')->on('cbe_journal_types')->onDelete('set null');
            });
        }

        // Backfill journal_type_id for every journal entry already posted,
        // inferred from source_type — REVERSAL and the two OPENING_BALANCE
        // source types map directly; everything else (TRANSACTION, BILL,
        // INVOICE, BILL_PAYMENT, FIXED_ASSET, BANK_RECON, MANUAL, ...)
        // defaults to GENERAL. Journal Number is intentionally left blank
        // for pre-existing entries (no reliable historical sequence to
        // reconstruct) — only entries posted from now on get one.
        $generalId = DB::table('cbe_journal_types')->where('type_code', 'GENERAL')->value('type_id');
        $reversalId = DB::table('cbe_journal_types')->where('type_code', 'REVERSAL')->value('type_id');
        $openingId = DB::table('cbe_journal_types')->where('type_code', 'OPENING')->value('type_id');

        DB::table('cbe_journal_entries')->whereNull('journal_type_id')->update(['journal_type_id' => $generalId]);
        DB::table('cbe_journal_entries')->where('source_type', 'REVERSAL')->update(['journal_type_id' => $reversalId]);
        DB::table('cbe_journal_entries')->whereIn('source_type', ['AR_OPENING_BALANCE', 'AP_OPENING_BALANCE', 'OPENING_BALANCE'])->update(['journal_type_id' => $openingId]);
    }

    public function down(): void
    {
        if (Schema::hasTable('cbe_journal_voucher_drafts') && Schema::hasColumn('cbe_journal_voucher_drafts', 'journal_type_id')) {
            Schema::table('cbe_journal_voucher_drafts', function (Blueprint $table) {
                $table->dropForeign(['cbe_journal_voucher_drafts_journal_type_id_foreign']);
                $table->dropColumn('journal_type_id');
            });
        }
        Schema::table('cbe_journal_lines', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_journal_lines', 'cost_centre_id')) {
                $table->dropForeign(['cbe_journal_lines_cost_centre_id_foreign']);
                $table->dropColumn('cost_centre_id');
            }
        });
        Schema::table('cbe_journal_entries', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_journal_entries', 'journal_type_id')) {
                $table->dropForeign(['cbe_journal_entries_journal_type_id_foreign']);
                $table->dropColumn('journal_type_id');
            }
            if (Schema::hasColumn('cbe_journal_entries', 'journal_no')) {
                $table->dropColumn('journal_no');
            }
        });
        Schema::dropIfExists('cbe_cost_centres');
        Schema::dropIfExists('cbe_journal_types');
    }
};
