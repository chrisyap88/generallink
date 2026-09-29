<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// -------------------------------------------------------
// NEW 19 Jul 2026 — per Chris: customer TYPE is an optional, fully
// user-configurable demographic/segment tag (VIP, Expatriate,
// Government Servant, Army, Professional, or anything else Admin adds)
// — separate from customer STATUS (see CustomerStatusController). No
// business logic depends on any specific type code, so nothing here is
// system-protected.
// -------------------------------------------------------
class CustomerTypeController extends Controller
{
    // REBUILT 22 Sep 2026 -- per Chris: no list screen may dump all
    // records by default. Nothing is queried or shown until Chris
    // actually types something into the search box and submits.
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $types = null;
        if ($q !== '') {
            $needle = '%'.$q.'%';
            $types = DB::table('customer_types')
                ->where(function ($qr) use ($needle) {
                    $qr->where('description', 'like', $needle)->orWhere('code', 'like', $needle);
                })
                ->orderBy('description')
                ->paginate(10)->withQueryString();
        }
        return view('masterfile.customer-types', compact('types'));
    }

    public function create()
    {
        return $this->edit(null);
    }

    public function edit(?string $id = null)
    {
        $type = $id ? DB::table('customer_types')->where('type_id', $id)->firstOrFail() : null;
        return view('masterfile.customer-type-edit', compact('type'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code'        => ['required', 'string', 'max:50', 'regex:/^[A-Z_]+$/', 'unique:customer_types,code'],
            'description' => ['required', 'string', 'max:100'],
        ], [
            'code.regex' => 'Code must be UPPERCASE_WITH_UNDERSCORES only (e.g. GOVERNMENT_SERVANT).',
        ]);

        DB::table('customer_types')->insert([
            'type_id'     => (string) Str::uuid(),
            'code'        => strtoupper($request->code),
            'description' => $request->description,
            'is_active'   => true,
            'is_system'   => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return redirect()->route('admin.masterfile.customer-types')->with('success', 'Customer Type created successfully.');
    }

    public function update(Request $request, string $id)
    {
        DB::table('customer_types')->where('type_id', $id)->firstOrFail();

        $request->validate([
            'description' => ['required', 'string', 'max:100'],
            'is_active'   => ['required', 'in:0,1'],
        ]);

        DB::table('customer_types')->where('type_id', $id)->update([
            'description' => $request->description,
            'is_active'   => $request->is_active,
            'updated_at'  => now(),
        ]);

        return redirect()->route('admin.masterfile.customer-types')->with('success', 'Customer Type updated successfully.');
    }

    public function destroy(string $id)
    {
        $type = DB::table('customer_types')->where('type_id', $id)->firstOrFail();
        abort_if(DB::table('customers')->where('customer_type_id', $id)->exists(), 422, 'Cannot delete — customers are currently assigned this type.');

        DB::table('customer_types')->where('type_id', $id)->delete();

        return redirect()->route('admin.masterfile.customer-types')->with('success', 'Customer Type removed.');
    }
}
