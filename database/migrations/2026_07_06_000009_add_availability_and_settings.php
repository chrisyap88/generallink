<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------
        // Availability — only meaningful for role=ADMIN. Simple
        // 2-state (Available/On Leave), per confirmed decision, not
        // full working-hours tracking.
        // -----------------------------------------------------
        Schema::table('agents', function (Blueprint $table) {
            if (! Schema::hasColumn('agents', 'availability_status')) {
                $table->string('availability_status')->default('AVAILABLE')->after('department');
            }
            if (! Schema::hasColumn('agents', 'availability_return_date')) {
                $table->date('availability_return_date')->nullable()->after('availability_status');
            }
        });

        // -----------------------------------------------------
        // system_settings — general-purpose key-value store, starting
        // with the withdrawal-approval reminder/escalation durations
        // (configurable by Admin Director, never hardcoded — per
        // confirmed decision, since fixed durations would cause
        // problems during long holidays like Chinese New Year).
        // -----------------------------------------------------
        if (! Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->string('setting_key')->primary();
                $table->text('setting_value');
                $table->uuid('updated_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['availability_status', 'availability_return_date']);
        });
    }
};
