<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 17 Jul 2026 — audit trail for automatic customer detail updates.
// When a future document upload (renewal, repeat purchase, etc.) is
// matched to an EXISTING customer record but a field like address or
// phone comes back different from what's on file, the system updates
// the customer record automatically but must never do so silently.
// Every such change is written here: which field, old value, new
// value, and what triggered it — so a disputed change can always be
// traced back to the exact document/session that caused it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_change_logs', function (Blueprint $table) {
            $table->uuid('change_id')->primary();
            $table->uuid('customer_id');
            $table->string('field_name', 50);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();

            // What caused the change — e.g. DOCUMENT_UPLOAD, MANUAL_EDIT.
            $table->string('source', 30);
            // Free-text pointer back to the cause — e.g. the document
            // reference number, or the sales transaction policy_id.
            $table->string('source_reference', 100)->nullable();

            // Who/what was logged in when this happened. Nullable
            // because a fully automated email-ingestion pipeline may
            // not have a logged-in human at all.
            $table->uuid('changed_by_agent_id')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('customer_id');
            $table->index('created_at');

            $table->foreign('customer_id')
                  ->references('customer_id')
                  ->on('customers')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_change_logs');
    }
};
