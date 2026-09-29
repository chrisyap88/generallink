<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 22 Aug 2026 — per Chris: event-side expenses (venue, catering,
// performers, decorations, etc.), needed to produce the Event Income &
// Expenditure Statement (contributions received minus these expenses).
// category_id reuses the same admin-configurable EXPENSE categories
// already built for the Bank Statement/Transactions module — one
// category list for the whole Temple, not a second one invented here.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_event_expenses')) {
            Schema::create('cbe_event_expenses', function (Blueprint $table) {
                $table->uuid('expense_id')->primary();
                $table->uuid('event_id');
                $table->uuid('category_id')->nullable();
                $table->date('expense_date');
                $table->string('description', 255)->nullable();
                $table->decimal('amount', 12, 2);
                $table->string('receipt_attachment_path', 500)->nullable();
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('event_id')->references('event_id')->on('cbe_events')->onDelete('cascade');
                $table->foreign('category_id')->references('category_id')->on('cbe_transaction_categories')->onDelete('restrict');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index('event_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_event_expenses');
    }
};
