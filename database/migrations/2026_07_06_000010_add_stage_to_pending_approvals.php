<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_approvals', function (Blueprint $table) {
            if (! Schema::hasColumn('pending_approvals', 'stage')) {
                $table->unsignedTinyInteger('stage')->default(1)->after('status');
            }
            if (! Schema::hasColumn('pending_approvals', 'parent_approval_id')) {
                $table->uuid('parent_approval_id')->nullable()->after('stage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pending_approvals', function (Blueprint $table) {
            $table->dropColumn(['stage', 'parent_approval_id']);
        });
    }
};
