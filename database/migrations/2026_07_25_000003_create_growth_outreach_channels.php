<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 25 Jul 2026 — Growth & Outreach Center, Phase 1 (task #209). Per
// Chris: "you can incorporate first i subscribe later... just a tick
// box for me to choose." This table is the on/off switch + a place to
// store the connection details Chris will fill in LATER once he
// actually signs up with a WhatsApp Business API provider (or
// Instagram/Twitter/WeChat/Telegram/Line). Ticking is_enabled now does
// NOT send anything — it just marks the channel as "we plan to use
// this," so it shows up as selectable everywhere else in Growth &
// Outreach (e.g. Broadcast Campaigns). Nothing here touches
// commission_transactions or CommissionEngine.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_channels', function (Blueprint $table) {
            $table->uuid('channel_id')->primary();
            $table->string('channel_code', 30)->unique(); // WHATSAPP, INSTAGRAM, TWITTER, WECHAT, TELEGRAM, LINE
            $table->string('channel_name', 60);
            $table->boolean('is_enabled')->default(false); // the tickbox
            $table->string('provider_name')->nullable(); // e.g. "Twilio", "Meta Direct", "360dialog" — filled in later
            $table->string('account_identifier')->nullable(); // phone number ID / page ID / bot token identifier
            $table->text('credentials_encrypted')->nullable(); // encrypted JSON of actual API secrets, filled in later
            $table->boolean('is_connected')->default(false); // true only once real credentials are saved & tested
            $table->timestamp('connected_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('updated_by')->references('agent_id')->on('agents')->nullOnDelete();
        });

        $now = now();
        $channels = [
            ['WHATSAPP', 'WhatsApp'],
            ['INSTAGRAM', 'Instagram'],
            ['TWITTER', 'Twitter / X'],
            ['WECHAT', 'WeChat'],
            ['TELEGRAM', 'Telegram'],
            ['LINE', 'Line'],
        ];
        foreach ($channels as [$code, $name]) {
            DB::table('growth_channels')->insert([
                'channel_id'   => (string) Str::uuid(),
                'channel_code' => $code,
                'channel_name' => $name,
                'is_enabled'   => false,
                'is_connected' => false,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_channels');
    }
};
