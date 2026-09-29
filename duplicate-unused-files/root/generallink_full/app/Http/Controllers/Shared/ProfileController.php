<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function index()
    {
        $agent = Auth::guard('agent')->user();
        $nricDecrypted = '';
        try { $nricDecrypted = '****' . substr(decrypt($agent->nric_encrypted), -4); } catch (\Exception $e) {}

        $bankAccount = '';
        try { if ($agent->bank_account_encrypted) $bankAccount = decrypt($agent->bank_account_encrypted); } catch (\Exception $e) {}

        $adminBankAccount = '';
        try { if ($agent->admin_bank_account_encrypted) $adminBankAccount = decrypt($agent->admin_bank_account_encrypted); } catch (\Exception $e) {}

        $pointsBalance = DB::table('reward_points_ledger')
            ->where('agent_id', $agent->agent_id)
            ->selectRaw('COALESCE(SUM(points_in)-SUM(points_out),0) as bal')
            ->value('bal') ?? 0;

        return view('profile.index', compact('agent', 'nricDecrypted', 'bankAccount', 'adminBankAccount', 'pointsBalance'));
    }

    public function update(Request $request)
    {
        $agent = Auth::guard('agent')->user();

        $request->validate([
            'phone'      => ['required', 'string', 'max:20'],
            'bank_name'  => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:30'],
            'admin_bank_name'    => ['nullable', 'string', 'max:100'],
            'admin_bank_account' => ['nullable', 'string', 'max:30'],
        ]);

        $before = $agent->toArray();

        $updateData = [
            'phone'      => $request->phone,
            'bank_name'  => $request->bank_name,
            'updated_by' => $agent->agent_id,
            'updated_at' => now(),
        ];

        if ($request->filled('bank_account')) {
            $updateData['bank_account_encrypted'] = encrypt($request->bank_account);
        }

        if ($agent->isAdmin()) {
            $updateData['admin_bank_name'] = $request->admin_bank_name;
            if ($request->filled('admin_bank_account')) {
                $updateData['admin_bank_account_encrypted'] = encrypt($request->admin_bank_account);
            }
        }

        DB::table('agents')->where('agent_id', $agent->agent_id)->update($updateData);
        AuditService::logChange('agents', $agent->agent_id, 'PROFILE_UPDATE', $before, $updateData);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', 'min:10', 'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            ],
        ], [
            'new_password.regex' => 'Password must be at least 10 characters with uppercase, lowercase, number and special character.',
        ]);

        $agent = Auth::guard('agent')->user();

        if (! Hash::check($request->current_password, $agent->password_hash)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        DB::table('agents')->where('agent_id', $agent->agent_id)->update([
            'password_hash' => Hash::make($request->new_password),
            'updated_at'    => now(),
        ]);

        AuditService::logChange('agents', $agent->agent_id, 'PASSWORD_CHANGE');

        return back()->with('success', 'Password changed successfully.');
    }

    public function generateQr()
    {
        $agent = Auth::guard('agent')->user();
        $qrUrl = url('/register?ref=' . $agent->qr_code_token);

        return view('profile.qr', compact('agent', 'qrUrl'));
    }
}
