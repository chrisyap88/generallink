<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>{{ __('batch.pdf_title') }}</title>
<style>
body { font-family: Arial, sans-serif; font-size: 12px; color: #2D3748; }
h1 { font-size: 18px; color: #0D5A8E; margin-bottom: 4px; }
.meta { color: #718096; font-size: 11px; margin-bottom: 20px; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th { background: #0D5A8E; color: #fff; padding: 8px; text-align: left; font-size: 11px; }
td { padding: 7px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top; font-size: 11px; }
tr:nth-child(even) { background: #F7FAFC; }
.err { color: #C53030; }
.footer { margin-top: 30px; font-size: 10px; color: #A0AEC0; text-align: center; }
</style>
</head>
<body>

<h1>{{ __('batch.pdf_h1') }}</h1>
<div class="meta">
    {{ __('batch.pdf_batch_file_label') }} <strong>{{ $batch->filename }}</strong> &nbsp;|&nbsp;
    {{ __('batch.pdf_generated_label') }} <strong>{{ now()->format('d M Y H:i') }}</strong> &nbsp;|&nbsp;
    {{ __('batch.pdf_total_invalid_rows_label') }} <strong>{{ count($invalid) }}</strong>
</div>

<table>
    <thead>
        <tr>
            <th style="width:40px">{{ __('batch.col_row') }}</th>
            <th style="width:120px">{{ __('gl.field_full_name') }}</th>
            <th style="width:140px">{{ __('gl.field_email') }}</th>
            <th style="width:100px">{{ __('batch.col_nric') }}</th>
            <th style="width:100px">{{ __('batch.col_sponsor') }}</th>
            <th>{{ __('batch.col_rejection_reasons') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($invalid as $r)
        @php $errs = $r->validation_errors ? json_decode($r->validation_errors, true) : []; @endphp
        <tr>
            <td><strong>#{{ $r->row_number }}</strong></td>
            <td>{{ $r->full_name ?: '—' }}</td>
            <td>{{ $r->email ?: '—' }}</td>
            <td>{{ $r->nric ? substr($r->nric,0,6).'****' : '—' }}</td>
            <td>{{ $r->sponsor_code ?: '—' }}</td>
            <td class="err">
                @foreach($errs as $e)
                • {{ $e }}<br>
                @endforeach
            </td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;color:#48BB78;padding:20px">{{ __('batch.no_invalid_records_note') }}</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    {{ __('batch.pdf_footer') }} &nbsp;|&nbsp; {{ now()->format('d M Y') }}
</div>
</body>
</html>
