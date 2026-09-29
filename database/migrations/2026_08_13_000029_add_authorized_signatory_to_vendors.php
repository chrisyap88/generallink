<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 13 Aug 2026 — per Chris: "which vendor email address? contact 1
// or 2 or 3? you need to add another contact as authorized Director
// name destination, email address hp... you suggest and make sure
// dont make the screen truncated or scroll."
//
// Contact 1 already captures name + designation + phone + email
// (pic_name/pic_designation/pic_phone/pic_email) and is already the
// vendor's login identity — for most vendors the same person who
// registers IS the person authorised to sign. So rather than a whole
// new always-visible 4th contact block (extra screen real estate on an
// already-full Step 1), these columns are an OPTIONAL override: null by
// default (meaning "Contact 1 is the authorised signatory"), only
// filled in when the vendor explicitly says someone else — e.g. the
// actual company Director — will be the one to digitally accept the
// GLADE Vendor Registration Activation Agreement via Email OTP. See
// Vendor::authorizedSignatory() for the single place that resolves
// "who actually signs" from these columns.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('authorized_signatory_name', 150)->nullable()->after('contact3_email');
            $table->string('authorized_signatory_designation', 100)->nullable()->after('authorized_signatory_name');
            $table->string('authorized_signatory_email', 150)->nullable()->after('authorized_signatory_designation');
            $table->string('authorized_signatory_phone', 30)->nullable()->after('authorized_signatory_email');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['authorized_signatory_name', 'authorized_signatory_designation', 'authorized_signatory_email', 'authorized_signatory_phone']);
        });
    }
};
