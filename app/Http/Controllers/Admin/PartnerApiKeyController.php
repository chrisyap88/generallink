<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// NEW 5 Aug 2026 — Outbound Partner API, key management. ADMIN-only:
// this is issuing GeneralLink's own credentials to outside systems, a
// meaningfully more sensitive action than an agent connecting their own
// BYOK integration, so it does not follow the per-agent pattern at all.
class PartnerApiKeyController extends Controller
{
    // Phase 1 — the only scope that actually does anything today. Add
    // more here ONLY once a real endpoint exists for it (same discipline
    // as the AI Guided Navigation goal list — never offer a scope that
    // doesn't correspond to a real, working endpoint).
    private const AVAILABLE_SCOPES = [
        'policy.read' => 'Read-only: look up a policy\'s status by policy number',
    ];

    public function index()
    {
        $keys = DB::table('partner_api_keys')->orderByDesc('created_at')->paginate(10);
        return view('admin.partner-api.index', ['keys' => $keys, 'availableScopes' => self::AVAILABLE_SCOPES]);
    }

    public function create()
    {
        return view('admin.partner-api.create', ['availableScopes' => self::AVAILABLE_SCOPES]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'key_name' => 'required|string|max:150',
            'scopes' => 'required|array|min:1',
            'scopes.*' => 'in:' . implode(',', array_keys(self::AVAILABLE_SCOPES)),
        ]);

        $rawKey = 'gl_live_' . Str::random(40);
        $prefix = substr($rawKey, 0, 20);

        DB::table('partner_api_keys')->insert([
            'key_name' => $request->key_name,
            'key_prefix' => $prefix,
            'api_key_hash' => Hash::make($rawKey),
            'scopes' => json_encode(array_values($request->scopes)),
            'status' => 'ACTIVE',
            'created_by' => Auth::guard('agent')->user()->agent_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Shown exactly once — the hash is all that's kept from here on.
        return view('admin.partner-api.created', ['rawKey' => $rawKey, 'keyName' => $request->key_name]);
    }

    public function revoke(int $keyId)
    {
        DB::table('partner_api_keys')->where('key_id', $keyId)->update([
            'status' => 'REVOKED',
            'revoked_at' => now(),
            'updated_at' => now(),
        ]);
        return back()->with('success', 'Key revoked. Any system still using it will now be rejected immediately.');
    }
}
