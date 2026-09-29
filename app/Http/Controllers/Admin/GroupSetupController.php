<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

// NEW 17 Aug 2026 — per Chris: "Group Set Up" used to be one long flat
// list (Organization Category, Rank Hierarchy, Rank Assignment,
// Promotion/Demotion, Breakaway Bonus, Group Name, Organization Rewards
// Groups, GL, TL, Introducer — all mixed together). Chris wants a real
// drill-down instead: Group Set Up shows exactly 3 choices — Direct
// Selling Group / Organization Rewards Group / Community & Business
// Enterprise Group — since each group type's setup is genuinely
// different (DSG = tier/rank based, ORG = management + affiliate/staff
// with optional rank, CBE = unlimited custom hierarchy levels). Picking
// one drills into that type's own programs, with a Prev link back —
// same one-page-per-level pattern already used for Network Tree, Vendor
// Approvals, etc.
class GroupSetupController extends Controller
{
    public function index()
    {
        return view('masterfile.group-setup.index');
    }

    public function dsg()
    {
        return view('masterfile.group-setup.dsg');
    }

    public function org()
    {
        return view('masterfile.group-setup.org');
    }

    // CBE's own program list is still being worked out with Chris —
    // placeholder landing for now so the 3-way choice is already
    // complete and consistent, without guessing at CBE's programs yet.
    public function cbe()
    {
        return view('masterfile.group-setup.cbe');
    }
}
