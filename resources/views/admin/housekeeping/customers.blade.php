@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_ops.hk_page_title'))

@section('content')
{{-- REBUILT 8 Aug 2026 per Chris: strict no-scroll rule — this screen was
     one long overflow-y:auto page with a top-left "← Back to Dashboard"
     link and Laravel's default numbered ->links() widget. Rebuilt to the
     fixed-height + bottom blue Prev/Next pattern every other list screen
     uses; the back link is removed (only the bottom pair may navigate —
     use the sidebar to leave). --}}
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px;">
        <div style="background:#EFF6FF; border-left:3px solid #1565C0; border-radius:6px; padding:6px 12px; font-size:10.5px; color:#1e3a5f;">
            {!! __('admin_ops.hk_intro') !!}
        </div>
        @if(session('success'))
        <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:5px 10px; font-size:10.5px; margin-top:6px;">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div style="background:#fde8e8; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:5px 10px; font-size:10.5px; margin-top:6px;">{{ session('error') }}</div>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.housekeeping.customers.purge') }}" id="purgeForm" style="flex:1; min-height:0; display:flex; flex-direction:column;">
        @csrf
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
            <div style="flex:1; min-height:0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:11px;">
                    <thead>
                        <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                            <th style="padding:5px 8px; width:26px;"><input type="checkbox" id="chkAll"></th>
                            <th style="text-align:left; padding:5px 8px; font-size:9.5px; color:#374151; text-transform:uppercase;">{{ __('admin_ops.hk_col_customer') }}</th>
                            <th style="text-align:left; padding:5px 8px; font-size:9.5px; color:#374151; text-transform:uppercase;">{{ __('admin_ops.hk_col_owned_by') }}</th>
                            <th style="text-align:left; padding:5px 8px; font-size:9.5px; color:#374151; text-transform:uppercase;">{{ __('admin_ops.hk_col_policies') }}</th>
                            <th style="text-align:left; padding:5px 8px; font-size:9.5px; color:#374151; text-transform:uppercase;">{{ __('admin_ops.hk_col_set_inactive') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $c)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:5px 8px;"><input type="checkbox" name="customer_ids[]" value="{{ $c->customer_id }}" class="rowChk"></td>
                            <td style="padding:5px 8px; font-weight:600; color:#1565C0; word-break:break-word;">
                                {{ $c->full_name }}<br>
                                <span style="font-size:9px; color:#9ca3af; font-weight:400;">{{ $c->phone }} @if($c->email) &middot; {{ $c->email }} @endif</span>
                            </td>
                            <td style="padding:5px 8px; word-break:break-word;">{{ $c->agent_name }}<br><span style="font-size:9px; color:#9ca3af;">{{ $c->agent_code }}</span></td>
                            <td style="padding:5px 8px;">
                                @if($c->policy_count > 0)
                                <span style="background:#FEF3C7;color:#92400e;padding:2px 8px;border-radius:20px;font-size:9px;font-weight:700;">{{ __('admin_ops.hk_has_history_badge', ['count' => $c->policy_count]) }}</span>
                                @else
                                <span style="background:#f3f4f6;color:#6b7280;padding:2px 8px;border-radius:20px;font-size:9px;font-weight:700;">{{ __('admin_ops.hk_none_badge') }}</span>
                                @endif
                            </td>
                            <td style="padding:5px 8px; font-size:10px; color:#4b5563;">{{ \Carbon\Carbon::parse($c->updated_at)->format('d M Y') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="padding:20px; text-align:center; color:#9ca3af; font-size:11px;">{{ __('admin_ops.hk_no_inactive_customers') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
                @if($customers->onFirstPage())
                    <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('growth.prev') }}</span>
                @else
                    <a href="{{ $customers->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('growth.prev') }}</a>
                @endif
                <span style="font-size:9.5px; color:#6b7280;">{{ __('admin_ops.hk_page_of_customers', ['current' => $customers->currentPage(), 'last' => $customers->lastPage(), 'total' => $customers->total()]) }}</span>
                @if($customers->hasMorePages())
                    <a href="{{ $customers->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('growth.next') }}</a>
                @else
                    <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('growth.next') }}</span>
                @endif
            </div>
        </div>

        @if($customers->isNotEmpty())
        <div style="flex-shrink:0; display:flex; gap:10px; margin-top:8px; align-items:center;">
            <button type="submit" name="action" value="soft" onclick="return confirm({{ json_encode(__('admin_ops.hk_soft_delete_confirm')) }});" style="background:#F59E0B; color:#fff; border:none; border-radius:6px; padding:7px 16px; font-size:11px; font-weight:700; cursor:pointer;">{{ __('admin_ops.hk_soft_delete_button') }}</button>
            <button type="submit" name="action" value="hard" onclick="return confirm({{ json_encode(__('admin_ops.hk_hard_delete_confirm')) }});" style="background:#DC2626; color:#fff; border:none; border-radius:6px; padding:7px 16px; font-size:11px; font-weight:700; cursor:pointer;">{{ __('admin_ops.hk_hard_delete_button') }}</button>
            <span style="font-size:9.5px; color:#9ca3af;">{{ __('admin_ops.hk_tick_hint') }}</span>
        </div>
        @endif
    </form>

</div>

<script>
document.getElementById('chkAll')?.addEventListener('change', function() {
    document.querySelectorAll('.rowChk').forEach(function(cb) { cb.checked = this.checked; }.bind(this));
});
</script>
@endsection
