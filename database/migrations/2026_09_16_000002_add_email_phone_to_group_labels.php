<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 16 Sep 2026 — per Chris: importing the HQ ("Persekutuan
// Pertubuhan Agama Tao Malaysia" / 马来西亚道教总会) committee list.
// group_labels already carries the group's own head-office address
// (15 Sep 2026 migration), but has nowhere for its head-office email
// or phone — this letterhead has both (daoism.malaysia@gmail.com and
// individual officer WhatsApp numbers), so this adds them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_labels', function (Blueprint $table) {
            if (! Schema::hasColumn('group_labels', 'email')) {
                $table->string('email', 200)->nullable()->after('contact_person_2');
            }
            if (! Schema::hasColumn('group_labels', 'phone')) {
                $table->string('phone', 30)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_labels', function (Blueprint $table) {
            foreach (['phone', 'email'] as $col) {
                if (Schema::hasColumn('group_labels', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
