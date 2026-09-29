<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 22 Sep 2026 — per Chris: Chart of Accounts full rebuild. Account
// Category previously only had names (Bank, Fixed Asset, etc.), no code.
// Chris asked for a 2-digit code on Account Category too, matching the
// numbering treatment already given to the GL Code itself.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_account_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_account_categories', 'category_code')) {
                $table->string('category_code', 2)->nullable()->after('category_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_account_categories', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_account_categories', 'category_code')) {
                $table->dropColumn('category_code');
            }
        });
    }
};
