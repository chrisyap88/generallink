<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// NEW 3 Sep 2026 (Task #381) — per Chris's Temple/NGO General Ledger
// spec, sections 1.2 and 1.3: Account Group (a 2nd-level classification
// under each Account Type — e.g. "Current Assets", "Bank", "Cash",
// "Accounts Receivable" all sit under ASSET) and Account Category (a
// flatter, reporting-only classification — "Bank", "AR Control",
// "Fixed Asset", "Operating Expense" etc.) are two DIFFERENT
// classifications the spec asks for, both missing until now — the
// existing cbe_chart_of_accounts only had a single account_type field.
// Both tables mirror the cbe_supplier_categories / cbe_customer_categories
// pattern already used across this system: group_label_id nullable
// (null = shared platform default available to every CBE community).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_account_groups')) {
            Schema::create('cbe_account_groups', function (Blueprint $table) {
                $table->uuid('group_id')->primary();
                $table->uuid('group_label_id')->nullable();
                $table->enum('account_type', ['ASSET', 'LIABILITY', 'EQUITY', 'INCOME', 'EXPENSE']);
                $table->string('group_name', 100);
                $table->string('group_name_zh', 100)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();

                $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->onDelete('cascade');
                $table->index(['group_label_id', 'account_type', 'is_active']);
            });

            $names = [
                ['ASSET', 'Current Assets'], ['ASSET', 'Bank'], ['ASSET', 'Cash'],
                ['ASSET', 'Accounts Receivable'], ['ASSET', 'Fixed Assets'], ['ASSET', 'Other Assets'],
                ['LIABILITY', 'Current Liabilities'], ['LIABILITY', 'Accounts Payable'],
                ['LIABILITY', 'Accrued Liabilities'], ['LIABILITY', 'Other Liabilities'],
                ['EQUITY', 'General Fund'], ['EQUITY', 'Building Fund'], ['EQUITY', 'Renovation Fund'],
                ['EQUITY', 'Education Fund'], ['EQUITY', 'Restricted Funds'], ['EQUITY', 'Other Reserves'],
                ['INCOME', 'Donation Income'], ['INCOME', 'Membership Income'], ['INCOME', 'Event Income'],
                ['INCOME', 'Rental Income'], ['INCOME', 'Other Income'],
                ['EXPENSE', 'Administration'], ['EXPENSE', 'Utilities'], ['EXPENSE', 'Maintenance'],
                ['EXPENSE', 'Religious Activities'], ['EXPENSE', 'Event Expenses'], ['EXPENSE', 'Staff Expenses'],
                ['EXPENSE', 'Depreciation'], ['EXPENSE', 'Other Expenses'],
            ];
            foreach ($names as $i => [$type, $name]) {
                DB::table('cbe_account_groups')->insert([
                    'group_id' => (string) Str::uuid(), 'group_label_id' => null,
                    'account_type' => $type, 'group_name' => $name, 'group_name_zh' => null,
                    'is_active' => true, 'display_order' => $i,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        if (! Schema::hasTable('cbe_account_categories')) {
            Schema::create('cbe_account_categories', function (Blueprint $table) {
                $table->uuid('category_id')->primary();
                $table->uuid('group_label_id')->nullable();
                $table->string('category_name', 100);
                $table->string('category_name_zh', 100)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();

                $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->onDelete('cascade');
                $table->index(['group_label_id', 'is_active']);
            });

            $names = [
                'Bank', 'Cash', 'AR Control', 'AP Control', 'Fixed Asset',
                'Accumulated Depreciation', 'Donation Income', 'Fund Income',
                'Operating Expense', 'Administrative Expense',
            ];
            foreach ($names as $i => $name) {
                DB::table('cbe_account_categories')->insert([
                    'category_id' => (string) Str::uuid(), 'group_label_id' => null,
                    'category_name' => $name, 'category_name_zh' => null,
                    'is_active' => true, 'display_order' => $i,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        Schema::table('cbe_chart_of_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_chart_of_accounts', 'account_group_id')) {
                $table->uuid('account_group_id')->nullable()->after('account_type');
                $table->foreign('account_group_id')->references('group_id')->on('cbe_account_groups')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_chart_of_accounts', 'account_category_id')) {
                $table->uuid('account_category_id')->nullable()->after('account_group_id');
                $table->foreign('account_category_id')->references('category_id')->on('cbe_account_categories')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_chart_of_accounts', 'normal_balance')) {
                $table->enum('normal_balance', ['DEBIT', 'CREDIT'])->nullable()->after('account_category_id');
            }
            if (! Schema::hasColumn('cbe_chart_of_accounts', 'is_posting_account')) {
                $table->boolean('is_posting_account')->default(true)->after('normal_balance');
            }
            if (! Schema::hasColumn('cbe_chart_of_accounts', 'is_control_account')) {
                $table->boolean('is_control_account')->default(false)->after('is_posting_account');
            }
            if (! Schema::hasColumn('cbe_chart_of_accounts', 'description')) {
                $table->string('description', 255)->nullable()->after('is_control_account');
            }
        });

        // Backfill normal_balance for every existing account from its
        // account_type, so nothing is left blank after this migration —
        // ASSET/EXPENSE are debit-normal, LIABILITY/EQUITY/INCOME are
        // credit-normal (same rule CbeAccountingController::normalBalance()
        // already applies at report time).
        DB::table('cbe_chart_of_accounts')->whereIn('account_type', ['ASSET', 'EXPENSE'])->update(['normal_balance' => 'DEBIT']);
        DB::table('cbe_chart_of_accounts')->whereIn('account_type', ['LIABILITY', 'EQUITY', 'INCOME'])->update(['normal_balance' => 'CREDIT']);
    }

    public function down(): void
    {
        Schema::table('cbe_chart_of_accounts', function (Blueprint $table) {
            foreach (['account_group_id', 'account_category_id'] as $fk) {
                if (Schema::hasColumn('cbe_chart_of_accounts', $fk)) {
                    $table->dropForeign(['cbe_chart_of_accounts_' . $fk . '_foreign']);
                }
            }
            $table->dropColumn(['account_group_id', 'account_category_id', 'normal_balance', 'is_posting_account', 'is_control_account', 'description']);
        });
        Schema::dropIfExists('cbe_account_categories');
        Schema::dropIfExists('cbe_account_groups');
    }
};
