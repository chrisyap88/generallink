@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', 'Enquiry — ' . $enquiry->subject)

@section('content')

{{-- NEW 21 Jul 2026 — Admin's thread view: same message layout as the
     agent side, plus Close/Reopen actions Admin alone can use. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:flex-end; align-items:center;">
        <div style="display:flex; align-items:center; gap:8px;">
            <form method="POST" action="{{ route('admin.enquiries.flag', $enquiry->enquiry_id) }}" style="display:inline;">
                @csrf
                <button type="submit" title="Flag" style="background:none; border:none; cursor:pointer; font-size:15px; color:{{ $enquiry->flagged_by_admin ? '#F6AD55' : '#d1d5db' }};">&#9733;</button>
            </form>
            <span style="padding:2px 10px; border-radius:20px; font-size:9.5px; font-weight:600;
                background:{{ $enquiry->status === 'OPEN' ? '#fff8e1' : ($enquiry->status === 'ANSWERED' ? '#e3f2fd' : '#f3f4f6') }};
                color:{{ $enquiry->status === 'OPEN' ? '#92400e' : ($enquiry->status === 'ANSWERED' ? '#1565C0' : '#6b7280') }};">{{ $enquiry->status }}</span>
            @if($enquiry->status !== 'CLOSED')
            <form method="POST" action="{{ route('admin.enquiries.close', $enquiry->enquiry_id) }}" style="display:inline;">
                @csrf
                <button type="submit" style="background:#f3f4f6; color:#374151; border:none; border-radius:6px; padding:5px 14px; font-size:9.5px; font-weight:600; cursor:pointer;">Close Enquiry</button>
            </form>
            @else
            <form method="POST" action="{{ route('admin.enquiries.reopen', $enquiry->enquiry_id) }}" style="display:inline;">
                @csrf
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:5px 14px; font-size:9.5px; font-weight:600; cursor:pointer;">Reopen</button>
            </form>
            @endif
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:4px 10px; font-size:10px; color:#b71c1c; margin-bottom:6px;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    <div style="flex-shrink:0; margin-bottom:8px;">
        <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ $enquiry->subject }}</div>
        <div style="font-size:9.5px; color:#9ca3af;">
            From: {{ $enquiry->full_name }} ({{ $enquiry->agent_code }} — {{ $enquiry->agent_role }}) — To: Admin{{ $ccNames->count() ? ' — Cc: ' . $ccNames->implode(', ') : '' }}<br>
            {{ str_replace('_', ' ', $enquiry->category) }} — raised {{ \Carbon\Carbon::parse($enquiry->created_at)->format('d M Y, h:i A') }}
        </div>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @foreach($messages as $m)
            @php $isAdminMsg = $m->role === 'ADMIN'; @endphp
            <div style="margin-bottom:10px; display:flex; {{ $isAdminMsg ? 'justify-content:flex-end;' : 'justify-content:flex-start;' }}">
                <div style="max-width:70%; background:{{ $isAdminMsg ? '#1565C0' : '#f3f4f6' }}; color:{{ $isAdminMsg ? '#fff' : '#374151' }}; border-radius:10px; padding:8px 10px;">
                    <div style="font-size:8.5px; opacity:.8; margin-bottom:3px;">{{ $m->full_name ?? 'Unknown' }} ({{ $isAdminMsg ? 'Admin' : 'Agent' }}) — {{ \Carbon\Carbon::parse($m->created_at)->format('d M Y, h:i A') }}</div>
                    <div style="font-size:10.5px; white-space:pre-wrap; line-height:1.4;">{{ $m->body }}</div>
                    @if($m->attachment_file_path)
                    <div style="margin-top:5px;">
                        <a href="{{ route('enquiries.attachment', $m->message_id) }}" target="_blank" style="font-size:9px; font-weight:600; color:{{ $isAdminMsg ? '#e3f2fd' : '#1565C0' }}; text-decoration:underline;">&#128206; {{ $m->attachment_file_name }}</a>
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('admin.enquiries.reply', $enquiry->enquiry_id) }}" enctype="multipart/form-data" style="border-top:1px solid #f3f4f6; padding-top:8px; margin-top:8px;">
            @csrf
            <textarea name="body" rows="2" maxlength="3000" required placeholder="Type your reply..." style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11px; margin-bottom:6px; box-sizing:border-box; resize:none;"></textarea>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf" style="font-size:9.5px; max-width:220px;">
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">Send Reply</button>
            </div>
        </form>
    </div>

    <div style="flex-shrink:0; padding-top:8px;">
        <a href="{{ route('admin.enquiries.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600; display:inline-block;">&larr; Prev</a>
    </div>

</div>
@endsection
