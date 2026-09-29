<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 8 Aug 2026 — GLADE Ecosystem Engagement, Phase 1 (Task #84). Records
// every push attempt (Email/WhatsApp/etc — NOT the Portal, which is just
// the Notice Board itself and tracked separately via notice_reads) so
// NoticeDeliveryService can (a) never push the same notice to the same
// agent on the same channel twice, and (b) enforce each agent's own
// weekly frequency cap by counting rows in the last 7 days.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notice_deliveries', function (Blueprint $table) {
            $table->uuid('delivery_id')->primary();
            $table->uuid('notice_id');
            $table->uuid('agent_id');
            $table->enum('channel', ['EMAIL', 'WHATSAPP', 'TELEGRAM', 'LINE', 'WECHAT', 'SMS']);
            $table->enum('status', ['SENT', 'SKIPPED', 'FAILED'])->default('SENT');
            $table->string('detail', 255)->nullable(); // cause+fix text when SKIPPED/FAILED
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('notice_id')->references('notice_id')->on('notices')->onDelete('cascade');
            $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
            $table->unique(['notice_id', 'agent_id', 'channel']);
            $table->index(['agent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_deliveries');
    }
};
