<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 29 Jul 2026 — EspoCRM integration, expanded scope (task #252). Per
// Chris's explicit request to sync "everything, not just customers" into
// EspoCRM, every GeneralLink customer/prospect now also gets a matching
// Contact record in EspoCRM, kept in sync both ways (create on
// first save, patched on every edit). Remembers the EspoCRM Contact id
// so updates/lookups don't create duplicates.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('espocrm_contact_id', 100)->nullable()->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('espocrm_contact_id');
        });
    }
};
