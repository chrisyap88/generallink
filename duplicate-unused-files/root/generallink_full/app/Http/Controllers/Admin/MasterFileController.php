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
    // VENDORS
    // -------------------------------------------------------
    public function vendors()
    {
        $vendors = DB::table('vendors')->orderBy('vendor_name')->get();
        return view('masterfile.vendors', compact('vendors'));
    }

    public function storeVendor(Request $request)
    {
        $request->validate([
            'vendor_name' => ['required', 'string', 'max:200'],
            'vendor_code' => ['required', 'string', 'max:20', 'unique:vendors,vendor_code'],
            'vendor_email'=> ['nullable', 'email'],
            'vendor_phone'=> ['nullable', 'string', 'max:20'],
            'pic_name'    => ['nullable', 'string', 'max:200'],
        ]);

        $id = Str::uuid()->toString();
        DB::table('vendors')->insert([
            'vendor_id'   => $id,
            'vendor_name' => $request->vendor_name,
            'vendor_code' => strtoupper($request->vendor_code),
            'vendor_email'=> $request->vendor_email,
            'vendor_phone'=> $request->vendor_phone,
            'pic_name'    => $request->pic_name,
            'is_active'   => true,
            'created_by'  => Auth::guard('agent')->id(),
            'created_at'  => now(), 'updated_at' => now(),
        ]);
        AuditService::logChange('vendors', $id, 'CREATE', null, $request->all());
        return back()->with('success', 'Vendor added.');
    }

    public function toggleVendor(string $id)
    {
        $vendor = DB::table('vendors')->where('vendor_id', $id)->first();
        DB::table('vendors')->where('vendor_id', $id)->update(['is_active' => ! $vendor->is_active, 'updated_at' => now()]);
        AuditService::logChange('vendors', $id, 'UPDATE', ['is_active' => $vendor->is_active], ['is_active' => ! $vendor->is_active]);
        return back()->with('success', 'Vendor status updated.');
    }

    // -------------------------------------------------------
    // PRODUCTS
    // -------------------------------------------------------
    public function products()
    {
        $products = DB::table('products as p')
            ->join('vendors as v', 'v.vendor_id', '=', 'p.vendor_id')
            ->select('p.*', 'v.vendor_name')
            ->orderBy('p.product_name')->get();
        $vendors = DB::table('vendors')->where('is_active', true)->orderBy('vendor_name')->get();
        return view('masterfile.products', compact('products', 'vendors'));
    }

    public function storeProduct(Request $request)
    {
        $request->validate([
            'vendor_id'    => ['required', 'exists:vendors,vendor_id'],
            'product_name' => ['required', 'string', 'max:200'],
            'product_code' => ['required', 'string', 'max:20', 'unique:products,product_code'],
            'product_type' => ['required', 'in:MOTOR,PERSONAL_ACCIDENT,FIRE,OTHER'],
        ]);

        $id = Str::uuid()->toString();
        DB::table('products')->insert([
            'product_id'   => $id,
            'vendor_id'    => $request->vendor_id,
            'product_name' => $request->product_name,
            'product_code' => strtoupper($request->product_code),
            'product_type' => $request->product_type,
            'description'  => $request->description,
            'campaign_start'=> $request->campaign_start,
            'campaign_end'  => $request->campaign_end,
            'is_active'    => true,
            'created_by'   => Auth::guard('agent')->id(),
            'created_at'   => now(), 'updated_at' => now(),
        ]);
        AuditService::logChange('products', $id, 'CREATE', null, $request->all());
        return back()->with('success', 'Product added.');
    }

    public function toggleProduct(string $id)
    {
        $p = DB::table('products')->where('product_id', $id)->first();
        DB::table('products')->where('product_id', $id)->update(['is_active' => ! $p->is_active, 'updated_at' => now()]);
        return back()->with('success', 'Product status updated.');
    }

    // -------------------------------------------------------
    // COMMISSION STRUCTURES (fully flexible)
    // -------------------------------------------------------
    public function commissionStructures()
    {
        $structures = DB::table('commission_structures as cs')
            ->join('vendors as v',  'v.vendor_id',  '=', 'cs.vendor_id')
            ->join('products as p', 'p.product_id', '=', 'cs.product_id')
            ->select('cs.*', 'v.vendor_name', 'p.product_name')
            ->orderBy('v.vendor_name')->orderBy('p.product_name')
            ->get();
        $vendors  = DB::table('vendors')->where('is_active', true)->get();
        $products = DB::table('products')->where('is_active', true)->get();
        return view('masterfile.commission-structures', compact('structures', 'vendors', 'products'));
    }

    public function storeCommissionStructure(Request $request)
    {
        $request->validate([
            'vendor_id'            => ['required', 'exists:vendors,vendor_id'],
            'product_id'           => ['required', 'exists:products,product_id'],
            'commission_basis'     => ['required', 'in:PREMIUM_PCT,SUM_INSURED_PCT'],
            'total_commission_pct' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'introducer_pct'       => ['required', 'numeric', 'min:0'],
            'team_leader_pct'      => ['required', 'numeric', 'min:0'],
            'group_leader_pct'     => ['required', 'numeric', 'min:0'],
            'valid_from'           => ['required', 'date'],
            'valid_to'             => ['nullable', 'date', 'after:valid_from'],
        ]);

        // RULE: splits must sum to 100
        $sum = $request->introducer_pct + $request->team_leader_pct + $request->group_leader_pct;
        if (abs($sum - 100) > 0.001) {
            return back()->withErrors(['splits' => "Role splits must sum to 100%. Current total: {$sum}%"])->withInput();
        }

        $id = Str::uuid()->toString();
        DB::table('commission_structures')->insert([
            'structure_id'         => $id,
            'vendor_id'            => $request->vendor_id,
            'product_id'           => $request->product_id,
            'commission_basis'     => $request->commission_basis,
            'total_commission_pct' => $request->total_commission_pct,
            'introducer_pct'       => $request->introducer_pct,
            'team_leader_pct'      => $request->team_leader_pct,
            'group_leader_pct'     => $request->group_leader_pct,
            'valid_from'           => $request->valid_from,
            'valid_to'             => $request->valid_to,
            'is_active'            => true,
            'created_by'           => Auth::guard('agent')->id(),
            'created_at'           => now(), 'updated_at' => now(),
        ]);
        AuditService::logChange('commission_structures', $id, 'CREATE', null, $request->all());
        return back()->with('success', 'Commission structure saved.');
    }

    public function toggleCommissionStructure(string $id)
    {
        $cs = DB::table('commission_structures')->where('structure_id', $id)->first();
        DB::table('commission_structures')->where('structure_id', $id)
            ->update(['is_active' => ! $cs->is_active, 'updated_at' => now()]);
        return back()->with('success', 'Commission structure status updated.');
    }

    // -------------------------------------------------------
    // GROUPS master
    // -------------------------------------------------------
    public function groups()
    {
        $groups = DB::table('groups')->orderBy('group_code')->get();
        return view('masterfile.groups', compact('groups'));
    }

    public function storeGroup(Request $request)
    {
        $request->validate([
            'group_name'  => ['required', 'string', 'max:200'],
            'group_code'  => ['required', 'string', 'max:20', 'unique:groups,group_code'],
            'group_email' => ['required', 'email'],
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
            'created_at'         => now(), 'updated_at' => now(),
        ]);
        AuditService::logChange('groups', $id, 'CREATE', null, $request->all());
        return back()->with('success', 'Group created.');
    }

    // -------------------------------------------------------
    // REWARD POINTS RATES
    // -------------------------------------------------------
    public function rewardRates()
    {
        $rates   = DB::table('reward_points_rates as r')
            ->leftJoin('vendors as v',  'v.vendor_id',  '=', 'r.vendor_id')
            ->leftJoin('products as p', 'p.product_id', '=', 'r.product_id')
            ->select('r.*', 'v.vendor_name', 'p.product_name')
            ->orderByDesc('r.created_at')->get();
        $vendors  = DB::table('vendors')->where('is_active', true)->get();
        $products = DB::table('products')->where('is_active', true)->get();
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
            'created_at'    => now(), 'updated_at' => now(),
        ]);
        AuditService::logChange('reward_points_rates', $id, 'CREATE', null, $request->all());
        return back()->with('success', 'Reward rate added.');
    }
}
