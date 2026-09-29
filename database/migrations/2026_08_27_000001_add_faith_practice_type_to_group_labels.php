<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 27 Aug 2026 — per Chris: "Sensei" (spirit/trance medium) is a
// TAOIST term only — a Christian group calls the equivalent duty
// "Confession" (person: Confessor/Pastor), Catholic calls it "Sacrament
// of Reconciliation" (person: Priest/Confessor), and this whole concept
// does not apply to non-religious CBE groups (charity, enterprise,
// government-linked, or a Muslim-owned company that isn't itself a
// mosque/congregation) — those keep a generic "Appointment" wording
// instead. This field is what CbeFaithTerminologyService reads to pick
// the right practitioner/duty wording per group — never hardcoded to
// "Sensei" again. Only meaningful when group_type = 'CBE'.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('group_labels') && ! Schema::hasColumn('group_labels', 'faith_practice_type')) {
            Schema::table('group_labels', function (Blueprint $table) {
                $table->enum('faith_practice_type', [
                    'NONE',
                    'TAOIST',
                    'CHRISTIAN_PROTESTANT',
                    'CATHOLIC',
                    'OTHER_RELIGIOUS',
                ])->default('NONE')->after('group_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('group_labels') && Schema::hasColumn('group_labels', 'faith_practice_type')) {
            Schema::table('group_labels', function (Blueprint $table) {
                $table->dropColumn('faith_practice_type');
            });
        }
    }
};
