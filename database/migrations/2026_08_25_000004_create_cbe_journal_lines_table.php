<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Aug 2026 — the actual debit/credit lines of a journal entry.
// Every journal entry must balance (sum of debit = sum of credit across
// its lines) — enforced in CbeAccountingService, not the database, same
// pattern as the rest of this app's business rules.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_journal_lines')) {
            Schema::create('cbe_journal_lines', function (Blueprint $table) {
                $table->uuid('line_id')->primary();
                $table->uuid('journal_id');
                $table->uuid('account_id');
                $table->decimal('debit', 12, 2)->default(0);
                $table->decimal('credit', 12, 2)->default(0);
                $table->string('memo', 255)->nullable();
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();

                $table->foreign('journal_id')->references('journal_id')->on('cbe_journal_entries')->onDelete('cascade');
                $table->foreign('account_id')->references('account_id')->on('cbe_chart_of_accounts')->onDelete('restrict');
                $table->index(['account_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_journal_lines');
    }
};
