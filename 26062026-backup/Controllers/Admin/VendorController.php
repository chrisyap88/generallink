<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $mode           = $request->get('mode', 'main');
        $vendors        = null;
        $selectedVendor = null;

        $states = [
            'Johor','Kedah','Kelantan','Melaka','Negeri Sembilan',
            'Pahang','Perak','Perlis','Pulau Pinang','Sabah',
            'Sarawak','Selangor','Terengganu','Kuala Lumpur',
            'Labuan','Putrajaya'
        ];

        // Search vendors
        if ($mode === 'search' && $request->has('do_search')) {
            $query = DB::table('vendors');
            if ($request->filled('vendor_name'))         $query->where('vendor_name',        'like', '%'.$request->vendor_name.'%');
            if ($request->filled('vendor_code'))         $query->where('vendor_code',         'like', '%'.$request->vendor_code.'%');
            if ($request->filled('vendor_office_phone')) $query->where('vendor_office_phone', 'like', '%'.$request->vendor_office_phone.'%');
            if ($request->filled('vendor_email'))        $query->where('vendor_email',        'like', '%'.$request->vendor_email.'%');
            if ($request->filled('vendor_website'))      $query->where('vendor_website',      'like', '%'.$request->vendor_website.'%');
            if ($request->filled('vendor_address'))      $query->where('vendor_address',      'like', '%'.$request->vendor_address.'%');
            if ($request->filled('vendor_postcode'))     $query->where('vendor_postcode',     'like', '%'.$request->vendor_postcode.'%');
            if ($request->filled('vendor_city'))         $query->where('vendor_city',         'like', '%'.$request->vendor_city.'%');
            if ($request->filled('vendor_state'))        $query->where('vendor_state',        $request->vendor_state);
            if ($request->filled('pic_name'))            $query->where('pic_name',            'like', '%'.$request->pic_name.'%');
            if ($request->filled('pic_phone'))           $query->where('pic_phone',           'like', '%'.$request->pic_phone.'%');
            if ($request->filled('is_active'))           $query->where('is_active',           $request->is_active);
            $vendors = $query->orderBy('vendor_name')->paginate(20)->withQueryString();
        }

        // Load selected vendor for edit
        if ($mode === 'edit' && $request->filled('vendor_id')) {
            $selectedVendor = DB::table('vendors')->where('vendor_id', $request->vendor_id)->first();
        }

        return view('masterfile.vendor-profile', compact('mode', 'vendors', 'selectedVendor', 'states'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'vendor_name' => ['required', 'string', 'max:200'],
            'vendor_code' => ['required', 'string', 'max:20', 'unique:vendors,vendor_code'],
        ]);

        $id = Str::uuid()->toString();
        DB::table('vendors')->insert([
            'vendor_id'           => $id,
            'vendor_name'         => $request->vendor_name,
            'vendor_code'         => strtoupper($request->vendor_code),
            'vendor_email'        => $request->vendor_email,
            'vendor_phone'        => $request->vendor_office_phone,
            'vendor_office_phone' => $request->vendor_office_phone,
            'vendor_website'      => $request->vendor_website,
            'vendor_address'      => $request->vendor_address,
            'vendor_postcode'     => $request->vendor_postcode,
            'vendor_city'         => $request->vendor_city,
            'vendor_state'        => $request->vendor_state,
            'pic_name'            => $request->pic_name,
            'pic_phone'           => $request->pic_phone,
            'is_active'           => true,
            'created_by'          => Auth::guard('agent')->id(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        AuditService::logChange('vendors', $id, 'CREATE', null, $request->all());
        return redirect()->route('admin.vendors.index', ['mode' => 'main'])->with('success', 'Vendor added successfully.');
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'vendor_name' => ['required', 'string', 'max:200'],
            'is_active'   => ['required', 'in:0,1'],
        ]);

        $before = DB::table('vendors')->where('vendor_id', $id)->first();

        DB::table('vendors')->where('vendor_id', $id)->update([
            'vendor_name'         => $request->vendor_name,
            'vendor_email'        => $request->vendor_email,
            'vendor_phone'        => $request->vendor_office_phone,
            'vendor_office_phone' => $request->vendor_office_phone,
            'vendor_website'      => $request->vendor_website,
            'vendor_address'      => $request->vendor_address,
            'vendor_postcode'     => $request->vendor_postcode,
            'vendor_city'         => $request->vendor_city,
            'vendor_state'        => $request->vendor_state,
            'pic_name'            => $request->pic_name,
            'pic_phone'           => $request->pic_phone,
            'is_active'           => $request->is_active,
            'updated_by'          => Auth::guard('agent')->id(),
            'updated_at'          => now(),
        ]);

        if ($request->is_active == 0) {
            DB::table('vendor_branches')->where('vendor_id', $id)->update(['is_active' => 0, 'updated_at' => now()]);
        }

        AuditService::logChange('vendors', $id, 'UPDATE', (array)$before, $request->all());

        // Return to search results page if back URL provided, otherwise edit page
        $backUrl = $request->filled('back') ? urldecode($request->back) : route('admin.vendors.index', ['mode' => 'edit', 'vendor_id' => $id]);
        return redirect($backUrl)->with('success', 'Vendor updated successfully.');
    }

    public function searchEdit(Request $request)
    {
        $s = '%' . $request->search . '%';
        $results = DB::table('vendors')
            ->where(function($q) use ($s) {
                $q->where('vendor_name',  'like', $s)
                  ->orWhere('vendor_code', 'like', $s)
                  ->orWhere('pic_name',    'like', $s)
                  ->orWhere('vendor_city', 'like', $s)
                  ->orWhere('vendor_state','like', $s);
            })
            ->orderBy('vendor_name')->limit(10)
            ->get(['vendor_id', 'vendor_name', 'vendor_code', 'vendor_city', 'vendor_state']);

        return response()->json($results);
    }

    public function storeBranch(Request $request, string $vendorId)
    {
        $request->validate([
            'branch_name' => ['required', 'string', 'max:200'],
            'branch_code' => ['required', 'string', 'max:20', 'unique:vendor_branches,branch_code'],
        ]);

        $id = Str::uuid()->toString();
        DB::table('vendor_branches')->insert([
            'branch_id'   => $id,
            'vendor_id'   => $vendorId,
            'branch_name' => $request->branch_name,
            'branch_code' => strtoupper($request->branch_code),
            'address'     => $request->address,
            'postcode'    => $request->postcode,
            'city'        => $request->city,
            'state'       => $request->state,
            'phone'       => $request->phone,
            'email'       => $request->email,
            'pic_name'    => $request->pic_name,
            'pic_phone'   => $request->pic_phone,
            'is_active'   => true,
            'created_by'  => Auth::guard('agent')->id(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        AuditService::logChange('vendor_branches', $id, 'CREATE', null, $request->all());
        return redirect()->route('admin.vendors.index', ['mode' => 'edit', 'vendor_id' => $vendorId])->with('success', 'Branch added successfully.');
    }

    public function updateBranch(Request $request, string $id)
    {
        $request->validate([
            'branch_name' => ['required', 'string', 'max:200'],
            'is_active'   => ['required', 'in:0,1'],
        ]);

        $before   = DB::table('vendor_branches')->where('branch_id', $id)->first();
        $vendorId = $before->vendor_id;

        DB::table('vendor_branches')->where('branch_id', $id)->update([
            'branch_name' => $request->branch_name,
            'address'     => $request->address,
            'postcode'    => $request->postcode,
            'city'        => $request->city,
            'state'       => $request->state,
            'phone'       => $request->phone,
            'email'       => $request->email,
            'pic_name'    => $request->pic_name,
            'pic_phone'   => $request->pic_phone,
            'is_active'   => $request->is_active,
            'updated_at'  => now(),
        ]);

        AuditService::logChange('vendor_branches', $id, 'UPDATE', (array)$before, $request->all());
        return redirect()->route('admin.vendors.index', ['mode' => 'edit', 'vendor_id' => $vendorId])->with('success', 'Branch updated successfully.');
    }
}
