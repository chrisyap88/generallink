<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__."/../routes/web.php",
        // NEW 18 Jul 2026 — api group added for EmailIngestionController's
        // inbound webhook: the 'api' middleware group has no CSRF check
        // (unlike 'web'), which an external mail-provider webhook POST
        // could never satisfy anyway.
        api: __DIR__."/../routes/api.php",
        commands: __DIR__."/../routes/console.php",
        health: "/up",
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            "role" => \App\Http\Middleware\RoleMiddleware::class,
            // NEW 5 Aug 2026 — Outbound Partner API: authenticates outside
            // systems (insurance vendor, hotel PMS, customer ERP/POS)
            // calling INTO GeneralLink with a GeneralLink-issued key.
            "partner.auth" => \App\Http\Middleware\PartnerApiAuth::class,
            // NEW 13 Aug 2026 — Vendor Onboarding restricted-access
            // design: locks a RESTRICTED-status vendor down to their
            // application status screen only, everywhere else in the
            // vendor portal route group.
            "vendor.restrict" => \App\Http\Middleware\RestrictVendorPortalAccess::class,
            // NEW 11 Sep 2026 (Task #413 follow-up) — per Chris's decision:
            // admits platform Admin OR an agent with an active
            // cbe_node_officers row, for the Entity Maintenance
            // create/store routes an officer's sidebar link already
            // pointed at.
            "cbe.officer.or.admin" => \App\Http\Middleware\RequireAdminOrCbeOfficer::class,
            // NEW 24 Sep 2026 -- forces every Admin screen to render
            // with one consistent sidebar (see the middleware class
            // itself for the full story on why this was needed).
            "glade.sidebar" => \App\Http\Middleware\EnsureGladeSidebar::class,
        ]);
        // Tell Laravel auth middleware to redirect to our custom login route
        $middleware->redirectGuestsTo(fn() => route('auth.login'));

        // NEW 22 Jul 2026 — sets the active display language for
        // TL/Introducer logins (GL/Admin always English) on every web
        // request, so __() calls anywhere in the app render correctly
        // without each route group needing its own setup.
        $middleware->web(append: [
            \App\Http\Middleware\SetAgentLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
