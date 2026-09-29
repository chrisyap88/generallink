<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\PhoneNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $mode           = $request->get('mode', 'main');
        $branches       = null;
        $selectedBranch = null;
        $vendors        = DB::table('vendors')->where('is_active', true)->orderBy('vendor_name')->get(['vendor_id', 'vendor_name', 'vendor_code']);

        $states = [
            'Johor','Kedah','Kelantan','Melaka','Negeri Sembilan',
            'Pahang','Perak','Perlis','Pulau Pinang','Sabah',
            'Sarawak','Selangor','Terengganu','Kuala Lumpur',
            'Labuan','Putrajaya'
        ];

        // Search branches
        if ($mode === 'search' && $request->has('do_search')) {
            $query = DB::table('vendor_branches as b')
                ->leftJoin('vendors as v', DB::raw('b.vendor_id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('v.vendor_id COLLATE utf8mb4_unicode_ci'));

            if ($request->filled('branch_name'))   $query->where('b.branch_name', 'like', '%'.$request->branch_name.'%');
            if ($request->filled('branch_code'))   $query->where('b.branch_code', 'like', '%'.$request->branch_code.'%');
            if ($request->filled('vendor_id'))     $query->where('b.vendor_id',   $request->vendor_id);
            if ($request->filled('phone'))         $query->where('b.phone',        'like', '%'.$request->phone.'%');
            if ($request->filled('email'))         $query->where('b.email',        'like', '%'.$request->email.'%');
            if ($request->filled('address'))       $query->where('b.address',      'like', '%'.$request->address.'%');
            if ($request->filled('postcode'))      $query->where('b.postcode',     'like', '%'.$request->postcode.'%');
            if ($request->filled('city'))          $query->where('b.city',         'like', '%'.$request->city.'%');
            if ($request->filled('state'))         $query->where('b.state',        $request->state);
            if ($request->filled('pic_name'))      $query->where('b.pic_name',     'like', '%'.$request->pic_name.'%');
            if ($request->filled('is_active'))     $query->where('b.is_active',    $request->is_active);

            // Smaller page size so a full page always fits the visible
            // area without scrolling — Prev/Next handles the rest.
            $branches = $query->orderBy('b.branch_name')
                ->paginate(7, [
                    'b.branch_id', 'b.branch_name', 'b.branch_code',
                    'b.phone', 'b.email', 'b.postcode', 'b.city', 'b.state',
                    'b.pic_name', 'b.is_active',
                    'v.vendor_name', 'v.vendor_code'
                ])
                ->withQueryString();
        }

        // Load selected branch for edit
        if ($mode === 'edit' && $request->filled('branch_id')) {
            $selectedBranch = DB::table('vendor_branches as b')
                ->leftJoin('vendors as v', DB::raw('b.vendor_id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('v.vendor_id COLLATE utf8mb4_unicode_ci'))
                ->where('b.branch_id', $request->branch_id)
                ->first([
                    'b.*',
                    'v.vendor_name', 'v.vendor_code'
                ]);
        }

        return view('masterfile.branch-profile', compact('mode', 'branches', 'selectedBranch', 'vendors', 'states'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'branch_name' => ['required', 'string', 'max:200'],
            'branch_code' => ['required', 'string', 'max:20', 'unique:vendor_branches,branch_code'],
            'vendor_id'   => ['required', 'exists:vendors,vendor_id'],
            'phone'       => ['nullable', 'string', PhoneNumberService::nullableRule()],
            'pic_phone'   => ['nullable', 'string', PhoneNumberService::nullableRule()],
        ]);

        $id = Str::uuid()->toString();
        DB::table('vendor_branches')->insert([
            'branch_id'   => $id,
            'vendor_id'   => $request->vendor_id,
            'branch_name' => $request->branch_name,
            'branch_code' => strtoupper($request->branch_code),
            'address'     => $request->address,
            'postcode'    => $request->postcode,
            'city'        => $request->city,
            'state'       => $request->state,
            'phone'       => PhoneNumberService::normalize($request->phone),
            'email'       => $request->email,
            'pic_name'    => $request->pic_name,
            'pic_phone'   => PhoneNumberService::normalize($request->pic_phone),
            'is_active'   => true,
            'created_by'  => Auth::guard('agent')->id(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        AuditService::logChange('vendor_branches', $id, 'CREATE', null, $request->all());
        return redirect()->route('admin.branches.index', ['mode' => 'main'])->with('success', 'Branch added successfully.');
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'branch_name' => ['required', 'string', 'max:200'],
            'is_active'   => ['required', 'in:0,1'],
            'phone'       => ['nullable', 'string', PhoneNumberService::nullableRule()],
            'pic_phone'   => ['nullable', 'string', PhoneNumberService::nullableRule()],
        ]);

        $before = DB::table('vendor_branches')->where('branch_id', $id)->first();

        DB::table('vendor_branches')->where('branch_id', $id)->update([
            'branch_name' => $request->branch_name,
            'address'     => $request->address,
            'postcode'    => $request->postcode,
            'city'        => $request->city,
            'state'       => $request->state,
            'phone'       => PhoneNumberService::normalize($request->phone),
            'email'       => $request->email,
            'pic_name'    => $request->pic_name,
            'pic_phone'   => PhoneNumberService::normalize($request->pic_phone),
            'is_active'   => $request->is_active,
            'updated_by'  => Auth::guard('agent')->id(),
            'updated_at'  => now(),
        ]);

        AuditService::logChange('vendor_branches', $id, 'UPDATE', (array)$before, $request->all());
        return redirect()->route('admin.branches.index', ['mode' => 'edit', 'branch_id' => $id])->with('success', 'Branch updated successfully.');
    }

    public function searchEdit(Request $request)
    {
        $s = '%' . $request->search . '%';
        $results = DB::table('vendor_branches as b')
            ->leftJoin('vendors as v', DB::raw('b.vendor_id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('v.vendor_id COLLATE utf8mb4_unicode_ci'))
            ->where(function ($q) use ($s) {
                $q->where('b.branch_name', 'like', $s)
                  ->orWhere('b.branch_code', 'like', $s)
                  ->orWhere('b.pic_name',    'like', $s)
                  ->orWhere('b.city',        'like', $s)
                  ->orWhere('b.state',       'like', $s)
                  ->orWhere('v.vendor_name', 'like', $s);
            })
            ->orderBy('b.branch_name')->limit(10)
            ->get(['b.branch_id', 'b.branch_name', 'b.branch_code', 'b.city', 'b.state', 'v.vendor_name']);

        return response()->json($results);
    }
}
