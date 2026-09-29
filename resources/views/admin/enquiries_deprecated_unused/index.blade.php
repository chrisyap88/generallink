@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', 'Enquiries')

@section('content')

{{-- NEW 21 Jul 2026 — Internal Enquiry system, Admin side. Defaults to
     OPEN enquiries only (a working queue, like Pending Top-Ups) — never
     dumps every enquiry ever raised by default. Status/category filter
     + name/code/subject search on top, Prev/Next paginated below. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">


    <div style="flex-shrink:0; margin-bottom:8px;">
        <div style="font-size:9.5px; color:#9ca3af;">{{ $openCount }} open enquiries awaiting a reply.</div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.enquiries.index') }}" style="margin-bottom:8px; display:flex; gap:6px; flex-wrap:wrap; align-items:center; flex-shrink:0;">
        <select name="status" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10px; background:#fff;">
            <option value="OPEN" {{ $status === 'OPEN' ? 'selected' : '' }}>Open</option>
            <option value="ANSWERED" {{ $status === 'ANSWERED' ? 'selected' : '' }}>Answered</option>
            <option value="CLOSED" {{ $status === 'CLOSED' ? 'selected' : '' }}>Closed</option>
            <option value="ALL" {{ $status === 'ALL' ? 'selected' : '' }}>All Statuses</option>
        </select>
        <select name="category" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10px; background:#fff;">
            <option value="">All Categories</option>
            <option value="GENERAL" {{ $category === 'GENERAL' ? 'selected' : '' }}>General</option>
            <option value="CLAIM_UPDATE" {{ $category === 'CLAIM_UPDATE' ? 'selected' : '' }}>Claim Update</option>
            <option value="TOPUP_PAYMENT" {{ $category === 'TOPUP_PAYMENT' ? 'selected' : '' }}>Top-Up Payment Issue</option>
            <option value="DATA_CORRECTION" {{ $category === 'DATA_CORRECTION' ? 'selected' : '' }}>Information / Data Looks Wrong</option>
        </select>
        <input type="text" name="search" value="{{ $search }}" placeholder="Search name, code or subject..." style="border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; min-width:200px; box-sizing:border-box;">
        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10px; font-weight:600; cursor:pointer;">Search</button>
        @if($search || $category)
        <a href="{{ route('admin.enquiries.index', ['status' => $status]) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:6px 14px; font-size:10px; font-weight:600; display:flex; align-items:center;">Clear</a>
        @endif
    </form>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:#f0f9ff;">
                        <th style="width:24px; padding:4px 4px;"></th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">Agent</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">Subject</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">Category</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">Last Activity</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($enquiries as $e)
                    @php
                        $unread = !$e->last_viewed_by_admin_at || \Carbon\Carbon::parse($e->last_message_at)->gt(\Carbon\Carbon::parse($e->last_viewed_by_admin_at));
                    @endphp
                    <tr style="border-bottom:1px solid #f3f4f6; cursor:pointer; {{ $unread ? 'background:#f0f9ff;' : '' }}" onclick="window.location='{{ route('admin.enquiries.show', $e->enquiry_id) }}'">
                        <td style="padding:5px 4px; text-align:center;">
                            <form method="POST" action="{{ route('admin.enquiries.flag', $e->enquiry_id) }}" onclick="event.stopPropagation();" style="display:inline;">
                                @csrf
                                <button type="submit" title="Flag" style="background:none; border:none; cursor:pointer; font-size:12px; color:{{ $e->flagged_by_admin ? '#F6AD55' : '#d1d5db' }};">&#9733;</button>
                            </form>
                        </td>
                        <td style="padding:5px 8px; color:#1565C0; font-weight:600;">{{ $e->full_name }} <span style="color:#9ca3af; font-weight:400;">({{ $e->agent_code }})</span></td>
                        <td style="padding:5px 8px; font-weight:{{ $unread ? '700' : '400' }};">
                            @if($unread)<span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#F44336; margin-right:5px;"></span>@endif
                            {{ $e->subject }}
                            @if($e->has_attachment)<span style="color:#9ca3af; margin-left:4px;" title="Has attachment">&#128206;</span>@endif
                            &rarr;
                        </td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ str_replace('_', ' ', $e->category) }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ \Carbon\Carbon::parse($e->last_message_at)->format('d M Y, h:i A') }}</td>
                        <td style="padding:5px 8px; text-align:right;">
                            <span style="padding:2px 8px; border-radius:20px; font-size:8.5px; font-weight:600;
                                background:{{ $e->status === 'OPEN' ? '#fff8e1' : ($e->status === 'ANSWERED' ? '#e3f2fd' : '#f3f4f6') }};
                                color:{{ $e->status === 'OPEN' ? '#92400e' : ($e->status === 'ANSWERED' ? '#1565C0' : '#6b7280') }};">{{ $e->status }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:16px; text-align:center; color:#9ca3af;">No enquiries found{{ $search ? ' matching "'.$search.'"' : '' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($enquiries->onFirstPage())
                <span style="background:#c4c9d0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">&larr; Prev</span>
            @else
                <a href="{{ $enquiries->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">&larr; Prev</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">Page {{ $enquiries->currentPage() }} of {{ $enquiries->lastPage() }} ({{ $enquiries->total() }} enquiries)</span>
            @if($enquiries->hasMorePages())
                <a href="{{ $enquiries->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">Next &rarr;</a>
            @else
                <span style="background:#c4c9d0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">Next &rarr;</span>
            @endif
        </div>
    </div>

</div>
@endsection
