@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.year_end_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.year_end_page_title') }}</div>
        <a href="{{ route('cbe.accounting.index') }}" onclick="history.back(); return false;" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_accounting') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">

        <div style="flex-shrink:0; display:flex; justify-content:center; align-items:center; gap:16px; margin-bottom:10px;">
            <a href="{{ route('cbe.accounting.year-end-closing', ['year' => $year - 1]) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            <span style="font-size:12.5px; font-weight:700; color:#263238;">{{ $year }}</span>
            <a href="{{ route('cbe.accounting.year-end-closing', ['year' => $year + 1]) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @if($checklist && $checklist->year_closed)
            <span style="background:#e0e0e0; color:#546E7A; border-radius:10px; padding:3px 10px; font-size:9px; font-weight:700;">{{ __('cbe_accounting.year_closed_label') }}</span>
            @endif
        </div>

        <div style="flex:1; min-height:0; overflow:hidden; display:flex; gap:12px;">

            {{-- Checklist --}}
            <div style="flex:1.2; display:flex; flex-direction:column; gap:8px; overflow:hidden;">
                <div style="display:flex; align-items:center; justify-content:space-between; background:{{ $allMonthsClosed ? '#e8f5e9' : '#fff8e1' }}; border-radius:6px; padding:8px 10px;">
                    <span style="font-size:10px; font-weight:600; color:#263238;">{{ __('cbe_accounting.year_end_checklist_months', ['closed' => $closedMonths]) }}</span>
                    @if(!$allMonthsClosed)
                    <a href="{{ route('cbe.accounting.periods', ['year' => $year]) }}" style="font-size:9px; color:var(--gl-blue); font-weight:600; text-decoration:none;">{{ __('cbe_accounting.year_end_go_to_periods') }}</a>
                    @endif
                </div>

                <form method="POST" action="{{ route('cbe.accounting.year-end-closing.checklist') }}" style="display:flex; flex-direction:column; gap:8px; overflow-y:auto;">
                    @csrf
                    <input type="hidden" name="year" value="{{ $year }}">
                    <div style="border:1px solid #e5e7eb; border-radius:6px; padding:8px 10px;">
                        <label style="display:flex; align-items:center; gap:6px; font-size:10px; font-weight:600; color:#263238;">
                            <input type="checkbox" name="agm_held" value="1" {{ $checklist && $checklist->agm_held ? 'checked' : '' }}>
                            {{ __('cbe_accounting.field_agm_held') }}
                        </label>
                        <input type="date" name="agm_date" value="{{ $checklist->agm_date ?? '' }}" style="margin-top:5px; width:160px; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; box-sizing:border-box;">
                    </div>
                    <div style="border:1px solid #e5e7eb; border-radius:6px; padding:8px 10px;">
                        <label style="display:flex; align-items:center; gap:6px; font-size:10px; font-weight:600; color:#263238;">
                            <input type="checkbox" name="ros_submitted" value="1" {{ $checklist && $checklist->ros_submitted ? 'checked' : '' }}>
                            {{ __('cbe_accounting.field_ros_submitted') }}
                        </label>
                        <input type="date" name="ros_submission_date" value="{{ $checklist->ros_submission_date ?? '' }}" style="margin-top:5px; width:160px; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_year_end_notes') }}</label>
                        <input type="text" name="notes" value="{{ $checklist->notes ?? '' }}" maxlength="500" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10px; box-sizing:border-box;">
                    </div>
                    <button type="submit" style="align-self:flex-start; background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
                </form>
            </div>

            {{-- Actions --}}
            <div style="flex:1; display:flex; flex-direction:column; gap:10px;">
                <div style="border:1px solid #e5e7eb; border-radius:6px; padding:10px;">
                    <div style="font-size:10.5px; font-weight:700; color:#263238; margin-bottom:4px;">{{ __('cbe_accounting.year_end_pack_title') }}</div>
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:8px; line-height:1.5;">{{ __('cbe_accounting.year_end_pack_note') }}</div>
                    <a href="{{ route('cbe.accounting.year-end-closing.pack', ['year' => $year]) }}" style="display:inline-block; background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:6px 16px; font-size:10px; font-weight:600;">{{ __('cbe_accounting.year_end_pack_button') }}</a>
                </div>

                @if(!($checklist && $checklist->year_closed))
                <div style="border:1px solid #e5e7eb; border-radius:6px; padding:10px;">
                    <div style="font-size:10.5px; font-weight:700; color:#263238; margin-bottom:4px;">{{ __('cbe_accounting.year_end_close_title') }}</div>
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:8px; line-height:1.5;">{{ __('cbe_accounting.year_end_close_note') }}</div>
                    <form method="POST" action="{{ route('cbe.accounting.year-end-closing.close') }}" onsubmit="return confirm('{{ __('cbe_accounting.year_end_close_confirm') }}');">
                        @csrf
                        <input type="hidden" name="year" value="{{ $year }}">
                        <button type="submit" {{ $allMonthsClosed ? '' : 'disabled' }} style="background:{{ $allMonthsClosed ? '#38A169' : '#c4c9d0' }}; color:#fff; border:none; border-radius:20px; padding:6px 16px; font-size:10px; font-weight:600; cursor:{{ $allMonthsClosed ? 'pointer' : 'not-allowed' }};">{{ __('cbe_accounting.year_end_close_button') }}</button>
                    </form>
                </div>
                @else
                <div style="border:1px solid #e5e7eb; border-radius:6px; padding:10px; background:#f7f7f5;">
                    <div style="font-size:10px; color:#546E7A;">{{ __('cbe_accounting.year_end_closed_note', ['date' => \Carbon\Carbon::parse($checklist->closed_at)->format('d M Y')]) }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
