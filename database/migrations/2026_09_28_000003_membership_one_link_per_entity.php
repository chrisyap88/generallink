<?php

// NEW 28 Sep 2026 — per Chris: one person can be affiliated to MANY entities
// of the same CBE group (Temple A and Temple B both under 道). The old key
// allowed only one link per person per CBE group; it becomes one link per
// person per ENTITY.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_group_memberships')) {
            return;
        }
        $has = fn ($n) => collect(DB::select('SHOW INDEX FROM cbe_group_memberships WHERE Key_name = ?', [$n]))->isNotEmpty();
        // the agent foreign key needs its own index before the old key goes
        if (! $has('cgm_agent_idx')) {
            DB::statement('CREATE INDEX cgm_agent_idx ON cbe_group_memberships (agent_id)');
        }
        if (! $has('cgm_agent_node_unique')) {
            DB::statement('CREATE UNIQUE INDEX cgm_agent_node_unique ON cbe_group_memberships (agent_id, cbe_node_id)');
        }
        // drop every unique key made of exactly (agent_id, group_label_id), whatever its name
        $cols = [];
        foreach (DB::select('SHOW INDEX FROM cbe_group_memberships WHERE Non_unique = 0') as $i) {
            $cols[$i->Key_name][(int) $i->Seq_in_index] = $i->Column_name;
        }
        foreach ($cols as $name => $c) {
            ksort($c);
            if (array_values($c) === ['agent_id', 'group_label_id']) {
                DB::statement('ALTER TABLE cbe_group_memberships DROP INDEX `'.$name.'`');
            }
        }
        if (! $has('cgm_agent_group_idx')) {
            DB::statement('CREATE INDEX cgm_agent_group_idx ON cbe_group_memberships (agent_id, group_label_id)');
        }
    }

    public function down(): void
    {
    }
};
