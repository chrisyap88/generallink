@extends('layouts.dashboard')
@section('title','Batch Registration')
@section('page-title','Batch Agent Registration')

@section('content')

@if(session('success'))
<div style="background:#C6F6D5;border:1px solid #9AE6B4;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#22543D;font-size:13px">✓ {{ session('success') }}</div>
@endif
@if(session('error'))
<div style="background:#FED7D7;border:1px solid #FC8181;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#742A2A;font-size:13px">{{ session('error') }}</div>
@endif

{{-- Upload card --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-title"><i class="ti ti-upload" style="color:#0D5A8E"></i> Upload Agent Registration File</div>

    <div style="background:#EBF8FF;border:1px solid #BEE3F8;border-radius:8px;padding:12px 16px;margin-bottom:14px;font-size:13px;color:#2C5282">
        <strong>Supported formats:</strong> CSV, XLSX, XLS — max 10MB.<br>
        <strong>Required columns:</strong> full_name, email, nric, phone, sponsor_code<br>
        <strong>Optional columns:</strong> bank_name, bank_account, role (default: INTRODUCER)
    </div>

    <div style="display:flex;gap:12px;align-items:center;margin-bottom:14px">
        <a href="{{ route('admin.batch.template') }}"
            style="display:inline-flex;align-items:center;gap:6px;background:#F7FAFC;border:1px solid #E2E8F0;color:#4A5568;padding:7px 14px;border-radius:8px;font-size:13px;text-decoration:none">
            <i class="ti ti-download"></i> Download Column Template
        </a>
        <span style="font-size:12px;color:#A0AEC0">Download this template, fill it in Excel, then upload below</span>
    </div>

    <form method="POST" action="{{ route('admin.batch.upload') }}" enctype="multipart/form-data">
        @csrf
        <div style="display:flex;gap:12px;align-items:flex-end">
            <div style="flex:1">
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Select file</label>
                <input type="file" name="batch_file" accept=".csv,.xlsx,.xls"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;background:#F7FAFC" required>
            </div>
            <button type="submit"
                style="background:#0D5A8E;color:#fff;padding:9px 22px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap">
                <i class="ti ti-upload"></i> Upload & Stage
            </button>
        </div>
    </form>
</div>

{{-- Batch list --}}
<div class="card">
    <div class="card-title"><i class="ti ti-history" style="color:#0D5A8E"></i> Upload History</div>

    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead>
                <tr style="background:#F7FAFC">
                    @foreach(['Uploaded','Filename','Uploaded By','Total','Valid','Invalid','Committed','Status','Action'] as $h)
                    <th style="padding:9px 10px;text-align:left;border-bottom:1px solid #E2E8F0;font-size:12px;color:#4A5568;font-weight:600">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($batches as $b)
                <tr style="border-bottom:1px solid #F7FAFC">
                    <td style="padding:9px 10px;font-size:12px;color:#718096">{{ \Carbon\Carbon::parse($b->created_at)->format('d M Y H:i') }}</td>
                    <td style="padding:9px 10px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $b->filename }}">
                        <i class="ti ti-file-spreadsheet" style="color:#059669"></i> {{ $b->filename }}
                    </td>
                    <td style="padding:9px 10px">{{ $b->uploader_name }}</td>
                    <td style="padding:9px 10px;text-align:center;font-weight:600">{{ $b->total_rows ?? 0 }}</td>
                    <td style="padding:9px 10px;text-align:center;color:#22543D;font-weight:600">{{ $b->valid_rows ?? 0 }}</td>
                    <td style="padding:9px 10px;text-align:center;color:#C53030;font-weight:600">{{ $b->invalid_rows ?? 0 }}</td>
                    <td style="padding:9px 10px;text-align:center;color:#0D5A8E;font-weight:600">{{ $b->committed_rows ?? 0 }}</td>
                    <td style="padding:9px 10px">
                        @php
                            $sc = match($b->status) {
                                'COMMITTED' => 'status-active',
                                'VALIDATED' => 'status-risk_debt',
                                'PENDING'   => 'status-inactive',
                                default     => 'status-inactive',
                            };
                        @endphp
                        <span class="status-badge {{ $sc }}">{{ $b->status }}</span>
                    </td>
                    <td style="padding:9px 10px">
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <a href="{{ route('admin.batch.show', $b->batch_id) }}"
                                style="background:#EBF8FF;border:1px solid #BEE3F8;color:#2B6CB0;padding:4px 10px;border-radius:6px;font-size:11px;text-decoration:none;font-weight:600">
                                <i class="ti ti-eye"></i> View
                            </a>
                            @if($b->invalid_rows > 0)
                            <a href="{{ route('admin.batch.rejection-report', $b->batch_id) }}"
                                style="background:#FFF5F5;border:1px solid #FC8181;color:#C53030;padding:4px 10px;border-radius:6px;font-size:11px;text-decoration:none;font-weight:600">
                                <i class="ti ti-file-type-pdf"></i> Rejection PDF
                            </a>
                            @endif
                            @if($b->status !== 'COMMITTED')
                            <form method="POST" action="{{ route('admin.batch.purge', $b->batch_id) }}"
                                  onsubmit="return confirm('Purge this batch? This cannot be undone.')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                    style="background:#F7FAFC;border:1px solid #E2E8F0;color:#718096;padding:4px 10px;border-radius:6px;font-size:11px;cursor:pointer">
                                    <i class="ti ti-trash"></i> Purge
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" style="padding:30px;text-align:center;color:#A0AEC0">No batches uploaded yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $batches->links() }}</div>
</div>

@endsection
