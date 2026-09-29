<?php

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

// NEW 8 Aug 2026 — sends the "your documents were approved — verify your
// email and set your password" link, shared by both ways a vendor login
// gets created: self-registration (after Admin approves the SSM/company
// documents) and Admin creating a login directly. Visiting the link IS
// the email verification (proves the vendor controls that inbox), then
// they set their own password on the same page — same
// "shown once, self-set" security posture as everywhere else in
// GeneralLink, no temporary passwords change hands.
class VendorVerificationMailService
{
    public function send(Vendor $vendor): void
    {
        $token = Str::random(64);
        $vendor->update(['email_verification_token' => $token, 'login_status' => 'AWAITING_PASSWORD']);

        $link = route('vendor.set-password.show', $token);
        $logoUrl = url('/images/generallink-logo.jpeg');

        $html = <<<HTML
<div style="font-family:'Segoe UI',Arial,sans-serif; max-width:480px; margin:0 auto; background:#f4f9fb;">
    <div style="background:linear-gradient(135deg,#1B9AE4,#0D5A8E); padding:16px; text-align:center; border-radius:8px 8px 0 0;">
        <img src="{$logoUrl}" alt="GeneralLink" style="max-height:34px; border-radius:6px; margin-bottom:6px;">
        <div style="color:#fff; font-size:14px; font-weight:700;">Vendor Account Approved</div>
    </div>
    <div style="background:#fff; padding:18px 16px; border-radius:0 0 8px 8px;">
        <p style="font-size:12px; color:#1a1a1a; margin:0 0 8px;">Dear {$vendor->vendor_name},</p>
        <p style="font-size:11px; color:#374151; line-height:1.4; margin:0 0 12px;">
            Your GeneralLink Vendor Portal account has been reviewed and approved. Click below to verify this email address and set your own password — you'll be able to sign in right after.
        </p>
        <p style="font-size:11px; color:#1565C0; background:#EFF6FF; border-radius:5px; padding:6px 10px; margin:0 0 12px; font-weight:600;">
            Your login ID is this email address: {$vendor->vendor_email}
        </p>
        <div style="text-align:center; margin:16px 0;">
            <a href="{$link}" style="background:#1565C0; color:#fff; text-decoration:none; padding:9px 24px; border-radius:6px; font-size:12px; font-weight:600; display:inline-block;">Verify Email &amp; Set Password</a>
        </div>
        <div style="background:#f7fdff; border-radius:5px; padding:6px 8px; font-size:9px; color:#6b7280; word-break:break-all;">
            If the button doesn't work, copy and paste this link:<br>
            <span style="color:#1565C0;">{$link}</span>
        </div>
        <p style="font-size:9.5px; color:#92400e; background:#fff8e1; border-radius:5px; padding:6px 10px; margin-top:12px;">
            If you did not expect this email, please disregard it.
        </p>
    </div>
</div>
HTML;

        try {
            Mail::html($html, function ($message) use ($vendor) {
                $message->to($vendor->vendor_email)->subject('GeneralLink — Your Vendor Account is Approved');
            });
        } catch (\Exception $e) {
            Log::warning('VendorVerificationMailService: send failed (account still approved): ' . $e->getMessage());
        }
    }
}
