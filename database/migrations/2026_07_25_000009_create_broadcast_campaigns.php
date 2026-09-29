<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Jul 2026 — Growth & Outreach Center (task #215). Broadcast
// Campaigns — Admin builds a message, picks an audience and a channel
// (from Channel Connections), and sends/schedules it. Per Chris: "you
// can incorporate first i subscribe later" — a campaign can only
// actually be sent once its channel is BOTH enabled AND connected
// (real API credentials tested), which won't be true for any channel
// until a future phase wires up each provider's real API. Until then,
// every campaign simply sits as DRAFT/SCHEDULED with a clear message
// explaining why. Deliberately its own tables — never touches
// commission_transactions or CommissionEngine.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_campaigns', function (Blueprint $table) {
            $table->uuid('campaign_id')->primary();
            $table->string('title', 150);
            $table->string('channel_code', 30);
            $table->enum('audience_type', ['CUSTOMERS', 'AGENTS']);
            $table->uuid('audience_group_label_id')->nullable(); // optional narrowing, either audience type
            $table->string('audience_role', 20)->nullable(); // AGENTS only — INTRODUCER/TEAM_LEADER/GROUP_LEADER
            $table->text('message_body');
            $table->enum('status', ['DRAFT', 'SCHEDULED', 'SENT', 'FAILED'])->default('DRAFT');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipient_count')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);

            $table->foreign('channel_code')->references('channel_code')->on('growth_channels')->cascadeOnDelete();
            $table->foreign('audience_group_label_id')->references('group_label_id')->on('group_labels')->nullOnDelete();
            $table->foreign('created_by')->references('agent_id')->on('agents')->nullOnDelete();
        });

        // One row per intended recipient — built at send time, so Admin
        // can see exactly who a campaign was (or would be) sent to.
        Schema::create('broadcast_recipients', function (Blueprint $table) {
            $table->uuid('recipient_row_id')->primary();
            $table->uuid('campaign_id');
            $table->enum('recipient_type', ['CUSTOMER', 'AGENT']);
            $table->uuid('recipient_ref_id'); // customer_id or agent_id, depending on recipient_type
            $table->string('contact_value', 150)->nullable(); // phone/email actually targeted
            $table->enum('status', ['PENDING', 'SENT', 'FAILED'])->default('PENDING');
            $table->timestamps();

            $table->index(['campaign_id', 'status']);

            $table->foreign('campaign_id')->references('campaign_id')->on('broadcast_campaigns')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_recipients');
        Schema::dropIfExists('broadcast_campaigns');
    }
};
