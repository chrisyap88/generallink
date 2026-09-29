<?php

namespace App\Http\Controllers\GL;

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
    public function show()
    {
        $agent = auth('agent')->user();

        $group = DB::table('groups')
            ->where('group_id', $agent->group_id)
            ->first();

        $recruiter = null;
        if ($agent->parent_id) {
            $recruiter = DB::table('agents')
                ->where('agent_id', $agent->parent_id)
                ->select('full_name', 'agent_code', 'role')
                ->first();
        }

        $nric = null;
        try {
            $nric = $agent->nric_encrypted ? Crypt::decrypt($agent->nric_encrypted) : '—';
        } catch (\Exception $e) {
            $nric = '(Unable to decrypt)';
        }

        $profile = DB::table('agent_profiles')
            ->where('agent_id', $agent->agent_id)
            ->first();

        $beneficiaries = DB::table('beneficiaries')
            ->where('agent_id', $agent->agent_id)
            ->where('is_active', 1)
            ->orderBy('priority_order')
            ->get();

        $history = DB::table('audit_logs')
            ->where('record_id', $agent->agent_id)
            ->where('table_name', 'agents')
            ->orderBy('created_at', 'desc')
            ->get();

        $qrUrl = url('/register?ref=' . $agent->qr_code_token);

        // MERGED 6 Aug 2026 — see Admin\ProfileController::show() for why:
        // this is now the ONE profile screen (view + inline edit), so it
        // needs everything edit() used to prepare too.
        $states = [
            'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan',
            'Pahang', 'Perak', 'Perlis', 'Pulau Pinang', 'Sabah',
            'Sarawak', 'Selangor', 'Terengganu', 'Kuala Lumpur',
            'Labuan', 'Putrajaya'
        ];
        $documentExtractionStatus = DocumentExtractionPreferenceService::statusFor($agent->agent_id);
        $voiceStatus = VoicePreferenceService::statusFor($agent->agent_id);
        $textChatStatus = TextChatPreferenceService::statusFor($agent->agent_id);
        $hubUnlocked = app(HubVaultService::class)->isUnlocked($agent->agent_id);

        return view('gl.profile.show', compact(
            'agent', 'group', 'recruiter', 'profile', 'beneficiaries', 'history', 'nric', 'qrUrl', 'states', 'documentExtractionStatus',
            'voiceStatus', 'textChatStatus', 'hubUnlocked'
        ));
    }

    // The separate Edit Profile page no longer exists — everything is
    // editable inline on the unified page above.
    public function edit()
    {
        return redirect()->route('gl.profile.show');
    }

    public function update(Request $request)
    {
        $agent = auth('agent')->user();

        $validated = $request->validate([
            'phone'     => ['required', 'string', PhoneNumberService::rule()],
            'email'     => 'required|email|max:255|unique:agents,email,' . $agent->agent_id . ',agent_id',
            'bank_name' => 'nullable|string|max:100',
            'address'   => 'nullable|string|max:500',
            'city'      => 'nullable|string|max:100',
            'state'     => 'nullable|string|max:100',
            'postcode'  => 'nullable|string|max:10',
            'photo'     => 'nullable|image|max:2048',
            // REWIRED 6 Aug 2026 — per Chris: an agent should never paste
            // the same API key twice. Voice Assistant and Text Chat now
            // just PICK a provider already connected in the Integration
            // Hub — the key itself lives only in the Hub's own vault.
            'voice_provider'     => 'nullable|in:' . implode(',', VoicePreferenceService::OPTIONS),
            'voice_id'           => 'nullable|string|max:150',
            'text_chat_provider' => 'nullable|in:' . implode(',', TextChatPreferenceService::OPTIONS),
            // NEW 5 Aug 2026 — Document Reading preference (OpenAI/Gemini
            // BYOK instead of Company Credit). See DocumentExtractionPreferenceService.
            'document_extraction_provider' => 'nullable|in:' . implode(',', DocumentExtractionPreferenceService::OPTIONS),
            // NEW 18 Aug 2026 — per Chris: language preference opened up
            // to Group Leader too (was TL/Introducer only).
            'preferred_language' => 'nullable|in:' . implode(',', \App\Services\LanguageService::SUPPORTED),
        ]);

        DocumentExtractionPreferenceService::assertUsable($agent->agent_id, $validated['document_extraction_provider'] ?? 'COMPANY_CREDIT');
        VoicePreferenceService::assertUsable($agent->agent_id, $validated['voice_provider'] ?? null);
        TextChatPreferenceService::assertUsable($agent->agent_id, $validated['text_chat_provider'] ?? null);

        $before = [
            'phone'              => $agent->phone,
            'email'              => $agent->email,
            'bank_name'          => $agent->bank_name,
            'voice_provider'     => $agent->voice_provider ?? null,
            'text_chat_provider' => $agent->text_chat_provider ?? null,
        ];

        // Clearing the provider back to "None" also clears the chosen
        // voice. No key material is ever handled here anymore.
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
                'bank_name'  => $validated['bank_name'] ?? null,
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
                'bank_name'          => $validated['bank_name'] ?? null,
                'voice_provider'     => $voiceUpdate['voice_provider'],
                'text_chat_provider' => $voiceUpdate['text_chat_provider'],
            ]),
            'ip_address'   => request()->ip(),
            'created_at'   => now(),
        ]);

        return redirect()->route('gl.profile.show')
            ->with('success', 'Profile updated successfully.');
    }

    public function changePasswordPage()
    {
        return view('gl.profile.change-password');
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

        return redirect()->route('gl.profile.change-password')
            ->with('password_success', true);
    }
}
