@extends('layouts.dashboard')
@section('title','Batch Detail')
@section('page-title','Batch: ' . $batch->filename)

@section('content')

@if(session('success'))
<div style="background:#C6F6D5;border:1px solid #9AE6B4;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#22543D;font-size:13px">✓ {{ session('success') }}</div>
@endif
@if(session('error'))
<div style="background:#FED7D7;border:1px solid #FC8181;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#742A2A;font-size:13px">{{ session('error') }}</div>
@endif

{{-- Batch summary --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px">
    @foreach([
        ['Total rows',     $batch->total_rows ?? 0,     '#4A5568'],
        ['Valid',          $batch->valid_rows ?? 0,      '#22543D'],
        ['Invalid',        $batch->invalid_rows ?? 0,    '#C53030'],
        ['Committed',      $batch->committed_rows ?? 0,  '#0D5A8E'],
    ] as [$lbl,$val,$col])
    <div class="metric-card">
        <div class="metric-label">{{ $lbl }}</div>
        <div class="metric-value" style="color:{{ $col }}">{{ $val }}</div>
    </div>
    @endforeach
    <div class="metric-card">
        <div class="metric-label">Status</div>
        <div style="margin-top:4px">
            @php $sc = match($batch->status) { 'COMMITTED'=>'status-active','VALIDATED'=>'status-risk_debt',default=>'status-inactive' }; @endphp
            <span class="status-badge {{ $sc }}" style="font-size:13px;padding:4px 12px">{{ $batch->status }}</span>
        </div>
    </div>
</div>

{{-- Action buttons --}}
<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
    <a href="{{ route('admin.batch.index') }}"
        style="background:#F7FAFC;border:1px solid #E2E8F0;color:#4A5568;padding:8px 16px;border-radius:8px;font-size:13px;text-decoration:none">
        <i class="ti ti-arrow-left"></i> Back
    </a>

    @if(in_array($batch->status, ['PENDING','VALIDATED']))
    <form method="POST" action="{{ route('admin.batch.validate', $batch->batch_id) }}">
        @csrf
        <button type="submit"
            style="background:#D97706;color:#fff;padding:8px 18px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
            <i class="ti ti-shield-check"></i> Validate All Rows
        </button>
    </form>
    @endif

    @if($batch->status === 'VALIDATED' && ($batch->valid_rows ?? 0) > 0)
    <form method="POST" action="{{ route('admin.batch.commit', $batch->batch_id) }}"
          onsubmit="return confirm('Commit {{ $batch->valid_rows }} valid records? This creates agent accounts.')">
        @csrf
        <button type="submit"
            style="background:#059669;color:#fff;padding:8px 18px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
            <i class="ti ti-user-check"></i> Commit Valid Records ({{ $batch->valid_rows }})
        </button>
    </form>
    @endif

    @if(($batch->invalid_rows ?? 0) > 0)
    <a href="{{ route('admin.batch.rejection-report', $batch->batch_id) }}"
        style="background:#FFF5F5;border:1px solid #FC8181;color:#C53030;padding:8px 16px;border-radius:8px;font-size:13px;text-decoration:none;font-weight:600">
        <i class="ti ti-file-type-pdf"></i> Download Rejection PDF
    </a>
    @endif

    @if($batch->status !== 'COMMITTED')
    <form method="POST" action="{{ route('admin.batch.purge', $batch->batch_id) }}"
          onsubmit="return confirm('Purge this entire batch?')" style="margin-left:auto">
        @csrf @method('DELETE')
        <button type="submit"
            style="background:#FFF5F5;border:1px solid #FC8181;color:#C53030;padding:8px 16px;border:1px solid #FC8181;border-radius:8px;font-size:13px;cursor:pointer">
            <i class="ti ti-trash"></i> Purge Batch
        </button>
    </form>
    @endif
</div>

{{-- Records table --}}
<div class="card">
    <div class="card-title">
        <i class="ti ti-table" style="color:#0D5A8E"></i> Staging Records
        <span style="margin-left:auto;font-size:12px;color:#718096">{{ $records->total() }} records</span>
    </div>

    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:12px;min-width:900px">
            <thead>
                <tr style="background:#F7FAFC">
                    @foreach(['Row','Name','Email','NRIC','Phone','Sponsor','Role','Status','Errors','Action'] as $h)
                    <th style="padding:8px;text-align:left;border-bottom:1px solid #E2E8F0;color:#4A5568;font-size:11px;font-weight:600;white-space:nowrap">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($records as $r)
                @php $errs = $r->validation_errors ? json_decode($r->validation_errors, true) : []; @endphp
                <tr style="border-bottom:1px solid #F7FAFC;background:{{ $r->status === 'INVALID' ? '#FFFAF0' : ($r->status === 'COMMITTED' ? '#F0FFF4' : '#fff') }}">
                    <td style="padding:8px;font-weight:600;color:#718096">#{{ $r->row_number }}</td>
                    <td style="padding:8px;font-weight:500;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $r->full_name }}">{{ $r->full_name ?: '—' }}</td>
                    <td style="padding:8px;color:#4A5568;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $r->email }}">{{ $r->email ?: '—' }}</td>
                    <td style="padding:8px;font-family:monospace">{{ $r->nric ? substr($r->nric,0,6).'****' : '—' }}</td>
                    <td style="padding:8px;color:#4A5568">{{ $r->phone ?: '—' }}</td>
                    <td style="padding:8px;font-family:monospace;color:#0D5A8E;font-weight:600">{{ $r->sponsor_code ?: '—' }}</td>
                    <td style="padding:8px"><span style="background:#E9D8FD;color:#44337A;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:600">{{ $r->role }}</span></td>
                    <td style="padding:8px">
                        @php
                            $sc = match($r->status) {
                                'VALID'     => 'status-active',
                                'INVALID'   => 'status-inactive',
                                'COMMITTED' => 'status-active',
                                default     => 'status-risk_debt',
                            };
                        @endphp
                        <span class="status-badge {{ $sc }}" style="font-size:10px">{{ $r->status }}</span>
                    </td>
                    <td style="padding:8px;max-width:200px">
                        @if(count($errs) > 0)
                        <ul style="margin:0;padding-left:14px;color:#C53030;font-size:11px">
                            @foreach($errs as $err)
                            <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                        @else
                        <span style="color:#48BB78;font-size:11px">✓ No errors</span>
                        @endif
                    </td>
                    <td style="padding:8px">
                        @if($r->status !== 'COMMITTED')
                        <button onclick="openEdit('{{ $r->record_id }}','{{ addslashes($r->full_name) }}','{{ $r->email }}','{{ $r->nric }}','{{ $r->phone }}','{{ $r->sponsor_code }}','{{ $r->bank_name }}','{{ $r->bank_account }}')"
                            style="background:#EBF8FF;border:1px solid #BEE3F8;color:#2B6CB0;padding:4px 9px;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600">
                            <i class="ti ti-edit"></i> Edit
                        </button>
                        @else
                        <span style="font-size:11px;color:#48BB78">✓ Created</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" style="padding:20px;text-align:center;color:#A0AEC0">No records</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $records->links() }}</div>
</div>

{{-- Edit record modal --}}
<div id="edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:12px;padding:24px;width:520px;max-width:95vw;max-height:90vh;overflow-y:auto">
        <div style="font-weight:600;font-size:16px;margin-bottom:16px;color:#1A202C">Edit Record</div>
        <form method="POST" id="edit-form">
            @csrf @method('PATCH')
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div style="grid-column:span 2">
                    <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Full name *</label>
                    <input type="text" name="full_name" id="edit-full_name"
                        style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px" required>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Email *</label>
                    <input type="email" name="email" id="edit-email"
                        style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px" required>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">NRIC (12 digits) *</label>
                    <input type="text" name="nric" id="edit-nric" maxlength="12"
                        style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px" required>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Phone *</label>
                    <input type="text" name="phone" id="edit-phone"
                        style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px" required>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Sponsor code *</label>
                    <input type="text" name="sponsor_code" id="edit-sponsor_code"
                        style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px" required>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Bank name</label>
                    <input type="text" name="bank_name" id="edit-bank_name"
                        style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Bank account</label>
                    <input type="text" name="bank_account" id="edit-bank_account"
                        style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:16px">
                <button type="submit"
                    style="background:#0D5A8E;color:#fff;padding:8px 20px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
                    Save & Re-validate
                </button>
                <button type="button" onclick="closeEdit()"
                    style="background:#F7FAFC;border:1px solid #E2E8F0;color:#4A5568;padding:8px 20px;border-radius:8px;font-size:13px;cursor:pointer">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openEdit(id, name, email, nric, phone, sponsor, bank, acc) {
    document.getElementById('edit-form').action = '/admin/batch/record/' + id;
    document.getElementById('edit-full_name').value  = name;
    document.getElementById('edit-email').value      = email;
    document.getElementById('edit-nric').value       = nric;
    document.getElementById('edit-phone').value      = phone;
    document.getElementById('edit-sponsor_code').value = sponsor;
    document.getElementById('edit-bank_name').value  = bank;
    document.getElementById('edit-bank_account').value = acc;
    document.getElementById('edit-modal').style.display = 'flex';
}
function closeEdit() {
    document.getElementById('edit-modal').style.display = 'none';
}
</script>
@endpush

@endsection
