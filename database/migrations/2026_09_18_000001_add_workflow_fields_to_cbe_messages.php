<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 18 Sep 2026 — per Chris: the internal CBE messaging engine
// (cbe_message_threads/cbe_messages, built 17 Sep) becomes the single
// shared way anyone in a CBE (Secretarial, Treasury, a sponsor, an
// event organizer, a donor, a purchaser/participant) raises a document
// request — a payment notification, receipt request, credit note,
// debit note, cheque or TT slip — with a file attached, instead of a
// separate "Payment Requests" screen. Adds: a document category, a
// tracked status with escalation (so a slow receipt doesn't leave a
// donor/sponsor unhappy and unsure), and AI reading/verification
// fields on the attachment itself (via the existing
// ClaudeDocumentExtractionService — no new AI pipeline built).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_message_threads', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_message_threads', 'category')) {
                $table->string('category', 30)->default('OTHER')->after('subject');
                // PAYMENT_NOTIFICATION, RECEIPT_REQUEST, CREDIT_NOTE,
                // DEBIT_NOTE, CHEQUE, TT_SLIP, OTHER
            }
            if (! Schema::hasColumn('cbe_message_threads', 'status')) {
                $table->enum('status', ['OPEN', 'RESPONDED', 'OUTSTANDING', 'ESCALATED', 'RESOLVED'])
                    ->default('OPEN')->after('category');
            }
            if (! Schema::hasColumn('cbe_message_threads', 'sla_notified_at')) {
                $table->timestamp('sla_notified_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('cbe_message_threads', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('sla_notified_at');
            }
        });

        Schema::table('cbe_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_messages', 'attachment_path')) {
                $table->string('attachment_path', 500)->nullable()->after('body');
            }
            if (! Schema::hasColumn('cbe_messages', 'attachment_original_name')) {
                $table->string('attachment_original_name', 255)->nullable()->after('attachment_path');
            }
            if (! Schema::hasColumn('cbe_messages', 'ai_extracted_amount')) {
                $table->decimal('ai_extracted_amount', 12, 2)->nullable()->after('attachment_original_name');
            }
            if (! Schema::hasColumn('cbe_messages', 'ai_extracted_payer')) {
                $table->string('ai_extracted_payer', 150)->nullable()->after('ai_extracted_amount');
            }
            if (! Schema::hasColumn('cbe_messages', 'ai_extracted_date')) {
                $table->date('ai_extracted_date')->nullable()->after('ai_extracted_payer');
            }
            if (! Schema::hasColumn('cbe_messages', 'ai_confidence')) {
                $table->enum('ai_confidence', ['HIGH', 'MEDIUM', 'LOW'])->nullable()->after('ai_extracted_date');
            }
            if (! Schema::hasColumn('cbe_messages', 'ai_verified')) {
                $table->boolean('ai_verified')->default(false)->after('ai_confidence');
            }
            if (! Schema::hasColumn('cbe_messages', 'ai_notes')) {
                $table->text('ai_notes')->nullable()->after('ai_verified');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_messages', function (Blueprint $table) {
            foreach (['attachment_path', 'attachment_original_name', 'ai_extracted_amount', 'ai_extracted_payer', 'ai_extracted_date', 'ai_confidence', 'ai_verified', 'ai_notes'] as $col) {
                if (Schema::hasColumn('cbe_messages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('cbe_message_threads', function (Blueprint $table) {
            foreach (['category', 'status', 'sla_notified_at', 'resolved_at'] as $col) {
                if (Schema::hasColumn('cbe_message_threads', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
