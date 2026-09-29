<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 27 Aug 2026 — per Chris (Task #233): "GLADE membership tier admin
// config screen." Edits the GLADE Public Model catalog tiers
// (cbe_glade_membership_tiers) — name, max users, annual fee, active
// flag. Seeded with sensible defaults (see migration
// 2026_08_27_000013) but every figure is Admin-editable, nothing
// hardcoded.
//
// CHANGED 24 Sep 2026 -- per Chris: "why you call pending approval? it
// the membership fees tier offering master file, it has nothing to do
// with pending approval or active tier." Removed the Pending
// Approvals / Active Tiers tabs and the approve/reject two-person
// control entirely — this screen is now the tier CATALOG only (add /
// edit tier definitions). Assigning a tier to a specific CBE group
// happens on the Group Name edit screen (GroupLabelController::
// syncGladeTiers), which now activates a tier link immediately since
// Chris is the sole Admin and a second approval step doesn't match
// his workflow.
class AdminGladeTierController extends Controller
{
    // CHANGED 24 Sep 2026 -- per Chris: server-side pagination here was
    // clipping Enterprise/Community off entirely on a smaller browser
    // window (overflow:hidden hides whatever doesn't fit -- it doesn't
    // scroll to it, it just disappears). The tier catalog is small
    // master data (a handful of tiers, never hundreds), so it's
    // fetched in full here and the VIEW does its own screen-fitting
    // client-side pagination in JS, which measures the real available
    // height and never hides a row permanently.
    public function index(Request $request)
    {
        $tiers = DB::table('cbe_glade_membership_tiers')->orderBy('sort_order')->get();

        return view('admin.glade-tiers.index', compact('tiers'));
    }

    // Carries pagination state through a redirect (add / edit), so the
    // Admin lands back on the same page instead of resetting to the top.
    private function redirectBack(Request $request): \Illuminate\Http\RedirectResponse
    {
        $params = $request->only(['tier_page']);

        return redirect()->route('admin.glade-tiers.index', $params);
    }

    // NEW 11 Sep 2026 — per Chris: "why only offer this few type? what
    // if new type introduce? you should not hardcode." The catalog was
    // already fully DB-driven (nothing hardcoded in PHP), but there was
    // genuinely no screen to ADD a brand-new tier — only edit the 6
    // seeded ones. This closes that gap: any Admin can add a new tier
    // here at any time, no code change or deployment needed.
    public function store(Request $request)
    {
        $name = trim((string) $request->get('tier_name'));
        if ($name === '') {
            return back()->withErrors(['new_tier_name' => __('admin_glade_tiers.err_name_required')])->withInput();
        }

        // tier_code is an internal stable identifier (e.g. for future
        // code that needs to special-case a tier) — auto-derived from
        // the name, uniqued the same way group slugs already are
        // elsewhere in this app.
        $baseCode = strtoupper((string) Str::slug($name, '_'));
        $code = $baseCode;
        $i = 1;
        while (DB::table('cbe_glade_membership_tiers')->where('tier_code', $code)->exists()) {
            $i++;
            $code = $baseCode . '_' . $i;
        }

        $maxUsers = $request->get('max_users');
        $fee = $request->get('annual_fee');
        $isCustom = $request->boolean('is_custom_quotation');
        $nextSort = 1 + (int) DB::table('cbe_glade_membership_tiers')->max('sort_order');

        DB::table('cbe_glade_membership_tiers')->insert([
            'tier_id' => (string) Str::uuid(),
            'tier_code' => $code,
            'tier_name' => $name,
            'max_users' => $maxUsers !== '' && $maxUsers !== null ? (int) $maxUsers : null,
            'annual_fee' => (! $isCustom && $fee !== '' && $fee !== null) ? (float) $fee : null,
            'is_custom_quotation' => $isCustom,
            'available_for' => null,
            'is_active' => true,
            'sort_order' => $nextSort,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->redirectBack($request)->with('cbe_tier_saved', $name);
    }

    // NEW 24 Sep 2026 -- per Chris: "why the save button appear in every
    // row?" -- a single form covering every tier row, one "Save All"
    // button. Fields come in as tiers[<tier_id>][field], one array
    // entry per row; any row whose name is left blank is skipped rather
    // than erroring the whole batch, since a still-loading "Add Tier"
    // row should never be able to wipe out an existing tier's name.
    public function updateAllTiers(Request $request)
    {
        $rows = $request->input('tiers', []);

        foreach ($rows as $tierId => $data) {
            $tier = DB::table('cbe_glade_membership_tiers')->where('tier_id', $tierId)->first();
            if (! $tier) {
                continue;
            }

            $name = trim((string) ($data['tier_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $maxUsers = $data['max_users'] ?? null;
            $fee = $data['annual_fee'] ?? null;
            $isCustom = ! empty($data['is_custom_quotation']);

            DB::table('cbe_glade_membership_tiers')->where('tier_id', $tierId)->update([
                'tier_name' => $name,
                'max_users' => ($maxUsers !== '' && $maxUsers !== null) ? (int) $maxUsers : null,
                'annual_fee' => (! $isCustom && $fee !== '' && $fee !== null) ? (float) $fee : null,
                'is_custom_quotation' => $isCustom,
                'is_active' => ! empty($data['is_active']),
                'updated_at' => now(),
            ]);
        }

        return $this->redirectBack($request)->with('cbe_tier_saved', __('admin_glade_tiers.saved'));
    }
}
