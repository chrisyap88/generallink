@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_compliance.page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_compliance.page_title') }}</div>
        @if($hasNode)
        <a href="{{ route('cbe.compliance-items.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_compliance.add_button') }}</a>
        @endif
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="font-size:9.5px; color:#6b7280; margin-bottom:8px;">{{ __('cbe_compliance.page_hint') }}</div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        @if(!$hasNode)
        <div style="margin:auto; text-align:center; color:#9ca3af; font-size:11px; max-width:320px;">{{ __('cbe_records.no_node_note') }}</div>
        @else
        <div style="flex:1; min-height:0; overflow-y:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_compliance.col_label') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_compliance.col_due') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_compliance.col_lead') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_compliance.col_status') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $it)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; font-weight:600; color:#263238;">{{ $it->label }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ \Carbon\Carbon::create(2000, $it->due_month, $it->due_day)->format('d M') }} {{ __('cbe_compliance.every_year') }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ __('cbe_compliance.lead_days_note', ['days' => $it->reminder_lead_days]) }}</td>
                        <td style="padding:5px 8px;">{{ $it->is_active ? __('admin_cbe_notice_styles.status_active') : __('admin_cbe_notice_styles.status_off') }}</td>
                        <td style="padding:5px 8px; text-align:right;">
                            @if($it->is_active)
                            <form method="POST" action="{{ route('cbe.compliance-items.deactivate', $it->compliance_item_id) }}" style="display:inline;">
                                @csrf
                                <button type="submit" onclick="return confirm({{ json_encode(__('cbe_compliance.deactivate_confirm_js')) }});" style="background:none; border:none; color:#c62828; font-weight:600; font-size:9.5px; cursor:pointer; padding:0;">{{ __('cbe_compliance.deactivate_button') }}</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_compliance.none_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection
