<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 9 Aug 2026 — per Chris: registration must collect up to 3 contact
// persons (name, designation, phone, email each) with Contact 1
// compulsory. Contact 1 reuses the existing pic_name/pic_phone columns
// (just gains a designation + its own email); Contacts 2 and 3 are new,
// fully optional.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('pic_designation', 100)->nullable()->after('pic_name');
            $table->string('pic_email', 200)->nullable()->after('pic_phone');

            $table->string('contact2_name', 200)->nullable()->after('pic_email');
            $table->string('contact2_designation', 100)->nullable()->after('contact2_name');
            $table->string('contact2_phone', 30)->nullable()->after('contact2_designation');
            $table->string('contact2_email', 200)->nullable()->after('contact2_phone');

            $table->string('contact3_name', 200)->nullable()->after('contact2_email');
            $table->string('contact3_designation', 100)->nullable()->after('contact3_name');
            $table->string('contact3_phone', 30)->nullable()->after('contact3_designation');
            $table->string('contact3_email', 200)->nullable()->after('contact3_phone');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn([
                'pic_designation', 'pic_email',
                'contact2_name', 'contact2_designation', 'contact2_phone', 'contact2_email',
                'contact3_name', 'contact3_designation', 'contact3_phone', 'contact3_email',
            ]);
        });
    }
};
