<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 22 Aug 2026 — per Chris's standing rule "all fees/settings MUST NOT
// hardcode": income/expense categories (e.g. Donations, Incense & Joss
// Paper Sales, Utilities, Renovation, Festival Costs) must be
// admin-configurable, not a fixed PHP list — different Temples/CBE
// communities will want their own category names. group_label_id nullable
// = a global default category available to every CBE community; set it to
// scope a category to one specific community instead.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_transaction_categories')) {
            Schema::create('cbe_transaction_categories', function (Blueprint $table) {
                $table->uuid('category_id')->primary();
                $table->uuid('group_label_id')->nullable();
                $table->string('category_name', 150);
                $table->string('category_name_zh', 150)->nullable();
                $table->enum('type', ['INCOME', 'EXPENSE']);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();

                $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->onDelete('cascade');
                $table->index(['group_label_id', 'type', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_transaction_categories');
    }
};
