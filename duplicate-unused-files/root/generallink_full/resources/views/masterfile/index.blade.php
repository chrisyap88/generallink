@extends('layouts.dashboard')
@section('title', 'Master File')
@section('page-title', 'Master File Management')

@section('content')

@if(session('success'))
<div style="background:#C6F6D5;border:1px solid #9AE6B4;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#22543D;font-size:13px">✓ {{ session('success') }}</div>
@endif
@if(session('error'))
<div style="background:#FED7D7;border:1px solid #FC8181;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#742A2A;font-size:13px">{{ session('error') }}</div>
@endif
@if($errors->has('splits'))
<div style="background:#FED7D7;border:1px solid #FC8181;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#742A2A;font-size:13px">{{ $errors->first('splits') }}</div>
@endif

{{-- Tab nav --}}
<div style="display:flex;gap:4px;border-bottom:2px solid #E2E8F0;margin-bottom:20px">
    @foreach([
        ['vendors','Vendors','ti-building-store'],
        ['products','Products','ti-file-certificate'],
        ['commissions','Commission Structures','ti-percentage'],
        ['groups','Groups','ti-sitemap'],
        ['rates','Reward Point Rates','ti-star'],
    ] as [$tab,$label,$icon])
    <button onclick="switchTab('{{ $tab }}')" id="tab-{{ $tab }}"
        style="padding:8px 16px;border:none;background:none;cursor:pointer;font-size:13px;
               border-bottom:2px solid transparent;margin-bottom:-2px;display:flex;align-items:center;gap:6px;color:#718096">
        <i class="ti {{ $icon }}"></i> {{ $label }}
    </button>
    @endforeach
</div>

{{-- ==================== VENDORS ==================== --}}
<div id="pane-vendors" class="tab-pane">
<div class="card" style="margin-bottom:16px">
    <div class="card-title"><i class="ti ti-plus" style="color:#0D5A8E"></i> Add Vendor</div>
    <form method="POST" action="{{ route('admin.masterfile.vendor.store') }}">
        @csrf
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:12px">
            <div>
                <label class="mf-label">Vendor name *</label>
                <input type="text" name="vendor_name" value="{{ old('vendor_name') }}" class="mf-input" required>
            </div>
            <div>
                <label class="mf-label">Vendor code * <span style="font-weight:400;color:#A0AEC0">(short, e.g. ALZ)</span></label>
                <input type="text" name="vendor_code" value="{{ old('vendor_code') }}" class="mf-input" required>
            </div>
            <div>
                <label class="mf-label">Email</label>
                <input type="email" name="vendor_email" value="{{ old('vendor_email') }}" class="mf-input">
            </div>
            <div>
                <label class="mf-label">Phone</label>
                <input type="text" name="vendor_phone" value="{{ old('vendor_phone') }}" class="mf-input">
            </div>
            <div>
                <label class="mf-label">Person in charge</label>
                <input type="text" name="pic_name" value="{{ old('pic_name') }}" class="mf-input">
            </div>
        </div>
        <button type="submit" class="mf-btn">Add Vendor</button>
    </form>
</div>
<div class="card">
    <div class="card-title"><i class="ti ti-building-store" style="color:#0D5A8E"></i> All Vendors ({{ count($vendors) }})</div>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead><tr style="background:#F7FAFC">
            @foreach(['Code','Name','Email','Phone','PIC','Status','Action'] as $h)
            <th style="padding:8px 10px;text-align:left;border-bottom:1px solid #E2E8F0;font-size:12px;color:#4A5568">{{ $h }}</th>
            @endforeach
        </tr></thead>
        <tbody>
            @forelse($vendors as $v)
            <tr style="border-bottom:1px solid #F7FAFC">
                <td style="padding:8px 10px;font-family:monospace;font-weight:600">{{ $v->vendor_code }}</td>
                <td style="padding:8px 10px;font-weight:500">{{ $v->vendor_name }}</td>
                <td style="padding:8px 10px;color:#718096">{{ $v->vendor_email ?? '—' }}</td>
                <td style="padding:8px 10px;color:#718096">{{ $v->vendor_phone ?? '—' }}</td>
                <td style="padding:8px 10px;color:#718096">{{ $v->pic_name ?? '—' }}</td>
                <td style="padding:8px 10px">
                    <span class="status-badge {{ $v->is_active ? 'status-active' : 'status-inactive' }}">
                        {{ $v->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td style="padding:8px 10px">
                    <form method="POST" action="{{ route('admin.masterfile.vendor.toggle', $v->vendor_id) }}" style="display:inline">
                        @csrf @method('PATCH')
                        <button type="submit" class="mf-toggle-btn">{{ $v->is_active ? 'Deactivate' : 'Activate' }}</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="padding:20px;text-align:center;color:#A0AEC0">No vendors yet</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
</div>

{{-- ==================== PRODUCTS ==================== --}}
<div id="pane-products" class="tab-pane" style="display:none">
<div class="card" style="margin-bottom:16px">
    <div class="card-title"><i class="ti ti-plus" style="color:#0D5A8E"></i> Add Product</div>
    <form method="POST" action="{{ route('admin.masterfile.product.store') }}">
        @csrf
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:12px">
            <div>
                <label class="mf-label">Vendor *</label>
                <select name="vendor_id" class="mf-input" required>
                    <option value="">— Select vendor —</option>
                    @foreach($vendors as $v)
                    <option value="{{ $v->vendor_id }}" {{ old('vendor_id') == $v->vendor_id ? 'selected':'' }}>{{ $v->vendor_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mf-label">Product name *</label>
                <input type="text" name="product_name" value="{{ old('product_name') }}" class="mf-input" required>
            </div>
            <div>
                <label class="mf-label">Product code *</label>
                <input type="text" name="product_code" value="{{ old('product_code') }}" class="mf-input" required>
            </div>
            <div>
                <label class="mf-label">Product type *</label>
                <select name="product_type" class="mf-input" required>
                    <option value="">— Select type —</option>
                    @foreach(['MOTOR'=>'Motor','PERSONAL_ACCIDENT'=>'Personal Accident','FIRE'=>'Fire','OTHER'=>'Other'] as $val=>$lbl)
                    <option value="{{ $val }}" {{ old('product_type') == $val ? 'selected':'' }}>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mf-label">Campaign start</label>
                <input type="date" name="campaign_start" value="{{ old('campaign_start') }}" class="mf-input">
            </div>
            <div>
                <label class="mf-label">Campaign end</label>
                <input type="date" name="campaign_end" value="{{ old('campaign_end') }}" class="mf-input">
            </div>
        </div>
        <button type="submit" class="mf-btn">Add Product</button>
    </form>
</div>
<div class="card">
    <div class="card-title"><i class="ti ti-file-certificate" style="color:#0D5A8E"></i> All Products ({{ count($products) }})</div>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead><tr style="background:#F7FAFC">
            @foreach(['Code','Product','Vendor','Type','Campaign','Status','Action'] as $h)
            <th style="padding:8px 10px;text-align:left;border-bottom:1px solid #E2E8F0;font-size:12px;color:#4A5568">{{ $h }}</th>
            @endforeach
        </tr></thead>
        <tbody>
            @forelse($products as $p)
            <tr style="border-bottom:1px solid #F7FAFC">
                <td style="padding:8px 10px;font-family:monospace;font-size:11px">{{ $p->product_code }}</td>
                <td style="padding:8px 10px;font-weight:500">{{ $p->product_name }}</td>
                <td style="padding:8px 10px;color:#718096">{{ $p->vendor_name }}</td>
                <td style="padding:8px 10px"><span style="background:#EBF8FF;color:#2B6CB0;padding:2px 7px;border-radius:4px;font-size:11px;font-weight:600">{{ $p->product_type }}</span></td>
                <td style="padding:8px 10px;font-size:12px;color:#718096">
                    {{ $p->campaign_start ? \Carbon\Carbon::parse($p->campaign_start)->format('d/m/Y') : '—' }}
                    {{ $p->campaign_end  ? ' – '.\Carbon\Carbon::parse($p->campaign_end)->format('d/m/Y') : '' }}
                </td>
                <td style="padding:8px 10px"><span class="status-badge {{ $p->is_active ? 'status-active':'status-inactive' }}">{{ $p->is_active ? 'Active':'Inactive' }}</span></td>
                <td style="padding:8px 10px">
                    <form method="POST" action="{{ route('admin.masterfile.product.toggle', $p->product_id) }}" style="display:inline">
                        @csrf @method('PATCH')
                        <button type="submit" class="mf-toggle-btn">{{ $p->is_active ? 'Deactivate':'Activate' }}</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="padding:20px;text-align:center;color:#A0AEC0">No products yet</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
</div>

{{-- ==================== COMMISSION STRUCTURES ==================== --}}
<div id="pane-commissions" class="tab-pane" style="display:none">
<div class="card" style="margin-bottom:16px">
    <div class="card-title"><i class="ti ti-plus" style="color:#0D5A8E"></i> Add Commission Structure</div>
    <div style="background:#EBF8FF;border:1px solid #BEE3F8;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#2C5282">
        <strong>Rule:</strong> Introducer % + Team Leader % + Group Leader % must equal exactly 100%.
        The total commission % applies to the selected basis (premium or sum insured).
    </div>
    <form method="POST" action="{{ route('admin.masterfile.commission.store') }}">
        @csrf
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:12px">
            <div>
                <label class="mf-label">Vendor *</label>
                <select name="vendor_id" class="mf-input" required>
                    <option value="">— Select vendor —</option>
                    @foreach($vendors as $v)
                    <option value="{{ $v->vendor_id }}">{{ $v->vendor_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mf-label">Product *</label>
                <select name="product_id" class="mf-input" required>
                    <option value="">— Select product —</option>
                    @foreach($products as $p)
                    <option value="{{ $p->product_id }}">{{ $p->product_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mf-label">Commission basis *</label>
                <select name="commission_basis" class="mf-input" required>
                    <option value="PREMIUM_PCT">% of Premium</option>
                    <option value="SUM_INSURED_PCT">% of Sum Insured</option>
                </select>
            </div>
            <div>
                <label class="mf-label">Total commission % * <span style="color:#A0AEC0;font-weight:400">(e.g. 10, 25)</span></label>
                <input type="number" name="total_commission_pct" step="0.0001" min="0.01" max="100" class="mf-input" required placeholder="e.g. 25">
            </div>
            <div>
                <label class="mf-label">Valid from *</label>
                <input type="date" name="valid_from" value="{{ date('Y-m-d') }}" class="mf-input" required>
            </div>
            <div>
                <label class="mf-label">Valid to <span style="color:#A0AEC0;font-weight:400">(leave blank = no expiry)</span></label>
                <input type="date" name="valid_to" class="mf-input">
            </div>
        </div>

        {{-- Role splits --}}
        <div style="background:#F7FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:14px;margin-bottom:14px">
            <div style="font-size:12px;font-weight:600;color:#4A5568;margin-bottom:10px">Role splits (must total 100%)</div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
                <div>
                    <label class="mf-label" style="color:#185FA5">Introducer % *</label>
                    <input type="number" name="introducer_pct" step="0.0001" min="0" max="100" class="mf-input" required placeholder="e.g. 60" oninput="updateSplitTotal()">
                </div>
                <div>
                    <label class="mf-label" style="color:#854F0B">Team Leader % *</label>
                    <input type="number" name="team_leader_pct" step="0.0001" min="0" max="100" class="mf-input" required placeholder="e.g. 25" oninput="updateSplitTotal()">
                </div>
                <div>
                    <label class="mf-label" style="color:#534AB7">Group Leader % *</label>
                    <input type="number" name="group_leader_pct" step="0.0001" min="0" max="100" class="mf-input" required placeholder="e.g. 15" oninput="updateSplitTotal()">
                </div>
            </div>
            <div id="split-total" style="margin-top:8px;font-size:12px;font-weight:600;color:#718096">Total: 0%</div>
        </div>

        <button type="submit" class="mf-btn">Save Commission Structure</button>
    </form>
</div>
<div class="card">
    <div class="card-title"><i class="ti ti-percentage" style="color:#0D5A8E"></i> All Commission Structures ({{ count($structures) }})</div>
    <table style="width:100%;border-collapse:collapse;font-size:12px">
        <thead><tr style="background:#F7FAFC">
            @foreach(['Vendor','Product','Basis','Total %','Introducer','Team Leader','Group Leader','Valid Period','Status','Action'] as $h)
            <th style="padding:8px;text-align:left;border-bottom:1px solid #E2E8F0;color:#4A5568;font-size:11px">{{ $h }}</th>
            @endforeach
        </tr></thead>
        <tbody>
            @forelse($structures as $s)
            <tr style="border-bottom:1px solid #F7FAFC">
                <td style="padding:8px;font-weight:500">{{ $s->vendor_name }}</td>
                <td style="padding:8px">{{ $s->product_name }}</td>
                <td style="padding:8px"><span style="font-size:10px;background:#E9D8FD;color:#44337A;padding:2px 6px;border-radius:4px">{{ $s->commission_basis }}</span></td>
                <td style="padding:8px;font-weight:700;color:#059669">{{ $s->total_commission_pct }}%</td>
                <td style="padding:8px;color:#185FA5;font-weight:600">{{ $s->introducer_pct }}%</td>
                <td style="padding:8px;color:#854F0B;font-weight:600">{{ $s->team_leader_pct }}%</td>
                <td style="padding:8px;color:#534AB7;font-weight:600">{{ $s->group_leader_pct }}%</td>
                <td style="padding:8px;font-size:11px;color:#718096">
                    {{ \Carbon\Carbon::parse($s->valid_from)->format('d/m/Y') }} –
                    {{ $s->valid_to ? \Carbon\Carbon::parse($s->valid_to)->format('d/m/Y') : '∞' }}
                </td>
                <td style="padding:8px"><span class="status-badge {{ $s->is_active ? 'status-active':'status-inactive' }}">{{ $s->is_active ? 'Active':'Inactive' }}</span></td>
                <td style="padding:8px">
                    <form method="POST" action="{{ route('admin.masterfile.commission.toggle', $s->structure_id) }}" style="display:inline">
                        @csrf @method('PATCH')
                        <button type="submit" class="mf-toggle-btn">{{ $s->is_active ? 'Deactivate':'Activate' }}</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="10" style="padding:20px;text-align:center;color:#A0AEC0">No commission structures yet</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
</div>

{{-- ==================== GROUPS ==================== --}}
<div id="pane-groups" class="tab-pane" style="display:none">
<div class="card" style="margin-bottom:16px">
    <div class="card-title"><i class="ti ti-plus" style="color:#0D5A8E"></i> Create Group</div>
    <form method="POST" action="{{ route('admin.masterfile.group.store') }}">
        @csrf
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:12px">
            <div>
                <label class="mf-label">Group leader name *</label>
                <input type="text" name="group_name" value="{{ old('group_name') }}" class="mf-input" required>
            </div>
            <div>
                <label class="mf-label">Group code * <span style="color:#A0AEC0;font-weight:400">(e.g. C0001)</span></label>
                <input type="text" name="group_code" value="{{ old('group_code') }}" class="mf-input" required>
            </div>
            <div>
                <label class="mf-label">Group email *</label>
                <input type="email" name="group_email" value="{{ old('group_email') }}" class="mf-input" required>
            </div>
            <div>
                <label class="mf-label">Separator character</label>
                <select name="separator_char" class="mf-input">
                    <option value="-">Dash ( - )</option>
                    <option value=".">Dot ( . )</option>
                    <option value="_">Underscore ( _ )</option>
                </select>
            </div>
        </div>
        <button type="submit" class="mf-btn">Create Group</button>
    </form>
</div>
<div class="card">
    <div class="card-title"><i class="ti ti-sitemap" style="color:#0D5A8E"></i> All Groups ({{ count($groups) }})</div>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead><tr style="background:#F7FAFC">
            @foreach(['Group Code','Name','Email','Separator','Status'] as $h)
            <th style="padding:8px 10px;text-align:left;border-bottom:1px solid #E2E8F0;font-size:12px;color:#4A5568">{{ $h }}</th>
            @endforeach
        </tr></thead>
        <tbody>
            @forelse($groups as $g)
            <tr style="border-bottom:1px solid #F7FAFC">
                <td style="padding:8px 10px;font-family:monospace;font-weight:700;color:#0D5A8E">{{ $g->group_code }}</td>
                <td style="padding:8px 10px;font-weight:500">{{ $g->group_name }}</td>
                <td style="padding:8px 10px;color:#718096">{{ $g->group_email }}</td>
                <td style="padding:8px 10px;font-family:monospace;font-size:16px;text-align:center">{{ $g->separator_char }}</td>
                <td style="padding:8px 10px"><span class="status-badge {{ $g->is_active ? 'status-active':'status-inactive' }}">{{ $g->is_active ? 'Active':'Inactive' }}</span></td>
            </tr>
            @empty
            <tr><td colspan="5" style="padding:20px;text-align:center;color:#A0AEC0">No groups yet</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
</div>

{{-- ==================== REWARD RATES ==================== --}}
<div id="pane-rates" class="tab-pane" style="display:none">
<div class="card" style="margin-bottom:16px">
    <div class="card-title"><i class="ti ti-plus" style="color:#0D5A8E"></i> Add Reward Points Rate</div>
    <div style="background:#FFFBEB;border:1px solid #F6E05E;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#744210">
        <strong>Priority:</strong> Vendor+Product specific → Vendor only → Global (both blank = global).
        The most specific active rate is applied at time of commission.
    </div>
    <form method="POST" action="{{ route('admin.masterfile.rate.store') }}">
        @csrf
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:12px">
            <div>
                <label class="mf-label">Vendor <span style="color:#A0AEC0;font-weight:400">(blank = all)</span></label>
                <select name="vendor_id" class="mf-input">
                    <option value="">— All vendors —</option>
                    @foreach($vendors as $v)
                    <option value="{{ $v->vendor_id }}">{{ $v->vendor_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mf-label">Product <span style="color:#A0AEC0;font-weight:400">(blank = all)</span></label>
                <select name="product_id" class="mf-input">
                    <option value="">— All products —</option>
                    @foreach($products as $p)
                    <option value="{{ $p->product_id }}">{{ $p->product_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mf-label">Points per RM 1.00 *</label>
                <input type="number" name="points_per_rm" step="0.0001" min="0.0001" class="mf-input" required placeholder="e.g. 2.5">
            </div>
            <div>
                <label class="mf-label">Valid from *</label>
                <input type="date" name="valid_from" value="{{ date('Y-m-d') }}" class="mf-input" required>
            </div>
        </div>
        <button type="submit" class="mf-btn">Add Rate</button>
    </form>
</div>
<div class="card">
    <div class="card-title"><i class="ti ti-star" style="color:#D97706"></i> All Reward Rates ({{ count($rates) }})</div>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead><tr style="background:#F7FAFC">
            @foreach(['Vendor','Product','Points/RM','Valid From','Valid To','Status'] as $h)
            <th style="padding:8px 10px;text-align:left;border-bottom:1px solid #E2E8F0;font-size:12px;color:#4A5568">{{ $h }}</th>
            @endforeach
        </tr></thead>
        <tbody>
            @forelse($rates as $r)
            <tr style="border-bottom:1px solid #F7FAFC">
                <td style="padding:8px 10px">{{ $r->vendor_name ?? '— All —' }}</td>
                <td style="padding:8px 10px">{{ $r->product_name ?? '— All —' }}</td>
                <td style="padding:8px 10px;font-weight:700;color:#D97706">{{ $r->points_per_rm }} pts</td>
                <td style="padding:8px 10px">{{ \Carbon\Carbon::parse($r->valid_from)->format('d/m/Y') }}</td>
                <td style="padding:8px 10px">{{ $r->valid_to ? \Carbon\Carbon::parse($r->valid_to)->format('d/m/Y') : '∞' }}</td>
                <td style="padding:8px 10px"><span class="status-badge {{ $r->is_active ? 'status-active':'status-inactive' }}">{{ $r->is_active ? 'Active':'Inactive' }}</span></td>
            </tr>
            @empty
            <tr><td colspan="6" style="padding:20px;text-align:center;color:#A0AEC0">No rates yet</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
</div>

<style>
.mf-label { display:block; font-size:12px; font-weight:600; color:#4A5568; margin-bottom:4px; }
.mf-input { width:100%; padding:8px 12px; border:1px solid #E2E8F0; border-radius:8px; font-size:13px; background:#fff; }
.mf-input:focus { outline:none; border-color:#1B9AE4; box-shadow:0 0 0 3px rgba(27,154,228,0.15); }
.mf-btn { background:#0D5A8E; color:#fff; padding:9px 22px; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; }
.mf-btn:hover { background:#0a4570; }
.mf-toggle-btn { background:#F7FAFC; border:1px solid #E2E8F0; color:#4A5568; padding:4px 10px; border-radius:6px; font-size:11px; cursor:pointer; }
.mf-toggle-btn:hover { background:#EBF5FB; color:#0D5A8E; }
</style>

@push('scripts')
<script>
const tabs = ['vendors','products','commissions','groups','rates'];

function switchTab(active) {
    tabs.forEach(t => {
        document.getElementById('pane-' + t).style.display = t === active ? 'block' : 'none';
        const btn = document.getElementById('tab-' + t);
        btn.style.borderBottomColor = t === active ? '#0D5A8E' : 'transparent';
        btn.style.color = t === active ? '#0D5A8E' : '#718096';
        btn.style.fontWeight = t === active ? '600' : '400';
    });
}

function updateSplitTotal() {
    const i  = parseFloat(document.querySelector('[name=introducer_pct]').value) || 0;
    const tl = parseFloat(document.querySelector('[name=team_leader_pct]').value) || 0;
    const gl = parseFloat(document.querySelector('[name=group_leader_pct]').value) || 0;
    const tot = i + tl + gl;
    const el = document.getElementById('split-total');
    el.textContent = 'Total: ' + tot.toFixed(4) + '%';
    el.style.color = Math.abs(tot - 100) < 0.001 ? '#22543D' : '#E53E3E';
}

// Activate first tab
switchTab('vendors');
</script>
@endpush

@endsection
