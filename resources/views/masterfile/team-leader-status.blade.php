@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('masterfile.update_status_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:8px 16px; box-sizing:border-box;">

    <div style="display:flex; align-items:baseline; gap:12px; margin-bottom:6px;">
        <a href="{{ route('admin.masterfile.team-leaders.edit', $agent->agent_id) }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('masterfile.back_to_edit') }}</a>
        <span style="font-size:11px; color:#9ca3af;">{{ $agent->agent_code }} &middot; {{ $agent->full_name }}</span>
    </div>


    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:8px 12px; font-size:11.5px; color:#b71c1c; margin-bottom:10px;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    <form method="POST" action="{{ route('admin.masterfile.team-leaders.status.update', $agent->agent_id) }}">
        @csrf

        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; max-width:520px;">

            <div style="margin-bottom:10px;">
                <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('masterfile.current_status_label') }}</label>
                <div style="border:1px solid #E0F7FA; background:#E0F7FA; border-radius:6px; padding:6px 10px; font-size:12px; font-weight:600; display:inline-block;">{{ $agent->status }}</div>
            </div>

            <div style="margin-bottom:10px;">
                <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('masterfile.new_status_label') }} <span style="color:#e53935;">*</span></label>
                <select name="new_status" id="newStatus" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box; background:#fff;">
                    <option value="">{{ __('masterfile.select_placeholder') }}</option>
                    <option value="ACTIVE" {{ old('new_status')=='ACTIVE'?'selected':'' }}>{{ __('masterfile.active') }}</option>
                    <option value="INACTIVE" {{ old('new_status')=='INACTIVE'?'selected':'' }}>{{ __('masterfile.inactive') }}</option>
                    <option value="TERMINATED" {{ old('new_status')=='TERMINATED'?'selected':'' }}>{{ __('masterfile.terminated_status') }}</option>
                </select>
            </div>

            @if($activeDownlineCount > 0)
            <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:8px 12px; font-size:11px; color:#b71c1c; margin-bottom:10px;">
                {{ __('masterfile.downline_warning', ['role' => \App\Services\RoleLabelService::label('TEAM_LEADER'), 'count' => $activeDownlineCount]) }}
            </div>
            @endif

            <div id="reasonSection" style="display:none; margin-bottom:10px;">
                <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('masterfile.reason_label') }} <span style="color:#e53935;">*</span></label>
                <select name="reason_code_id" id="reasonCode" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box; background:#fff; margin-bottom:8px;">
                    <option value="">{{ __('masterfile.select_reason_placeholder') }}</option>
                    @foreach($terminationReasons as $reason)
                        <option value="{{ $reason->reason_code_id }}" {{ old('reason_code_id')==$reason->reason_code_id?'selected':'' }}>{{ $reason->description }}</option>
                    @endforeach
                </select>
                <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('masterfile.additional_notes_label') }} <span style="font-weight:400; color:#9ca3af;">{{ __('masterfile.optional_label') }}</span></label>
                <textarea name="reason_notes" rows="3" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box; resize:vertical;">{{ old('reason_notes') }}</textarea>
            </div>

            <div style="display:flex; gap:8px;">
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 22px; font-size:12px; font-weight:700; cursor:pointer;">{{ __('masterfile.confirm_button') }}</button>
                <a href="{{ route('admin.masterfile.team-leaders.edit', $agent->agent_id) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:7px 18px; font-size:12px; font-weight:500;">{{ __('masterfile.cancel') }}</a>
            </div>
        </div>
    </form>
</div>

<script>
document.getElementById('newStatus').addEventListener('change', function() {
    var section = document.getElementById('reasonSection');
    var reasonCode = document.getElementById('reasonCode');
    if (this.value === 'TERMINATED') {
        section.style.display = 'block';
        reasonCode.required = true;
    } else {
        section.style.display = 'none';
        reasonCode.required = false;
    }
});
</script>
@endsection
