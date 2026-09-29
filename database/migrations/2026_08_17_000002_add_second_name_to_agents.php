<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 17 Aug 2026 — per Chris: "Name (Other Language)" — a second name
// field so Chinese, Tamil, Hindi, or any other script can be recorded
// alongside the required Latin-script full_name. Applies to every
// agent (DSG/ORG/CBE alike) since it's stored right on the agents
// table itself, not per-group. Nullable — optional on every screen.
// Inherits the table's utf8mb4/utf8mb4_unicode_ci default, which
// already supports any Unicode script.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('second_name', 200)->nullable()->after('full_name');
        });

        // Batch-import staging table mirrors the same fields it will
        // eventually insert into agents — needs the column too so a
        // CSV upload can carry a second name through to commit.
        if (Schema::hasTable('batch_registration_records')) {
            Schema::table('batch_registration_records', function (Blueprint $table) {
                $table->string('second_name', 200)->nullable()->after('full_name');
            });
        }
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('second_name');
        });
        if (Schema::hasTable('batch_registration_records')) {
            Schema::table('batch_registration_records', function (Blueprint $table) {
                $table->dropColumn('second_name');
            });
        }
    }
};
