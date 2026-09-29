<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 3 Sep 2026 (Task #389) — per Chris's Temple/NGO GL spec, Journal
// Voucher section: an Attachment field, same pattern already used on
// Purchase Bills (attachment_path + attachment_original_name, stored on
// the local disk, served through a dedicated download route). Added to
// both cbe_journal_entries (the posted journal) and
// cbe_journal_voucher_drafts (the maker-checker staging row), so a file
// attached before approval survives the trip from draft to posted entry.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_journal_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_journal_entries', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('description');
            }
            if (! Schema::hasColumn('cbe_journal_entries', 'attachment_original_name')) {
                $table->string('attachment_original_name')->nullable()->after('attachment_path');
            }
        });

        if (Schema::hasTable('cbe_journal_voucher_drafts')) {
            Schema::table('cbe_journal_voucher_drafts', function (Blueprint $table) {
                if (! Schema::hasColumn('cbe_journal_voucher_drafts', 'attachment_path')) {
                    $table->string('attachment_path')->nullable()->after('description');
                }
                if (! Schema::hasColumn('cbe_journal_voucher_drafts', 'attachment_original_name')) {
                    $table->string('attachment_original_name')->nullable()->after('attachment_path');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('cbe_journal_entries', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_original_name']);
        });
        if (Schema::hasTable('cbe_journal_voucher_drafts')) {
            Schema::table('cbe_journal_voucher_drafts', function (Blueprint $table) {
                $table->dropColumn(['attachment_path', 'attachment_original_name']);
            });
        }
    }
};
