<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 22 Jul 2026 — per Chris: TL and Introducer logins may set a
// preferred language (English / Chinese / Malay). GL and Admin are
// deliberately excluded — this column exists on every agent row for
// simplicity, but the UI to set it, and the translation logic that
// reads it, only ever engage for TEAM_LEADER and INTRODUCER roles
// (see App\Services\LanguageService::effectiveLanguage()). Default
// 'EN' so nothing changes for anyone until they actively choose
// otherwise.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->enum('preferred_language', ['EN', 'ZH', 'MS'])->default('EN')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('preferred_language');
        });
    }
};
