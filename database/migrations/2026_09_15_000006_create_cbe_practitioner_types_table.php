<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// NEW 15 Sep 2026 — per Chris: the appointment booking module needs a
// practitioner "type" (Sensei, Consultant, Legal Advisor, etc.) — same
// "never hardcode" master-file pattern as Appointment Terminology Types
// / Committee Positions / GLADE Tiers, so Admin can add a new type of
// practitioner at any time without any code change.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_practitioner_types')) {
            Schema::create('cbe_practitioner_types', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 50)->unique();
                $table->string('type_label', 150);
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });

            $now = now();
            \Illuminate\Support\Facades\DB::table('cbe_practitioner_types')->insert([
                ['id' => (string) Str::uuid(), 'code' => 'SENSEI', 'type_label' => 'Sensei', 'is_system' => true, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['id' => (string) Str::uuid(), 'code' => 'CONSULTANT', 'type_label' => 'Advisor', 'is_system' => true, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
                ['id' => (string) Str::uuid(), 'code' => 'LEGAL_ADVISOR', 'type_label' => 'Legal Advisor', 'is_system' => true, 'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_practitioner_types');
    }
};
