<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            return; // already exists — do nothing, avoids duplicate-table errors
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('notification_id')->primary();
            $table->uuid('recipient_agent_id'); // who sees this in their bell
            $table->string('type'); // NEW_REGISTRATION, PROMOTION, DEMOTION
            $table->string('title');
            $table->text('message');
            $table->uuid('related_agent_id')->nullable(); // the person the notification is ABOUT
            $table->uuid('reason_code_id')->nullable(); // for demotion, per our reason-code design
            $table->text('reason_notes')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('recipient_agent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
