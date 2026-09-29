<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------
        // Per confirmed legal/privacy requirement (06 Jul 2026): NRIC
        // and bank details are NEVER stored on an agent's profile.
        // This permanently removes any existing stored data in these
        // columns, not just stops future use of them.
        // -----------------------------------------------------
        Schema::table('agents', function (Blueprint $table) {
            if (Schema::hasColumn('agents', 'nric_encrypted')) {
                $table->dropColumn('nric_encrypted');
            }
            if (Schema::hasColumn('agents', 'bank_name')) {
                $table->dropColumn('bank_name');
            }
            if (Schema::hasColumn('agents', 'bank_account_encrypted')) {
                $table->dropColumn('bank_account_encrypted');
            }
        });

        Schema::table('batch_registration_records', function (Blueprint $table) {
            if (Schema::hasColumn('batch_registration_records', 'nric')) {
                $table->dropColumn('nric');
            }
            if (Schema::hasColumn('batch_registration_records', 'bank_name')) {
                $table->dropColumn('bank_name');
            }
            if (Schema::hasColumn('batch_registration_records', 'bank_account')) {
                $table->dropColumn('bank_account');
            }
        });
    }

    public function down(): void
    {
        // Note: this restores the COLUMNS, but the actual data that was
        // in them at the time of the up() migration is permanently gone
        // — dropColumn() is a destructive, non-recoverable operation.
        Schema::table('agents', function (Blueprint $table) {
            $table->text('nric_encrypted')->nullable();
            $table->string('bank_name')->nullable();
            $table->text('bank_account_encrypted')->nullable();
        });
        Schema::table('batch_registration_records', function (Blueprint $table) {
            $table->string('nric')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
        });
    }
};
