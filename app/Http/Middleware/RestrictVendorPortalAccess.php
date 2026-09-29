<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// NEW 13 Aug 2026 — per Chris's restricted-access design, confirmed:
// once a vendor's mandatory documents are verified (see
// VendorDocumentChecklistService::allMandatoryVerified()), they can log
// in with login_status = RESTRICTED — but must only see application
// status, the communication thread, and document upload; the full
// Vendor Dashboard (transactions, commission, rebate applications,
// content submission, etc.) stays locked until Admin's final approval
// flips them to ACTIVE. Applied to the whole auth:vendor route group in
// routes/web.php; the one screen a RESTRICTED vendor IS allowed to see
// is explicitly named here rather than guessed per-route, so a new
// route added to that group is locked down by default unless someone
// deliberately adds it to the allow-list.
class RestrictVendorPortalAccess
{
    private const ALLOWED_ROUTE_NAMES = [
        'vendor.application-status',
        'vendor.application-status.message',
        'vendor.application-status.attachment',
        'vendor.logout',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        $vendor = Auth::guard('vendor')->user();

        if ($vendor && $vendor->login_status === 'RESTRICTED' && !in_array($request->route()?->getName(), self::ALLOWED_ROUTE_NAMES, true)) {
            return redirect()->route('vendor.application-status')
                ->with('info', 'Your account is still awaiting final approval — you have access to your application status and messages for now.');
        }

        return $next($request);
    }
}
