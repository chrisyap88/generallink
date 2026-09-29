<?php

// NEW 28 Sep 2026 — per Chris (master spec §96.22): Member Maintenance for
// ALL CBEs. Standard Add | Search / View / Edit.
//  Add New Member  = the person only (3 screens: Profile / Contact & Address /
//                    Preferences & Instructions) + duplicate check on Save;
//                    no CBE is assigned on Add.
//  Search          = CBE Group (optional) → HQ → State → Branch → City →
//                    Postcode → Name → Mobile → Race → Religion → Gender →
//                    Birthday Month → Position → Plan → Fee Status → GO.
//  View / Edit     = the same 3 screens + Affiliation Register (search-first
//                    Add Affiliation, End, Record Payment) + Committee
//                    Positions (read-only, from every CBE's Committee tab).

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CbeTierService;
use App\Services\MemberFileService as MF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminMemberFileController extends Controller
{
    // CHANGED 28 Sep 2026 — per Chris (item 36): a CBE officer can use the
    // member file for HIS OWN entity (its sub-entities and affiliated
    // entities). Admin sees every CBE. Race / Religion / NRIC are Admin only
    // (item 19). Returns null scope for Admin, else the officer's node ids.
    private ?array $scopeCache = null;

    private function isAdmin(): bool
    {
        return Auth::guard('agent')->user()?->role === 'ADMIN';
    }

    private function scope(): ?array
    {
        if ($this->isAdmin()) {
            return null;
        }
        if ($this->scopeCache !== null) {
            return $this->scopeCache;
        }
        $nodeIds = DB::table('cbe_node_officers')->where('agent_id', Auth::guard('agent')->id())->where('is_active', true)->pluck('node_id')->all();
        abort_if(! $nodeIds, 403);
        $ids = DB::table('cbe_hierarchy_nodes')->where(function ($w) use ($nodeIds) {
            $w->whereIn('node_id', $nodeIds);
            foreach ($nodeIds as $n) {
                $w->orWhere('hierarchy_path', 'like', '%/'.$n.'/%');
            }
        })->pluck('node_id')->all();
        $ids = array_values(array_unique(array_merge($ids, DB::table('cbe_hierarchy_nodes')->whereIn('affiliated_node_id', $ids ?: ['#'])->pluck('node_id')->all())));

        return $this->scopeCache = $ids;
    }

    private function adminOnly(): void
    {
        // admin OR officer may enter; what an officer may touch is checked per action
        $this->scope();
    }

    private function inScope(?string $nodeId): bool
    {
        $s = $this->scope();

        return $s === null || ($nodeId && in_array($nodeId, $s, true));
    }

    // may the current user open this person?
    private function canSeePerson(string $agentId): bool
    {
        $s = $this->scope();
        if ($s === null) {
            return true;
        }
        if (DB::table('agents')->where('agent_id', $agentId)->where('created_by', Auth::guard('agent')->id())->exists()) {
            return true;
        }
        if (DB::table('cbe_group_memberships')->where('agent_id', $agentId)->whereIn('cbe_node_id', $s)->exists()) {
            return true;
        }
        if (request()->filled('add_node') && in_array(request('add_node'), $s, true)) {
            return true;   // opened from "+ Add Member" of his own entity (duplicate found)
        }

        return false;
    }

    private function officerGroupId(): ?string
    {
        $s = $this->scope();
        if ($s === null) {
            return null;
        }
        $n = DB::table('cbe_node_officers')->where('agent_id', Auth::guard('agent')->id())->where('is_active', true)->value('node_id');

        return DB::table('cbe_hierarchy_nodes')->where('node_id', $n)->value('group_label_id');
    }

    private function options(): array
    {
        $zh = app()->getLocale() === 'zh';
        $opt = fn ($list) => DB::table('member_profile_options')->where('list_code', $list)->where('is_active', true)->orderBy('sort_order')->get()
            ->map(fn ($o) => (object) ['id' => $o->id, 'code' => $o->code, 'label' => ($zh && $o->label_zh) ? $o->label_zh : $o->label]);
        $master = fn ($t, $id) => DB::table($t)->where('is_active', true)->orderBy('description')->get([$id.' as id', 'code', 'description as label']);

        return [
            'genders' => $opt('GENDER'), 'races' => $opt('RACE'), 'religions' => $opt('RELIGION'), 'nationalities' => $opt('NATIONALITY'),
            'maritals' => $opt('MARITAL'), 'diets' => $opt('DIET'), 'affTypes' => $opt('AFFTYPE'), 'memTypes' => $opt('MEMTYPE'),
            'occupationGroups' => $master('occupation_groups', 'occupation_group_id'),
            'customerTypes' => $master('customer_types', 'type_id'),
            'customerCategories' => $master('customer_categories', 'category_id'),
            'sources' => $master('customer_sources', 'source_id'),
            // NEW 28 Sep 2026 — item 15 Customer Status (Customer Status master), item 29 relationship list
            'customerStatuses' => DB::table('customer_statuses')->where('is_active', true)->orderBy('description')->get(['status_id as id', 'code', 'description as label']),
            'relations' => $opt('RELATION'),
            // item 19: Race / Religion / NRIC visible to Admin only
            'canSensitive' => $this->isAdmin(),
        ];
    }

    public function landing()
    {
        $this->adminOnly();

        // CHANGED 28 Sep 2026 — per Chris: choose the CBE first, then Add | Search / View / Edit
        $groups = DB::table('group_labels')->where('group_type', 'CBE')->when($this->officerGroupId(), fn ($q, $g) => $q->where('group_label_id', $g))->orderBy('group_name')->get(['group_label_id', 'group_name']);

        return view('admin.member-file.landing', ['groups' => $groups, 'group' => $groups->firstWhere('group_label_id', request('group'))]);
    }

    public function create()
    {
        $this->adminOnly();

        return view('admin.member-file.form', array_merge($this->options(), ['person' => null, 'register' => [], 'committee' => collect(), 'plans' => collect()]));
    }

    private function personFields(Request $request): array
    {
        $f = fn ($k) => ($v = trim((string) $request->get($k))) === '' ? null : $v;

        return [
            'full_name' => $f('full_name'), 'second_name' => $f('second_name'), 'nick_name' => $f('nick_name'),
            'date_of_birth' => $f('date_of_birth'), 'gender_id' => $f('gender_id'), 'race_id' => $f('race_id'), 'religion_id' => $f('religion_id'),
            'nationality_id' => $f('nationality_id'), 'marital_status_id' => $f('marital_status_id'),
            'occupation_group_id' => $f('occupation_group_id'), 'occupation' => $f('occupation'),
            'customer_type_id' => $f('customer_type_id'), 'customer_category_id' => $f('customer_category_id'), 'source_id' => $f('source_id'),
            'phone' => $f('phone'), 'office_phone' => $f('office_phone'), 'preferred_language' => strtoupper((string) $f('preferred_language')) ?: 'EN',
            'address' => $f('address'), 'postcode' => $f('postcode'), 'city' => $f('city'), 'state' => $f('state'),
            'dietary_id' => $f('dietary_id'), 'personal_instructions' => $f('personal_instructions'),
            'customer_status_id' => $f('customer_status_id'),
        ];
    }

    private function validatePerson(Request $request): ?array
    {
        $errors = [];
        if (trim((string) $request->get('full_name')) === '') {
            $errors['full_name'] = __('member_file.err_name');
        }
        if (MF::normPhone($request->get('phone')) === '') {
            $errors['phone'] = __('member_file.err_phone');
        }
        $sensitive = $request->filled('race_id') || $request->filled('religion_id') || $request->filled('nric');
        if ($sensitive && ! $request->boolean('pdpa_consent')) {
            $errors['pdpa_consent'] = __('member_file.err_pdpa');
        }

        return $errors ?: null;
    }

    public function store(Request $request)
    {
        $this->adminOnly();
        if (! $this->isAdmin() && ! $request->filled('add_node')) {
            // officer: the new / found person goes straight to Add Affiliation at his own entity
            $request->merge(['add_node' => DB::table('cbe_node_officers')->where('agent_id', Auth::guard('agent')->id())->where('is_active', true)->value('node_id')]);
        }
        if ($e = $this->validatePerson($request)) {
            return back()->withErrors($e)->withInput();
        }
        $dups = MF::duplicates($request->only(['phone', 'email', 'nric', 'full_name']));
        if ($dups['exact']->isNotEmpty()) {
            // same Mobile / NRIC / Email = the same person — never saved twice
            return view('admin.member-file.duplicates', ['exact' => $dups['exact'], 'sameName' => collect(), 'input' => $request->all()]);
        }
        if ($dups['sameName']->isNotEmpty() && ! $request->boolean('save_anyway')) {
            return view('admin.member-file.duplicates', ['exact' => collect(), 'sameName' => $dups['sameName'], 'input' => $request->all()]);
        }

        $email = strtolower(trim((string) $request->get('email')));
        if ($email === '') {
            $email = 'member-'.strtolower(Str::random(10)).'@noemail.generallink.local';
        }
        $agentId = (string) Str::uuid();
        do {
            $agentCode = 'CBE-'.strtoupper(Str::random(8));
        } while (DB::table('agents')->where('agent_code', $agentCode)->exists());
        $fields = $this->personFields($request);
        if (! $this->isAdmin()) {
            unset($fields['race_id'], $fields['religion_id']);   // item 19 — Admin only
        }
        $row = array_merge($fields, [
            'agent_id' => $agentId, 'agent_code' => $agentCode, 'email' => $email,
            'password_hash' => Hash::make(Str::random(32)), 'role' => 'INTRODUCER', 'status' => 'ACTIVE',
            'parent_id' => null, 'hierarchy_path' => '/'.$agentId.'/', 'qr_code_token' => Str::random(10),
            'pdpa_consent_at' => $request->boolean('pdpa_consent') ? now() : null,
            'created_by' => Auth::guard('agent')->id(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($request->filled('nric') && $this->isAdmin()) {
            $row['nric_encrypted'] = encrypt(trim($request->nric));
            $row['nric_hash'] = hash('sha256', preg_replace('/\D/', '', $request->nric) ?: trim($request->nric));
        }
        DB::table('agents')->insert($row);
        // NEW 28 Sep 2026 — item 26: started from Donor Link › Add as New Member
        if ($request->filled('link_donor') && DB::table('cbe_donors')->where('donor_id', $request->link_donor)->whereNull('agent_id')->exists()) {
            MF::linkDonor($request->link_donor, $agentId, null);
            $ret = (string) $request->get('return');
            if (str_starts_with($ret, url('/'))) {
                return redirect()->to($ret)->with('member_saved', __('member_file.donor_linked'));
            }
        }

        // NEW 28 Sep 2026 — started from an entity's "+ Add Member Here": go straight
        // to this person's Add Affiliation with that entity already picked.
        if ($request->filled('add_node') && DB::table('cbe_hierarchy_nodes')->where('node_id', $request->add_node)->exists()) {
            return redirect()->route('admin.member-file.edit', ['id' => $agentId, 'panel' => 'register', 'add_node' => $request->add_node, 'back' => $request->get('return'), 'return' => $request->get('return')])
                ->with('member_saved', trim((string) $request->full_name));
        }

        return redirect()->route('admin.member-file.saved', array_filter(['id' => $agentId, 'group' => $request->get('add_group')]));
    }

    public function saved(string $id)
    {
        $this->adminOnly();
        $p = DB::table('agents')->where('agent_id', $id)->first();
        abort_if(! $p || ! $this->canSeePerson($id), 404);

        return view('admin.member-file.saved', ['person' => $p]);
    }

    public function index(Request $request)
    {
        $this->adminOnly();
        $groups = DB::table('group_labels')->where('group_type', 'CBE')->orderBy('group_name')->get(['group_label_id', 'group_name']);
        if ($og = $this->officerGroupId()) {   // officer: his own CBE only
            $groups = $groups->where('group_label_id', $og)->values();
            $request->merge(['group' => $og]);
        }
        $group = $request->filled('group') ? $groups->firstWhere('group_label_id', $request->get('group')) : null;

        // HQ / State / Branch boxes for the chosen CBE (from its entities' uplines)
        $tierOptions = ['hq' => [], 'state' => [], 'branch' => []];
        $groupNodeIds = [];
        if ($group) {
            $groupNodeIds = DB::table('cbe_hierarchy_nodes')->where('group_label_id', $group->group_label_id)->pluck('node_id')->all();
            $ups = [];
            foreach (DB::table('cbe_hierarchy_nodes')->whereIn('node_id', $groupNodeIds ?: ['#'])->pluck('hierarchy_path') as $path) {
                foreach (array_filter(explode('/', (string) $path)) as $a) {
                    $ups[$a] = true;
                }
            }
            foreach (array_chunk(array_keys($ups), 1000) as $chunk) {
                foreach (DB::table('cbe_hierarchy_nodes')->whereIn('node_id', $chunk)->get(['node_id', 'level_id', 'node_name', 'node_code', 'hierarchy_path']) as $u) {
                    $t = CbeTierService::tierOfNode($u);
                    if (isset($tierOptions[$t])) {
                        $tierOptions[$t][] = ['v' => $u->node_name, 'l' => (string) $u->node_code, 'id' => $u->node_id, 'path' => (string) $u->hierarchy_path];
                    }
                }
            }
            foreach ($tierOptions as $k => $l) {
                $tierOptions[$k] = collect($l)->sortBy('v')->values()->all();
            }
        }

        $searched = $request->has('go');
        $rows = null;
        if ($searched) {
            $q = DB::table('agents as a')->where('a.is_deleted', false)
                ->whereNotIn('a.role', ['ADMIN'])
                ->where(function ($w) {   // members: anyone in the member file (linked to a CBE, or added here)
                    $w->whereExists(fn ($x) => $x->select(DB::raw(1))->from('cbe_group_memberships as m')->whereColumn('m.agent_id', 'a.agent_id'))
                        ->orWhere('a.agent_code', 'like', 'CBE-%');
                });
            // entity scope: CBE group, then HQ / State / Branch pick
            $scopeIds = null;
            if ($group) {
                $scopeIds = $groupNodeIds;
                foreach (['hq_id', 'state_id', 'branch_id'] as $f) {
                    if ($pick = (string) $request->get($f)) {
                        $scopeIds = DB::table('cbe_hierarchy_nodes')->where(fn ($w) => $w->where('node_id', $pick)->orWhere('hierarchy_path', 'like', '%/'.$pick.'/%'))->pluck('node_id')->all();
                        // entities affiliated to the picked branch count too
                        $scopeIds = array_merge($scopeIds, DB::table('cbe_hierarchy_nodes')->whereIn('affiliated_node_id', $scopeIds ?: ['#'])->pluck('node_id')->all());
                    }
                }
            }
            if (($os = $this->scope()) !== null) {   // officer: only his own entities
                $scopeIds = array_values(array_intersect($scopeIds ?? $os, $os));
            }
            if ($scopeIds !== null || $request->filled('plan_id') || $request->filled('fee_status')) {
                $q->whereExists(function ($x) use ($scopeIds, $request) {
                    $x->select(DB::raw(1))->from('cbe_group_memberships as m')->join('cbe_member_role_tags as t', 't.membership_id', '=', 'm.membership_id')
                        ->whereColumn('m.agent_id', 'a.agent_id')->where('t.status', 'ACTIVE')
                        ->when($scopeIds !== null, fn ($w) => $w->whereIn('m.cbe_node_id', $scopeIds ?: ['#']))
                        ->when($request->filled('plan_id'), fn ($w) => $w->where('t.plan_id', $request->get('plan_id')));
                });
            }
            // NEW 28 Sep 2026 — per Chris: Membership Type (Individual / Family / SBE …) of an active Member line
            if ($request->filled('membership_type_id')) {
                $q->whereExists(fn ($x) => $x->select(DB::raw(1))->from('cbe_group_memberships as m2')
                    ->join('cbe_member_role_tags as t2', 't2.membership_id', '=', 'm2.membership_id')
                    ->join('cbe_membership_plans as p2', 'p2.id', '=', 't2.plan_id')
                    ->whereColumn('m2.agent_id', 'a.agent_id')->where('t2.status', 'ACTIVE')->where('t2.tag', 'MEMBER')
                    ->where('p2.membership_type_id', $request->get('membership_type_id'))
                    ->when($scopeIds !== null, fn ($w) => $w->whereIn('m2.cbe_node_id', $scopeIds ?: ['#'])));
            }
            if ($c = trim((string) $request->get('city'))) {
                $q->where('a.city', 'like', '%'.$c.'%');
            }
            $from = preg_replace('/\D/', '', (string) $request->get('pc_from'));
            $to = preg_replace('/\D/', '', (string) $request->get('pc_to'));
            if ($from !== '' || $to !== '') {
                $q->whereRaw('CAST(a.postcode AS UNSIGNED) BETWEEN ? AND ?', [$from !== '' ? (int) $from : 0, $to !== '' ? (int) $to : 99999]);
            }
            if ($n = trim((string) $request->get('name'))) {
                $q->where(fn ($w) => $w->where('a.full_name', 'like', '%'.$n.'%')->orWhere('a.nick_name', 'like', '%'.$n.'%')->orWhere('a.second_name', 'like', '%'.$n.'%'));
            }
            if (($ph = MF::normPhone($request->get('mobile'))) !== '') {
                $q->whereRaw("REPLACE(REPLACE(REPLACE(a.phone,'-',''),' ',''),'+','') LIKE ?", ['%'.$ph.'%']);
            }
            foreach (($this->isAdmin() ? ['race_id', 'religion_id', 'gender_id'] : ['gender_id']) as $f) {
                if ($request->filled($f)) {
                    $q->where('a.'.$f, $request->get($f));
                }
            }
            if ($request->filled('birth_month')) {
                $q->whereMonth('a.date_of_birth', (int) $request->get('birth_month'));
            }
            if ($pos = trim((string) $request->get('position'))) {
                $q->whereExists(fn ($x) => $x->select(DB::raw(1))->from('group_committee_members as c')->join('cbe_committee_position_types as pt', 'pt.id', '=', 'c.position_type_id')
                    ->whereColumn('c.agent_id', 'a.agent_id')->where('pt.position_label', 'like', '%'.$pos.'%')
                    ->where(fn ($w) => $w->whereNull('c.term_end_date')->orWhere('c.term_end_date', '>=', now()->toDateString())));
            }
            $q->orderBy('a.full_name');
            $all = $q->get(['a.agent_id', 'a.full_name', 'a.nick_name', 'a.second_name', 'a.phone', 'a.city', 'a.postcode']);
            if ($fs = $request->get('fee_status')) {
                $all = $all->filter(function ($p) use ($fs) {
                    foreach (MF::register($p->agent_id) as $row) {
                        foreach ($row['lines'] as $l) {
                            if ($l['status'] === 'ACTIVE' && $l['fee_status'] === $fs) {
                                return true;
                            }
                        }
                    }
                    return false;
                })->values();
            }
            // record range + rows that fit the screen (per_page set by the page)
            $per = max(3, min(50, (int) $request->get('per_page', 12)));
            $page = max(1, (int) $request->get('page', 1));
            $slice = $all->slice(($page - 1) * $per, $per)->values()->map(function ($p) {
                $p->affiliated = MF::affiliatedTo($p->agent_id);
                $p->positions = MF::committeePositions($p->agent_id)->where('current', true)->pluck('position_label')->unique()->values()->all();
                return $p;
            });
            $rows = new \Illuminate\Pagination\LengthAwarePaginator($slice, $all->count(), $per, $page, ['path' => $request->url(), 'query' => $request->except('page')]);
        }

        // item 8 — type-ahead lists (members' own cities / postcodes, committee positions)
        $cityList = DB::table('agents')->whereNotNull('city')->where('city', '!=', '')->where(fn ($w) => $w->where('agent_code', 'like', 'CBE-%')
            ->orWhereExists(fn ($x) => $x->select(DB::raw(1))->from('cbe_group_memberships as m')->whereColumn('m.agent_id', 'agents.agent_id')))
            ->distinct()->orderBy('city')->limit(500)->pluck('city');
        $pcList = DB::table('agents')->whereNotNull('postcode')->where('postcode', '!=', '')->where(fn ($w) => $w->where('agent_code', 'like', 'CBE-%')
            ->orWhereExists(fn ($x) => $x->select(DB::raw(1))->from('cbe_group_memberships as m')->whereColumn('m.agent_id', 'agents.agent_id')))
            ->select('postcode', DB::raw('MAX(city) as city'))->groupBy('postcode')->orderBy('postcode')->limit(500)->get()
            ->map(fn ($r) => ['v' => $r->postcode, 'l' => (string) $r->city])->all();
        $positionList = DB::table('cbe_committee_position_types')->where('is_active', true)
            ->when($group, fn ($q) => $q->where('group_label_id', $group->group_label_id))->orderBy('position_label')->pluck('position_label')->unique()->values();

        return view('admin.member-file.index', array_merge($this->options(), [
            'cityList' => $cityList, 'pcList' => $pcList, 'positionList' => $positionList,
            'groups' => $groups, 'group' => $group, 'tierOptions' => $tierOptions, 'rows' => $rows, 'searched' => $searched,
            'plans' => $group ? DB::table('cbe_membership_plans')->where('group_label_id', $group->group_label_id)->where('is_active', true)->orderBy('sort_order')->get() : collect(),
        ]));
    }

    public function edit(Request $request, string $id)
    {
        $this->adminOnly();
        $p = DB::table('agents')->where('agent_id', $id)->where('is_deleted', false)->first();
        abort_if(! $p || ! $this->canSeePerson($id), 404);

        return view('admin.member-file.form', array_merge($this->options(), [
            'person' => $p,
            'register' => MF::register($id),
            'committee' => MF::committeePositions($id),
            'typeLabels' => MF::typeLabels(),
            'scopeIds' => $this->scope(),
            'practitionerTypes' => DB::table('cbe_practitioner_types')->where('is_active', true)->orderBy('sort_order')->get(['id', 'type_label']),
            'addNode' => $request->filled('add_node') ? DB::table('cbe_hierarchy_nodes as n')->join('group_labels as g', 'g.group_label_id', '=', 'n.group_label_id')
                ->where('n.node_id', $request->get('add_node'))->first(['n.node_id', 'n.node_name', 'n.node_name_zh', 'g.group_name']) : null,
        ]));
    }

    public function update(Request $request, string $id)
    {
        $this->adminOnly();
        $p = DB::table('agents')->where('agent_id', $id)->first();
        abort_if(! $p || ! $this->canSeePerson($id), 404);
        if ($e = $this->validatePerson($request)) {
            return back()->withErrors($e)->withInput();
        }
        $dups = MF::duplicates($request->only(['phone', 'email', 'nric']), $id);
        if ($dups['exact']->isNotEmpty()) {
            return back()->withErrors(['phone' => __('member_file.err_dup_on_edit', ['name' => $dups['exact']->first()->full_name])])->withInput();
        }
        $row = $this->personFields($request);
        if (! $this->isAdmin()) {
            unset($row['race_id'], $row['religion_id']);   // item 19 — Admin only
            $request->request->remove('nric');
        }
        $email = strtolower(trim((string) $request->get('email')));
        if ($email !== '') {
            $row['email'] = $email;
        }
        if ($request->filled('nric')) {
            $row['nric_encrypted'] = encrypt(trim($request->nric));
            $row['nric_hash'] = hash('sha256', preg_replace('/\D/', '', $request->nric) ?: trim($request->nric));
        }
        if ($request->boolean('pdpa_consent') && ! $p->pdpa_consent_at) {
            $row['pdpa_consent_at'] = now();
        }
        $row['updated_at'] = now();
        DB::table('agents')->where('agent_id', $id)->update($row);
        $ret = (string) $request->get('return');

        return (str_starts_with($ret, url('/')) ? redirect()->to($ret) : redirect()->route('admin.member-file.index'))
            ->with('member_saved', $row['full_name']);
    }

    // NEW 28 Sep 2026 — item 8: Name / Mobile type-ahead (Member ID · name · mobile), scoped for an officer
    public function personLookup(Request $request)
    {
        $this->adminOnly();
        $q = trim((string) $request->get('q'));
        if ($q === '') {
            return response()->json([]);
        }
        $rows = DB::table('agents as a')->where('a.is_deleted', false)->whereNotIn('a.role', ['ADMIN'])
            ->where(fn ($w) => $w->where('a.agent_code', 'like', 'CBE-%')->orWhereExists(fn ($x) => $x->select(DB::raw(1))->from('cbe_group_memberships as m')->whereColumn('m.agent_id', 'a.agent_id')))
            ->when($request->get('by') === 'mobile',
                fn ($w) => $w->whereRaw("REPLACE(REPLACE(REPLACE(a.phone,'-',''),' ',''),'+','') LIKE ?", ['%'.MF::normPhone($q).'%']),
                fn ($w) => $w->where(fn ($x) => $x->where('a.full_name', 'like', '%'.$q.'%')->orWhere('a.nick_name', 'like', '%'.$q.'%')->orWhere('a.second_name', 'like', '%'.$q.'%')->orWhere('a.agent_code', 'like', '%'.$q.'%')))
            ->when($this->scope() !== null, fn ($w) => $w->where(fn ($x) => $x->where('a.created_by', Auth::guard('agent')->id())
                ->orWhereExists(fn ($y) => $y->select(DB::raw(1))->from('cbe_group_memberships as m2')->whereColumn('m2.agent_id', 'a.agent_id')->whereIn('m2.cbe_node_id', $this->scope() ?: ['#']))))
            ->when($request->filled('group') && $this->scope() === null, fn ($w) => $w->whereExists(fn ($y) => $y->select(DB::raw(1))->from('cbe_group_memberships as m3')->whereColumn('m3.agent_id', 'a.agent_id')->where('m3.group_label_id', $request->get('group'))))
            ->orderBy('a.full_name')->limit(15)->get(['a.agent_code as code', 'a.full_name as name', 'a.phone']);

        return response()->json($rows);
    }

    // ---- affiliation register ----
    public function entityLookup(Request $request)
    {
        $this->adminOnly();
        $q = trim((string) $request->get('q'));
        if (mb_strlen($q) < 1) {
            return response()->json([]);
        }
        $rows = DB::table('cbe_hierarchy_nodes as n')->join('group_labels as g', 'g.group_label_id', '=', 'n.group_label_id')
            ->leftJoin('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
            ->where(fn ($w) => $w->where('n.node_name', 'like', '%'.$q.'%')->orWhere('n.node_name_zh', 'like', '%'.$q.'%')->orWhere('n.node_code', 'like', '%'.$q.'%')->orWhere('g.group_name', 'like', '%'.$q.'%'))
            ->when($request->filled('group'), fn ($w) => $w->where('n.group_label_id', $request->get('group')))
            ->when($this->scope() !== null, fn ($w) => $w->whereIn('n.node_id', $this->scope() ?: ['#']))
            ->orderBy('g.group_name')->orderBy('n.node_name')->limit(30)
            ->get(['n.node_id', 'n.node_name', 'n.node_name_zh', 'n.node_code', 'n.city', 'g.group_name', 'g.group_label_id', 'l.level_name']);

        return response()->json($rows);
    }

    public function existing(Request $request, string $id)
    {
        $this->adminOnly();
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', (string) $request->get('node'))->first();
        abort_if(! $node || ! $this->inScope($node->node_id), 404);

        return response()->json([
            'existing' => array_values(MF::existingAt($id, $node->node_id)),
            'plans' => DB::table('cbe_membership_plans')->where('group_label_id', $node->group_label_id)->where('is_active', true)->orderBy('sort_order')->get(['id', 'plan_name', 'fee', 'period']),
        ]);
    }

    public function addAffiliation(Request $request, string $id)
    {
        $this->adminOnly();
        abort_if(! DB::table('agents')->where('agent_id', $id)->exists() || ! $this->inScope((string) $request->get('node_id')), 404);
        $n = MF::addTypes($id, (string) $request->get('node_id'), (array) $request->get('types', []), $request->get('plan_id'), 'STAFF');
        // NEW 28 Sep 2026 — added from an entity's Members tab: go back to that entity
        $back = (string) $request->get('back');
        if ($back !== '' && str_starts_with($back, url('/'))) {
            return redirect()->to($back)->with('member_saved', __('member_file.aff_added', ['count' => $n]));
        }

        return redirect()->route('admin.member-file.edit', ['id' => $id, 'panel' => 'register', 'return' => $request->get('return')])
            ->with('member_saved', __('member_file.aff_added', ['count' => $n]));
    }

    public function endLine(Request $request, string $tagId)
    {
        $this->adminOnly();
        $line = DB::table('cbe_member_role_tags as t')->join('cbe_group_memberships as m', 'm.membership_id', '=', 't.membership_id')->where('t.tag_id', $tagId)->first(['m.agent_id', 'm.cbe_node_id']);
        $agentId = $line->agent_id ?? null;
        abort_if(! $agentId || ! $this->inScope($line->cbe_node_id), 404);
        MF::endLine($tagId);

        return redirect()->route('admin.member-file.edit', ['id' => $agentId, 'panel' => 'register', 'return' => $request->get('return')])
            ->with('member_saved', __('member_file.aff_ended'));
    }

    public function payment(Request $request, string $tagId)
    {
        $this->adminOnly();
        $request->validate(['amount' => ['required', 'numeric', 'min:0'], 'paid_on' => ['required', 'date']]);
        $line = DB::table('cbe_member_role_tags as t')->join('cbe_group_memberships as m', 'm.membership_id', '=', 't.membership_id')->where('t.tag_id', $tagId)->first(['m.agent_id', 'm.cbe_node_id']);
        $agentId = $line->agent_id ?? null;
        abort_if(! $agentId || ! $this->inScope($line->cbe_node_id), 404);
        MF::recordPayment($tagId, (float) $request->amount, $request->paid_on, $request->get('receipt_no'), Auth::guard('agent')->id());

        return redirect()->route('admin.member-file.edit', ['id' => $agentId, 'panel' => 'register', 'return' => $request->get('return')])
            ->with('member_saved', __('member_file.payment_saved'));
    }

    // ---- NEW 28 Sep 2026 — item 25: + Practitioner from the member file ----
    public function practitionerAdd(Request $request, string $id)
    {
        $this->adminOnly();
        $request->validate(['node_id' => ['required'], 'practitioner_type_id' => ['required', 'exists:cbe_practitioner_types,id']]);
        abort_if(! $this->canSeePerson($id) || ! $this->inScope($request->node_id), 404);
        $pid = MF::addPractitioner($id, $request->node_id, $request->practitioner_type_id);

        return redirect()->route('admin.member-file.edit', ['id' => $id, 'panel' => 'register', 'return' => $request->get('return')])
            ->with('member_saved', __('member_file.practitioner_added'))->with('practitioner_profile', $pid);
    }

    // ---- item 29: Family membership ----
    public function familyAdd(Request $request, string $tagId)
    {
        $this->adminOnly();
        $line = DB::table('cbe_member_role_tags as t')->join('cbe_group_memberships as m', 'm.membership_id', '=', 't.membership_id')->where('t.tag_id', $tagId)->first(['m.agent_id', 'm.cbe_node_id']);
        abort_if(! $line || ! $this->inScope($line->cbe_node_id), 404);
        $who = DB::table('agents')->where('is_deleted', false)->where(fn ($w) => $w->where('agent_code', trim((string) $request->get('member')))->orWhere('agent_id', (string) $request->get('member_id')))->value('agent_id');
        $ok = $who && MF::addFamily($tagId, $who, $request->get('relationship') ?: null);

        return redirect()->route('admin.member-file.edit', ['id' => $line->agent_id, 'panel' => 'register', 'family' => $tagId, 'return' => $request->get('return')])
            ->with('member_saved', $ok ? __('member_file.family_added') : __('member_file.family_not_added'));
    }

    public function familyRemove(Request $request, string $familyId)
    {
        $this->adminOnly();
        $f = DB::table('cbe_membership_family as f')->join('cbe_member_role_tags as t', 't.tag_id', '=', 'f.principal_tag_id')
            ->join('cbe_group_memberships as m', 'm.membership_id', '=', 't.membership_id')->where('f.id', $familyId)->first(['m.agent_id', 'm.cbe_node_id', 'f.principal_tag_id']);
        abort_if(! $f || ! $this->inScope($f->cbe_node_id), 404);
        MF::removeFamily($familyId);

        return redirect()->route('admin.member-file.edit', ['id' => $f->agent_id, 'panel' => 'register', 'family' => $f->principal_tag_id, 'return' => $request->get('return')])
            ->with('member_saved', __('member_file.family_removed'));
    }

    // ---- item 29 / 26: company record ----
    public function companyLookup(Request $request)
    {
        $this->adminOnly();
        $q = trim((string) $request->get('q'));

        return response()->json($q === '' ? [] : DB::table('member_companies')->where(fn ($w) => $w->where('company_name', 'like', '%'.$q.'%')->orWhere('registration_no', 'like', '%'.$q.'%'))
            ->orderBy('company_name')->limit(15)->get(['company_id', 'company_name', 'registration_no']));
    }

    public function companySet(Request $request, string $tagId)
    {
        $this->adminOnly();
        $line = DB::table('cbe_member_role_tags as t')->join('cbe_group_memberships as m', 'm.membership_id', '=', 't.membership_id')->where('t.tag_id', $tagId)->first(['m.agent_id', 'm.cbe_node_id']);
        abort_if(! $line || ! $this->inScope($line->cbe_node_id), 404);
        $companyId = (string) $request->get('company_id');
        if ($companyId === '' || ! DB::table('member_companies')->where('company_id', $companyId)->exists()) {
            $request->validate(['company_name' => ['required', 'string', 'max:200']]);
            $companyId = MF::saveCompany(['company_name' => $request->company_name, 'registration_no' => $request->registration_no, 'contact_agent_id' => $line->agent_id], Auth::guard('agent')->id());
        }
        DB::table('cbe_member_role_tags')->where('tag_id', $tagId)->update(['company_id' => $companyId, 'updated_at' => now()]);

        return redirect()->route('admin.member-file.edit', ['id' => $line->agent_id, 'panel' => 'register', 'return' => $request->get('return')])
            ->with('member_saved', __('member_file.company_saved'));
    }
}
