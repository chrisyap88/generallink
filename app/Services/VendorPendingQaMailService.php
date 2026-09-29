<?php

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

// NEW 12 Aug 2026 — per Chris: "how admin communicate with new vendor
// before approval, means Q&A and the communication history log?" A
// vendor has no login before Admin approves them, so there was
// previously no in-app way to message them. This mirrors
// VendorVerificationMailService's exact token pattern (a plain
// unguessable Str::random(64) token, no expiry — same trust model
// already used for the "verify email + set password" link) but points
// at a lightweight, no-login Q&A thread page instead. The token is
// generated once per vendor and reused for every message after that, so
// the vendor can always return to the same conversation link throughout
// their whole pending review.
class VendorPendingQaMailService
{
    /** Ensures the vendor has a qa_access_token, generating one if this is the first message, then emails them the thread link with the new message included. */
    public function notify(Vendor $vendor, string $messageBody): void
    {
        if (empty($vendor->qa_access_token)) {
            $vendor->update(['qa_access_token' => Str::random(64)]);
            $vendor->refresh();
        }

        $link = route('vendor.pending-qa.show', $vendor->qa_access_token);
        $logoUrl = url('/images/generallink-logo.jpeg');
        $safeMessage = nl2br(e($messageBody));

        $html = <<<HTML
<div style="font-family:'Segoe UI',Arial,sans-serif; max-width:480px; margin:0 auto; background:#f4f9fb;">
    <div style="background:linear-gradient(135deg,#1B9AE4,#0D5A8E); padding:16px; text-align:center; border-radius:8px 8px 0 0;">
        <img src="{$logoUrl}" alt="GeneralLink" style="max-height:34px; border-radius:6px; margin-bottom:6px;">
        <div style="color:#fff; font-size:14px; font-weight:700;">Message About Your Vendor Registration</div>
    </div>
    <div style="background:#fff; padding:18px 16px; border-radius:0 0 8px 8px;">
        <p style="font-size:12px; color:#1a1a1a; margin:0 0 8px;">Dear {$vendor->vendor_name},</p>
        <p style="font-size:11px; color:#374151; line-height:1.4; margin:0 0 10px;">
            GeneralLink has a message about your pending vendor registration:
        </p>
        <div style="background:#f7fdff; border-left:3px solid #1565C0; border-radius:4px; padding:8px 10px; font-size:11px; color:#1a1a1a; margin:0 0 14px;">
            {$safeMessage}
        </div>
        <div style="text-align:center; margin:16px 0;">
            <a href="{$link}" style="background:#1565C0; color:#fff; text-decoration:none; padding:9px 24px; border-radius:6px; font-size:12px; font-weight:600; display:inline-block;">View &amp; Reply</a>
        </div>
        <div style="background:#f7fdff; border-radius:5px; padding:6px 8px; font-size:9px; color:#6b7280; word-break:break-all;">
            If the button doesn't work, copy and paste this link:<br>
            <span style="color:#1565C0;">{$link}</span>
        </div>
        <p style="font-size:9.5px; color:#92400e; background:#fff8e1; border-radius:5px; padding:6px 10px; margin-top:12px;">
            This link is private to your registration — please don't forward it. You do not need a password to reply; this link is enough.
        </p>
    </div>
</div>
HTML;

        try {
            Mail::html($html, function ($message) use ($vendor) {
                $message->to($vendor->vendor_email)->subject('GeneralLink — Message About Your Vendor Registration');
            });
        } catch (\Exception $e) {
            Log::warning('VendorPendingQaMailService: send failed (message still saved): ' . $e->getMessage());
        }
    }
}
