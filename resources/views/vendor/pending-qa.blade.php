<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('vendor.pending_qa_page_title') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    {{-- NEW 12 Aug 2026 — pre-approval vendor Q&A page. Same font/colour
         family as vendor-login.blade.php / vendor-register.blade.php
         (Outfit + Rajdhani, #0D5A8E/#1B9AE4 blue) so a vendor lands
         somewhere visually familiar even though they have no login yet.
         Whole page fits with no outer scroll; only the message list
         itself scrolls internally once it's longer than its box, same
         "small internal scroll on an otherwise fixed screen" convention
         used everywhere else in GeneralLink. --}}
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100vh; width: 100vw; overflow: hidden; font-family: 'Outfit', sans-serif; }
        body { display: flex; align-items: center; justify-content: center; background: linear-gradient(160deg, #e0f7fa 0%, #b2ebf2 40%, #e0f2f1 100%); padding: 3vh 2vw; }
        .card { width: 100%; max-width: 560px; height: 90vh; max-height: 680px; background: #fff; border-radius: 16px; box-shadow: 0 12px 40px rgba(13,90,142,.18); display: flex; flex-direction: column; overflow: hidden; }
        .card-header { flex-shrink: 0; background: linear-gradient(90deg, #1B9AE4 0%, #0D5A8E 100%); padding: 14px 18px; display: flex; align-items: center; gap: 10px; }
        .card-header img { height: 30px; border-radius: 6px; }
        .card-header .titles { min-width: 0; }
        .card-header h1 { font-family: 'Rajdhani', sans-serif; font-size: 15px; font-weight: 700; color: #fff; line-height: 1.2; }
        .card-header .sub { font-size: 10px; color: rgba(255,255,255,.85); margin-top: 1px; }
        .alert-box { flex-shrink: 0; margin: 10px 18px 0; border-radius: 8px; padding: 6px 10px; font-size: 11px; border-left: 3px solid; }
        .alert-success { background:#e8f5e9; border-color:#38A169; color:#1b5e20; }
        .thread { flex: 1; min-height: 0; overflow-y: auto; padding: 14px 18px; display: flex; flex-direction: column; gap: 8px; }
        .bubble-row { display: flex; }
        .bubble-row.vendor { justify-content: flex-end; }
        .bubble { max-width: 78%; border-radius: 12px; padding: 7px 11px; font-size: 11.5px; line-height: 1.4; word-break: break-word; }
        .bubble.admin { background: #f0f9ff; color: #1a2b3c; border-bottom-left-radius: 3px; }
        .bubble.vendor { background: #1B9AE4; color: #fff; border-bottom-right-radius: 3px; }
        .bubble-meta { font-size: 8.5px; color: #9ca3af; margin-top: 2px; }
        .bubble-row.vendor .bubble-meta { text-align: right; }
        .empty { color: #9ca3af; font-size: 11px; text-align: center; margin: auto; }
        .amend-tag { font-size: 8.5px; font-weight: 700; color: #b45309; margin-bottom: 2px; }
        .bubble-row.vendor .amend-tag { text-align: right; }
        .attach-link { display: inline-block; margin-top: 3px; font-size: 9.5px; font-weight: 600; color: #0D5A8E; background: #e0f2fe; border-radius: 5px; padding: 2px 8px; text-decoration: none; }
        .reply-box { flex-shrink: 0; border-top: 1px solid #eef2f7; padding: 12px 18px; display: flex; flex-direction: column; gap: 8px; }
        textarea { width: 100%; resize: none; height: 52px; border: 1.5px solid #b2ebf2; border-radius: 10px; padding: 8px 10px; font-size: 11.5px; font-family: 'Outfit', sans-serif; color: #2D3748; background: #f7fdff; outline: none; }
        textarea:focus { border-color: #1B9AE4; background: #fff; box-shadow: 0 0 0 3px rgba(27,154,228,.12); }
        .reply-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .reply-row input[type=file] { font-size: 9.5px; max-width: 55%; }
        .btn-send { flex-shrink: 0; padding: 7px 22px; background: linear-gradient(90deg, #1B9AE4 0%, #0D5A8E 100%); color: #fff; border: none; border-radius: 8px; font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; cursor: pointer; }
        .btn-send:hover { opacity: .92; }
    </style>
</head>
<body>

<div class="card">
    <div class="card-header">
        <img src="{{ asset('images/generallink-logo.jpeg') }}" alt="GeneralLink Logo">
        <div class="titles">
            <h1>{{ __('vendor.pending_qa_heading') }}</h1>
            <div class="sub">{{ $vendor->vendor_name }}</div>
        </div>
    </div>

    @if(session('success'))
    <div class="alert-box alert-success">{{ session('success') }}</div>
    @endif

    <div class="thread">
        @forelse($messages as $m)
        <div class="bubble-row {{ $m->sender_type === 'VENDOR' ? 'vendor' : 'admin' }}">
            <div>
                @if(($m->message_type ?? 'MESSAGE') === 'AMENDMENT_REQUEST')
                <div class="amend-tag">{{ __('vendor.change_requested_tag') }}</div>
                @endif
                <div class="bubble {{ $m->sender_type === 'VENDOR' ? 'vendor' : 'admin' }}">{{ $m->message }}</div>
                @if(!empty($m->attachment_path))
                <div><a class="attach-link" href="{{ route('vendor.pending-qa.attachment', [$vendor->qa_access_token, $m->message_id]) }}" target="_blank">&#128206; {{ $m->attachment_file_name }}</a></div>
                @endif
                <div class="bubble-meta">{{ $m->sender_type === 'VENDOR' ? __('help-desk.you') : 'GeneralLink' }} &middot; {{ \Carbon\Carbon::parse($m->created_at)->format('d M Y, h:ia') }}</div>
            </div>
        </div>
        @empty
        <div class="empty">{{ __('vendor.no_messages_yet') }}</div>
        @endforelse
    </div>

    <form class="reply-box" method="POST" action="{{ route('vendor.pending-qa.reply', $vendor->qa_access_token) }}" enctype="multipart/form-data">
        @csrf
        <textarea name="message" maxlength="2000" required placeholder="{{ __('vendor.reply_placeholder') }}"></textarea>
        <div class="reply-row">
            <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
            <button type="submit" class="btn-send">{{ __('help-desk.send') }}</button>
        </div>
    </form>
</div>

</body>
</html>
