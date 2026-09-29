@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_ops.pa_index_title'))

@section('content')

{{-- NEW 5 Aug 2026 — Outbound Partner API key management. Reverse
     direction from the Integration Hub: these are GeneralLink's OWN
     keys, handed out to outside systems. ADMIN only. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; align-items:center; justify-content:space-between;">
        <div>
            <div style="font-size:10px; color:#6b7280; margin-top:2px;">{{ __('admin_ops.pa_index_intro') }}</div>
        </div>
        <a href="{{ route('admin.partner-api.create') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('admin_ops.pa_new_key_button') }}</a>
    </div>

    @if(session('success'))
    <div style="flex-shrink:0; background:#e8f5e9; color:#1b5e20; border-radius:6px; padding:6px 10px; font-size:10.5px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow-y:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:10.5px;">
                <thead>
                    <tr style="text-align:left; color:#6b7280; border-bottom:1px solid #e5e7eb;">
                        <th style="padding:6px 8px; font-weight:600;">{{ __('masterfile.col_name') }}</th>
                        <th style="padding:6px 8px; font-weight:600;">{{ __('admin_ops.pa_col_key_prefix') }}</th>
                        <th style="padding:6px 8px; font-weight:600;">{{ __('admin_ops.pa_col_scopes') }}</th>
                        <th style="padding:6px 8px; font-weight:600;">{{ __('network.status') }}</th>
                        <th style="padding:6px 8px; font-weight:600;">{{ __('admin_ops.pa_col_last_used') }}</th>
                        <th style="padding:6px 8px; font-weight:600;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($keys as $k)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:7px 8px; font-weight:600; color:#263238;">{{ $k->key_name }}</td>
                        <td style="padding:7px 8px; color:#6b7280; font-family:monospace;">{{ $k->key_prefix }}...</td>
                        <td style="padding:7px 8px; color:#6b7280;">{{ implode(', ', json_decode($k->scopes, true)) }}</td>
                        <td style="padding:7px 8px;">
                            @if($k->status === 'ACTIVE')
                            <span style="background:#e8f5e9; color:#1b5e20; border-radius:20px; padding:1px 8px; font-size:8.5px; font-weight:700;">{{ __('network.active') }}</span>
                            @else
                            <span style="background:#fde8e8; color:#b71c1c; border-radius:20px; padding:1px 8px; font-size:8.5px; font-weight:700;">{{ __('admin_ops.pa_revoked_badge') }}</span>
                            @endif
                        </td>
                        <td style="padding:7px 8px; color:#9ca3af;">{{ $k->last_used_at ? \Carbon\Carbon::parse($k->last_used_at)->diffForHumans() : __('admin_ops.pa_never_label') }}</td>
                        <td style="padding:7px 8px; text-align:right;">
                            @if($k->status === 'ACTIVE')
                            <form method="POST" action="{{ route('admin.partner-api.revoke', $k->key_id) }}" onsubmit="return confirm({{ json_encode(__('admin_ops.pa_revoke_confirm', ['name' => $k->key_name])) }});">
                                @csrf
                                <button type="submit" style="background:#fff; color:#b71c1c; border:1px solid #f3d4d4; border-radius:6px; padding:4px 10px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('admin_ops.pa_revoke_button') }}</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:24px; text-align:center; color:#9ca3af;">{{ __('admin_ops.pa_no_keys_yet') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($keys->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('growth.prev') }}</span>
            @else
                <a href="{{ $keys->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('growth.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('admin_ops.pa_page_of_keys', ['current' => $keys->currentPage(), 'last' => $keys->lastPage(), 'total' => $keys->total()]) }}</span>
            @if($keys->hasMorePages())
                <a href="{{ $keys->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('growth.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('growth.next') }}</span>
            @endif
        </div>
    </div>

</div>
@endsection
