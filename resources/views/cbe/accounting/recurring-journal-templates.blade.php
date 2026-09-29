@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.recurring_templates_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.recurring_templates_page_title') }}</div>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="{{ route('cbe.accounting.recurring-journal-templates.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.add_recurring_template_button') }}</a>
            <a href="{{ route('cbe.accounting.journal-vouchers') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_template_name') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_frequency') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_next_run_date') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_last_generated') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $t)
                    <tr style="border-bottom:1px solid #f3f4f6; {{ !$t->is_active ? 'opacity:.5;' : '' }}">
                        <td style="padding:5px 8px; font-weight:600; color:#263238;">{{ $t->template_name }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ __('cbe_accounting.frequency_'.strtolower($t->frequency)) }}</td>
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($t->next_run_date)->format('d M Y') }}</td>
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ $t->last_generated_date ? \Carbon\Carbon::parse($t->last_generated_date)->format('d M Y') : '—' }}</td>
                        <td style="padding:5px 8px; text-align:right; white-space:nowrap;">
                            @if($t->is_active)
                            <form method="POST" action="{{ route('cbe.accounting.recurring-journal-templates.generate', $t->template_id) }}" style="display:inline;" onsubmit="return confirm({{ json_encode(__('cbe_accounting.recurring_generate_confirm_js')) }});">
                                @csrf
                                <button type="submit" style="background:none; border:none; color:var(--gl-blue); font-weight:600; font-size:9.5px; cursor:pointer;">{{ __('cbe_accounting.recurring_generate_button') }}</button>
                            </form>
                            <form method="POST" action="{{ route('cbe.accounting.recurring-journal-templates.deactivate', $t->template_id) }}" style="display:inline; margin-left:8px;" onsubmit="return confirm({{ json_encode(__('cbe_records.deactivate_confirm_js')) }});">
                                @csrf
                                <button type="submit" style="background:none; border:none; color:#e53935; font-weight:600; font-size:9.5px; cursor:pointer;">{{ __('cbe_records.deactivate_button') }}</button>
                            </form>
                            @else
                            <span style="color:#9ca3af;">{{ __('cbe_records.inactive_label') }}</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_recurring_templates_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($templates->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $templates->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $templates->currentPage(), 'last' => $templates->lastPage(), 'total' => $templates->total()]) }}</span>
            @if($templates->hasMorePages())
                <a href="{{ $templates->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
