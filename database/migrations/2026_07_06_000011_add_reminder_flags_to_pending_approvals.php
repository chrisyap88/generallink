<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_approvals', function (Blueprint $table) {
            if (! Schema::hasColumn('pending_approvals', 'reminder_sent')) {
                $table->boolean('reminder_sent')->default(false)->after('parent_approval_id');
            }
            if (! Schema::hasColumn('pending_approvals', 'escalation_sent')) {
                $table->boolean('escalation_sent')->default(false)->after('reminder_sent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pending_approvals', function (Blueprint $table) {
            $table->dropColumn(['reminder_sent', 'escalation_sent']);
        });
    }
};
