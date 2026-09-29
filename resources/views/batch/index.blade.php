@extends('layouts.dashboard')

@section('page-title', __('batch.title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:6px 16px; box-sizing:border-box;">

    <div>
        <a href="{{ route('admin.dashboard') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('tl.back_to_dashboard_link') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:6px 12px; font-size:11px; margin:6px 0;">{{ session('success') }}</div>
    @endif
    @if(session('error') || $errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 12px; font-size:11px; margin:6px 0;">
        {{ session('error') }}
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:6px;">

        {{-- UPLOAD FORM --}}
        <form method="POST" action="{{ route('admin.batch.upload') }}" enctype="multipart/form-data">
            @csrf
            <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <span style="font-size:11.5px; font-weight:700; color:#1565C0;">{{ __('batch.upload_batch_file_heading') }}</span>
                    <a href="{{ route('admin.batch.template') }}" style="color:#1B9AE4; font-size:10.5px; font-weight:600; text-decoration:none;">{{ __('batch.download_template_link') }}</a>
                </div>

                <label style="font-size:9.5px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('batch.entire_file_for_label') }} <span style="color:#e53935;">*</span></label>
                <select name="batch_type" id="batchType" required onchange="handleBatchTypeChange(this.value)" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; margin-bottom:8px; background:#fff;">
                    <option value="">{{ __('growth.select_dash_dash') }}</option>
                    <option value="GL_BATCH">{{ \App\Services\RoleLabelService::label('GROUP_LEADER') }} {{ __('batch.gl_batch_option_note') }}</option>
                    <option value="TL_BATCH">{{ \App\Services\RoleLabelService::label('TEAM_LEADER') }} {{ __('batch.tl_batch_option_note', ['label' => \App\Services\RoleLabelService::label('GROUP_LEADER')]) }}</option>
                    <option value="INTRODUCER_BATCH">{{ \App\Services\RoleLabelService::label('INTRODUCER') }} {{ __('batch.introducer_batch_option_note', ['label' => \App\Services\RoleLabelService::label('TEAM_LEADER')]) }}</option>
                </select>

                <div id="ownerSection" style="display:none; margin-bottom:8px; background:#f9fafb; border-radius:6px; padding:10px;">
                    <label style="font-size:9.5px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('batch.owner_option_a_label') }}</label>
                    <input type="file" id="ownerQrUpload" accept="image/*" onchange="handleOwnerQrUpload(this)" style="font-size:10.5px; margin-bottom:8px;">

                    <label style="font-size:9.5px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('batch.owner_option_b_label') }}</label>
                    <div style="position:relative;">
                        <input type="text" id="ownerSearchInput" placeholder="{{ __('batch.start_typing_placeholder') }}" autocomplete="off" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
                        <div id="ownerDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:180px; overflow-y:auto; margin-top:2px;"></div>
                    </div>
                    <div id="ownerSelectedDisplay" style="display:none; margin-top:8px; background:#E0F7FA; border:1px solid #B2EBF2; border-radius:6px; padding:6px 10px; font-size:11px; font-weight:600; color:#1565C0;"></div>
                    <input type="hidden" name="owner_agent_id" id="ownerAgentId">
                </div>

                <label style="font-size:9.5px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('batch.field_csv_xlsx_label') }} <span style="color:#e53935;">*</span></label>
                <input type="file" name="batch_file" accept=".csv,.xlsx,.xls" required style="width:100%; font-size:11px; margin-bottom:10px;">
                <div style="font-size:9px; color:#9ca3af; margin-bottom:10px;">{{ __('batch.required_columns_note') }}</div>

                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 22px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('batch.upload_button') }}</button>
            </div>
        </form>

        {{-- PAST BATCHES LIST --}}
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px;">
            <div style="font-size:11.5px; font-weight:700; color:#1565C0; margin-bottom:8px;">{{ __('batch.past_batches_heading') }}</div>
            <div style="max-height:320px; overflow-y:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:11px;">
                    <thead>
                        <tr style="background:#f0f9ff;">
                            <th style="text-align:left; padding:4px 6px; font-size:9.5px; color:#374151;">{{ __('batch.col_filename') }}</th>
                            <th style="text-align:left; padding:4px 6px; font-size:9.5px; color:#374151;">{{ __('gl.col_type') }}</th>
                            <th style="text-align:left; padding:4px 6px; font-size:9.5px; color:#374151;">{{ __('gl.col_status') }}</th>
                            <th style="text-align:left; padding:4px 6px; font-size:9.5px; color:#374151;">{{ __('batch.rows_word') }}</th>
                            <th style="text-align:left; padding:4px 6px; font-size:9.5px; color:#374151;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($batches as $b)
                        <tr style="border-top:1px solid #f3f4f6;">
                            <td style="padding:4px 6px;">{{ $b->filename }}</td>
                            <td style="padding:4px 6px; color:#4b5563;">{{ str_replace('_BATCH', '', $b->batch_type ?? '—') }}</td>
                            <td style="padding:4px 6px;">
                                <span style="padding:2px 6px; border-radius:20px; font-size:9px; font-weight:600;
                                    {{ $b->status === 'COMMITTED' ? 'background:#e8f5e9;color:#1b5e20;' : ($b->status === 'VALIDATION_FAILED' ? 'background:#fde8e8;color:#b71c1c;' : 'background:#fff8e1;color:#92400e;') }}">
                                    {{ $b->status }}
                                </span>
                            </td>
                            <td style="padding:4px 6px; color:#4b5563;">{{ $b->total_rows }}</td>
                            <td style="padding:4px 6px;">
                                <a href="{{ route('admin.batch.show', $b->batch_id) }}" style="color:#1B9AE4; text-decoration:none; font-weight:600;">{{ __('gl.view_link') }}</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('batch.no_batches_uploaded_note') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
function handleBatchTypeChange(type) {
    var section = document.getElementById('ownerSection');
    section.style.display = (type === 'TL_BATCH' || type === 'INTRODUCER_BATCH') ? 'block' : 'none';
    document.getElementById('ownerAgentId').required = section.style.display === 'block';
}

var ownerRoleMap = { TL_BATCH: 'GROUP_LEADER', INTRODUCER_BATCH: 'TEAM_LEADER' };
var ownerTimer;

document.getElementById('ownerSearchInput').addEventListener('input', function() {
    clearTimeout(ownerTimer);
    var q = this.value.trim();
    var role = ownerRoleMap[document.getElementById('batchType').value];
    var dropdown = document.getElementById('ownerDropdown');
    if (q.length < 2 || !role) { dropdown.style.display = 'none'; return; }
    ownerTimer = setTimeout(function() {
        fetch('{{ route('admin.batch.owner-lookup') }}?q=' + encodeURIComponent(q) + '&role=' + role)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.length) { dropdown.style.display = 'none'; return; }
                dropdown.innerHTML = '';
                data.forEach(function(item) {
                    var d = document.createElement('div');
                    d.style.cssText = 'padding:6px 8px; font-size:11px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                    d.innerHTML = '<strong>' + item.full_name + '</strong> (' + item.agent_code + ')';
                    d.onmousedown = function(e) {
                        e.preventDefault();
                        selectOwner(item.agent_id, item.full_name, item.agent_code);
                        dropdown.style.display = 'none';
                    };
                    dropdown.appendChild(d);
                });
                dropdown.style.display = 'block';
            });
    }, 250);
});

function selectOwner(id, name, code) {
    document.getElementById('ownerAgentId').value = id;
    var display = document.getElementById('ownerSelectedDisplay');
    display.textContent = @json(__('batch.selected_prefix_js')) + name + ' (' + code + ')';
    display.style.display = 'block';
    document.getElementById('ownerSearchInput').value = '';
}

function handleOwnerQrUpload(input) {
    if (!input.files || !input.files[0]) return;
    // Reuses the same jsQR decode approach as the registration page.
    var reader = new FileReader();
    reader.onload = function(e) {
        var img = new Image();
        img.onload = function() {
            var canvas = document.createElement('canvas');
            canvas.width = img.width; canvas.height = img.height;
            var ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);
            var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            if (typeof jsQR === 'undefined') { alert(@json(__('batch.qr_scanner_not_loaded_js'))); return; }
            var code = jsQR(imageData.data, imageData.width, imageData.height);
            if (code) {
                var match = code.data.match(/ref=([a-zA-Z0-9]+)/);
                if (match) {
                    fetch('{{ url('/affiliate/lookup-by-token') }}?token=' + match[1])
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data && data.agent_id) selectOwner(data.agent_id, data.full_name, data.agent_code);
                            else alert(@json(__('batch.qr_not_recognized_js')));
                        });
                }
            } else {
                alert(@json(__('batch.qr_could_not_read_js')));
            }
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(input.files[0]);
}
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsQR/1.4.0/jsQR.js"></script>
@endsection
