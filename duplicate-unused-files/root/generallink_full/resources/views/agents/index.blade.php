@extends('layouts.dashboard')
@section('title','Agent Management')
@section('page-title','Agent Management')

@section('content')

@if(session('success'))
<div style="background:#C6F6D5;border:1px solid #9AE6B4;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#22543D;font-size:13px">✓ {{ session('success') }}</div>
@endif

{{-- Filters --}}
<div class="card" style="margin-bottom:16px">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div>
            <label style="font-size:11px;font-weight:600;color:#4A5568;display:block;margin-bottom:3px">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Name, email, or code..."
                style="padding:7px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;width:220px">
        </div>
        <div>
            <label style="font-size:11px;font-weight:600;color:#4A5568;display:block;margin-bottom:3px">Role</label>
            <select name="role" style="padding:7px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;background:#fff">
                <option value="">All roles</option>
                @foreach(['INTRODUCER','TEAM_LEADER','GROUP_LEADER'] as $r)
                <option value="{{ $r }}" {{ request('role')==$r?'selected':'' }}>{{ str_replace('_',' ',$r) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size:11px;font-weight:600;color:#4A5568;display:block;margin-bottom:3px">Status</label>
            <select name="status" style="padding:7px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;background:#fff">
                <option value="">All statuses</option>
                @foreach(['ACTIVE','INACTIVE','TERMINATED','RISK_DEBT','RESIGNED','DECEASED'] as $s)
                <option value="{{ $s }}" {{ request('status')==$s?'selected':'' }}>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size:11px;font-weight:600;color:#4A5568;display:block;margin-bottom:3px">Group</label>
            <select name="group" style="padding:7px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;background:#fff">
                <option value="">All groups</option>
                @foreach($groups as $g)
                <option value="{{ $g->group_code }}" {{ request('group')==$g->group_code?'selected':'' }}>{{ $g->group_code }} — {{ $g->group_name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" style="background:#0D5A8E;color:#fff;padding:8px 18px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
            <i class="ti ti-filter"></i> Filter
        </button>
        <a href="{{ route('admin.agents.index') }}" style="padding:8px 14px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;color:#4A5568;text-decoration:none">Clear</a>
    </form>
</div>

<div class="card">
    <div class="card-title">
        <i class="ti ti-users" style="color:#0D5A8E"></i> All Agents
        <span style="margin-left:auto;font-size:12px;color:#718096">{{ $agents->total() }} agents</span>
    </div>

    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:12px">
            <thead>
                <tr style="background:#F7FAFC">
                    @foreach(['Code','Name','Email','Role','Group','Sponsor','Status','Joined','Action'] as $h)
                    <th style="padding:8px 10px;text-align:left;border-bottom:1px solid #E2E8F0;font-size:11px;color:#4A5568;font-weight:600;white-space:nowrap">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($agents as $a)
                <tr style="border-bottom:1px solid #F7FAFC">
                    <td style="padding:8px 10px;font-family:monospace;font-size:11px;color:#0D5A8E;font-weight:600">{{ $a->member_code ?? $a->agent_code }}</td>
                    <td style="padding:8px 10px;font-weight:500;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $a->full_name }}">{{ $a->full_name }}</td>
                    <td style="padding:8px 10px;color:#718096;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $a->email }}">{{ $a->email }}</td>
                    <td style="padding:8px 10px">
                        @php $roleColors = ['INTRODUCER'=>'#7C3AED','TEAM_LEADER'=>'#0284C7','GROUP_LEADER'=>'#059669']; @endphp
                        <span style="background:{{ ($roleColors[$a->role] ?? '#718096') }}20;color:{{ $roleColors[$a->role] ?? '#718096' }};padding:2px 7px;border-radius:4px;font-size:10px;font-weight:700">
                            {{ str_replace('_',' ',$a->role) }}
                        </span>
                    </td>
                    <td style="padding:8px 10px;font-family:monospace;font-size:11px">{{ $a->group_code ?? '—' }}</td>
                    <td style="padding:8px 10px;color:#718096;font-size:11px">{{ $a->parent_name ?? '—' }}</td>
                    <td style="padding:8px 10px"><span class="status-badge status-{{ strtolower($a->status) }}" style="font-size:10px">{{ $a->status }}</span></td>
                    <td style="padding:8px 10px;color:#718096;font-size:11px;white-space:nowrap">{{ \Carbon\Carbon::parse($a->created_at)->format('d/m/Y') }}</td>
                    <td style="padding:8px 10px">
                        <a href="{{ route('admin.agents.show', $a->agent_id) }}"
                            style="background:#EBF8FF;border:1px solid #BEE3F8;color:#2B6CB0;padding:4px 9px;border-radius:6px;font-size:11px;text-decoration:none;font-weight:600">
                            <i class="ti ti-eye"></i> View
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" style="padding:30px;text-align:center;color:#A0AEC0">No agents found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $agents->links() }}</div>
</div>

@endsection
