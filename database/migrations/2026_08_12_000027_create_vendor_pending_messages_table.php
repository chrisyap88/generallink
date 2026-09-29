<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 12 Aug 2026 — per Chris: "how admin communicate with new vendor
// before approval, means Q&A and the communication history log?" A
// vendor has no login at all before Admin approves them (no password
// set yet — see VendorAuthController), so there was previously no way
// to message them in-app at all. This gives every pending vendor a
// simple 2-way message thread: Admin writes from the Pending Vendor
// Logins screen, the vendor reads/replies via a private emailed link
// (same "unguessable token" pattern already used for the vendor's own
// "verify email + set password" link — see vendors.email_verification_
// token) since they can't log in yet. Every message is kept, so this
// doubles as the communication history log Chris asked about.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            // Generated the first time Admin sends a message; reused for
            // every message after that (no per-message expiry — the
            // vendor should be able to keep returning to the same
            // conversation throughout their whole pending review, same
            // no-expiry choice already made for email_verification_token).
            $table->string('qa_access_token', 64)->nullable()->unique()->after('email_verification_token');
        });

        Schema::create('vendor_pending_messages', function (Blueprint $table) {
            $table->uuid('message_id')->primary();
            $table->uuid('vendor_id');
            $table->string('sender_type', 10); // ADMIN, VENDOR
            $table->uuid('sender_admin_id')->nullable(); // set when sender_type = ADMIN
            $table->text('message');
            $table->timestamps();

            $table->foreign('vendor_id')->references('vendor_id')->on('vendors')->cascadeOnDelete();
            $table->foreign('sender_admin_id')->references('agent_id')->on('agents')->nullOnDelete();
            $table->index(['vendor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_pending_messages');
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('qa_access_token');
        });
    }
};
