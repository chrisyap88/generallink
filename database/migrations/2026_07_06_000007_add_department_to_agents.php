<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('agents', 'department')) {
            return; // already exists — avoids duplicate-column errors
        }

        Schema::table('agents', function (Blueprint $table) {
            // Only meaningful for role=ADMIN. Determines which category
            // of 4-eye approvals this Admin can act on — FINANCE,
            // SALES, or DIRECTOR (universal fallback, can approve any
            // category). Nullable since non-Admin roles don't use this.
            $table->string('department')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('department');
        });
    }
};
