@extends('layouts.dashboard')

@section('page-title', __('batch.detail_title_prefix') . $batch->filename)

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:6px 16px; box-sizing:border-box;">

    <div>
        <a href="{{ route('admin.batch.index') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('batch.back_to_batch_upload_link') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:6px 12px; font-size:11px; margin:6px 0;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fde8e8; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 12px; font-size:11px; margin:6px 0;">{{ session('error') }}</div>
    @endif

    @php
        $batchStatusLabels = ['PENDING_VALIDATION' => __('batch.batch_status_pending_validation'), 'VALIDATED' => __('batch.batch_status_validated'), 'VALIDATION_FAILED' => __('batch.batch_status_validation_failed'), 'OWNER_INVALID' => __('batch.batch_status_owner_invalid'), 'COMMITTED' => __('batch.batch_status_committed')];
    @endphp
    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 12px; margin:6px 0; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:11px; color:#374151;">
            <strong>{{ $batch->filename }}</strong> &middot; {{ str_replace('_BATCH', '', $batch->batch_type) }}
            &middot; {{ $batch->total_rows }} {{ __('batch.rows_word') }} &middot;
            <span style="padding:2px 8px; border-radius:20px; font-size:9.5px; font-weight:600;
                {{ $batch->status === 'COMMITTED' ? 'background:#e8f5e9;color:#1b5e20;' : ($batch->status === 'VALIDATION_FAILED' || $batch->status === 'OWNER_INVALID' ? 'background:#fde8e8;color:#b71c1c;' : ($batch->status === 'VALIDATED' ? 'background:#e8f5e9;color:#1b5e20;' : 'background:#fff8e1;color:#92400e;')) }}">
                {{ $batchStatusLabels[$batch->status] ?? $batch->status }}
            </span>
            @if($batch->status !== 'PENDING_VALIDATION')
                &nbsp;&middot;&nbsp; <span style="color:#1b5e20;">{{ $batch->valid_rows }} {{ __('batch.valid_word') }}</span> &middot; <span style="color:#b71c1c;">{{ $batch->invalid_rows }} {{ __('batch.failed_word') }}</span>
            @endif
        </div>

        <div style="display:flex; gap:8px;">
            @if($batch->status === 'PENDING_VALIDATION' || $batch->status === 'VALIDATION_FAILED')
            <form method="POST" action="{{ route('admin.batch.validate', $batch->batch_id) }}">
                @csrf
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('batch.validate_all_button') }}</button>
            </form>
            @endif

            @if($batch->invalid_rows > 0)
            <a href="{{ route('admin.batch.rejection-report', $batch->batch_id) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:5px; padding:6px 14px; font-size:11px; font-weight:600;">{{ __('batch.download_rejection_report_link') }}</a>
            @endif

            @if($batch->status === 'VALIDATED')
                <div onclick="document.getElementById('commitModal').style.display='block'; document.getElementById('commitBackdrop').style.display='block';" style="background:#1565C0; color:#fff; border-radius:5px; padding:6px 16px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('batch.commit_create_accounts_label', ['count' => $batch->valid_rows]) }}</div>
            @elseif($batch->status !== 'COMMITTED')
                <div style="background:#c4c9d0; color:#fff; border-radius:5px; padding:6px 16px; font-size:11px; font-weight:600;">{{ __('batch.commit_blocked_note') }}</div>
            @endif

            @if($batch->status !== 'COMMITTED')
            <form method="POST" action="{{ route('admin.batch.purge', $batch->batch_id) }}" onsubmit="return confirm('{{ __('batch.purge_confirm_js') }}');">
                @csrf @method('DELETE')
                <button type="submit" style="background:#fde8e8; color:#b71c1c; border:none; border-radius:5px; padding:6px 14px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('batch.purge_batch_button') }}</button>
            </form>
            @endif
        </div>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:11px;">
            <thead>
                <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                    <th style="text-align:left; padding:5px 8px; font-size:10px; color:#374151; width:30px;">#</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10px; color:#374151;">{{ __('batch.col_full_name') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10px; color:#374151;">{{ __('gl.field_phone') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10px; color:#374151;">{{ __('gl.field_email') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10px; color:#374151;">{{ __('gl.col_status') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10px; color:#374151;">{{ __('batch.col_action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($records as $r)
                <tr style="border-bottom:1px solid #f3f4f6; {{ $r->status === 'INVALID' ? 'background:#fde8e8;' : '' }}">
                    <td style="padding:4px 8px; color:#9ca3af;">{{ $r->row_number }}</td>
                    <td style="padding:4px 8px;">{{ $r->full_name }}</td>
                    <td style="padding:4px 8px;">{{ $r->phone }}</td>
                    <td style="padding:4px 8px;">{{ $r->email }}</td>
                    <td style="padding:4px 8px;">
                        @if($r->status === 'VALID')
                            <span style="color:#1b5e20; font-weight:600;">{{ __('batch.status_valid') }}</span>
                        @elseif($r->status === 'INVALID')
                            <span style="color:#b71c1c; font-weight:600;" title="{{ implode(' | ', json_decode($r->validation_errors ?? '[]')) }}">{{ implode(', ', json_decode($r->validation_errors ?? '[]')) }}</span>
                        @elseif($r->status === 'COMMITTED')
                            <span style="color:#1565C0; font-weight:600;">{{ __('batch.status_committed') }}</span>
                        @else
                            <span style="color:#9ca3af;">{{ __('batch.status_pending') }}</span>
                        @endif
                    </td>
                    <td style="padding:4px 8px;">
                        @if($r->status === 'INVALID')
                        <span onclick="showFixRow('{{ $r->record_id }}')" style="color:#1B9AE4; cursor:pointer; font-weight:600;">{{ __('batch.fix_row_link') }}</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="padding:20px; text-align:center; color:#9ca3af;">{{ __('batch.no_records_note') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($records instanceof \Illuminate\Pagination\LengthAwarePaginator && $records->total() > 0)
    <div style="display:flex; align-items:center; justify-content:space-between; margin-top:6px; padding:6px 12px; background:#fff; border:1px solid #d1d5db; border-radius:8px;">
        @if($records->onFirstPage())
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.prev') }}</span>
        @else
            <a href="{{ $records->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.prev') }}</a>
        @endif
        <span style="font-size:10.5px; color:#4b5563;">{!! __('batch.showing_x_to_y_of_z_page_a_of_b', ['first' => $records->firstItem(), 'last' => $records->lastItem(), 'total' => $records->total(), 'current' => $records->currentPage(), 'last_page' => $records->lastPage()]) !!}</span>
        @if($records->hasMorePages())
            <a href="{{ $records->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.next') }}</a>
        @else
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.next') }}</span>
        @endif
    </div>
    @endif

</div>

{{-- Commit Modal --}}
<div id="commitBackdrop" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.35); z-index:999;" onclick="document.getElementById('commitModal').style.display='none'; this.style.display='none';"></div>
<div id="commitModal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); width:340px; background:#fff; border-radius:10px; padding:16px; box-shadow:0 10px 30px rgba(0,0,0,.25); z-index:1000;">
    <div style="font-size:12px; font-weight:700; color:#1565C0; margin-bottom:8px;">{{ __('batch.confirm_commit_heading') }}</div>
    <div style="font-size:11px; color:#4b5563; margin-bottom:10px;">{{ __('batch.commit_creates_accounts_note', ['count' => $batch->valid_rows]) }}</div>
    <form method="POST" action="{{ route('admin.batch.commit', $batch->batch_id) }}">
        @csrf
        @if($batch->batch_type === 'GL_BATCH')
        <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('batch.group_name_label_optional') }} <span style="font-weight:400; color:#9ca3af;">({{ __('network.optional_placeholder') }})</span></label>
        <select name="group_label_id" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; margin-bottom:10px;">
            <option value="">{{ __('batch.none_dash_dash') }}</option>
            @foreach(\DB::table('group_labels')->orderBy('group_name')->get() as $gl)
                <option value="{{ $gl->group_label_id }}">{{ $gl->group_name }}</option>
            @endforeach
        </select>
        @endif
        <div style="display:flex; gap:8px;">
            <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 18px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('batch.confirm_commit_button') }}</button>
            <div onclick="document.getElementById('commitModal').style.display='none'; document.getElementById('commitBackdrop').style.display='none';" style="background:#f3f4f6; color:#374151; border-radius:6px; padding:7px 14px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('gl.cancel_button') }}</div>
        </div>
    </form>
</div>

{{-- Fix Row Modal --}}
<div id="fixRowBackdrop" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.35); z-index:999;" onclick="document.getElementById('fixRowModal').style.display='none'; this.style.display='none';"></div>
<div id="fixRowModal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); width:380px; background:#fff; border-radius:10px; padding:16px; box-shadow:0 10px 30px rgba(0,0,0,.25); z-index:1000;">
    <div style="font-size:12px; font-weight:700; color:#1565C0; margin-bottom:8px;">{{ __('batch.fix_this_row_heading') }}</div>
    <form id="fixRowForm" method="POST">
        @csrf @method('PATCH')
        <label style="font-size:9px; color:#374151;">{{ __('gl.field_full_name') }}</label>
        <input type="text" name="full_name" id="fixFullName" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; margin-bottom:6px;">
        <label style="font-size:9px; color:#374151;">{{ __('batch.field_name_other_language_label') }}</label>
        <input type="text" name="second_name" id="fixSecondName" placeholder="{{ __('network.optional_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; margin-bottom:6px;">
        <label style="font-size:9px; color:#374151;">{{ __('gl.field_phone') }}</label>
        <input type="text" name="phone" id="fixPhone" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; margin-bottom:6px;">
        <label style="font-size:9px; color:#374151;">{{ __('gl.field_email') }}</label>
        <input type="text" name="email" id="fixEmail" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; margin-bottom:6px;">
        <label style="font-size:9px; color:#374151;">{{ __('gl.field_address') }}</label>
        <input type="text" name="address" id="fixAddress" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; margin-bottom:6px;">
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:6px; margin-bottom:10px;">
            <div>
                <label style="font-size:9px; color:#374151;">{{ __('gl.field_postcode') }}</label>
                <input type="text" name="postcode" id="fixPostcode" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; box-sizing:border-box;">
            </div>
            <div>
                <label style="font-size:9px; color:#374151;">{{ __('gl.field_city') }}</label>
                <input type="text" name="city" id="fixCity" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; box-sizing:border-box;">
            </div>
            <div>
                <label style="font-size:9px; color:#374151;">{{ __('gl.field_state') }}</label>
                <input type="text" name="state" id="fixState" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; box-sizing:border-box;">
            </div>
        </div>
        <div style="display:flex; gap:8px;">
            <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('batch.save_recheck_button') }}</button>
            <div onclick="document.getElementById('fixRowModal').style.display='none'; document.getElementById('fixRowBackdrop').style.display='none';" style="background:#f3f4f6; color:#374151; border-radius:6px; padding:6px 14px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('gl.cancel_button') }}</div>
        </div>
    </form>
</div>

<script>
var records = @json($records->items());
var recordUrlTemplate = '{{ route('admin.record.update', ['recordId' => 'PLACEHOLDER']) }}';
function showFixRow(recordId) {
    var record = records.find(function(r) { return r.record_id === recordId; });
    if (!record) return;
    document.getElementById('fixFullName').value = record.full_name;
    document.getElementById('fixSecondName').value = record.second_name || '';
    document.getElementById('fixPhone').value = record.phone;
    document.getElementById('fixEmail').value = record.email;
    document.getElementById('fixAddress').value = record.address;
    document.getElementById('fixPostcode').value = record.postcode;
    document.getElementById('fixCity').value = record.city;
    document.getElementById('fixState').value = record.state;
    document.getElementById('fixRowForm').action = recordUrlTemplate.replace('PLACEHOLDER', recordId);
    document.getElementById('fixRowModal').style.display = 'block';
    document.getElementById('fixRowBackdrop').style.display = 'block';
}
</script>
@endsection
