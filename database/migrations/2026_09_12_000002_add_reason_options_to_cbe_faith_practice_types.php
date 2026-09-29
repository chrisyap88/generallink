<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// NEW 12 Sep 2026 — per Chris: appointment "reasons" (what the member is
// booking the appointment FOR) were hardcoded to
// PRAYER/COUNSELING/BLESSING/OTHER directly in three controllers
// (Members/Customers/Donors). That made sense only for a temple — it
// breaks completely for a Legal Advisor or Medical Advisor position,
// where nobody books a "Prayer". Per Chris's decision: each position in
// the catalog gets its OWN admin-editable reason list (free text,
// comma-separated, same editable pattern as Hierarchy Level Names) —
// never a fixed set again.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cbe_faith_practice_types', 'reason_options')) {
            Schema::table('cbe_faith_practice_types', function (Blueprint $table) {
                $table->text('reason_options')->nullable()->after('log_form_title');
            });
        }

        // Backfill the 5 system-seeded rows with sensible defaults that
        // match what was previously hardcoded, so existing behavior is
        // unchanged until an Admin edits them. Any Admin-added custom
        // rows created before this migration get a generic starter list
        // (Admin can rename/expand it any time on the catalog screen).
        $defaults = [
            'NONE' => 'Consultation,Follow-up,Other',
            'TAOIST' => 'Prayer,Counseling,Blessing,Other',
            'CHRISTIAN_PROTESTANT' => 'Confession,Counseling,Other',
            'CATHOLIC' => 'Confession,Counseling,Other',
            'OTHER_RELIGIOUS' => 'Consultation,Counseling,Other',
        ];
        foreach ($defaults as $code => $reasons) {
            DB::table('cbe_faith_practice_types')->where('code', $code)->whereNull('reason_options')->update(['reason_options' => $reasons]);
        }
        DB::table('cbe_faith_practice_types')->whereNull('reason_options')->update(['reason_options' => 'Consultation,Other']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('cbe_faith_practice_types', 'reason_options')) {
            Schema::table('cbe_faith_practice_types', function (Blueprint $table) {
                $table->dropColumn('reason_options');
            });
        }
    }
};
