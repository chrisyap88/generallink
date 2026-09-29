<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 14 Aug 2026 — per Chris: "there must be a program to retrieve all
// past email OTP records to comply the Malaysia's Electronic Commerce
// Act 2006." vendor_agreement_acceptances (the table built earlier
// today) is a single MUTABLE row per vendor — re-sending an OTP
// overwrites otp_code/otp_sent_at/otp_expires_at, so if a vendor
// requested the code 3 times, only the LAST request survives. That's
// fine for running the flow, but not good enough as legal evidence: an
// authority or court may reasonably ask "show every code you ever sent
// this person and every attempt they made," not just the final
// successful one.
//
// This table is append-only and never updated or overwritten — one row
// per event, forever. Deliberately does NOT store the actual OTP digits
// (sent or attempted) — storing real one-time codes at rest is a
// needless security risk and isn't what's legally required; what
// matters for evidence is WHEN a code was sent, WHEN/whether it was
// verified, and from where — all of which this captures.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendor_agreement_otp_log')) {
            return; // already exists — avoids duplicate-table errors
        }

        Schema::create('vendor_agreement_otp_log', function (Blueprint $table) {
            $table->uuid('log_id')->primary();
            $table->uuid('vendor_id');
            $table->uuid('acceptance_id')->nullable(); // vendor_agreement_acceptances.acceptance_id, nullable in case the parent row is ever missing

            // OTP_SENT, OTP_VERIFY_SUCCESS, OTP_VERIFY_FAILED,
            // OTP_VERIFY_EXPIRED, OTP_VERIFY_TOO_MANY_ATTEMPTS
            $table->string('event_type', 40);

            $table->string('recipient_email', 200)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('device', 500)->nullable();
            $table->string('note', 255)->nullable(); // e.g. "5th consecutive failed attempt"

            $table->timestamp('created_at')->nullable();

            $table->index(['vendor_id', 'created_at']);
            $table->index('acceptance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_agreement_otp_log');
    }
};
