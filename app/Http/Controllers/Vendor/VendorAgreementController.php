<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Admin\VendorLoginApprovalController;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Vendor;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * NEW 14 Aug 2026 — per Chris: "build OTP-click flow and complete the
 * entire approval process because i will login chrisyap@mybbs.com.my
 * and click/type the OTP acceptance number." Digital acceptance of the
 * GLADE Vendor Registration Activation Agreement, exactly as that
 * document's own Clause 10 describes: OTP sent to the vendor's
 * registered email, vendor types it in, then affirmatively clicks
 * "Accept Agreement" — recorded with the same evidence trail Clause
 * 10.4 promises (identity, email, IP, device, OTP time, acceptance
 * time, document hash).
 *
 * Scope note left for Chris: this does NOT change when a vendor gets
 * full Vendor Dashboard access — that still happens the moment Admin
 * clicks Approve (see VendorLoginApprovalController::approve()), same
 * as before this was built. The paper agreement's Clause 4 frames
 * account activation as happening only AFTER acceptance + payment; the
 * built system currently activates first and asks for acceptance after.
 * Say the word if you'd rather gate full access behind acceptance
 * instead — that's a bigger follow-up change to the RESTRICTED/ACTIVE
 * flow, not done here.
 */
class VendorAgreementController extends Controller
{
    private function vendor(): Vendor
    {
        return auth('vendor')->user();
    }

    private function record(string $vendorId)
    {
        $row = DB::table('vendor_agreement_acceptances')->where('vendor_id', $vendorId)->first();
        if ($row) {
            return $row;
        }
        $id = (string) Str::uuid();
        DB::table('vendor_agreement_acceptances')->insert([
            'acceptance_id' => $id,
            'vendor_id' => $vendorId,
            'agreement_number' => $this->nextAgreementNumber(),
            'agreement_version' => '1.0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return DB::table('vendor_agreement_acceptances')->where('acceptance_id', $id)->first();
    }

    private function nextAgreementNumber(): string
    {
        $year = now()->format('Y');
        $count = DB::table('vendor_agreement_acceptances')->whereYear('created_at', $year)->count() + 1;
        return 'AGR-' . $year . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * NEW 14 Aug 2026 — per Chris: "there must be a program to retrieve
     * all past email OTP records to comply the Malaysia's Electronic
     * Commerce Act 2006." One append-only row per event — see the
     * vendor_agreement_otp_log migration for why this exists separately
     * from the mutable vendor_agreement_acceptances row.
     */
    private function logOtpEvent(string $vendorId, ?string $acceptanceId, string $eventType, ?string $recipientEmail, Request $request, ?string $note = null): void
    {
        try {
            DB::table('vendor_agreement_otp_log')->insert([
                'log_id' => (string) Str::uuid(),
                'vendor_id' => $vendorId,
                'acceptance_id' => $acceptanceId,
                'event_type' => $eventType,
                'recipient_email' => $recipientEmail,
                'ip_address' => $request->ip(),
                'device' => substr((string) $request->userAgent(), 0, 500),
                'note' => $note,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // A logging failure must never block the actual OTP flow —
            // but it should never happen silently either.
            Log::warning('VendorAgreementController::logOtpEvent failed: ' . $e->getMessage());
        }
    }

    /**
     * NEW 14 Aug 2026 — per Chris: "you have to push to 3 admins
     * automatically with notification or alert that fires. and carolyn
     * is informed also." Every ADMIN whose department is SALES, FINANCE,
     * or DIRECTOR gets a bell notification + email the moment a vendor
     * accepts (currently that's exactly finance@generallink.my,
     * sales@generallink.my, admin@generallink.my — but this reads live
     * from the department column, so it stays correct if that roster
     * ever changes). Carolyn picks this up automatically too — bell
     * notifications feed ProactiveAlertService's unread count, and a
     * dedicated block there (see that file) names the vendor
     * specifically rather than just a generic count.
     */
    private function notifyAdminsOfAcceptance(Vendor $vendor, object $acceptance): void
    {
        $admins = Agent::where('role', 'ADMIN')
            ->whereIn('department', ['SALES', 'FINANCE', 'DIRECTOR'])
            ->where('is_deleted', false)
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        $message = "{$vendor->vendor_name} has digitally accepted the GLADE Vendor Registration Activation Agreement.\n\n"
            . "Agreement No: {$acceptance->agreement_number}\n"
            . "Accepted By: {$acceptance->accepted_by_name} ({$acceptance->accepted_by_email})\n"
            . "Accepted At: " . \Carbon\Carbon::parse($acceptance->accepted_at)->format('d M Y, h:ia') . "\n"
            . "IP Address: {$acceptance->accepted_ip}\n\n"
            . "Full evidence record: " . route('admin.vendor-agreements.show', $acceptance->acceptance_id);

        app(NotificationService::class)->notify(
            $admins->all(),
            'VENDOR_AGREEMENT_ACCEPTED',
            'Registration Agreement Accepted — ' . $vendor->vendor_name,
            $message
        );
    }

    /** The agreement screen: preview + OTP request/verify, or the Acceptance Certificate if already done. */
    public function show()
    {
        $vendor = $this->vendor();
        $acceptance = $this->record($vendor->vendor_id);

        return view('vendor.agreement', compact('vendor', 'acceptance'));
    }

    /** Serves the same agreement PDF Admin's letter attaches, so the vendor can read the real document, not a re-typed copy. */
    public function file()
    {
        $path = base_path(VendorLoginApprovalController::AGREEMENT_FILE);
        if (!file_exists($path)) {
            abort(404, 'Agreement file not found on server.');
        }
        return response()->file($path);
    }

    /** Emails a fresh 6-digit OTP to the vendor's registered email, valid 10 minutes. */
    public function sendOtp(Request $request)
    {
        $vendor = $this->vendor();
        $acceptance = $this->record($vendor->vendor_id);

        if ($acceptance->accepted_at) {
            return back()->with('error', 'This agreement has already been accepted — nothing further to do.');
        }

        $otp = (string) random_int(100000, 999999);
        DB::table('vendor_agreement_acceptances')->where('acceptance_id', $acceptance->acceptance_id)->update([
            'otp_code' => $otp,
            'otp_sent_at' => now(),
            'otp_expires_at' => now()->addMinutes(10),
            'otp_attempts' => 0,
            'updated_at' => now(),
        ]);

        $toEmail = $vendor->pic_email ?: $vendor->vendor_email;
        try {
            Mail::html(
                '<p style="font-family:Arial,sans-serif; font-size:14px;">Your GLADE Vendor Registration Activation Agreement one-time verification code is:</p>'
                . '<p style="font-family:Arial,sans-serif; font-size:28px; font-weight:700; letter-spacing:6px; color:#0D5A8E;">' . $otp . '</p>'
                . '<p style="font-family:Arial,sans-serif; font-size:12px; color:#6b7280;">This code expires in 10 minutes. If you did not request this, you can ignore this email.</p>',
                function ($mail) use ($toEmail) {
                    $mail->to($toEmail)->subject('Your GLADE Agreement Verification Code');
                }
            );
        } catch (\Exception $e) {
            Log::warning('VendorAgreementController::sendOtp mail failed: ' . $e->getMessage());
            $this->logOtpEvent($vendor->vendor_id, $acceptance->acceptance_id, 'OTP_SENT', $toEmail, $request, 'Mail delivery failed: ' . $e->getMessage());
            return back()->with('error', 'Could not send the verification email — please try again or contact support.');
        }

        $this->logOtpEvent($vendor->vendor_id, $acceptance->acceptance_id, 'OTP_SENT', $toEmail, $request);

        return back()->with('success', 'A verification code has been emailed to ' . $toEmail . '. It expires in 10 minutes.');
    }

    /** Verifies the typed OTP and, if correct, records full acceptance with the Clause 10.4 evidence trail. */
    public function accept(Request $request)
    {
        $request->validate([
            'otp_code' => ['required', 'string', 'size:6'],
            'confirm' => ['accepted'],
        ]);

        $vendor = $this->vendor();
        $acceptance = $this->record($vendor->vendor_id);

        $toEmail = $vendor->pic_email ?: $vendor->vendor_email;

        if ($acceptance->accepted_at) {
            return back()->with('error', 'This agreement has already been accepted.');
        }
        if (!$acceptance->otp_code || !$acceptance->otp_expires_at || now()->greaterThan($acceptance->otp_expires_at)) {
            $this->logOtpEvent($vendor->vendor_id, $acceptance->acceptance_id, 'OTP_VERIFY_EXPIRED', $toEmail, $request);
            return back()->with('error', 'Your verification code has expired — please request a new one.');
        }
        if ($acceptance->otp_attempts >= 5) {
            $this->logOtpEvent($vendor->vendor_id, $acceptance->acceptance_id, 'OTP_VERIFY_TOO_MANY_ATTEMPTS', $toEmail, $request);
            return back()->with('error', 'Too many incorrect attempts — please request a new verification code.');
        }
        if (!hash_equals($acceptance->otp_code, $request->otp_code)) {
            DB::table('vendor_agreement_acceptances')->where('acceptance_id', $acceptance->acceptance_id)->increment('otp_attempts');
            $this->logOtpEvent($vendor->vendor_id, $acceptance->acceptance_id, 'OTP_VERIFY_FAILED', $toEmail, $request, 'Attempt #' . ($acceptance->otp_attempts + 1));
            return back()->with('error', 'That code is incorrect — please check your email and try again.');
        }

        $agreementPath = base_path(VendorLoginApprovalController::AGREEMENT_FILE);
        $hash = file_exists($agreementPath) ? hash_file('sha256', $agreementPath) : null;

        DB::table('vendor_agreement_acceptances')->where('acceptance_id', $acceptance->acceptance_id)->update([
            'otp_verified_at' => now(),
            'accepted_by_name' => $vendor->pic_name ?: $vendor->vendor_name,
            'accepted_by_email' => $toEmail,
            'accepted_at' => now(),
            'accepted_ip' => $request->ip(),
            'accepted_device' => substr((string) $request->userAgent(), 0, 500),
            'document_hash' => $hash,
            'otp_code' => null, // used — cleared so it can never be replayed
            'updated_at' => now(),
        ]);

        $this->logOtpEvent($vendor->vendor_id, $acceptance->acceptance_id, 'OTP_VERIFY_SUCCESS', $toEmail, $request);

        \App\Services\AuditService::logChange('vendor_agreement_acceptances', $acceptance->acceptance_id, 'VENDOR_AGREEMENT_ACCEPTED', null, ['vendor_id' => $vendor->vendor_id, 'accepted_by_email' => $toEmail], null);

        // NEW 14 Aug 2026 — per Chris: "pushes to 3 admins automatically
        // with notification or alert that fires. and carolyn is
        // informed also." Re-fetch the row fresh (the object in memory
        // above is now stale — this update just wrote to it).
        $freshAcceptance = DB::table('vendor_agreement_acceptances')->where('acceptance_id', $acceptance->acceptance_id)->first();
        $this->notifyAdminsOfAcceptance($vendor, $freshAcceptance);

        return redirect()->route('vendor.agreement')->with('success', 'Agreement accepted — thank you. Your Acceptance Certificate is below.');
    }
}
