@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.ros_checklist_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.ros_checklist_page_title') }}</div>
        <a href="{{ route('cbe.accounting.index') }}" onclick="history.back(); return false;" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_accounting') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">

        <div style="flex-shrink:0; display:flex; justify-content:center; align-items:center; gap:16px; margin-bottom:8px;">
            <a href="{{ route('cbe.accounting.ros-submission-checklist', ['year' => $year - 1]) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            <span style="font-size:12.5px; font-weight:700; color:#263238;">{{ $year }}</span>
            <a href="{{ route('cbe.accounting.ros-submission-checklist', ['year' => $year + 1]) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
        </div>

        <div style="flex-shrink:0; display:flex; gap:6px; border-bottom:1px solid #e5e7eb; margin-bottom:8px;">
            <div class="rsc-tab active" data-tab="checklist" onclick="rscSwitchTab('checklist')" style="padding:6px 14px; font-size:9.5px; font-weight:700; color:var(--gl-blue); cursor:pointer; border-bottom:2px solid var(--gl-blue); margin-bottom:-1px;">{{ __('cbe_accounting.ros_tab_checklist') }}</div>
            <div class="rsc-tab" data-tab="activity" onclick="rscSwitchTab('activity')" style="padding:6px 14px; font-size:9.5px; font-weight:700; color:#6b7280; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px;">{{ __('cbe_accounting.ros_tab_activity_report') }} ({{ count($activityRows) }})</div>
        </div>

        <div id="rscPanel-activity" style="display:none; flex:1; min-height:0; flex-direction:column; gap:6px;">
            <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <div style="font-size:8.5px; color:#9ca3af;">{{ __('cbe_accounting.ros_activity_report_note') }}</div>
                <a href="{{ route('cbe.annual-report.secretary', ['year' => $year]) }}" style="flex-shrink:0; background:#455A64; color:#fff; text-decoration:none; border-radius:6px; padding:5px 10px; font-size:8.5px; font-weight:700;">{{ __('cbe_accounting.ros_download_activity_report') }}</a>
            </div>
            <div style="flex:1; min-height:0; overflow-y:auto; border:1px solid #e5e7eb; border-radius:6px;">
                @forelse($activityRows as $row)
                <div style="display:flex; align-items:center; gap:8px; padding:6px 10px; border-bottom:1px solid #f3f4f6; font-size:9.5px;">
                    <span style="flex-shrink:0; width:64px; font-weight:700; color:{{ $row->planned ? '#f9a825' : 'var(--gl-blue)' }};">{{ $row->type }}</span>
                    <span style="flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#263238;">{{ $row->summary_line }}</span>
                </div>
                @empty
                <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9.5px;">{{ __('cbe_accounting.ros_activity_report_empty') }}</div>
                @endforelse
            </div>
        </div>

        <form id="rscPanel-checklist" method="POST" action="{{ route('cbe.accounting.ros-submission-checklist.store') }}" style="flex:1; min-height:0; display:flex; flex-direction:column; overflow-y:auto; gap:6px;">
            @csrf
            <input type="hidden" name="year" value="{{ $year }}">

            <label style="display:flex; align-items:center; gap:6px; font-size:10.5px; color:#263238; border:1px solid #e5e7eb; border-radius:6px; padding:7px 10px;">
                <input type="checkbox" name="item_annual_report_pack" value="1" {{ $checklist && $checklist->item_annual_report_pack ? 'checked' : '' }}>
                {{ __('cbe_accounting.ros_item_annual_report_pack') }}
            </label>
            <label style="display:flex; align-items:center; gap:6px; font-size:10.5px; color:#263238; border:1px solid #e5e7eb; border-radius:6px; padding:7px 10px;">
                <input type="checkbox" name="item_office_bearer_list" value="1" {{ $checklist && $checklist->item_office_bearer_list ? 'checked' : '' }}>
                {{ __('cbe_accounting.ros_item_office_bearer_list') }}
            </label>
            <label style="display:flex; align-items:center; gap:6px; font-size:10.5px; color:#263238; border:1px solid #e5e7eb; border-radius:6px; padding:7px 10px;">
                <input type="checkbox" name="item_financial_statements" value="1" {{ $checklist && $checklist->item_financial_statements ? 'checked' : '' }}>
                {{ __('cbe_accounting.ros_item_financial_statements') }}
            </label>
            <label style="display:flex; align-items:center; gap:6px; font-size:10.5px; color:#263238; border:1px solid #e5e7eb; border-radius:6px; padding:7px 10px;">
                <input type="checkbox" name="item_agm_minutes" value="1" {{ $checklist && $checklist->item_agm_minutes ? 'checked' : '' }}>
                {{ __('cbe_accounting.ros_item_agm_minutes') }}
            </label>
            <label style="display:flex; align-items:center; gap:6px; font-size:10.5px; color:#263238; border:1px solid #e5e7eb; border-radius:6px; padding:7px 10px;">
                <input type="checkbox" name="item_membership_register" value="1" {{ $checklist && $checklist->item_membership_register ? 'checked' : '' }}>
                {{ __('cbe_accounting.ros_item_membership_register') }}
            </label>
            <label style="display:flex; align-items:center; gap:6px; font-size:10.5px; color:#263238; border:1px solid #e5e7eb; border-radius:6px; padding:7px 10px;">
                <input type="checkbox" name="item_form_submitted" value="1" {{ $checklist && $checklist->item_form_submitted ? 'checked' : '' }}>
                {{ __('cbe_accounting.ros_item_form_submitted') }}
            </label>

            <div style="display:flex; gap:8px;">
                <div style="flex:1;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_ros_reference_no') }}</label>
                    <input type="text" name="ros_reference_no" value="{{ $checklist->ros_reference_no ?? '' }}" maxlength="100" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10px; box-sizing:border-box;">
                </div>
                <div style="width:170px;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_ros_submission_date') }}</label>
                    <input type="date" name="submission_date" value="{{ $checklist->submission_date ?? '' }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10px; box-sizing:border-box;">
                </div>
            </div>
            <div>
                <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_year_end_notes') }}</label>
                <input type="text" name="notes" value="{{ $checklist->notes ?? '' }}" maxlength="500" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10px; box-sizing:border-box;">
            </div>

            <button type="submit" style="align-self:flex-start; background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
        </form>
    </div>
</div>

<script>
function rscSwitchTab(tab){
    document.getElementById('rscPanel-checklist').style.display = (tab === 'checklist') ? 'flex' : 'none';
    document.getElementById('rscPanel-activity').style.display = (tab === 'activity') ? 'flex' : 'none';
    document.querySelectorAll('.rsc-tab').forEach(function(el){
        var active = el.getAttribute('data-tab') === tab;
        el.style.color = active ? 'var(--gl-blue)' : '#6b7280';
        el.style.borderBottomColor = active ? 'var(--gl-blue)' : 'transparent';
    });
}
</script>
@endsection
