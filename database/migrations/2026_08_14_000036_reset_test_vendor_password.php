<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// NEW 14 Aug 2026 — per Chris: "now i want to login chrisyap@mybbs.com.my
// what login i should use?" That email belongs to the vendor "My
// Broadband Solution" (vendor_id 3e7c4250-0e6b-47ff-87d8-43efd2f24125,
// login_status RESTRICTED), which already has a password — but it's
// self-set from earlier testing and Chris doesn't know it. Resetting it
// to Password@123 here, same known password as the admin test accounts,
// so he can log into the Vendor Portal and test the OTP/agreement
// acceptance flow.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('vendors')->where('vendor_email', 'chrisyap@mybbs.com.my')->update([
            'password_hash' => Hash::make('Password@123'),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // No-op — original password hash cannot be recovered.
    }
};
