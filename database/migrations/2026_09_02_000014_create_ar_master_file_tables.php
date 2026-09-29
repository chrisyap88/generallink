<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 2 Sep 2026 (Task #357) — per Chris's Temple/NGO AR module spec:
// three Master File setup tables that AR daily entry depends on.
// Mirrors the existing cbe_transaction_categories pattern exactly
// (group_label_id nullable = global default available to every CBE
// community; set it to scope a row to one specific community).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_customer_categories')) {
            Schema::create('cbe_customer_categories', function (Blueprint $table) {
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
        }

        if (! Schema::hasTable('cbe_payment_methods')) {
            Schema::create('cbe_payment_methods', function (Blueprint $table) {
                $table->uuid('method_id')->primary();
                $table->uuid('group_label_id')->nullable();
                $table->string('method_name', 100);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();

                $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->onDelete('cascade');
                $table->index(['group_label_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('cbe_payment_terms')) {
            Schema::create('cbe_payment_terms', function (Blueprint $table) {
                $table->uuid('term_id')->primary();
                $table->uuid('group_label_id')->nullable();
                $table->string('term_name', 100);
                $table->unsignedInteger('net_days')->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();

                $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->onDelete('cascade');
                $table->index(['group_label_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_payment_terms');
        Schema::dropIfExists('cbe_payment_methods');
        Schema::dropIfExists('cbe_customer_categories');
    }
};
