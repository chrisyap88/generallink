<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// NEW 22 Aug 2026 — before importing the real 591-temple master list,
// checked the actual data and found telephone_1 entries up to 41
// characters (some cells hold more than one number with notes, e.g.
// "0379821457, 0123336968 (苏),0126637086 (苏）") — wider than the
// telephone_1 varchar(30) set on 21 Aug 2026. Widening both telephone
// columns now so the import doesn't truncate real contact info.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('cbe_hierarchy_nodes', 'telephone_1')) {
            DB::statement('ALTER TABLE cbe_hierarchy_nodes MODIFY telephone_1 VARCHAR(150) NULL');
        }
        if (Schema::hasColumn('cbe_hierarchy_nodes', 'telephone_2')) {
            DB::statement('ALTER TABLE cbe_hierarchy_nodes MODIFY telephone_2 VARCHAR(50) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cbe_hierarchy_nodes', 'telephone_1')) {
            DB::statement('ALTER TABLE cbe_hierarchy_nodes MODIFY telephone_1 VARCHAR(30) NULL');
        }
        if (Schema::hasColumn('cbe_hierarchy_nodes', 'telephone_2')) {
            DB::statement('ALTER TABLE cbe_hierarchy_nodes MODIFY telephone_2 VARCHAR(30) NULL');
        }
    }
};
