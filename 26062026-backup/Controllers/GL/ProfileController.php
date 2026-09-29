<?php

namespace App\Http\Controllers\GL;

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

        return view('gl.profile.show', compact(
            'agent', 'group', 'recruiter', 'profile', 'beneficiaries', 'history', 'nric'
        ));
    }

    public function edit()
    {
        $agent = auth('agent')->user();

        $group = DB::table('groups')
            ->where('group_id', $agent->group_id)
            ->first();

        $profile = DB::table('agent_profiles')
            ->where('agent_id', $agent->agent_id)
            ->first();

        $banks = [
            'Maybank', 'CIMB Bank', 'Public Bank', 'RHB Bank', 'Hong Leong Bank',
            'AmBank', 'Bank Islam', 'Bank Rakyat', 'OCBC Bank', 'UOB Bank',
            'Standard Chartered', 'HSBC Bank', 'Citibank', 'Affin Bank',
            'Alliance Bank', 'Bank Muamalat', 'MBSB Bank', 'Other'
        ];

        $states = [
            'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan',
            'Pahang', 'Perak', 'Perlis', 'Pulau Pinang', 'Sabah',
            'Sarawak', 'Selangor', 'Terengganu', 'Kuala Lumpur',
            'Labuan', 'Putrajaya'
        ];

        return view('gl.profile.edit', compact('agent', 'group', 'profile', 'banks', 'states'));
    }

    public function update(Request $request)
    {
        $agent = auth('agent')->user();

        $validated = $request->validate([
            'phone'     => 'required|string|max:20',
            'email'     => 'required|email|max:255|unique:agents,email,' . $agent->agent_id . ',agent_id',
            'bank_name' => 'nullable|string|max:100',
            'address'   => 'nullable|string|max:500',
            'city'      => 'nullable|string|max:100',
            'state'     => 'nullable|string|max:100',
            'postcode'  => 'nullable|string|max:10',
        ]);

        $before = [
            'phone'     => $agent->phone,
            'email'     => $agent->email,
            'bank_name' => $agent->bank_name,
        ];

        DB::table('agents')
            ->where('agent_id', $agent->agent_id)
            ->update([
                'phone'      => $validated['phone'],
                'email'      => $validated['email'],
                'bank_name'  => $validated['bank_name'] ?? null,
                'updated_at' => now(),
            ]);

        $existing = DB::table('agent_profiles')
            ->where('agent_id', $agent->agent_id)
            ->first();

        if ($existing) {
            DB::table('agent_profiles')
                ->where('agent_id', $agent->agent_id)
                ->update([
                    'address'    => $validated['address'] ?? null,
                    'city'       => $validated['city'] ?? null,
                    'state'      => $validated['state'] ?? null,
                    'postcode'   => $validated['postcode'] ?? null,
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
                'phone'     => $validated['phone'],
                'email'     => $validated['email'],
                'bank_name' => $validated['bank_name'] ?? null,
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
