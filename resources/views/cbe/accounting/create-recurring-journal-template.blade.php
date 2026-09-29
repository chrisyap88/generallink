@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.add_recurring_template_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.add_recurring_template_button') }}</div>
        <a href="{{ route('cbe.accounting.recurring-journal-templates') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.accounting.recurring-journal-templates.store') }}" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            <div style="flex-shrink:0; display:flex; gap:8px; margin-bottom:8px;">
                <div style="flex:1;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_template_name') }}</label>
                    <input type="text" name="template_name" value="{{ old('template_name') }}" maxlength="150" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="width:130px;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_frequency') }}</label>
                    <select name="frequency" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                        <option value="MONTHLY">{{ __('cbe_accounting.frequency_monthly') }}</option>
                        <option value="QUARTERLY">{{ __('cbe_accounting.frequency_quarterly') }}</option>
                        <option value="YEARLY">{{ __('cbe_accounting.frequency_yearly') }}</option>
                    </select>
                </div>
                <div style="width:150px;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_next_run_date') }}</label>
                    <input type="date" name="next_run_date" value="{{ old('next_run_date', now()->toDateString()) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
            </div>
            <div style="flex-shrink:0; margin-bottom:8px;">
                <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_jv_description') }}</label>
                <input type="text" name="description" value="{{ old('description') }}" maxlength="255" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
            </div>

            <div style="font-size:8px; color:#9ca3af; margin-bottom:4px;">{{ __('cbe_accounting.jv_lines_helper_note') }}</div>

            <div style="flex:1; min-height:0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                    <thead>
                        <tr style="background:var(--gl-light);">
                            <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_name') }}</th>
                            <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase; width:130px;">{{ __('cbe_accounting.col_cost_centre') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase; width:105px;">{{ __('cbe_accounting.col_jv_debit') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase; width:105px;">{{ __('cbe_accounting.col_jv_credit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for($i = 0; $i < 6; $i++)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:4px 6px;">
                                <select name="line_account[]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box;">
                                    <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                                    @foreach($accounts as $a)
                                    <option value="{{ $a->account_id }}">{{ $a->account_code }} — {{ $a->account_name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="padding:4px 6px;">
                                <select name="line_cost_centre[]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box;">
                                    <option value="">{{ __('cbe_accounting.coa_no_group') }}</option>
                                    @foreach($costCentres as $c)
                                    <option value="{{ $c->centre_id }}">{{ $c->centre_name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="padding:4px 6px;">
                                <input type="number" step="0.01" min="0" name="line_debit[]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box; text-align:right;">
                            </td>
                            <td style="padding:4px 6px;">
                                <input type="number" step="0.01" min="0" name="line_credit[]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box; text-align:right;">
                            </td>
                        </tr>
                        @endfor
                    </tbody>
                </table>
            </div>

            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.accounting.recurring-journal-templates') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
