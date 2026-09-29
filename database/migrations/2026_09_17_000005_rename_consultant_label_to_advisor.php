<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// NEW 17 Sep 2026 — per Chris: rename "Consultant" to "Advisor"
// everywhere. Every other mention was in a lang file (fixed directly,
// no migration needed) — these are the only 2 places the OLD wording
// was already stored as real data (seeded before this rename): the
// Practitioner Types catalog and the Program Library catalog. The
// underlying CODE/identifier ('CONSULTANT', route names, etc.) is left
// untouched on purpose — only the human-readable label changes, so
// nothing that reads the code breaks.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('cbe_practitioner_types')
            ->where('code', 'CONSULTANT')
            ->where('type_label', 'Consultant')
            ->update(['type_label' => 'Advisor']);

        DB::table('program_catalog')
            ->where('program_key', 'glade_admin_cbe_kpi_members')
            ->where('label', 'Consultant Maintenance')
            ->update(['label' => 'Advisor Maintenance']);
    }

    public function down(): void
    {
        DB::table('cbe_practitioner_types')
            ->where('code', 'CONSULTANT')
            ->where('type_label', 'Advisor')
            ->update(['type_label' => 'Consultant']);

        DB::table('program_catalog')
            ->where('program_key', 'glade_admin_cbe_kpi_members')
            ->where('label', 'Advisor Maintenance')
            ->update(['label' => 'Consultant Maintenance']);
    }
};
