<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\AuditService;
use App\Services\PhoneNumberService;
use App\Services\VendorVerificationMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VendorController extends Controller
{
    /**
     * Fixed industry categories a vendor can belong to. REPLACED 8 Aug
     * 2026 per Chris's explicit list (standard broad economic sectors,
     * not just consumer-retail groupings) for the vendor registration
     * rebuild. Existing vendor rows are remapped to the closest new
     * category by migration 2026_08_08_000009_remap_vendor_industries —
     * see that file for the exact old-to-new mapping. OTHER is kept as a
     * practical fallback (not in Chris's original list) so a genuinely
     * uncommon business isn't forced into a bad-fit category.
     */
    public const INDUSTRIES = [
        'AGRICULTURE'               => 'Agriculture',
        'AUTOMOTIVE'                => 'Automotive',
        'BANKING_FINANCE'           => 'Banking and Finance',
        'CONSTRUCTION'              => 'Construction',
        'EDUCATION'                 => 'Education',
        'ENGINEERING'               => 'Engineering',
        'ENERGY_UTILITIES'          => 'Energy and Utilities',
        'FOOD_BEVERAGE'             => 'Food and Beverage',
        'GOVERNMENT'                => 'Government',
        'HEALTHCARE'                => 'Healthcare',
        'HOSPITALITY_TOURISM'       => 'Hospitality and Tourism',
        'INFORMATION_TECHNOLOGY'    => 'Information Technology',
        'INSURANCE'                 => 'Insurance',
        'LOGISTICS_TRANSPORTATION'  => 'Logistics and Transportation',
        'MANUFACTURING'             => 'Manufacturing',
        'PROFESSIONAL_SERVICES'     => 'Professional Services',
        'RETAIL_WHOLESALE'          => 'Retail and Wholesale',
        'TELECOMMUNICATIONS'        => 'Telecommunications',
        'TRADING_ECOMMERCE'         => 'Trading and E-Commerce',
        'OTHER'                     => 'Other',
    ];

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
            // Smaller page size so a full page always fits the visible
            // area without scrolling — Prev/Next handles the rest.
            $vendors = $query->orderBy('vendor_name')->paginate(7)->withQueryString();
        }

        $vendorProducts     = collect();
        $catalogSuggestions = collect();

        // Load selected vendor for edit
        if ($mode === 'edit' && $request->filled('vendor_id')) {
            $selectedVendor = DB::table('vendors')->where('vendor_id', $request->vendor_id)->first();

            if ($selectedVendor) {
                // Products this vendor already offers.
                $vendorProducts = DB::table('products')
                    ->where('vendor_id', $selectedVendor->vendor_id)
                    ->orderBy('product_name')
                    ->get();

                // Products offered elsewhere in the system (by name+type),
                // used as a quick-add checklist so Admin doesn't have to
                // retype a product that already exists for another vendor.
                // Excludes names this vendor already has.
                $ownedNames = $vendorProducts->pluck('product_name')->map(fn($n) => mb_strtolower($n))->all();
                $catalogSuggestions = DB::table('products')
                    ->select('product_name', 'product_type')
                    ->distinct()
                    ->orderBy('product_name')
                    ->get()
                    ->filter(fn($p) => !in_array(mb_strtolower($p->product_name), $ownedNames))
                    ->values();
            }
        }

        return view('masterfile.vendor-profile', compact('mode', 'vendors', 'selectedVendor', 'states', 'vendorProducts', 'catalogSuggestions'));
    }

    /**
     * Quick-add one or more products to this vendor from a checklist,
     * instead of Admin having to fill out the full Product Maintenance
     * form for each one. Product code is auto-generated from the vendor
     * code + product name; Admin can refine name/code/campaign dates
     * afterwards in Product Maintenance if needed.
     */
    public function quickAddProducts(Request $request, string $id)
    {
        $vendor = DB::table('vendors')->where('vendor_id', $id)->first();
        abort_if(!$vendor, 404);

        $request->validate([
            'picks'   => ['required', 'array', 'min:1'],
            'picks.*' => ['required', 'string'],
        ]);

        $existingNames = DB::table('products')
            ->where('vendor_id', $id)
            ->pluck('product_name')
            ->map(fn($n) => mb_strtolower($n))
            ->all();

        $added  = 0;
        $skipped = 0;
        $validTypes = ['MOTOR', 'PERSONAL_ACCIDENT', 'FIRE', 'OTHER'];

        foreach ($request->picks as $pick) {
            // Each checkbox value is encoded as "Product Name::TYPE".
            $parts = explode('::', $pick);
            $type  = strtoupper(array_pop($parts));
            $name  = trim(implode('::', $parts));
            if ($name === '' || !in_array($type, $validTypes)) {
                $skipped++;
                continue;
            }
            if (in_array(mb_strtolower($name), $existingNames)) {
                $skipped++;
                continue;
            }

            // Generate a unique product code from vendor code + product name.
            $base = strtoupper($vendor->vendor_code) . '-' . strtoupper(Str::slug($name, ''));
            $base = substr($base, 0, 16);
            $code = $base;
            $suffix = 1;
            while (DB::table('products')->where('product_code', $code)->exists()) {
                $suffix++;
                $code = substr($base, 0, 16 - strlen((string) $suffix)) . $suffix;
            }

            $productId = Str::uuid()->toString();
            DB::table('products')->insert([
                'product_id'   => $productId,
                'vendor_id'    => $id,
                'product_name' => $name,
                'product_code' => $code,
                'product_type' => $type,
                'is_active'    => true,
                'created_by'   => Auth::guard('agent')->id(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            AuditService::logChange('products', $productId, 'PRODUCT_CREATED', null, ['vendor_id' => $id, 'product_name' => $name, 'product_type' => $type, 'source' => 'vendor_quick_add']);
            $existingNames[] = mb_strtolower($name);
            $added++;
        }

        $message = $added . ' product' . ($added === 1 ? '' : 's') . ' added.';
        if ($skipped) $message .= ' ' . $skipped . ' skipped (already exists for this vendor).';

        return redirect()->route('admin.vendors.index', ['mode' => 'edit', 'vendor_id' => $id])->with('success', $message);
    }

    // NEW 8 Aug 2026 (Task #92, REDESIGNED same day per Chris) — lets
    // Admin give an EXISTING vendor record a Vendor Portal login
    // directly, without that vendor having to self-register. Since
    // Admin is creating this record themselves (already vetted by
    // adding it under Master File Maintenance), there's no document
    // review step here — but for the SAME security reason as
    // self-registration (no passwords changing hands insecurely), this
    // still emails the vendor a "verify your email + set your password"
    // link rather than generating a temporary password Admin would have
    // to relay by phone/text.
    public function createLogin(string $id, VendorVerificationMailService $mailer)
    {
        $vendor = Vendor::where('vendor_id', $id)->firstOrFail();

        if ($vendor->login_status !== 'NONE') {
            return back()->with('error', $vendor->vendor_name . ' already has a login (status: ' . $vendor->login_status . ').');
        }
        if (!$vendor->vendor_email) {
            return back()->with('error', 'Add an email address for ' . $vendor->vendor_name . ' first — it\'s needed as their Vendor Portal login username.');
        }

        $mailer->send($vendor); // sets login_status = AWAITING_PASSWORD internally

        AuditService::logChange('vendors', $id, 'VENDOR_LOGIN_CREATED_BY_ADMIN', ['login_status' => 'NONE'], ['login_status' => 'AWAITING_PASSWORD'], Auth::guard('agent')->id());

        return back()->with('success', 'A link to verify their email and set a password has been sent to ' . $vendor->vendor_email . '.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'vendor_name' => ['required', 'string', 'max:200'],
            'second_name' => ['nullable', 'string', 'max:200'],
            'vendor_code' => ['required', 'string', 'max:20', 'unique:vendors,vendor_code'],
            'industry'    => ['required', 'in:' . implode(',', array_keys(self::INDUSTRIES))],
            'vendor_type' => ['required', 'in:' . implode(',', array_keys(\App\Models\Vendor::TYPES))],
            'vendor_office_phone' => ['nullable', 'string', PhoneNumberService::nullableRule()],
            'pic_phone'           => ['nullable', 'string', PhoneNumberService::nullableRule()],
        ]);

        $id = Str::uuid()->toString();
        DB::table('vendors')->insert([
            'vendor_id'           => $id,
            'vendor_name'         => $request->vendor_name,
            'second_name'         => $request->second_name ?: null,
            'vendor_code'         => strtoupper($request->vendor_code),
            'industry'            => $request->industry,
            'vendor_type'         => $request->vendor_type,
            'vendor_email'        => $request->vendor_email,
            'vendor_phone'        => PhoneNumberService::normalize($request->vendor_office_phone),
            'vendor_office_phone' => PhoneNumberService::normalize($request->vendor_office_phone),
            'vendor_website'      => $request->vendor_website,
            'vendor_address'      => $request->vendor_address,
            'vendor_postcode'     => $request->vendor_postcode,
            'vendor_city'         => $request->vendor_city,
            'vendor_state'        => $request->vendor_state,
            'pic_name'            => $request->pic_name,
            'pic_phone'           => PhoneNumberService::normalize($request->pic_phone),
            'sst_registration_number' => $request->sst_registration_number,
            'is_active'           => true,
            'created_by'          => Auth::guard('agent')->id(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        AuditService::logChange('vendors', $id, 'VENDOR_CREATED', null, $request->all());
        return redirect()->route('admin.vendors.index', ['mode' => 'main'])->with('success', 'Vendor added successfully.');
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'vendor_name' => ['required', 'string', 'max:200'],
            'second_name' => ['nullable', 'string', 'max:200'],
            'is_active'   => ['required', 'in:0,1'],
            'industry'    => ['required', 'in:' . implode(',', array_keys(self::INDUSTRIES))],
            'vendor_type' => ['required', 'in:' . implode(',', array_keys(\App\Models\Vendor::TYPES))],
            'vendor_office_phone' => ['nullable', 'string', PhoneNumberService::nullableRule()],
            'pic_phone'           => ['nullable', 'string', PhoneNumberService::nullableRule()],
        ]);

        $before = DB::table('vendors')->where('vendor_id', $id)->first();

        DB::table('vendors')->where('vendor_id', $id)->update([
            'vendor_name'         => $request->vendor_name,
            'second_name'         => $request->second_name ?: null,
            'industry'            => $request->industry,
            'vendor_type'         => $request->vendor_type,
            'vendor_email'        => $request->vendor_email,
            'vendor_phone'        => PhoneNumberService::normalize($request->vendor_office_phone),
            'vendor_office_phone' => PhoneNumberService::normalize($request->vendor_office_phone),
            'vendor_website'      => $request->vendor_website,
            'vendor_address'      => $request->vendor_address,
            'vendor_postcode'     => $request->vendor_postcode,
            'vendor_city'         => $request->vendor_city,
            'vendor_state'        => $request->vendor_state,
            'pic_name'            => $request->pic_name,
            'pic_phone'           => PhoneNumberService::normalize($request->pic_phone),
            'sst_registration_number' => $request->sst_registration_number,
            'is_active'           => $request->is_active,
            'updated_by'          => Auth::guard('agent')->id(),
            'updated_at'          => now(),
        ]);

        if ($request->is_active == 0) {
            DB::table('vendor_branches')->where('vendor_id', $id)->update(['is_active' => 0, 'updated_at' => now()]);
        }

        AuditService::logChange('vendors', $id, 'VENDOR_UPDATED', (array)$before, $request->all());

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
            'phone'       => ['nullable', 'string', PhoneNumberService::nullableRule()],
            'pic_phone'   => ['nullable', 'string', PhoneNumberService::nullableRule()],
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
            'phone'       => PhoneNumberService::normalize($request->phone),
            'email'       => $request->email,
            'pic_name'    => $request->pic_name,
            'pic_phone'   => PhoneNumberService::normalize($request->pic_phone),
            'is_active'   => true,
            'created_by'  => Auth::guard('agent')->id(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        AuditService::logChange('vendor_branches', $id, 'VENDOR_BRANCH_CREATED', null, $request->all());
        return redirect()->route('admin.vendors.index', ['mode' => 'edit', 'vendor_id' => $vendorId])->with('success', 'Branch added successfully.');
    }

    public function updateBranch(Request $request, string $id)
    {
        $request->validate([
            'branch_name' => ['required', 'string', 'max:200'],
            'is_active'   => ['required', 'in:0,1'],
            'phone'       => ['nullable', 'string', PhoneNumberService::nullableRule()],
            'pic_phone'   => ['nullable', 'string', PhoneNumberService::nullableRule()],
        ]);

        $before   = DB::table('vendor_branches')->where('branch_id', $id)->first();
        $vendorId = $before->vendor_id;

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
            'updated_at'  => now(),
        ]);

        AuditService::logChange('vendor_branches', $id, 'VENDOR_BRANCH_UPDATED', (array)$before, $request->all());
        return redirect()->route('admin.vendors.index', ['mode' => 'edit', 'vendor_id' => $vendorId])->with('success', 'Branch updated successfully.');
    }
}
