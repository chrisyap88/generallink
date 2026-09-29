<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MasterFileController extends Controller
{
    // -------------------------------------------------------
    // VENDORS (legacy)
    // -------------------------------------------------------
    public function vendors(Request $request)
    {
        $query = DB::table('vendors');
        $hasFilter = $request->filled('search') || ($request->has('status') && $request->status !== '');
        if ($hasFilter) {
            if ($request->filled('search')) {
                $s = '%' . $request->search . '%';
                $query->where(function($q) use ($s) {
                    $q->where('vendor_name',    'like', $s)
                      ->orWhere('vendor_code',   'like', $s)
                      ->orWhere('pic_name',       'like', $s)
                      ->orWhere('vendor_phone',   'like', $s)
                      ->orWhere('vendor_email',   'like', $s)
                      ->orWhere('vendor_address', 'like', $s);
                });
            }
            if ($request->has('status') && $request->status !== '') {
                $query->where('is_active', $request->status);
            }
            $vendors = $query->orderBy('vendor_name')->paginate(20)->withQueryString();
        } else {
            $vendors = collect();
        }
        return view('masterfile.vendors', compact('vendors'));
    }

    public function storeVendor(Request $request)
    {
        $request->validate([
            'vendor_name'    => ['required', 'string', 'max:200'],
            'vendor_code'    => ['required', 'string', 'max:20', 'unique:vendors,vendor_code'],
            'vendor_email'   => ['nullable', 'email'],
            'vendor_phone'   => ['nullable', 'string', 'max:20'],
            'vendor_address' => ['nullable', 'string', 'max:500'],
            'pic_name'       => ['nullable', 'string', 'max:200'],
        ]);
        $id = Str::uuid()->toString();
        DB::table('vendors')->insert([
            'vendor_id'      => $id,
            'vendor_name'    => $request->vendor_name,
            'vendor_code'    => strtoupper($request->vendor_code),
            'vendor_email'   => $request->vendor_email,
            'vendor_phone'   => $request->vendor_phone,
            'vendor_address' => $request->vendor_address,
            'pic_name'       => $request->pic_name,
            'is_active'      => true,
            'created_by'     => Auth::guard('agent')->id(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
        AuditService::logChange('vendors', $id, 'CREATE', null, $request->all());
        return back()->with('success', 'Vendor added successfully.');
    }

    public function toggleVendor(string $id)
    {
        $vendor = DB::table('vendors')->where('vendor_id', $id)->first();
        DB::table('vendors')->where('vendor_id', $id)->update([
            'is_active'  => !$vendor->is_active,
            'updated_at' => now(),
        ]);
        AuditService::logChange('vendors', $id, 'UPDATE', ['is_active' => $vendor->is_active], ['is_active' => !$vendor->is_active]);
        return back()->with('success', 'Vendor status updated.');
    }

    // -------------------------------------------------------
    // PRODUCTS
    // -------------------------------------------------------
    public function products(Request $request)
    {
        $mode            = $request->get('mode', 'main');
        $products        = null;
        $selectedProduct = null;
        $vendors         = DB::table('vendors')->where('is_active', true)->orderBy('vendor_name')->get(['vendor_id', 'vendor_name', 'vendor_code']);

        if ($mode === 'search' && $request->has('do_search')) {
            $query = DB::table('products as p')
                ->leftJoin('vendors as v', DB::raw('p.vendor_id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('v.vendor_id COLLATE utf8mb4_unicode_ci'))
                ->select('p.*', 'v.vendor_name', 'v.vendor_code');

            if ($request->filled('product_name'))  $query->where('p.product_name',  'like', '%'.$request->product_name.'%');
            if ($request->filled('product_code'))  $query->where('p.product_code',  'like', '%'.$request->product_code.'%');
            if ($request->filled('vendor_id'))     $query->where('p.vendor_id',     $request->vendor_id);
            if ($request->filled('product_type'))  $query->where('p.product_type',  $request->product_type);
            if ($request->filled('is_active'))     $query->where('p.is_active',     $request->is_active);

            $products = $query->orderBy('p.product_name')->paginate(20)->withQueryString();
        }

        if ($mode === 'edit' && $request->filled('product_id')) {
            $selectedProduct = DB::table('products as p')
                ->leftJoin('vendors as v', DB::raw('p.vendor_id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('v.vendor_id COLLATE utf8mb4_unicode_ci'))
                ->where('p.product_id', $request->product_id)
                ->first(['p.*', 'v.vendor_name', 'v.vendor_code']);
        }

        return view('masterfile.products', compact('mode', 'products', 'selectedProduct', 'vendors'));
    }

    public function storeProduct(Request $request)
    {
        $request->validate([
            'vendor_id'      => ['required', 'exists:vendors,vendor_id'],
            'product_name'   => ['required', 'string', 'max:200'],
            'product_code'   => ['required', 'string', 'max:20', 'unique:products,product_code'],
            'product_type'   => ['required', 'in:MOTOR,PERSONAL_ACCIDENT,FIRE,OTHER'],
            'campaign_start' => ['nullable', 'date'],
            'campaign_end'   => ['nullable', 'date', 'after:campaign_start'],
        ]);
        $id = Str::uuid()->toString();
        DB::table('products')->insert([
            'product_id'     => $id,
            'vendor_id'      => $request->vendor_id,
            'product_name'   => $request->product_name,
            'product_code'   => strtoupper($request->product_code),
            'product_type'   => $request->product_type,
            'description'    => $request->description,
            'campaign_start' => $request->campaign_start,
            'campaign_end'   => $request->campaign_end,
            'is_active'      => true,
            'created_by'     => Auth::guard('agent')->id(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
        AuditService::logChange('products', $id, 'CREATE', null, $request->all());
        return redirect()->route('admin.masterfile.products', ['mode' => 'main'])->with('success', 'Product added successfully.');
    }

    public function updateProduct(Request $request, string $id)
    {
        $request->validate([
            'product_name'   => ['required', 'string', 'max:200'],
            'product_type'   => ['required', 'in:MOTOR,PERSONAL_ACCIDENT,FIRE,OTHER'],
            'is_active'      => ['required', 'in:0,1'],
            'campaign_start' => ['nullable', 'date'],
            'campaign_end'   => ['nullable', 'date', 'after:campaign_start'],
        ]);
        $before = DB::table('products')->where('product_id', $id)->first();
        DB::table('products')->where('product_id', $id)->update([
            'product_name'   => $request->product_name,
            'product_type'   => $request->product_type,
            'description'    => $request->description,
            'campaign_start' => $request->campaign_start,
            'campaign_end'   => $request->campaign_end,
            'is_active'      => $request->is_active,
            'updated_by'     => Auth::guard('agent')->id(),
            'updated_at'     => now(),
        ]);
        AuditService::logChange('products', $id, 'UPDATE', (array)$before, $request->all());
        $backUrl = $request->filled('back') ? urldecode($request->back) : route('admin.masterfile.products', ['mode' => 'search']);
        return redirect($backUrl)->with('success', 'Product updated successfully.');
    }

    public function toggleProduct(string $id)
    {
        $p = DB::table('products')->where('product_id', $id)->first();
        DB::table('products')->where('product_id', $id)->update([
            'is_active'  => !$p->is_active,
            'updated_at' => now(),
        ]);
        AuditService::logChange('products', $id, 'UPDATE', ['is_active' => $p->is_active], ['is_active' => !$p->is_active]);
        return back()->with('success', 'Product status updated.');
    }

    // -------------------------------------------------------
    // COMMISSION STRUCTURES
    // -------------------------------------------------------
    public function commissionStructures(Request $request)
    {
        $query = DB::table('commission_structures as cs')
            ->join('vendors as v',  'v.vendor_id',  '=', 'cs.vendor_id')
            ->join('products as p', 'p.product_id', '=', 'cs.product_id')
            ->select('cs.*', 'v.vendor_name', 'p.product_name');

        if ($request->filled('vendor_id') || $request->filled('product_id') || ($request->has('status') && $request->status !== '')) {
            if ($request->filled('vendor_id'))  $query->where('cs.vendor_id',  $request->vendor_id);
            if ($request->filled('product_id')) $query->where('cs.product_id', $request->product_id);
            if ($request->has('status') && $request->status !== '') $query->where('cs.is_active', $request->status);
            $structures = $query->orderBy('v.vendor_name')->orderBy('p.product_name')->get();
        } else {
            $structures = collect();
        }

        $vendors  = DB::table('vendors')->where('is_active', true)->orderBy('vendor_name')->get();
        $products = DB::table('products')->where('is_active', true)->orderBy('product_name')->get();
        return view('masterfile.commission-structures', compact('structures', 'vendors', 'products'));
    }

    public function storeCommissionStructure(Request $request)
    {
        $request->validate([
            'vendor_id'            => ['required', 'exists:vendors,vendor_id'],
            'product_id'           => ['required', 'exists:products,product_id'],
            'commission_basis'     => ['required', 'in:PREMIUM_PCT,SUM_INSURED_PCT'],
            'total_commission_pct' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'group_leader_pct'     => ['required', 'numeric', 'min:0'],
            'team_leader_pct'      => ['required', 'numeric', 'min:0'],
            'introducer_pct'       => ['required', 'numeric', 'min:0'],
            'valid_from'           => ['required', 'date'],
            'valid_to'             => ['nullable', 'date', 'after:valid_from'],
        ]);

        $allocated = $request->group_leader_pct + $request->team_leader_pct + $request->introducer_pct;
        if (abs($allocated - $request->total_commission_pct) > 0.001) {
            return back()->withErrors([
                'splits' => "GL + TL + Introducer % must equal Total Commission % ({$request->total_commission_pct}%). Current allocated: {$allocated}%."
            ])->withInput();
        }

        $id = Str::uuid()->toString();
        DB::table('commission_structures')->insert([
            'structure_id'         => $id,
            'vendor_id'            => $request->vendor_id,
            'product_id'           => $request->product_id,
            'commission_basis'     => $request->commission_basis,
            'total_commission_pct' => $request->total_commission_pct,
            'group_leader_pct'     => $request->group_leader_pct,
            'team_leader_pct'      => $request->team_leader_pct,
            'introducer_pct'       => $request->introducer_pct,
            'valid_from'           => $request->valid_from,
            'valid_to'             => $request->valid_to,
            'is_active'            => true,
            'created_by'           => Auth::guard('agent')->id(),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
        AuditService::logChange('commission_structures', $id, 'CREATE', null, $request->all());
        return back()->with('success', 'Commission structure saved.');
    }

    public function toggleCommissionStructure(string $id)
    {
        $cs = DB::table('commission_structures')->where('structure_id', $id)->first();
        DB::table('commission_structures')->where('structure_id', $id)->update([
            'is_active'  => !$cs->is_active,
            'updated_at' => now(),
        ]);
        AuditService::logChange('commission_structures', $id, 'UPDATE', ['is_active' => $cs->is_active], ['is_active' => !$cs->is_active]);
        return back()->with('success', 'Commission structure status updated.');
    }

    // -------------------------------------------------------
    // GROUPS
    // -------------------------------------------------------
    public function groups(Request $request)
    {
        $mode          = $request->get('mode', 'main');
        $groups        = null;
        $selectedGroup = null;

        if ($mode === 'search' && $request->has('do_search')) {
            $query = DB::table('groups');
            if ($request->filled('group_name'))  $query->where('group_name',  'like', '%'.$request->group_name.'%');
            if ($request->filled('group_code'))  $query->where('group_code',  'like', '%'.$request->group_code.'%');
            if ($request->filled('group_email')) $query->where('group_email', 'like', '%'.$request->group_email.'%');
            if ($request->filled('is_active'))   $query->where('is_active',   $request->is_active);
            $groups = $query->orderBy('group_code')->paginate(20)->withQueryString();
        }

        if ($mode === 'edit' && $request->filled('group_id')) {
            $selectedGroup = DB::table('groups')->where('group_id', $request->group_id)->first();
        }

        return view('masterfile.groups', compact('mode', 'groups', 'selectedGroup'));
    }

    public function storeGroup(Request $request)
    {
        $request->validate([
            'group_name'     => ['required', 'string', 'max:200'],
            'group_code'     => ['required', 'string', 'max:20', 'unique:groups,group_code'],
            'group_email'    => ['required', 'email'],
            'separator_char' => ['nullable', 'in:-,.,_'],
        ]);
        $id = Str::uuid()->toString();
        DB::table('groups')->insert([
            'group_id'           => $id,
            'group_name'         => $request->group_name,
            'group_code'         => strtoupper($request->group_code),
            'group_email'        => strtolower($request->group_email),
            'separator_char'     => $request->separator_char ?? '-',
            'root_member_suffix' => '0',
            'is_active'          => true,
            'created_by'         => Auth::guard('agent')->id(),
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
        AuditService::logChange('groups', $id, 'CREATE', null, $request->all());
        return redirect()->route('admin.masterfile.groups', ['mode' => 'main'])->with('success', 'Group created successfully.');
    }

    public function updateGroup(Request $request, string $id)
    {
        $request->validate([
            'group_name'     => ['required', 'string', 'max:200'],
            'group_email'    => ['required', 'email'],
            'separator_char' => ['nullable', 'in:-,.,_'],
            'is_active'      => ['required', 'in:0,1'],
        ]);
        $before = DB::table('groups')->where('group_id', $id)->first();
        DB::table('groups')->where('group_id', $id)->update([
            'group_name'     => $request->group_name,
            'group_email'    => strtolower($request->group_email),
            'separator_char' => $request->separator_char ?? '-',
            'is_active'      => $request->is_active,
            'updated_by'     => Auth::guard('agent')->id(),
            'updated_at'     => now(),
        ]);
        AuditService::logChange('groups', $id, 'UPDATE', (array)$before, $request->all());
        $backUrl = $request->filled('back') ? urldecode($request->back) : route('admin.masterfile.groups', ['mode' => 'search']);
        return redirect($backUrl)->with('success', 'Group updated successfully.');
    }

    // -------------------------------------------------------
    // REWARD POINTS RATES
    // -------------------------------------------------------
    public function rewardRates(Request $request)
    {
        $query = DB::table('reward_points_rates as r')
            ->leftJoin('vendors as v',  'v.vendor_id',  '=', 'r.vendor_id')
            ->leftJoin('products as p', 'p.product_id', '=', 'r.product_id')
            ->select('r.*', 'v.vendor_name', 'p.product_name');

        if ($request->filled('vendor_id') || $request->filled('product_id') || ($request->has('status') && $request->status !== '')) {
            if ($request->filled('vendor_id'))  $query->where('r.vendor_id',  $request->vendor_id);
            if ($request->filled('product_id')) $query->where('r.product_id', $request->product_id);
            if ($request->has('status') && $request->status !== '') $query->where('r.is_active', $request->status);
            $rates = $query->orderByDesc('r.created_at')->get();
        } else {
            $rates = collect();
        }

        $vendors  = DB::table('vendors')->where('is_active', true)->orderBy('vendor_name')->get();
        $products = DB::table('products')->where('is_active', true)->orderBy('product_name')->get();
        return view('masterfile.reward-rates', compact('rates', 'vendors', 'products'));
    }

    public function storeRewardRate(Request $request)
    {
        $request->validate([
            'points_per_rm' => ['required', 'numeric', 'min:0.0001'],
            'valid_from'    => ['required', 'date'],
            'valid_to'      => ['nullable', 'date', 'after:valid_from'],
        ]);
        $id = Str::uuid()->toString();
        DB::table('reward_points_rates')->insert([
            'rate_id'       => $id,
            'vendor_id'     => $request->vendor_id ?: null,
            'product_id'    => $request->product_id ?: null,
            'points_per_rm' => $request->points_per_rm,
            'valid_from'    => $request->valid_from,
            'valid_to'      => $request->valid_to,
            'is_active'     => true,
            'created_by'    => Auth::guard('agent')->id(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        AuditService::logChange('reward_points_rates', $id, 'CREATE', null, $request->all());
        return back()->with('success', 'Reward rate added.');
    }
}
