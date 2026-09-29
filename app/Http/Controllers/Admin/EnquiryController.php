<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

// SUPERSEDED 21 Jul 2026 — this was Admin's half of the old
// agent-to-Admin-only "Enquiries" feature. Per Chris, that was rebuilt
// into hierarchy-wide "Help Desk" messaging (any agent to their own
// upline/downline, Admin included as just another party) — see
// App\Http\Controllers\Shared\HelpDeskController, which now serves
// every role, Admin included. No routes reference this class anymore;
// left in place (rather than deleted) only because this environment
// can't delete files.
class EnquiryController extends Controller
{
}
