<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Jul 2026 — Growth & Outreach Center (task #213). Customer
// refer-a-friend, per Chris's original growth proposal. There's no
// Customer Portal yet (task #147, deferred), so the owning agent logs
// this on the customer's behalf — same pattern as Prospect creation.
// Reward is never auto-credited — same "voucher only" pattern as
// Breakaway Bonus / Contests; Admin marks it awarded once the actual
// points/document credit/cash is paid out through the relevant existing
// screen.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_referrals', function (Blueprint $table) {
            $table->uuid('referral_id')->primary();
            $table->uuid('referring_customer_id'); // the existing customer who referred someone
            $table->uuid('agent_id'); // the owning agent — gets the new lead
            $table->string('referred_name', 200);
            $table->string('referred_contact', 100); // phone or email, free text
            $table->text('notes')->nullable();
            $table->enum('status', ['SUBMITTED', 'CONTACTED', 'CONVERTED', 'DECLINED'])->default('SUBMITTED');
            $table->uuid('converted_customer_id')->nullable(); // once the referred person becomes a real customer
            $table->enum('reward_type', ['POINTS', 'CASH', 'DOCUMENT_CREDIT'])->nullable();
            $table->decimal('reward_value', 15, 2)->nullable();
            $table->enum('reward_status', ['NONE', 'PENDING', 'AWARDED'])->default('NONE');
            $table->timestamp('rewarded_at')->nullable();
            $table->uuid('rewarded_by')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'status']);

            $table->foreign('referring_customer_id')->references('customer_id')->on('customers')->cascadeOnDelete();
            $table->foreign('agent_id')->references('agent_id')->on('agents')->cascadeOnDelete();
            $table->foreign('converted_customer_id')->references('customer_id')->on('customers')->nullOnDelete();
            $table->foreign('created_by')->references('agent_id')->on('agents')->nullOnDelete();
            $table->foreign('rewarded_by')->references('agent_id')->on('agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_referrals');
    }
};
