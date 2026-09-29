<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 27 Aug 2026 — per Chris: Events Overview drill-down needs to
// filter/group by event type (Seminar, Festival, Prayer, Anniversary,
// etc.) — cbe_events previously had no type field, only a free-text
// name/description.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_events', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_events', 'event_type')) {
                $table->enum('event_type', ['SEMINAR', 'FESTIVAL', 'PRAYER', 'ANNIVERSARY', 'CHARITY', 'MEETING', 'OTHER'])
                    ->default('OTHER')
                    ->after('event_name_zh');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_events', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_events', 'event_type')) {
                $table->dropColumn('event_type');
            }
        });
    }
};
