<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PhoneNumberService;
use App\Services\DocumentExtractionPreferenceService;
use App\Services\HubVaultService;
use App\Services\VoicePreferenceService;
use App\Services\TextChatPreferenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    // REBUILT 6 Aug 2026 — per Chris: having a separate read-only "My
    // Profile" page and a separate "Edit Profile" page was confusing —
    // clicking one showed a screen with no obvious way to actually change
    // anything. This is now the ONE profile screen: viewing AND editing,
    // inline, no second page to navigate to. See admin/profile/show.blade.php.
    public function show()
    {
        $agent = auth('agent')->user();

        // Decrypt NRIC for own profile view
        $nric = null;
        try {
            $nric = $agent->nric_encrypted ? Crypt::decrypt($agent->nric_encrypted) : '—';
        } catch (\Exception $e) {
            $nric = '(Unable to decrypt)';
        }

        // Agent profile (address info)
        $profile = DB::table('agent_profiles')
            ->where('agent_id', $agent->agent_id)
            ->first();

        // History from audit_logs
        $history = DB::table('audit_logs')
            ->where('record_id', $agent->agent_id)
            ->where('table_name', 'agents')
            ->orderBy('created_at', 'desc')
            ->get();

        // Referral QR — same as every other role, per confirmed decision
        // that Admin gets both photo and QR.
        $qrUrl = url('/register?ref=' . $agent->qr_code_token);

        $states = [
            'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan',
            'Pahang', 'Perak', 'Perlis', 'Pulau Pinang', 'Sabah',
            'Sarawak', 'Selangor', 'Terengganu', 'Kuala Lumpur',
            'Labuan', 'Putrajaya'
        ];

        $documentExtractionStatus = DocumentExtractionPreferenceService::statusFor($agent->agent_id);

        // NEW 6 Aug 2026 — per Chris: Voice Assistant / Text Chat now pick
        // a provider already connected in the Integration Hub instead of
        // taking a raw key here — same pattern as Document Reading above.
        $voiceStatus = VoicePreferenceService::statusFor($agent->agent_id);
        $textChatStatus = TextChatPreferenceService::statusFor($agent->agent_id);
        $hubUnlocked = app(HubVaultService::class)->isUnlocked($agent->agent_id);

        return view('admin.profile.show', compact(
            'agent', 'profile', 'history', 'nric', 'qrUrl', 'states', 'documentExtractionStatus',
            'voiceStatus', 'textChatStatus', 'hubUnlocked'
        ));
    }

    // The separate Edit Profile page no longer exists — everything is
    // editable inline on the unified page above. This route is kept only
    // so any old bookmark/link still lands somewhere sensible instead of
    // 404ing.
    public function edit()
    {
        return redirect()->route('admin.profile.show');
    }

    public function update(Request $request)
    {
        $agent = auth('agent')->user();

        $validated = $request->validate([
            'phone'    => ['required', 'string', PhoneNumberService::rule()],
            'email'    => 'required|email|max:255|unique:agents,email,' . $agent->agent_id . ',agent_id',
            'address'  => 'nullable|string|max:500',
            'city'     => 'nullable|string|max:100',
            'state'    => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:10',
            'photo'    => 'nullable|image|max:2048',
            // REWIRED 6 Aug 2026 — per Chris: an agent should never paste
            // the same API key twice. Voice Assistant and Text Chat now
            // just PICK a provider already connected (and Test
            // Connection'd) in the Integration Hub — the key itself lives
            // only in the Hub's own vault (agent_integrations table),
            // never here. voice_id is still a free-text field (which
            // specific voice to use — separate from the key).
            'voice_provider'     => 'nullable|in:' . implode(',', VoicePreferenceService::OPTIONS),
            'voice_id'           => 'nullable|string|max:150',
            'text_chat_provider' => 'nullable|in:' . implode(',', TextChatPreferenceService::OPTIONS),
            // NEW 5 Aug 2026 — Document Reading preference: read sales
            // documents using the agent's OWN OpenAI/Gemini key instead
            // of the company's Document Credit wallet. Only OpenAI and
            // Gemini are supported here (see DocumentExtractionPreferenceService).
            'document_extraction_provider' => 'nullable|in:' . implode(',', DocumentExtractionPreferenceService::OPTIONS),
            // NEW 18 Aug 2026 — per Chris: language preference opened up
            // to Admin too (was TL/Introducer only).
            'preferred_language' => 'nullable|in:' . implode(',', \App\Services\LanguageService::SUPPORTED),
        ]);

        // Blocks saving a preference unless that provider is actually
        // connected AND successfully tested in the Integration Hub right
        // now — never trust a preference that could point at a dead key.
        DocumentExtractionPreferenceService::assertUsable($agent->agent_id, $validated['document_extraction_provider'] ?? 'COMPANY_CREDIT');
        VoicePreferenceService::assertUsable($agent->agent_id, $validated['voice_provider'] ?? null);
        TextChatPreferenceService::assertUsable($agent->agent_id, $validated['text_chat_provider'] ?? null);

        $before = [
            'phone'              => $agent->phone,
            'email'              => $agent->email,
            'voice_provider'     => $agent->voice_provider ?? null,
            'text_chat_provider' => $agent->text_chat_provider ?? null,
        ];

        // Clearing the provider back to "None" also clears the chosen
        // voice, so nothing stale lingers for a provider that's no longer
        // picked. No key material is ever handled here anymore.
        $voiceUpdate = [
            'voice_provider'     => $validated['voice_provider'] ?? null,
            'voice_id'           => !empty($validated['voice_provider'] ?? null) ? ($validated['voice_id'] ?? null) : null,
            'text_chat_provider' => $validated['text_chat_provider'] ?? null,
        ];

        DB::table('agents')
            ->where('agent_id', $agent->agent_id)
            ->update(array_merge([
                'phone'      => PhoneNumberService::normalize($validated['phone']),
                'email'      => $validated['email'],
                'document_extraction_provider' => $validated['document_extraction_provider'] ?? 'COMPANY_CREDIT',
                'preferred_language' => $validated['preferred_language'] ?? ($agent->preferred_language ?? 'EN'),
                'updated_at' => now(),
            ], $voiceUpdate));

        $existing = DB::table('agent_profiles')
            ->where('agent_id', $agent->agent_id)
            ->first();

        $photoPath = $existing->photo_path ?? null;
        if ($request->hasFile('photo')) {
            if ($photoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($photoPath)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($photoPath);
            }
            $photoPath = $request->file('photo')->store('profile-pictures', 'public');
        }

        if ($existing) {
            DB::table('agent_profiles')
                ->where('agent_id', $agent->agent_id)
                ->update([
                    'address'    => $validated['address'] ?? null,
                    'city'       => $validated['city'] ?? null,
                    'state'      => $validated['state'] ?? null,
                    'postcode'   => $validated['postcode'] ?? null,
                    'photo_path' => $photoPath,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('agent_profiles')->insert([
                'profile_id' => Str::uuid(),
                'agent_id'   => $agent->agent_id,
                'address'    => $validated['address'] ?? null,
                'city'       => $validated['city'] ?? null,
                'state'      => $validated['state'] ?? null,
                'postcode'   => $validated['postcode'] ?? null,
                'photo_path' => $photoPath,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('audit_logs')->insert([
            'log_id'       => Str::uuid(),
            'agent_id'     => $agent->agent_id,
            'table_name'   => 'agents',
            'record_id'    => $agent->agent_id,
            'action'       => 'PROFILE_UPDATE',
            'before_value' => json_encode($before),
            'after_value'  => json_encode([
                'phone'              => $validated['phone'],
                'email'              => $validated['email'],
                'voice_provider'     => $voiceUpdate['voice_provider'],
                'text_chat_provider' => $voiceUpdate['text_chat_provider'],
            ]),
            'ip_address'   => request()->ip(),
            'created_at'   => now(),
        ]);

        return redirect()->route('admin.profile.show')
            ->with('success', 'Profile updated successfully.');
    }

    public function changePasswordPage()
    {
        return view('admin.profile.change-password');
    }

    public function changePassword(Request $request)
    {
        $agent = auth('agent')->user();

        $request->validate([
            'current_password' => 'required',
            'new_password'     => ['required', 'confirmed', Password::min(8)],
        ]);

        if (!Hash::check($request->current_password, $agent->password_hash)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        DB::table('agents')
            ->where('agent_id', $agent->agent_id)
            ->update([
                'password_hash' => Hash::make($request->new_password),
                'updated_at'    => now(),
            ]);

        DB::table('audit_logs')->insert([
            'log_id'       => Str::uuid(),
            'agent_id'     => $agent->agent_id,
            'table_name'   => 'agents',
            'record_id'    => $agent->agent_id,
            'action'       => 'PASSWORD_CHANGE',
            'before_value' => null,
            'after_value'  => json_encode(['changed_at' => now()]),
            'ip_address'   => request()->ip(),
            'created_at'   => now(),
        ]);

        return redirect()->route('admin.profile.show')
            ->with('success', 'Password changed successfully.');
    }
}
