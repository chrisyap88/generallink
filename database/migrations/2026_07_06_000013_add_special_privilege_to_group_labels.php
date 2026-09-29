<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_labels', function (Blueprint $table) {
            // Default TRUE — every existing/normal group keeps standard
            // Promotion/Demotion behavior. Only special-privilege groups
            // (Tekun Corporation, Felda, Police Cooperative, etc.) get
            // this explicitly turned OFF by Admin when creating them.
            // Generic and reusable — not tied to any specific org.
            if (! Schema::hasColumn('group_labels', 'promotion_demotion_enabled')) {
                $table->boolean('promotion_demotion_enabled')->default(true)->after('description');
            }
            if (! Schema::hasColumn('group_labels', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('promotion_demotion_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_labels', function (Blueprint $table) {
            $table->dropColumn(['promotion_demotion_enabled', 'logo_path']);
        });
    }
};
