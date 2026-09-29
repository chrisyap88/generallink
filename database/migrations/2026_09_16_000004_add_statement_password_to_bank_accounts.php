<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 16 Sep 2026 — per Chris: "Honestly all bank statement is
// password protected in Malaysia" — he doesn't want to retype the same
// password on every upload. This lets him save each bank account's
// statement password ONCE on that account's own master-file record,
// encrypted with Laravel's own encrypt()/decrypt() (same reversible
// approach already used elsewhere in the app — NOT a one-way hash,
// since the real password has to be recovered to hand to qpdf).
// AiAccountingController::storeBatch() then tries the saved password(s)
// automatically whenever the manual "PDF Password" field on the upload
// form is left blank.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_bank_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_bank_accounts', 'statement_password_encrypted')) {
                $table->text('statement_password_encrypted')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_bank_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_bank_accounts', 'statement_password_encrypted')) {
                $table->dropColumn('statement_password_encrypted');
            }
        });
    }
};
