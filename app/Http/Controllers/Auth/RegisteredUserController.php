<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

// SUPERSEDED — this early draft of registration was replaced by
// AuthController::register() (which correctly encrypts NRIC and bank
// details, per Crypt::encrypt() usage there). Confirmed unreferenced
// by any route in routes/web.php.
//
// GUTTED 22 Jul 2026 during a security hardening pass — this class
// previously stored NRIC and bank account numbers in PLAIN TEXT
// (`'nric_encrypted' => $request->nric, // TODO: encrypt in production`),
// which is exactly the kind of landmine a security audit should catch
// even when the code path is dead: if anyone ever re-wired this
// controller into a route without noticing that comment, it would
// silently store sensitive PII unencrypted. Left in place only as an
// empty stub (rather than deleted) because this environment can't
// delete files — see other SUPERSEDED stubs in this codebase for the
// same reasoning (e.g. Admin\EnquiryController).
class RegisteredUserController extends Controller
{
}
