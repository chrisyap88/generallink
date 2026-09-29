<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 17 Aug 2026 — per Chris: same "Name (Other Language)" field just
// added to agents, now for vendors (Company Name in Chinese/Tamil/
// Hindi/other script). Nullable, optional everywhere. Inherits the
// table's utf8mb4/utf8mb4_unicode_ci default.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('second_name', 200)->nullable()->after('vendor_name');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('second_name');
        });
    }
};
