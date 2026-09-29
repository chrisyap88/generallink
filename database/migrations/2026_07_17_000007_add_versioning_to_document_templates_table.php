<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// NEW 17 Jul 2026 — version history for Document Templates. If a
// vendor changes their document layout, calibrating it again creates
// a NEW version instead of overwriting the old one — the old
// calibration is kept (deactivated, not deleted) so it's still
// inspectable, and only one version per vendor+product stays "active"
// (matched against future uploads) at a time.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->uuid('template_group_id')->nullable()->after('template_id');
            $table->unsignedInteger('version_number')->default(1)->after('template_name');
            $table->timestamp('superseded_at')->nullable()->after('is_active');
        });

        // Backfill — every template that already exists today is its
        // own first version of its own group.
        DB::table('document_templates')->whereNull('template_group_id')->update([
            'template_group_id' => DB::raw('template_id'),
        ]);

        Schema::table('document_templates', function (Blueprint $table) {
            $table->index('template_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn(['template_group_id', 'version_number', 'superseded_at']);
        });
    }
};
