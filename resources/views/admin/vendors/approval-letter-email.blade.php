{{-- NEW 14 Aug 2026 — the actual email body sent by
     VendorLoginApprovalController::sendApprovalWelcomeLetter(), once
     approve() has genuinely finalized the vendor to ACTIVE. Same content
     as approval-letter.blade.php's preview pane, re-laid-out as
     standalone email-safe HTML (no external CSS, no app layout/nav —
     email clients strip both). The agreement PDF is attached separately
     by the mailer, not embedded here. --}}
<div style="font-family:'Times New Roman', Georgia, serif; color:#1a1a1a; font-size:13px; line-height:1.6; max-width:680px; margin:0 auto; padding:16px;">

    <div style="text-align:center; margin-bottom:4px;">
        <div style="font-size:22px; font-weight:700; letter-spacing:2px; color:#0D5A8E;">GLADE</div>
        <div style="font-size:10.5px; color:#6b7280;">{{ __('admin_vendors.letter_operated_by') }}</div>
        <div style="font-size:10px; color:#9ca3af;">No 5, Jalan Aman Perdana 7C/KU5, Taman Aman Perdana, 41050 Meru, Kapar, Selangor</div>
    </div>

    <div style="text-align:center; background:#0D5A8E; color:#ffffff; font-weight:700; letter-spacing:1px; padding:8px; margin:16px 0; font-size:13px;">{{ __('admin_vendors.letter_welcome_heading') }}</div>

    <div style="margin-bottom:10px;">{{ __('admin_vendors.letter_date_label') }} {{ now()->format('d F Y') }}</div>

    <div style="margin-bottom:2px;"><strong>{{ __('admin_vendors.letter_to_label') }}</strong> {{ $vendor->vendor_name }}</div>
    @if($vendor->vendor_address)
    <div style="margin-bottom:2px; margin-left:36px; white-space:pre-line;">{{ $vendor->vendor_address }}</div>
    @endif
    <div style="margin-bottom:2px;"><strong>{{ __('admin_vendors.letter_attention_label') }}</strong> {{ $vendor->pic_name ?: __('admin_vendors.letter_the_management') }}{{ $vendor->pic_designation ? ' (' . $vendor->pic_designation . ')' : '' }}</div>
    @php $ccNames = array_values(array_filter([$vendor->contact2_name ?? null, $vendor->contact3_name ?? null])); @endphp
    @if(!empty($ccNames))
    <div style="margin-bottom:12px;"><strong>{{ __('admin_vendors.letter_cc_label') }}</strong> {{ implode(', ', $ccNames) }}</div>
    @endif

    <div style="margin-bottom:16px;"><strong>{{ __('admin_vendors.letter_subject') }}</strong></div>

    <p>{{ __('admin_vendors.letter_dear', ['name' => $vendor->pic_name ?: __('admin_vendors.letter_sir_madam')]) }}</p>

    {!! '<p>' . __('admin_vendors.letter_intro_para', ['name' => '<strong>' . e($vendor->vendor_name) . '</strong>']) . '</p>' !!}

    <p><strong>{{ __('admin_vendors.letter_your_account_heading') }}</strong><br>
    {{ __('admin_vendors.letter_login_email_label') }} {{ $vendor->pic_email ?: $vendor->vendor_email }}<br>
    {{ __('admin_vendors.letter_password_note') }}</p>

    <p><strong>{{ __('admin_vendors.letter_carolyn_heading') }}</strong><br>
    {{ __('admin_vendors.letter_carolyn_para') }}</p>

    <p><strong>{{ __('admin_vendors.letter_fees_heading') }}</strong></p>
    <table style="width:100%; border-collapse:collapse; margin-bottom:12px; font-size:12.5px;">
        <tr style="background:#f0f9ff;">
            <td style="border:1px solid #cbd5e1; padding:6px 9px; font-weight:700;">{{ __('vendor.col_fee_item') }}</td>
            <td style="border:1px solid #cbd5e1; padding:6px 9px; font-weight:700;">{{ __('vendor.col_amount') }}</td>
            <td style="border:1px solid #cbd5e1; padding:6px 9px; font-weight:700;">{{ __('vendor.col_frequency') }}</td>
        </tr>
        <tr>
            <td style="border:1px solid #cbd5e1; padding:6px 9px;">{{ __('vendor.activation_fee_label') }}</td>
            <td style="border:1px solid #cbd5e1; padding:6px 9px;">RM {{ number_format(\App\Http\Controllers\Admin\VendorLoginApprovalController::ACTIVATION_FEE, 2) }}</td>
            <td style="border:1px solid #cbd5e1; padding:6px 9px;">{{ __('vendor.frequency_onetime') }}</td>
        </tr>
        <tr>
            <td style="border:1px solid #cbd5e1; padding:6px 9px;">{{ __('vendor.maintenance_fee_label') }}</td>
            <td style="border:1px solid #cbd5e1; padding:6px 9px;">RM {{ number_format(\App\Http\Controllers\Admin\VendorLoginApprovalController::ANNUAL_FEE, 2) }}</td>
            <td style="border:1px solid #cbd5e1; padding:6px 9px;">{{ __('vendor.frequency_annual') }}</td>
        </tr>
    </table>

    {!! '<p>' . __('admin_vendors.letter_payment_terms_email') . '</p>' !!}

    <p>{{ __('admin_vendors.letter_questions_para') }}</p>

    {{-- NEW 14 Aug 2026 — per Chris's uploaded signature. Embedded as a
         base64 data URI (not a public URL) since GLADE isn't hosted
         publicly yet — this way the signature still renders correctly
         in the recipient's inbox regardless, same self-contained
         approach as the attached agreement PDF. --}}
    <p style="margin-top:22px;">{{ __('admin_vendors.letter_yours_sincerely') }}<br>
    @if(file_exists(public_path('images/ywj-signature.png')))
    <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/ywj-signature.png'))) }}" alt="Signature" style="height:46px; margin:2px 0 -6px 0; display:block;">
    @else
    <br>
    @endif
    <strong>Yap Wai Jyh</strong><br>
    {{ __('admin_vendors.letter_signatory_title') }}<br>
    Email: chrisyap@mybbs.com.my &middot; HP: 012-2252275 / 016-6621311</p>
</div>
