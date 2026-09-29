@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_ai.upload_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_ai.upload_page_title') }}</div>
        <a href="{{ route('cbe.ai-accounting.index') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    {{-- TIGHTENED 16 Sep 2026 — per Chris: screenshot showed the Upload
         button clipped off the bottom of this screen (page must fit with
         NO scrolling per standing rule). Card padding/gaps/field spacing
         all reduced so the full form — including the button — reliably
         fits within a normal browser window height without any element
         being cut off. Same fields, same font/colour scheme, just less
         vertical space between them. --}}
    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 14px; flex:1; min-height:0; overflow:hidden; display:flex; flex-direction:column;">

        <div style="background:#fff8e1; border-left:3px solid #D97706; color:#8d6e00; border-radius:6px; padding:5px 8px; font-size:9px; margin-bottom:8px; flex-shrink:0;">
            {{ __('cbe_ai.upload_scope_note') }}
        </div>

        <form method="POST" action="{{ route('cbe.ai-accounting.batches.store') }}" enctype="multipart/form-data" style="flex:1; min-height:0; display:flex; flex-direction:column; gap:8px;">
            @csrf

            <div>
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_ai.field_batch_label') }}</label>
                <input type="text" name="label" required maxlength="150" placeholder="{{ __('cbe_ai.field_batch_label_placeholder') }}" value="{{ old('label') }}" style="width:100%; max-width:420px; border:1px solid #d1d5db; border-radius:6px; padding:5px 10px; font-size:10.5px; box-sizing:border-box;">
            </div>

            <div>
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_ai.field_bank_account_fallback') }}</label>
                <select name="bank_account_id" style="width:100%; max-width:420px; border:1px solid #d1d5db; border-radius:6px; padding:5px 10px; font-size:10.5px; box-sizing:border-box;">
                    <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                    @foreach($bankAccounts as $a)
                    <option value="{{ $a->bank_account_id }}" {{ old('bank_account_id') === $a->bank_account_id ? 'selected' : '' }}>{{ $a->bank_name }} — {{ $a->account_name }}</option>
                    @endforeach
                </select>
                <div style="font-size:8px; color:#9ca3af; margin-top:2px;">{{ __('cbe_ai.field_bank_account_fallback_helper') }}</div>
            </div>

            <div>
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_ai.field_statements') }}</label>
                <input type="file" name="statements[]" multiple required accept="application/pdf" style="width:100%; max-width:420px; border:1px solid #d1d5db; border-radius:6px; padding:5px 10px; font-size:9.5px; box-sizing:border-box; background:#fff;">
                <div style="font-size:8px; color:#9ca3af; margin-top:2px;">{{ __('cbe_ai.field_statements_helper') }}</div>
            </div>

            <div>
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_ai.field_pdf_password') }}</label>
                {{-- FIXED 16 Sep 2026 — per Chris: every file in a batch
                     failed to unlock even though the correct password was
                     already saved on the Bank Account master file.
                     autocomplete="off" is routinely ignored by Chrome/Edge
                     on password-type fields, which can silently offer to
                     fill in an unrelated saved website password here —
                     "new-password" is the value browsers actually respect
                     to suppress that suggestion. Combined with the
                     server-side fallback added today (a typed password
                     that fails now also tries the saved one automatically
                     before giving up), a stray autofill here should no
                     longer break the upload. --}}
                <input type="password" name="pdf_password" maxlength="100" autocomplete="new-password" style="width:100%; max-width:420px; border:1px solid #d1d5db; border-radius:6px; padding:5px 10px; font-size:10.5px; box-sizing:border-box;">
                <div style="font-size:8px; color:#9ca3af; margin-top:2px;">{{ __('cbe_ai.field_pdf_password_helper') }}</div>
            </div>

            <div style="margin-top:auto; flex-shrink:0; padding-top:4px;">
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:14px; padding:6px 20px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_ai.upload_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
