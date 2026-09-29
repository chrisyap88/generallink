<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 19 Jul 2026 — per Chris: the Follow Up Reminder form now has a
// "Save" (private, unchanged) and a "Save and Send" action. Save and
// Send also fires a bell + email notification (via NotificationService)
// to whichever side isn't the person clicking it:
//   - If the agent who owns this customer is the one adding the
//     reminder, their whole upline chain (TL, GL, Admin) is notified —
//     "FYI, I've set a follow-up for this contact."
//   - If Admin (the only role that can reach a customer they don't
//     personally own, per the new visibility rule) adds a reminder,
//     the OWNING agent is notified instead — "Admin set a follow-up
//     reminder on your contact."
// This column just remembers whether that happened, so the reminder
// list can show a small "Sent" badge — it does not affect scope/
// permissions, purely a display flag.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_reminders', function (Blueprint $table) {
            $table->boolean('escalated')->default(false)->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('personal_reminders', function (Blueprint $table) {
            $table->dropColumn('escalated');
        });
    }
};
