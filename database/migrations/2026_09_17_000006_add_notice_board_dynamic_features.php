<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// NEW 17 Sep 2026 — per Chris: (1) reuse Carolyn to help rephrase a
// notice, (2) a live preview of exactly how a notice will look once
// posted — content-aware + seasonal styling, auto by default, Admin
// can turn off/edit/add, (3) some notices post themselves with no
// officer action — festival greetings and (opt-in only) birthday
// notices, (4) richer attachments — flyer/catalog/video, each either a
// pasted link or an uploaded file. This migration adds everything
// those 4 features need: new columns on cbe_temple_notices, the two
// new admin-editable catalogs (cbe_notice_styles, cbe_season_themes —
// same pattern as Faith Types/Practitioner Types), and the member's
// own opt-in flag on agents.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cbe_temple_notices', 'notice_source')) {
            Schema::table('cbe_temple_notices', function (Blueprint $table) {
                // Who/what created this notice.
                $table->enum('notice_source', ['OFFICER', 'SYSTEM'])->default('OFFICER')->after('category');
                // Only set for notice_source=SYSTEM — e.g. FESTIVAL_GREETING,
                // BIRTHDAY_MONTH, BIRTHDAY_CARD — lets the generator command
                // find/avoid duplicating its own past posts.
                $table->string('notice_type', 40)->nullable()->after('notice_source');
                // De-dupe key for system notices only, e.g. "2026-12"
                // (birthday-of-the-month) or "CHRISTMAS-2026" (festival) —
                // unique per node+type so the daily job never double-posts.
                $table->string('system_period_key', 60)->nullable()->after('notice_type');

                // Content-aware styling (see cbe_notice_styles below).
                // style_key is auto-detected from title+body keywords unless
                // style_is_override is true (officer picked one manually).
                $table->string('style_key', 30)->nullable()->after('body');
                $table->boolean('style_is_override')->default(false)->after('style_key');

                // PUBLIC = whole entity's Notice Board (the normal case).
                // MEMBER_PRIVATE = only target_agent_id can see it — used
                // for the individual "Happy Birthday" card on that member's
                // own dashboard, nobody else's.
                $table->enum('visibility', ['PUBLIC', 'MEMBER_PRIVATE'])->default('PUBLIC')->after('style_is_override');
                $table->uuid('target_agent_id')->nullable()->after('visibility');

                // Existing attachment_file_name/attachment_file_path columns
                // now serve as the "Flyer" attachment's FILE option; this adds
                // its LINK option plus two more attachment slots (Catalog,
                // Video), each independently either a link or an uploaded file.
                $table->string('flyer_link_url', 500)->nullable()->after('attachment_file_path');

                $table->string('catalog_file_name')->nullable()->after('flyer_link_url');
                $table->string('catalog_file_path')->nullable()->after('catalog_file_name');
                $table->string('catalog_link_url', 500)->nullable()->after('catalog_file_path');

                $table->string('video_file_name')->nullable()->after('catalog_link_url');
                $table->string('video_file_path')->nullable()->after('video_file_name');
                $table->string('video_link_url', 500)->nullable()->after('video_file_path');

                $table->foreign('target_agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->index(['cbe_node_id', 'notice_type', 'system_period_key'], 'cbe_notices_sys_period_idx');
                $table->index(['target_agent_id', 'visibility'], 'cbe_notices_target_visibility_idx');
            });
        }

        if (! Schema::hasTable('cbe_notice_styles')) {
            Schema::create('cbe_notice_styles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('style_key', 30)->unique();
                $table->string('label', 60);
                $table->string('icon_key', 30)->default('bell');
                $table->string('bg_color', 20);
                $table->string('accent_color', 20);
                $table->string('text_color', 20);
                // Comma-separated trigger words the auto-detector scans a
                // notice's title+body for, case-insensitive — Admin's own
                // words, not hardcoded by us.
                $table->text('keywords')->nullable();
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });

            $now = now();
            DB::table('cbe_notice_styles')->insert([
                ['id' => (string) Str::uuid(), 'style_key' => 'SECURITY_ALERT', 'label' => 'Security Alert', 'icon_key' => 'alert-triangle', 'bg_color' => '#2A0E0E', 'accent_color' => '#E23B3B', 'text_color' => '#FFFFFF', 'keywords' => 'pickpocket,theft,robbery,snatch,break-in,burglar,security alert,intruder', 'is_system' => true, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['id' => (string) Str::uuid(), 'style_key' => 'SCAM_WARNING', 'label' => 'Scam Warning', 'icon_key' => 'alert-circle', 'bg_color' => '#2A0E0E', 'accent_color' => '#E23B3B', 'text_color' => '#FFFFFF', 'keywords' => 'scam,fraud,fake call,phishing,bank transfer,con artist', 'is_system' => true, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
                ['id' => (string) Str::uuid(), 'style_key' => 'CELEBRATION', 'label' => 'Celebration', 'icon_key' => 'star', 'bg_color' => '#FFF6E8', 'accent_color' => '#B8790E', 'text_color' => '#5C3B0A', 'keywords' => 'anniversary,congratulations,celebration,thank you,achievement,milestone', 'is_system' => true, 'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
                ['id' => (string) Str::uuid(), 'style_key' => 'BIRTHDAY', 'label' => 'Birthday', 'icon_key' => 'gift', 'bg_color' => '#F1F7FF', 'accent_color' => '#3E6FC4', 'text_color' => '#1E3A66', 'keywords' => 'birthday,happy birthday,born', 'is_system' => true, 'is_active' => true, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
                ['id' => (string) Str::uuid(), 'style_key' => 'PROMO', 'label' => 'Promo', 'icon_key' => 'tag', 'bg_color' => '#FFF1E6', 'accent_color' => '#FF6A00', 'text_color' => '#7A2E00', 'keywords' => 'promo,promotion,% off,discount,early-bird,early bird,limited time,offer', 'is_system' => true, 'is_active' => true, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
                ['id' => (string) Str::uuid(), 'style_key' => 'GENERAL', 'label' => 'General', 'icon_key' => 'bell', 'bg_color' => '#FFFFFF', 'accent_color' => '#546E7A', 'text_color' => '#263238', 'keywords' => '', 'is_system' => true, 'is_active' => true, 'sort_order' => 99, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        if (! Schema::hasTable('cbe_season_themes')) {
            Schema::create('cbe_season_themes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('season_key', 30)->unique();
                $table->string('label', 60);
                $table->unsignedTinyInteger('start_month');
                $table->unsignedTinyInteger('start_day');
                $table->unsignedTinyInteger('end_month');
                $table->unsignedTinyInteger('end_day');
                $table->string('bg_color', 20);
                $table->string('accent_color', 20);
                // Shown as the header band's own message, and also used as
                // the body text of the auto-posted festival greeting notice.
                $table->string('greeting_text', 200)->nullable();
                $table->boolean('post_greeting_notice')->default(true);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });

            $now = now();
            DB::table('cbe_season_themes')->insert([
                ['id' => (string) Str::uuid(), 'season_key' => 'CHRISTMAS', 'label' => 'Christmas', 'start_month' => 12, 'start_day' => 20, 'end_month' => 12, 'end_day' => 26, 'bg_color' => '#0B3D2E', 'accent_color' => '#C1272D', 'greeting_text' => 'Wishing our community a warm and peaceful Christmas season', 'post_greeting_notice' => true, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['id' => (string) Str::uuid(), 'season_key' => 'CNY', 'label' => 'Chinese New Year', 'start_month' => 1, 'start_day' => 20, 'end_month' => 2, 'end_day' => 10, 'bg_color' => '#8E1B1B', 'accent_color' => '#D4AF37', 'greeting_text' => 'Wishing our community prosperity and good health in the new year', 'post_greeting_notice' => true, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        if (! Schema::hasColumn('agents', 'share_birthday_public')) {
            Schema::table('agents', function (Blueprint $table) {
                // Opt-in only, per Chris — off unless the member turns it on
                // themselves. When on, they appear in that month's public
                // "Birthday of the Month" notice; the individual birthday
                // card on their own dashboard doesn't need this (private,
                // seen only by them either way) but we gate both the same
                // way for one consistent, simple rule the member understands.
                $table->boolean('share_birthday_public')->default(false)->after('date_of_birth');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('agents', 'share_birthday_public')) {
            Schema::table('agents', function (Blueprint $table) {
                $table->dropColumn('share_birthday_public');
            });
        }
        Schema::dropIfExists('cbe_season_themes');
        Schema::dropIfExists('cbe_notice_styles');
        if (Schema::hasColumn('cbe_temple_notices', 'notice_source')) {
            Schema::table('cbe_temple_notices', function (Blueprint $table) {
                $table->dropForeign(['target_agent_id']);
                $table->dropColumn([
                    'notice_source', 'notice_type', 'system_period_key',
                    'style_key', 'style_is_override', 'visibility', 'target_agent_id',
                    'flyer_link_url', 'catalog_file_name', 'catalog_file_path', 'catalog_link_url',
                    'video_file_name', 'video_file_path', 'video_link_url',
                ]);
            });
        }
    }
};
