<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 14 Aug 2026 — per Chris: "build OTP-click flow and complete the
// entire approval process because i will login chrisyap@mybbs.com.my
// and click/type the OTP acceptance number." This is the record behind
// that flow — one row per vendor, matching exactly the evidence fields
// GLADE-Vendor-Registration-Activation-Agreement-DRAFT.pdf's Clause 10.4
// says GLADE will retain: accepting user's identity, registered email,
// IP address, device information, OTP verification timestamp,
// acceptance timestamp, and a document hash.
//
// otp_code is stored PLAIN, not hashed — deliberately: it's a 6-digit,
// single-use, 10-minute-expiry code with no other security value once
// used or expired, the same tradeoff most OTP implementations make. It
// is cleared (set null) the moment it's successfully verified so a used
// code can never be replayed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_agreement_acceptances', function (Blueprint $table) {
            $table->uuid('acceptance_id')->primary();
            $table->uuid('vendor_id')->unique(); // one active agreement record per vendor
            $table->string('agreement_number', 40)->nullable();
            $table->string('agreement_version', 20)->default('1.0');

            // OTP challenge, in progress until accepted
            $table->string('otp_code', 6)->nullable();
            $table->timestamp('otp_sent_at')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->timestamp('otp_verified_at')->nullable();

            // Recorded only once genuinely accepted
            $table->string('accepted_by_name', 200)->nullable();
            $table->string('accepted_by_email', 200)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('accepted_ip', 64)->nullable();
            $table->string('accepted_device', 500)->nullable();
            $table->string('document_hash', 64)->nullable(); // SHA-256 of the agreement PDF at time of acceptance

            $table->timestamps();

            $table->foreign('vendor_id')->references('vendor_id')->on('vendors')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_agreement_acceptances');
    }
};
