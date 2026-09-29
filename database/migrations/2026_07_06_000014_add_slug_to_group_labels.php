<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_labels', function (Blueprint $table) {
            if (! Schema::hasColumn('group_labels', 'slug')) {
                $table->string('slug')->nullable()->unique()->after('group_name');
            }
        });

        // Backfill slugs for any existing group labels, so this never
        // breaks a group created before this feature existed.
        $labels = DB::table('group_labels')->whereNull('slug')->get();
        foreach ($labels as $label) {
            $base = Str::slug($label->group_name);
            $slug = $base;
            $i = 1;
            while (DB::table('group_labels')->where('slug', $slug)->exists()) {
                $slug = $base . '-' . $i;
                $i++;
            }
            DB::table('group_labels')->where('group_label_id', $label->group_label_id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('group_labels', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
