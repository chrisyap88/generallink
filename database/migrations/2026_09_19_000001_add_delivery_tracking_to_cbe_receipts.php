<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 19 Sep 2026 — per the outstanding item flagged 8 Aug ("receipt to
// the recipient is by email ya or whatapps if available"): CBE receipts
// can now also be delivered by WhatsApp (see WhatsAppCloudConnector::
// sendDocument()), alongside the existing email delivery. These columns
// give basic traceability for both — who it was sent to and when — since
// cbe_receipts previously had no delivery record at all (email was
// fire-and-forget, success only shown once in the UI). Purely additive.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cbe_receipts', 'emailed_to')) {
            Schema::table('cbe_receipts', function (Blueprint $table) {
                $table->string('emailed_to', 150)->nullable()->after('issued_at');
                $table->timestamp('emailed_at')->nullable()->after('emailed_to');
                $table->string('whatsapp_sent_to', 20)->nullable()->after('emailed_at');
                $table->timestamp('whatsapp_sent_at')->nullable()->after('whatsapp_sent_to');
                $table->string('whatsapp_note', 255)->nullable()->after('whatsapp_sent_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cbe_receipts', 'emailed_to')) {
            Schema::table('cbe_receipts', function (Blueprint $table) {
                $table->dropColumn(['emailed_to', 'emailed_at', 'whatsapp_sent_to', 'whatsapp_sent_at', 'whatsapp_note']);
            });
        }
    }
};
