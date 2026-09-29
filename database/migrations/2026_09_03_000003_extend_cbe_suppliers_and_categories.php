<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// NEW 3 Sep 2026 (Task #371) — per Chris's Temple/NGO AP spec: Supplier
// Master File gaps. Mirrors the cbe_customer_categories pattern already
// built for AR (Task #357) — a new cbe_supplier_categories table, seeded
// with the exact category list from Chris's spec — plus the missing
// fields on cbe_suppliers itself (code, business reg no, bank info,
// category/terms/method links, active/inactive status). Payment Method
// and Payment Terms are NOT duplicated here — cbe_payment_methods and
// cbe_payment_terms already exist (built for AR) and are shared across
// both AP and AR, exactly like Chart of Accounts is shared.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_supplier_categories')) {
            Schema::create('cbe_supplier_categories', function (Blueprint $table) {
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

            // Seed the exact examples from Chris's spec, as global
            // defaults (group_label_id null) available to every CBE
            // community — same seeding approach as other global lookup
            // tables in this system.
            $names = [
                'Food & Catering', 'Utilities', 'Maintenance', 'Cleaning Services',
                'Security Services', 'IT Services', 'Religious Supplies',
                'Event Suppliers', 'Contractors', 'Professional Services', 'Other Suppliers',
            ];
            foreach ($names as $i => $name) {
                DB::table('cbe_supplier_categories')->insert([
                    'category_id' => (string) Str::uuid(),
                    'group_label_id' => null,
                    'category_name' => $name,
                    'category_name_zh' => null,
                    'is_active' => true,
                    'display_order' => $i,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        Schema::table('cbe_suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_suppliers', 'supplier_code')) {
                $table->string('supplier_code', 30)->nullable()->after('supplier_id');
            }
            if (! Schema::hasColumn('cbe_suppliers', 'business_reg_no')) {
                $table->string('business_reg_no', 60)->nullable()->after('supplier_name');
            }
            if (! Schema::hasColumn('cbe_suppliers', 'category_id')) {
                $table->uuid('category_id')->nullable()->after('business_reg_no');
                $table->foreign('category_id')->references('category_id')->on('cbe_supplier_categories')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_suppliers', 'payment_term_id')) {
                $table->uuid('payment_term_id')->nullable()->after('category_id');
                $table->foreign('payment_term_id')->references('term_id')->on('cbe_payment_terms')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_suppliers', 'payment_method_id')) {
                $table->uuid('payment_method_id')->nullable()->after('payment_term_id');
                $table->foreign('payment_method_id')->references('method_id')->on('cbe_payment_methods')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_suppliers', 'bank_name')) {
                $table->string('bank_name', 100)->nullable()->after('payment_method_id');
            }
            if (! Schema::hasColumn('cbe_suppliers', 'bank_account_no')) {
                $table->string('bank_account_no', 60)->nullable()->after('bank_name');
            }
            if (! Schema::hasColumn('cbe_suppliers', 'bank_account_holder')) {
                $table->string('bank_account_holder', 150)->nullable()->after('bank_account_no');
            }
            if (! Schema::hasColumn('cbe_suppliers', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('bank_account_holder');
            }
        });

        // Backfill supplier_code for any suppliers created before this
        // column existed, so every row has one — SUP-0001, SUP-0002...
        // ordered by creation so the numbering matches entry order.
        $suppliers = DB::table('cbe_suppliers')->whereNull('supplier_code')->orderBy('created_at')->get();
        foreach ($suppliers as $i => $s) {
            DB::table('cbe_suppliers')->where('supplier_id', $s->supplier_id)
                ->update(['supplier_code' => sprintf('SUP-%04d', $i + 1)]);
        }
    }

    public function down(): void
    {
        Schema::table('cbe_suppliers', function (Blueprint $table) {
            foreach (['category_id', 'payment_term_id', 'payment_method_id'] as $fk) {
                if (Schema::hasColumn('cbe_suppliers', $fk)) {
                    $table->dropForeign(['cbe_suppliers_' . $fk . '_foreign']);
                }
            }
            $table->dropColumn(['supplier_code', 'business_reg_no', 'category_id', 'payment_term_id', 'payment_method_id', 'bank_name', 'bank_account_no', 'bank_account_holder', 'is_active']);
        });
        Schema::dropIfExists('cbe_supplier_categories');
    }
};
