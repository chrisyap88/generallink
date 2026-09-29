<?php

// NEW 28 Sep 2026 — per Chris: the affiliation register keeps FULL history.
// A person who leaves and rejoins the same entity as the same type gets a
// new line (the old line stays ENDED with its From / To), so the old
// "one row per membership + tag" unique key is dropped. Only one ACTIVE
// line per membership + type is enforced by the application.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_member_role_tags')) {
            return;
        }
        // plain index first (the membership foreign key needs one), then drop the unique key
        $has = collect(DB::select("SHOW INDEX FROM cbe_member_role_tags WHERE Key_name = 'cmrt_membership_tag_idx'"))->isNotEmpty();
        if (! $has) {
            DB::statement('CREATE INDEX cmrt_membership_tag_idx ON cbe_member_role_tags (membership_id, tag)');
        }
        $idx = collect(DB::select("SHOW INDEX FROM cbe_member_role_tags WHERE Non_unique = 0 AND Key_name <> 'PRIMARY'"))->pluck('Key_name')->unique();
        foreach ($idx as $name) {
            DB::statement("ALTER TABLE cbe_member_role_tags DROP INDEX `$name`");
        }
    }

    public function down(): void
    {
    }
};
