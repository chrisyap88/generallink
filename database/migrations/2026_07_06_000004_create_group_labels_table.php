<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------
        // group_labels — a completely separate, simple identity label
        // (e.g. "prihatin2u", "rela2u"). Assigned once by Admin,
        // NEVER touched by Promotion/Demotion or any other automatic
        // process. Many unrelated agents can share the same label.
        // -----------------------------------------------------
        if (! Schema::hasTable('group_labels')) {
            Schema::create('group_labels', function (Blueprint $table) {
                $table->uuid('group_label_id')->primary();
                $table->string('group_name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // -----------------------------------------------------
        // Link field on agents — which label (if any) this agent
        // belongs to. Nullable, since not every agent needs one.
        // -----------------------------------------------------
        if (! Schema::hasColumn('agents', 'group_label_id')) {
            Schema::table('agents', function (Blueprint $table) {
                $table->uuid('group_label_id')->nullable()->after('group_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('group_label_id');
        });
        Schema::dropIfExists('group_labels');
    }
};
