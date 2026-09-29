<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// NEW 12 Sep 2026 — per Chris: appointment_type on cbe_appointments was a
// fixed ENUM ('PRAYER','COUNSELING','BLESSING','PRESS_INTERVIEW',
// 'VIP_VISIT','OTHER') — a hardcoded list. Now that each catalog
// position (cbe_faith_practice_types) carries its OWN admin-editable
// reason list, this column must accept free text (whatever reason label
// the Admin typed for that position), so it becomes a plain VARCHAR.
//
// Also adds practice_type_id — WHICH position (Sensei, Legal Advisor,
// etc.) this specific appointment was booked under, now that a
// community can offer several at once. Backfilled from whatever single
// faith_practice_type that community had at the time (the only option
// that existed before today), so historic records keep their correct
// wording context; new appointments will set this explicitly going
// forward.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cbe_appointments', 'practice_type_id')) {
            Schema::table('cbe_appointments', function (Blueprint $table) {
                $table->uuid('practice_type_id')->nullable()->after('cbe_node_id');
                $table->foreign('practice_type_id')->references('id')->on('cbe_faith_practice_types')->nullOnDelete();
            });
        }

        // Backfill practice_type_id from each appointment's node -> its
        // community's (formerly single) faith_practice_type.
        $rows = DB::table('cbe_appointments as ap')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'ap.cbe_node_id')
            ->join('group_labels as g', 'g.group_label_id', '=', 'n.group_label_id')
            ->whereNull('ap.practice_type_id')
            ->whereNotNull('g.faith_practice_type')
            ->select('ap.appointment_id', 'g.faith_practice_type')
            ->get();

        $codeToId = DB::table('cbe_faith_practice_types')->pluck('id', 'code');
        foreach ($rows as $row) {
            $typeId = $codeToId[$row->faith_practice_type] ?? null;
            if ($typeId) {
                DB::table('cbe_appointments')->where('appointment_id', $row->appointment_id)->update(['practice_type_id' => $typeId]);
            }
        }

        // Widen appointment_type from ENUM to free text, mapping the old
        // fixed codes to the human-readable labels an Admin would now
        // type for the same reason, so existing history reads naturally.
        DB::statement('ALTER TABLE cbe_appointments MODIFY appointment_type VARCHAR(100) NOT NULL');
        $map = [
            'PRAYER' => 'Prayer',
            'COUNSELING' => 'Counseling',
            'BLESSING' => 'Blessing',
            'PRESS_INTERVIEW' => 'Press Interview',
            'VIP_VISIT' => 'VIP Visit',
            'OTHER' => 'Other',
        ];
        foreach ($map as $old => $new) {
            DB::table('cbe_appointments')->where('appointment_type', $old)->update(['appointment_type' => $new]);
        }
    }

    public function down(): void
    {
        $map = [
            'Prayer' => 'PRAYER',
            'Counseling' => 'COUNSELING',
            'Blessing' => 'BLESSING',
            'Press Interview' => 'PRESS_INTERVIEW',
            'VIP Visit' => 'VIP_VISIT',
        ];
        foreach ($map as $new => $old) {
            DB::table('cbe_appointments')->where('appointment_type', $new)->update(['appointment_type' => $old]);
        }
        DB::table('cbe_appointments')->whereNotIn('appointment_type', array_values($map))->update(['appointment_type' => 'OTHER']);
        DB::statement("ALTER TABLE cbe_appointments MODIFY appointment_type ENUM('PRAYER','COUNSELING','BLESSING','PRESS_INTERVIEW','VIP_VISIT','OTHER') NOT NULL");

        if (Schema::hasColumn('cbe_appointments', 'practice_type_id')) {
            Schema::table('cbe_appointments', function (Blueprint $table) {
                $table->dropForeign(['practice_type_id']);
                $table->dropColumn('practice_type_id');
            });
        }
    }
};
