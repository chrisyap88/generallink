<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\HubVaultService;
use App\Services\Integrations\IntegrationConnectorResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// NEW 4 Aug 2026, real per-agent encryption added 5 Aug 2026 — Integration
// Hub. Every action here is scoped to Auth::guard('agent')->user()->agent_id
// — one agent can never see or touch another agent's connections. Nothing
// here ever falls back to a shared/platform credential.
//
// Every screen behind /integrations is now gated by the agent's OWN Hub
// vault (see HubVaultService): first visit ever -> set up a Hub password;
// every new login session after that -> unlock with it. Nobody, including
// Admin, can read a saved provider secret without that password.
class IntegrationHubController extends Controller
{
    public function __construct(private HubVaultService $vault) {}

    /** Redirects to setup/unlock if needed; returns null if the Hub is ready to use. */
    private function requireUnlockedVault(string $agentId)
    {
        if (!$this->vault->hasVault($agentId)) {
            return redirect()->route('integrations.vault.setup');
        }
        if (!$this->vault->isUnlocked($agentId)) {
            return redirect()->route('integrations.vault.unlock');
        }
        return null;
    }

    public function index()
    {
        $agentId = Auth::guard('agent')->user()->agent_id;
        if ($redirect = $this->requireUnlockedVault($agentId)) return $redirect;

        $connected = DB::table('agent_integrations')->where('agent_id', $agentId)->get()->keyBy(fn($r) => $r->category . ':' . $r->provider);

        $categories = [];
        foreach (IntegrationConnectorResolver::categories() as $key => $meta) {
            $total = count($meta['providers']);
            $connectedCount = 0;
            $savedCount = 0;
            foreach ($meta['providers'] as $pKey => $p) {
                $row = $connected[$key . ':' . $pKey] ?? null;
                if (($row->status ?? null) === 'CONNECTED') $connectedCount++;
                if (!empty($row->api_key_encrypted ?? null) || !empty($row->client_id_encrypted ?? null)) $savedCount++;
            }
            $categories[] = [
                'key' => $key,
                'label' => __('integrations.cat_' . $key . '_label'),
                'icon' => $meta['icon'],
                'description' => __('integrations.cat_' . $key . '_desc'),
                'total' => $total,
                'connected' => $connectedCount,
                'saved' => $savedCount,
                'verifiable' => collect($meta['providers'])->contains(fn($p) => $p['connector'] !== null),
            ];
        }

        return view('integrations.index', compact('categories'));
    }

    public function category(string $category)
    {
        $agentId = Auth::guard('agent')->user()->agent_id;
        if ($redirect = $this->requireUnlockedVault($agentId)) return $redirect;

        $meta = IntegrationConnectorResolver::categories()[$category] ?? null;
        abort_if(!$meta, 404);

        $rows = DB::table('agent_integrations')->where('agent_id', $agentId)->where('category', $category)->get()->keyBy('provider');

        $providers = [];
        foreach ($meta['providers'] as $key => $p) {
            $row = $rows[$key] ?? null;
            $shape = IntegrationConnectorResolver::fieldShape($p['credential_type']);
            $providers[] = [
                'key' => $key,
                'label' => $p['label'],
                'credential_type' => $p['credential_type'],
                'shape' => $shape,
                'available' => $p['connector'] !== null,
                'note' => $p['note'] ?? null,
                'help' => $p['help'] ?? null,
                'status' => $row->status ?? 'DISCONNECTED',
                'last_tested_at' => $row->last_tested_at ?? null,
                'last_test_result' => $row->last_test_result ?? null,
                'has_key' => !empty($row->api_key_encrypted ?? null) || !empty($row->client_id_encrypted ?? null),
            ];
        }

        return view('integrations.category', [
            'categoryKey' => $category,
            'categoryLabel' => __('integrations.cat_' . $category . '_label'),
            'providers' => $providers,
        ]);
    }

    public function connect(Request $request, string $category, string $provider)
    {
        $agentId = Auth::guard('agent')->user()->agent_id;
        if ($redirect = $this->requireUnlockedVault($agentId)) return $redirect;

        $meta = IntegrationConnectorResolver::provider($category, $provider);
        abort_if(!$meta, 404);

        $shape = IntegrationConnectorResolver::fieldShape($meta['credential_type']);
        $values = [
            'mode' => 'BYOK',
            'credential_type' => $meta['credential_type'],
            'updated_at' => now(),
            'created_at' => now(),
        ];

        // NEW 5 Aug 2026 — encrypted with THIS AGENT's own vault key
        // (HubVaultService), not the app-wide Crypt facade. Only this
        // agent's own Hub password can ever decrypt these again.
        if ($shape['type'] === 'pair') {
            $request->validate(['field1' => 'required|string|max:500', 'field2' => 'required|string|max:500']);
            $values['client_id_encrypted'] = $this->vault->encryptSecret($agentId, $request->input('field1'));
            $values['client_secret_encrypted'] = $this->vault->encryptSecret($agentId, $request->input('field2'));
        } else {
            $request->validate(['field1' => 'required|string|max:8000']);
            $values['api_key_encrypted'] = $this->vault->encryptSecret($agentId, $request->input('field1'));
        }

        $values['status'] = 'DISCONNECTED';
        $values['last_tested_at'] = null;
        $values['last_test_result'] = null;

        DB::table('agent_integrations')->updateOrInsert(
            ['agent_id' => $agentId, 'category' => $category, 'provider' => $provider],
            $values
        );

        $msg = $meta['connector'] !== null
            ? __('integrations.connect_saved_testable', ['label' => $meta['label']])
            : __('integrations.connect_saved_not_testable', ['label' => $meta['label']]);

        // NEW 6 Aug 2026 (fix) — per Chris: after saving, the page was
        // redirecting back to the category screen and always defaulting
        // to the FIRST provider tab (e.g. OpenAI), even if he'd just
        // saved a key for a different provider (e.g. ElevenLabs) — so it
        // looked like "nothing happened" because the success message
        // appeared above a tab he wasn't looking at. The 'tab' query
        // param tells the view which provider tab to actually open.
        return redirect()->route('integrations.category', ['category' => $category, 'tab' => $provider])->with('success', $msg);
    }

    public function test(string $category, string $provider)
    {
        $agentId = Auth::guard('agent')->user()->agent_id;
        if ($redirect = $this->requireUnlockedVault($agentId)) return $redirect;

        $meta = IntegrationConnectorResolver::provider($category, $provider);
        abort_if(!$meta, 404);

        $row = DB::table('agent_integrations')->where('agent_id', $agentId)->where('category', $category)->where('provider', $provider)->first();

        // FIX 6 Aug 2026 — see the matching note in connect() above: same
        // "which tab was I even on" problem applied to Test Connection
        // and Disconnect too, since both used to just call back().
        $hasSingleKey = !empty($row->api_key_encrypted ?? null);
        $hasPairKey = !empty($row->client_id_encrypted ?? null) && !empty($row->client_secret_encrypted ?? null);

        if (!$row || (!$hasSingleKey && !$hasPairKey)) {
            return redirect()->route('integrations.category', ['category' => $category, 'tab' => $provider])->with('error', __('integrations.test_connect_first', ['label' => $meta['label']]));
        }

        // FIX 6 Aug 2026 — genuine pre-existing gap found while wiring up
        // WhatsApp: this method only ever decrypted api_key_encrypted, so
        // any "pair"-shaped credential (Client ID + Client Secret, or
        // WhatsApp's Phone Number ID + Access Token) could NEVER actually
        // be tested — Test Connection would have silently done nothing
        // useful for those providers. Now branches on whichever shape
        // this row actually has.
        try {
            if ($hasSingleKey) {
                $credentials = ['api_key' => $this->vault->decryptSecret($agentId, $row->api_key_encrypted)];
            } else {
                $credentials = [
                    'client_id' => $this->vault->decryptSecret($agentId, $row->client_id_encrypted),
                    'client_secret' => $this->vault->decryptSecret($agentId, $row->client_secret_encrypted),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('IntegrationHub: failed to decrypt key', ['agent_id' => $agentId, 'category' => $category, 'provider' => $provider]);
            return redirect()->route('integrations.category', ['category' => $category, 'tab' => $provider])->with('error', __('integrations.test_stored_key_unreadable'));
        }

        $connector = IntegrationConnectorResolver::connectorFor($category, $provider);
        $result = $connector ? $connector->testConnection($credentials) : ['success' => false, 'message' => __('integrations.test_not_available_yet')];

        DB::table('agent_integrations')->where('agent_id', $agentId)->where('category', $category)->where('provider', $provider)->update([
            'status' => $result['success'] ? 'CONNECTED' : 'ERROR',
            'last_tested_at' => now(),
            'last_test_result' => substr($result['message'], 0, 255),
            'updated_at' => now(),
        ]);

        return redirect()->route('integrations.category', ['category' => $category, 'tab' => $provider])->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function disconnect(string $category, string $provider)
    {
        $agentId = Auth::guard('agent')->user()->agent_id;
        if ($redirect = $this->requireUnlockedVault($agentId)) return $redirect;

        DB::table('agent_integrations')->where('agent_id', $agentId)->where('category', $category)->where('provider', $provider)->delete();
        return redirect()->route('integrations.category', ['category' => $category, 'tab' => $provider])->with('success', __('integrations.disconnected_flash'));
    }

    // ---------------- Vault setup / unlock ----------------

    public function vaultSetupForm()
    {
        $agentId = Auth::guard('agent')->user()->agent_id;
        if ($this->vault->hasVault($agentId)) return redirect()->route('integrations.vault.unlock');
        return view('integrations.vault-setup');
    }

    // NEW 5 Aug 2026 — same strength rule as the login password. This
    // one guards every connected key AND can never be recovered if
    // forgotten (see resetHubPassword below), so it should never be
    // weaker than the login password it sits next to.
    private const PASSWORD_RULE = ['required', 'string', 'min:10', 'confirmed',
        'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
    ];
    private const PASSWORD_MESSAGE = 'Hub password must be at least 10 characters with uppercase, lowercase, number and special character.';

    public function vaultSetup(Request $request)
    {
        $agentId = Auth::guard('agent')->user()->agent_id;
        $request->validate([
            'hub_password' => self::PASSWORD_RULE,
        ], [
            'hub_password.regex' => self::PASSWORD_MESSAGE,
        ]);

        $this->vault->setup($agentId, $request->input('hub_password'));

        return redirect()->route('integrations.index')->with('success', __('integrations.vault_setup_success'));
    }

    public function vaultUnlockForm()
    {
        $agentId = Auth::guard('agent')->user()->agent_id;
        if (!$this->vault->hasVault($agentId)) return redirect()->route('integrations.vault.setup');
        if ($this->vault->isUnlocked($agentId)) return redirect()->route('integrations.index');
        return view('integrations.vault-unlock');
    }

    public function vaultUnlock(Request $request)
    {
        $agentId = Auth::guard('agent')->user()->agent_id;
        $request->validate(['hub_password' => 'required|string']);

        if (!$this->vault->unlock($agentId, $request->input('hub_password'))) {
            return back()->withErrors(['hub_password' => __('integrations.vault_wrong_password')]);
        }

        return redirect()->route('integrations.index');
    }

    public function vaultLock()
    {
        $agentId = Auth::guard('agent')->user()->agent_id;
        $this->vault->lock($agentId);
        return redirect()->route('integrations.vault.unlock')->with('success', __('integrations.vault_locked_flash'));
    }

    // ---------------- Hub Security page (reached from My Profile) ----------------

    public function hubSecurity()
    {
        $agentId = Auth::guard('agent')->user()->agent_id;
        return view('integrations.hub-security', ['hasVault' => $this->vault->hasVault($agentId)]);
    }

    /** Agent remembers their CURRENT Hub password and wants to change it — nothing already connected is lost. */
    public function changeHubPassword(Request $request)
    {
        $agentId = Auth::guard('agent')->user()->agent_id;
        $request->validate([
            'current_hub_password' => 'required|string',
            'new_hub_password' => self::PASSWORD_RULE,
        ], [
            'new_hub_password.regex' => self::PASSWORD_MESSAGE,
        ]);

        if (!$this->vault->changePassword($agentId, $request->input('current_hub_password'), $request->input('new_hub_password'))) {
            return back()->withErrors(['current_hub_password' => __('integrations.current_hub_password_wrong')]);
        }

        return back()->with('success', __('integrations.hub_password_changed'));
    }

    /**
     * Self-service "I forgot my Hub password" reset. Confirmed by their
     * LOGIN password (proves it's really them, since they can't prove it
     * with the Hub password they've forgotten) — NOT recoverable, wipes
     * every connected integration since those are encrypted under a
     * vault key that no longer exists anywhere.
     */
    public function resetHubPassword(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        $request->validate([
            'login_password' => 'required|string',
            'new_hub_password' => self::PASSWORD_RULE,
        ], [
            'new_hub_password.regex' => self::PASSWORD_MESSAGE,
        ]);

        if (!\Illuminate\Support\Facades\Hash::check($request->input('login_password'), $agent->password_hash)) {
            return back()->withErrors(['login_password' => __('integrations.login_password_incorrect')]);
        }

        $this->vault->resetVault($agent->agent_id, $request->input('new_hub_password'));
        // The old vault key is gone — every row encrypted under it is now
        // permanently unreadable garbage. Wipe them rather than leave dead
        // rows the agent would otherwise see as "connected" but can never
        // use or even disconnect cleanly.
        DB::table('agent_integrations')->where('agent_id', $agent->agent_id)->delete();

        return redirect()->route('integrations.index')->with('success', __('integrations.hub_password_reset_success'));
    }
}
