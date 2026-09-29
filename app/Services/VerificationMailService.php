<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// -------------------------------------------------------
// NEW 15 Jul 2026 — single shared sender for the "verify your email"
// message, used by: public self-registration, Organization Rewards Group
// registration, and the Admin/GL/TL "Resend Verification" action.
// Previously this HTML was duplicated in two places and always showed
// the generic GeneralLink logo. Now it resolves the agent's own
// group_label branding (logo + name) the same way the set-password
// and verification-success pages already do, so Organization Rewards Group agents
// (e.g. PVATM) see their own logo in the verification email too —
// keeping Public and Special group identity consistent everywhere,
// per the master file policy.
// -------------------------------------------------------
class VerificationMailService
{
    public function resolveBranding(?Agent $agent): array
    {
        $logoUrl = url('/images/generallink-logo.jpeg');
        $groupName = 'GeneralLink';

        if ($agent && $agent->group_label_id) {
            $label = DB::table('group_labels')->where('group_label_id', $agent->group_label_id)->first();
            if ($label) {
                $groupName = $label->group_name;
                if ($label->logo_path) {
                    $logoUrl = url('/images/' . $label->logo_path);
                }
            }
        }

        return [$logoUrl, $groupName];
    }

    public function send(Agent $agent): void
    {
        $link = route('auth.verify-email', $agent->email_verification_token);
        $roleLabel = ucwords(strtolower(str_replace('_', ' ', $agent->role)));
        [$logoUrl, $groupName] = $this->resolveBranding($agent);
        $createdByName = $agent->created_by ? (Agent::find($agent->created_by)->full_name ?? 'GeneralLink Admin') : 'Self-Registered';
        $createdAt = $agent->created_at ? $agent->created_at->format('d M Y, h:i A') : now()->format('d M Y, h:i A');

        // UPDATED 16 Jul 2026 — rebuilt as a table-based two-column layout
        // (left = logo/brand/welcome, right = the letter) so the emailed
        // version visually matches the new left/right split verify-pending
        // web page. Email clients don't support flexbox/100vh, so this
        // uses a plain HTML table with inline styles + bgcolor fallbacks
        // (solid color instead of the web page's CSS gradient) for
        // compatibility across mail clients.
        $html = <<<HTML
<div style="font-family:'Segoe UI',Arial,sans-serif; max-width:600px; margin:0 auto; background:#ffffff;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
        <tr>
            <td width="36%" valign="top" align="center" bgcolor="#e0f7fa" style="background-color:#e0f7fa; padding:26px 10px;">
                <img src="{$logoUrl}" alt="{$groupName}" style="width:100%; max-width:180px; height:auto; border-radius:12px; margin-bottom:10px;"><br>
                <span style="font-family:Arial,sans-serif; font-weight:700; font-size:17px; color:#0D5A8E;">{$groupName}</span><br>
                <span style="font-style:italic; font-size:11.5px; color:#0D5A8E;">Smarter. More Efficient. Highly Transparent.</span><br>
                <span style="font-size:10.5px; color:#0D5A8E; display:inline-block; margin-top:10px; line-height:1.5;">Welcome aboard! You're almost ready to start your journey with {$groupName}.</span>

                <div style="background:#ffffff; border-radius:5px; padding:6px 8px; font-size:9px; color:#6b7280; margin-top:18px; text-align:left; word-break:break-all;">
                    If the button doesn't work, copy and paste this link into your browser:<br>
                    <span style="color:#1565C0;">{$link}</span>
                </div>
                <p style="font-size:10px; color:#374151; margin-top:10px; text-align:left;">
                    Kind Regards,<br>
                    <strong>{$groupName} Admin Director</strong>
                </p>
            </td>
            <td width="64%" valign="top" bgcolor="#ffffff" style="background-color:#ffffff; padding:20px 16px;">
                <div style="font-weight:700; font-size:15px; color:#0D5A8E; text-align:center; text-transform:uppercase; letter-spacing:1px; margin-bottom:12px;">Activate Your Account</div>
                <p style="font-size:12px; color:#1a1a1a; margin:0 0 8px;">Dear {$agent->full_name},</p>
                <p style="font-size:11px; color:#374151; line-height:1.4; margin:0 0 10px;">
                    Thank you for joining {$groupName} (part of the GeneralLink Digital Affiliate Ecosystem) as a <strong>{$roleLabel}</strong>.
                    Please verify your email address below to activate your account.
                </p>
                <p style="font-size:11px; color:#374151; line-height:1.4; margin:0 0 10px;">
                    Once your account is fully verified and activated, our system will automatically generate your personal QR code. Please download and save a copy on your phone or computer. You may use this QR code to log in to our Affiliate Ecosystem and renew your own car insurance, or share it with your family, friends, and community so they can conveniently renew theirs as well. Every successful renewal completed through your QR code will be recorded as part of your earning income, based on the applicable commission rate for that product.
                </p>
                <div style="background:#f0f9ff; border-radius:5px; padding:6px 10px; font-size:10px; color:#374151; margin-bottom:12px;">
                    <strong>Registered By:</strong> {$createdByName}<br>
                    <strong>Date/Time:</strong> {$createdAt}
                </div>
                <div style="text-align:center; margin:14px 0;">
                    <a href="{$link}" style="background:#1565C0; color:#fff; text-decoration:none; padding:9px 22px; border-radius:6px; font-size:12px; font-weight:600; display:inline-block;">Verify My Email Address</a>
                </div>
                <p style="font-size:9.5px; color:#92400e; background:#fff8e1; border-radius:5px; padding:6px 10px; margin-top:10px;">
                    🔒 Security Notice: this link expires in 24 hours. If you did not expect this email, please disregard it.
                </p>
            </td>
        </tr>
    </table>
    <div style="text-align:center; padding:8px; font-size:8.5px; color:#9ca3af; letter-spacing:.05em;">
        AI-POWERED &middot; MALAYSIA &middot; SOUTHEAST ASIA
    </div>
</div>
HTML;

        // Wrapped in try/catch — the account/token is already saved by
        // this point. A mail-sending hiccup should never crash the
        // calling flow (registration, resend, etc).
        try {
            Mail::html($html, function ($message) use ($agent, $groupName) {
                $message->to($agent->email)
                        ->subject("Welcome to {$groupName}! Please Verify Your Email");
            });
        } catch (\Exception $e) {
            Log::warning('Verification email failed to send (account still created): ' . $e->getMessage());
        }
    }
}
