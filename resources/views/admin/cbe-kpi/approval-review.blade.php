@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_exec.row_pending_approval_messages'))

@section('content')

{{-- NEW 27 Aug 2026 — Box 6's Approve/Reject action screen, per Chris:
"the sender can attached the print out pdf attachment or you can use
internal messaging OTP approval method... I am not referring a fully
automatic workflow." Same OTP send/verify UX as the vendor agreement
acceptance screen (resources/views/vendor/agreement.blade.php) — Send
Verification Code, then type the 6-digit code back in alongside an
Approve or Reject decision. --}}
<style>
.ed-box{flex:1; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; padding:10px 12px; display:flex; flex-direction:column; min-width:0; box-sizing:border-box; box-shadow:0 2px 10px rgba(21,101,192,0.10); overflow:hidden;}
.ed-box-title{font-size:9px; font-weight:700; color:var(--gl-blue); text-transform:uppercase; letter-spacing:0.3px; margin-bottom:6px; padding-bottom:5px; border-bottom:1px solid #eef2f7; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.cbe-review-field{display:flex; flex-direction:column; gap:2px; margin-bottom:8px;}
.cbe-review-label{font-size:7.5px; font-weight:700; color:#6b7280; text-transform:uppercase;}
.cbe-review-value{font-size:9.5px; color:#263238;}
.cbe-otp-input{font-family:'Poppins',sans-serif; font-size:16px; font-weight:700; letter-spacing:6px; text-align:center; color:#0D5A8E; border:1px solid #b2ebf2; border-radius:6px; padding:8px; width:160px; box-sizing:border-box;}
.cbe-btn{border:none; border-radius:5px; padding:7px 18px; font-size:9px; font-weight:700; cursor:pointer;}
.cbe-btn-primary{background:var(--gl-blue); color:#fff;}
.cbe-btn-approve{background:#2e7d32; color:#fff;}
.cbe-btn-reject{background:#c62828; color:#fff;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:6px 16px; box-sizing:border-box; gap:8px; overflow:hidden;">

    <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; gap:10px;">
        <div style="font-size:12px; font-weight:700; color:#263238;">{{ __('cbe_exec.row_pending_approval_messages') }}</div>
        <a href="{{ route('admin.cbe-kpi.approvals', $backQuery) }}" style="font-size:8.5px; color:var(--gl-blue); text-decoration:none; font-weight:700; white-space:nowrap;">← {{ __('admin_cbe_kpi.back_to_dashboard') }}</a>
    </div>

    @if(session('success'))
    <div style="flex-shrink:0; background:#E8F5E9; color:#2e7d32; border-radius:6px; padding:6px 12px; font-size:9px; font-weight:600;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="flex-shrink:0; background:#FFEBEE; color:#c62828; border-radius:6px; padding:6px 12px; font-size:9px; font-weight:600;">{{ session('error') }}</div>
    @endif

    <div style="flex:1; min-height:0; display:flex; gap:10px;">
        <div class="ed-box" style="flex:1;">
            <div class="ed-box-title">{{ __('cbe_exec.approval_subject_label') }}</div>
            <div style="overflow-y:auto;">
                <div class="cbe-review-field">
                    <span class="cbe-review-label">{{ __('cbe_exec.approval_subject_label') }}</span>
                    <span class="cbe-review-value">{{ $msg->subject }}</span>
                </div>
                <div class="cbe-review-field">
                    <span class="cbe-review-label">{{ __('cbe_exec.approval_category_label') }}</span>
                    <span class="cbe-review-value">{{ __('cbe_exec.approval_cat_'.strtolower($msg->category)) }}</span>
                </div>
                <div class="cbe-review-field">
                    <span class="cbe-review-label">{{ __('cbe_exec.approval_sender_label') }}</span>
                    <span class="cbe-review-value">{{ $msg->sender_name ?? '—' }}</span>
                </div>
                <div class="cbe-review-field">
                    <span class="cbe-review-label">{{ __('cbe_exec.approval_recipient_label') }}</span>
                    <span class="cbe-review-value">{{ $msg->recipient_name ?? '—' }} ({{ $msg->recipient_email ?? '—' }})</span>
                </div>
                @if($msg->body)
                <div class="cbe-review-field">
                    <span class="cbe-review-label">{{ __('cbe_exec.approval_body_label') }}</span>
                    <span class="cbe-review-value">{{ $msg->body }}</span>
                </div>
                @endif
            </div>
        </div>

        <div class="ed-box" style="flex:1;">
            <div class="ed-box-title">{{ __('cbe_exec.approval_otp_code_label') }}</div>
            @if($msg->status !== 'PENDING')
            <div style="font-size:9.5px; color:#6b7280;">{{ __('cbe_exec.approval_already_decided') }}</div>
            @else
            <form method="POST" action="{{ route('admin.cbe-kpi.approvals.send-otp', $msg->message_id) }}" style="margin-bottom:10px;">
                @csrf
                @foreach($backQuery as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <button type="submit" class="cbe-btn cbe-btn-primary">{{ __('cbe_exec.approval_send_otp_btn') }}</button>
            </form>

            <form method="POST" action="{{ route('admin.cbe-kpi.approvals.decide', $msg->message_id) }}">
                @csrf
                <div class="cbe-review-field">
                    <span class="cbe-review-label">{{ __('cbe_exec.approval_otp_code_label') }}</span>
                    <input type="text" name="otp_code" maxlength="6" class="cbe-otp-input" placeholder="000000">
                </div>
                <div class="cbe-review-field">
                    <span class="cbe-review-label">{{ __('cbe_exec.approval_response_note_label') }}</span>
                    <textarea name="response_note" rows="2" style="font-family:'Poppins',sans-serif; font-size:9px; border:1px solid #d1d5db; border-radius:5px; padding:6px; width:100%; box-sizing:border-box;"></textarea>
                </div>
                <div style="display:flex; gap:8px; margin-top:6px;">
                    <button type="submit" name="decision" value="APPROVED" class="cbe-btn cbe-btn-approve">{{ __('cbe_exec.approval_approve_btn') }}</button>
                    <button type="submit" name="decision" value="REJECTED" class="cbe-btn cbe-btn-reject">{{ __('cbe_exec.approval_reject_btn') }}</button>
                </div>
            </form>
            @endif
        </div>
    </div>
</div>
@endsection
