<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('agent_profiles', 'photo_path')) {
            return; // already exists — avoids duplicate-column errors
        }

        Schema::table('agent_profiles', function (Blueprint $table) {
            // Stores only the file PATH (e.g. "profile-pictures/xxx.jpg"),
            // never the actual image data — the real file lives on disk
            // (local storage now, easy to migrate to S3/Spaces later).
            $table->string('photo_path')->nullable()->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('agent_profiles', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
