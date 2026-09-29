<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// -------------------------------------------------------
// NEW 19 Jul 2026 — per Chris: customer STATUS (Active, Prospect,
// Suspended, Withdrawn, ...) must be a fully user-configurable list,
// same code/description/is_active convention as reason_codes — not
// hardcoded in PHP. ACTIVE and PROSPECT are seeded as is_system=true
// because business logic keys off those two specific codes (a Prospect
// auto-converts to Active the moment a real Sales Transaction is
// submitted for them) — their CODE is locked, but description/active
// can still be edited, and Admin can freely add more statuses.
// -------------------------------------------------------
class CustomerStatusController extends Controller
{
    // REBUILT 22 Sep 2026 -- per Chris: no list screen may dump all
    // records by default. Nothing is queried or shown until Chris
    // actually types something into the search box and submits.
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $statuses = null;
        if ($q !== '') {
            $needle = '%'.$q.'%';
            $statuses = DB::table('customer_statuses')
                ->where(function ($qr) use ($needle) {
                    $qr->where('description', 'like', $needle)->orWhere('code', 'like', $needle);
                })
                ->orderByDesc('is_system')->orderBy('description')
                ->paginate(10)->withQueryString();
        }
        return view('masterfile.customer-statuses', compact('statuses'));
    }

    public function create()
    {
        return $this->edit(null);
    }

    public function edit(?string $id = null)
    {
        $status = $id ? DB::table('customer_statuses')->where('status_id', $id)->firstOrFail() : null;
        return view('masterfile.customer-status-edit', compact('status'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code'        => ['required', 'string', 'max:50', 'regex:/^[A-Z_]+$/', 'unique:customer_statuses,code'],
            'description' => ['required', 'string', 'max:100'],
        ], [
            'code.regex' => 'Code must be UPPERCASE_WITH_UNDERSCORES only (e.g. BLACKLISTED).',
        ]);

        DB::table('customer_statuses')->insert([
            'status_id'   => (string) Str::uuid(),
            'code'        => strtoupper($request->code),
            'description' => $request->description,
            'is_active'   => true,
            'is_system'   => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return redirect()->route('admin.masterfile.customer-statuses')->with('success', 'Customer Status created successfully.');
    }

    public function update(Request $request, string $id)
    {
        $status = DB::table('customer_statuses')->where('status_id', $id)->firstOrFail();

        $request->validate([
            'description' => ['required', 'string', 'max:100'],
            'is_active'   => ['required', 'in:0,1'],
        ]);

        // System rows (ACTIVE/PROSPECT/...) can have their description and
        // active flag edited, but never their code — business logic
        // depends on the exact code matching.
        DB::table('customer_statuses')->where('status_id', $id)->update([
            'description' => $request->description,
            'is_active'   => $request->is_active,
            'updated_at'  => now(),
        ]);

        return redirect()->route('admin.masterfile.customer-statuses')->with('success', 'Customer Status updated successfully.');
    }

    public function destroy(string $id)
    {
        $status = DB::table('customer_statuses')->where('status_id', $id)->firstOrFail();
        abort_if($status->is_system, 403, 'This is a system status and cannot be deleted.');
        abort_if(DB::table('customers')->where('status_id', $id)->exists(), 422, 'Cannot delete — customers are currently assigned this status.');

        DB::table('customer_statuses')->where('status_id', $id)->delete();

        return redirect()->route('admin.masterfile.customer-statuses')->with('success', 'Customer Status removed.');
    }
}
