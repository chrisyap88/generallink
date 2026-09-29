<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------
        // Missing required address fields — caught during design
        // review. Every real agent needs these (already required for
        // normal registration); the original template was missing them.
        // -----------------------------------------------------
        Schema::table('batch_registration_records', function (Blueprint $table) {
            if (! Schema::hasColumn('batch_registration_records', 'address')) {
                $table->string('address')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('batch_registration_records', 'postcode')) {
                $table->string('postcode')->nullable()->after('address');
            }
            if (! Schema::hasColumn('batch_registration_records', 'city')) {
                $table->string('city')->nullable()->after('postcode');
            }
            if (! Schema::hasColumn('batch_registration_records', 'state')) {
                $table->string('state')->nullable()->after('city');
            }
        });

        // -----------------------------------------------------
        // Batch-level ownership — per the final confirmed design,
        // Admin declares "this whole file is for GL/TL/Introducer" plus
        // WHO (via QR/wildcard search, reusing the existing selection
        // engine) once at upload time — not per-row in the Excel file.
        // -----------------------------------------------------
        Schema::table('batch_registration_staging', function (Blueprint $table) {
            if (! Schema::hasColumn('batch_registration_staging', 'batch_type')) {
                $table->string('batch_type')->nullable()->after('filename'); // GL_BATCH, TL_BATCH, INTRODUCER_BATCH
            }
            if (! Schema::hasColumn('batch_registration_staging', 'owner_agent_id')) {
                $table->uuid('owner_agent_id')->nullable()->after('batch_type'); // NULL for GL_BATCH
            }
            if (! Schema::hasColumn('batch_registration_staging', 'group_label_id')) {
                $table->uuid('group_label_id')->nullable()->after('owner_agent_id'); // chosen at commit, GL_BATCH only
            }
        });
    }

    public function down(): void
    {
        Schema::table('batch_registration_staging', function (Blueprint $table) {
            $table->dropColumn(['batch_type', 'owner_agent_id', 'group_label_id']);
        });
        Schema::table('batch_registration_records', function (Blueprint $table) {
            $table->dropColumn(['address', 'postcode', 'city', 'state']);
        });
    }
};
