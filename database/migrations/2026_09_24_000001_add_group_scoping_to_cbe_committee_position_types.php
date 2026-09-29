<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 24 Sep 2026 — per Chris: "you must have the position for each cbe
// group, different cbe may have different position... this position
// masterfile set up follow the method how we set up rank in org group."
// Committee/Management Positions was one single shared catalog used
// unrestricted by every CBE group (a temple group could pick "CEO", an
// SME group could pick "Pengerusi"). This adds group_label_id, scoping
// each position to ONE specific CBE group -- exactly the same pattern
// already used for role_ranks (see
// 2026_07_31_000008_rescope_role_ranks_to_group_label.php): NULL
// group_label_id = "System Default", a shared position every CBE group
// can use unless/until it defines its own.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_committee_position_types', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_committee_position_types', 'group_label_id')) {
                $table->uuid('group_label_id')->nullable()->after('id');
                $table->foreign('group_label_id')
                      ->references('group_label_id')
                      ->on('group_labels')
                      ->cascadeOnDelete();
                $table->index(['group_label_id', 'is_active'], 'cbe_committee_position_types_grouplabel_active_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_committee_position_types', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_committee_position_types', 'group_label_id')) {
                $table->dropForeign(['group_label_id']);
                $table->dropIndex('cbe_committee_position_types_grouplabel_active_idx');
                $table->dropColumn('group_label_id');
            }
        });
    }
};
