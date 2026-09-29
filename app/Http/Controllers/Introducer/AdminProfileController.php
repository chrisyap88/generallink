<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        // NRIC is NEVER stored on the agent record — removed per
        // confirmed legal/privacy requirement (06 Jul 2026).

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

        return view('admin.profile.show', compact(
            'agent',
            'profile',
            'history',
            'qrUrl'
        ));
    }

    public function edit()
    {
        $agent = auth('agent')->user();

        $profile = DB::table('agent_profiles')
            ->where('agent_id', $agent->agent_id)
            ->first();

        $states = [
            'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan',
            'Pahang', 'Perak', 'Perlis', 'Pulau Pinang', 'Sabah',
            'Sarawak', 'Selangor', 'Terengganu', 'Kuala Lumpur',
            'Labuan', 'Putrajaya'
        ];

        return view('admin.profile.edit', compact('agent', 'profile', 'states'));
    }

    public function update(Request $request)
    {
        $agent = auth('agent')->user();

        $validated = $request->validate([
            'phone'    => 'required|string|max:20',
            'email'    => 'required|email|max:255|unique:agents,email,' . $agent->agent_id . ',agent_id',
            'address'  => 'nullable|string|max:500',
            'city'     => 'nullable|string|max:100',
            'state'    => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:10',
            'photo'    => 'nullable|image|max:2048',
        ]);

        $before = [
            'phone' => $agent->phone,
            'email' => $agent->email,
        ];

        DB::table('agents')
            ->where('agent_id', $agent->agent_id)
            ->update([
                'phone'      => $validated['phone'],
                'email'      => $validated['email'],
                'updated_at' => now(),
            ]);

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
                'phone' => $validated['phone'],
                'email' => $validated['email'],
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
