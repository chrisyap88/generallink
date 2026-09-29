<?php

// NEW 28 Sep 2026 — per Chris (master spec §96.22): master files for the
// member profile.
//  Member Pick Lists  — Gender, Race, Religion, Nationality, Marital Status,
//                       Dietary Preference, Affiliation Type.
//  Membership Plans   — per CBE group: plan name, fee, period (Free / Yearly /
//                       One-time / Lifetime).
// One screen each: choose the list (or CBE group) first, then the rows with
// Add / Edit on the same screen; rows never deleted, only switched off.

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminMemberMastersController extends Controller
{
    public const LISTS = ['MEMTYPE', 'GENDER', 'RACE', 'RELIGION', 'NATIONALITY', 'MARITAL', 'DIET', 'AFFTYPE'];

    private function adminOnly(): void
    {
        abort_if(Auth::guard('agent')->user()?->role !== 'ADMIN', 403);
    }

    public function lists(Request $request)
    {
        $this->adminOnly();
        $list = in_array($request->get('list'), self::LISTS, true) ? $request->get('list') : 'RACE';
        $rows = DB::table('member_profile_options')->where('list_code', $list)->orderBy('sort_order')->get();

        $ret = (string) $request->get('return');

        return view('admin.member-file.pick-lists', ['list' => $list, 'rows' => $rows, 'lists' => self::LISTS,
            'ret' => str_starts_with($ret, url('/')) ? $ret : '']);
    }

    public function saveOption(Request $request)
    {
        $this->adminOnly();
        $request->validate(['list' => ['required', 'in:'.implode(',', self::LISTS)], 'label' => ['required', 'string', 'max:120'], 'label_zh' => ['nullable', 'string', 'max:120']]);
        $list = $request->get('list');
        $data = ['label' => trim($request->label), 'label_zh' => trim((string) $request->label_zh) ?: null, 'is_active' => $request->boolean('is_active', true), 'updated_at' => now()];
        if ($request->filled('id')) {
            DB::table('member_profile_options')->where('id', $request->id)->where('list_code', $list)->update($data);
        } else {
            $code = strtoupper(Str::slug($request->label, '_')) ?: 'ITEM';
            $base = $code;
            $i = 1;
            while (DB::table('member_profile_options')->where('list_code', $list)->where('code', $code)->exists()) {
                $code = $base.'_'.(++$i);
            }
            DB::table('member_profile_options')->insert($data + ['id' => (string) Str::uuid(), 'list_code' => $list, 'code' => substr($code, 0, 30),
                'sort_order' => 1 + (int) DB::table('member_profile_options')->where('list_code', $list)->max('sort_order'), 'created_at' => now()]);
        }

        return redirect()->route('admin.member-pick-lists.index', array_filter(['list' => $list, 'return' => $request->get('return')]))->with('saved', true);
    }

    public function plans(Request $request)
    {
        $this->adminOnly();
        $groups = DB::table('group_labels')->where('group_type', 'CBE')->orderBy('group_name')->get(['group_label_id', 'group_name']);
        $group = $groups->firstWhere('group_label_id', $request->get('group')) ?? $groups->first();
        $rows = $group ? DB::table('cbe_membership_plans')->where('group_label_id', $group->group_label_id)->orderBy('sort_order')->get() : collect();

        $types = DB::table('member_profile_options')->where('list_code', 'MEMTYPE')->where('is_active', true)->orderBy('sort_order')->get(['id', 'label']);

        return view('admin.member-file.plans', ['groups' => $groups, 'group' => $group, 'rows' => $rows, 'types' => $types]);
    }

    public function savePlan(Request $request)
    {
        $this->adminOnly();
        $request->validate([
            'group' => ['required', 'exists:group_labels,group_label_id'],
            'plan_name' => ['required', 'string', 'max:120'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'period' => ['required', 'in:FREE,YEARLY,ONE_TIME,LIFETIME'],
        ]);
        $data = ['plan_name' => trim($request->plan_name), 'fee' => $request->period === 'FREE' ? 0 : (float) $request->fee,
            'period' => $request->period, 'is_active' => $request->boolean('is_active', true), 'updated_at' => now(),
            'membership_type_id' => $request->get('membership_type_id') ?: null];
        if ($request->filled('id')) {
            DB::table('cbe_membership_plans')->where('id', $request->id)->where('group_label_id', $request->group)->update($data);
        } else {
            DB::table('cbe_membership_plans')->insert($data + ['id' => (string) Str::uuid(), 'group_label_id' => $request->group,
                'sort_order' => 1 + (int) DB::table('cbe_membership_plans')->where('group_label_id', $request->group)->max('sort_order'), 'created_at' => now()]);
        }

        return redirect()->route('admin.membership-plans.index', ['group' => $request->group])->with('saved', true);
    }
}
