<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// NEW 13 Aug 2026 — per Chris's restricted-access design for self-
// registered vendors, confirmed: a vendor gets a real, logged-in but
// LOCKED-DOWN account the moment every mandatory document is verified
// (not at raw registration — that would undo the existing anti-fraud
// rule that no login exists until documents are checked by Admin — and
// not only at final approval either). New middle state:
//   PENDING (documents awaiting review, no password yet)
//   -> AWAITING_PASSWORD (all mandatory docs verified, vendor emailed a
//      verify-email-and-set-password link)
//   -> RESTRICTED (password set — vendor can log in to a locked-down
//      view: application status, communication thread, document
//      upload — full Vendor Dashboard still hidden)
//   -> ACTIVE (Admin's final 1st/2nd approval — full dashboard unlocked)
// Legacy vendors with no entity_type checklist skip RESTRICTED entirely
// (same PENDING -> AWAITING_PASSWORD -> ACTIVE path as before), and
// Admin-created logins (Master File Maintenance "Create Login") also go
// straight to ACTIVE, unchanged — see Vendor::authorizedSignatory()-
// style branching in VendorAuthController::setPassword().
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE vendors MODIFY login_status ENUM('NONE','PENDING','AWAITING_PASSWORD','RESTRICTED','ACTIVE','REJECTED') DEFAULT 'NONE'");
    }

    public function down(): void
    {
        DB::statement("UPDATE vendors SET login_status = 'PENDING' WHERE login_status = 'RESTRICTED'");
        DB::statement("ALTER TABLE vendors MODIFY login_status ENUM('NONE','PENDING','AWAITING_PASSWORD','ACTIVE','REJECTED') DEFAULT 'NONE'");
    }
};
