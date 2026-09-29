<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// NEW 14 Aug 2026 (3rd pass) — per Chris's exact SOP: "I say only 2
// approved will do. finance or sales admin approve then admin director
// approve... Finance and sales admin cannot send confirmation letter
// ONLY admin director can send." The OLD "how many different admins
// must click Confirm & Send" counter (vendor_approval_required_count,
// set to 2 back on 12 Aug 2026 before the Sales/Finance/Director gate
// existed) is now redundant with — and was actively conflicting with —
// the new gate. Setting it to 1 so Director's single click (which now
// performs the send directly, see approveDepartment()) fully finalizes
// in one step, with no separate second admin click required on top.
//
// Also resets the one test vendor's stale approved_by_1/approved_at_1
// left over from testing the OLD 2-click flow before this fix — without
// this, the new single-click Director flow would incorrectly treat that
// vendor as "already partially approved" and block Director from
// finishing. New vendors going forward never hit this since they'll
// never sit in a partial state under the new flow.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'vendor_approval_required_count'],
            ['setting_value' => 1, 'updated_at' => now()]
        );

        DB::table('vendors')->where('vendor_email', 'chrisyap@mybbs.com.my')->update([
            'approved_by_1' => null,
            'approved_at_1' => null,
            'approved_by_2' => null,
            'approved_at_2' => null,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'vendor_approval_required_count'],
            ['setting_value' => 2, 'updated_at' => now()]
        );
        // Not reversing the test vendor reset — original values aren't worth recovering.
    }
};
