<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

// NEW 5 Aug 2026 — Outbound Partner API. Every request from an outside
// system (insurance vendor, hotel PMS, customer ERP/POS) must present
// `Authorization: Bearer <key>`. This middleware checks the key against
// the hashed partner_api_keys table, confirms it's ACTIVE, confirms it
// has the scope this specific route requires, and — if it passes —
// stamps $request->partnerKey so the controller knows who's calling
// (for logging) without ever seeing the raw key again.
class PartnerApiAuth
{
    public function handle(Request $request, Closure $next, string $requiredScope)
    {
        $header = $request->header('Authorization', '');
        if (!str_starts_with($header, 'Bearer ')) {
            return response()->json(['error' => 'Missing Authorization: Bearer <key> header.'], 401);
        }

        $rawKey = trim(substr($header, 7));
        if ($rawKey === '' || !str_starts_with($rawKey, 'gl_live_')) {
            return response()->json(['error' => 'Malformed API key.'], 401);
        }

        // The prefix is stored in the clear specifically so we can find
        // the ONE candidate row cheaply, then bcrypt-check only that row
        // — never loop-checking every key in the table against a hash.
        $prefix = substr($rawKey, 0, 20);
        $row = DB::table('partner_api_keys')->where('key_prefix', $prefix)->first();

        if (!$row || !Hash::check($rawKey, $row->api_key_hash)) {
            Log::warning('[PartnerApi] rejected — key not found or hash mismatch', ['prefix' => $prefix, 'ip' => $request->ip()]);
            return response()->json(['error' => 'Invalid API key.'], 401);
        }

        if ($row->status !== 'ACTIVE') {
            Log::warning('[PartnerApi] rejected — key not active', ['key_id' => $row->key_id, 'status' => $row->status]);
            return response()->json(['error' => 'This API key has been revoked.'], 403);
        }

        $scopes = json_decode($row->scopes, true) ?: [];
        if (!in_array($requiredScope, $scopes, true)) {
            Log::warning('[PartnerApi] rejected — missing scope', ['key_id' => $row->key_id, 'required' => $requiredScope, 'has' => $scopes]);
            return response()->json(['error' => "This API key does not have the '{$requiredScope}' scope."], 403);
        }

        DB::table('partner_api_keys')->where('key_id', $row->key_id)->update(['last_used_at' => now()]);
        Log::info('[PartnerApi] authenticated request', ['key_id' => $row->key_id, 'key_name' => $row->key_name, 'route' => $request->path()]);

        $request->attributes->set('partnerKeyId', $row->key_id);
        $request->attributes->set('partnerKeyName', $row->key_name);

        return $next($request);
    }
}
