<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use App\Services\BeneficiaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BeneficiaryController extends Controller
{
    public function __construct(private BeneficiaryService $service) {}

    public function index()
    {
        $agent = Auth::guard('agent')->user();
        $beneficiaries = Beneficiary::where('agent_id', $agent->agent_id)
                                    ->orderBy('priority_order')
                                    ->get()
                                    ->map(function ($b) {
                                        $b->nric_display = '****' . substr(decrypt($b->nric_encrypted), -4);
                                        return $b;
                                    });
        return view('beneficiary.index', compact('agent', 'beneficiaries'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'full_name'    => ['required', 'string', 'max:200'],
            'nric'         => ['required', 'string', 'min:12', 'max:12'],
            'relationship' => ['required', 'string', 'max:100'],
            'phone'        => ['nullable', 'string', 'max:20'],
            'email'        => ['nullable', 'email', 'max:200'],
            'address'      => ['nullable', 'string'],
            'bank_name'    => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:30'],
            'priority_order' => ['nullable', 'integer', 'min:1'],
        ]);

        $agent = Auth::guard('agent')->user();
        $this->service->save($agent, $request->all());

        return redirect()->route('beneficiary.index')
                         ->with('success', 'Beneficiary added successfully.');
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'full_name'    => ['required', 'string', 'max:200'],
            'nric'         => ['required', 'string', 'min:12', 'max:12'],
            'relationship' => ['required', 'string', 'max:100'],
            'phone'        => ['nullable', 'string', 'max:20'],
            'email'        => ['nullable', 'email', 'max:200'],
            'address'      => ['nullable', 'string'],
            'bank_name'    => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:30'],
            'priority_order' => ['nullable', 'integer', 'min:1'],
        ]);

        $agent = Auth::guard('agent')->user();
        $this->service->save($agent, $request->all(), $id);

        return redirect()->route('beneficiary.index')
                         ->with('success', 'Beneficiary updated successfully.');
    }

    public function destroy(string $id)
    {
        $agent = Auth::guard('agent')->user();
        $ben = Beneficiary::where('beneficiary_id', $id)
                          ->where('agent_id', $agent->agent_id)
                          ->firstOrFail();

        if ($ben->takeover_triggered) {
            return back()->with('error', 'Cannot delete a beneficiary that has already taken over.');
        }

        $ben->update(['is_active' => false]);
        return redirect()->route('beneficiary.index')->with('success', 'Beneficiary removed.');
    }
}
