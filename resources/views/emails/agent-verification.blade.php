<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('emails.verify_email_page_title') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#e0f7fa; font-family: Arial, sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#e0f7fa; padding: 40px 0;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                <tr>
                    <td align="center" style="background: linear-gradient(135deg, #1B9AE4, #0D5A8E); padding: 40px 30px;">
                        <h1 style="color:#ffffff; margin:0; font-size:28px; font-weight:800;">{{ __('emails.brand_name_header') }}</h1>
                        <p style="color:#b2ebf2; margin:8px 0 0; font-size:13px; letter-spacing:3px;">{{ __('emails.brand_tagline') }}</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 40px 40px 20px;">
                        <h2 style="color:#0D5A8E; font-size:22px; margin:0 0 16px;">{{ __('emails.welcome_heading') }}</h2>
                        <p style="color:#444; font-size:15px; line-height:1.7; margin:0 0 16px;">
                            {!! __('emails.dear_greeting_template', ['name' => $name]) !!}
                        </p>
                        <p style="color:#444; font-size:15px; line-height:1.7; margin:0 0 16px;">
                            {!! __('emails.registering_thanks_note') !!}
                        </p>
                        <p style="color:#444; font-size:15px; line-height:1.7; margin:0 0 24px;">
                            {{ __('emails.verify_activate_note') }}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding: 0 40px 30px;">
                        <table cellpadding="0" cellspacing="0">
                            <tr>
                                <td align="center" style="background: linear-gradient(135deg, #1B9AE4, #0D5A8E); border-radius:12px; padding:16px 40px;">
                                    <a href="{{ $verificationUrl }}"
                                       style="color:#ffffff; text-decoration:none; font-size:16px; font-weight:bold;">
                                        {{ __('emails.verify_button_label') }}
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 0 40px 30px;">
                        <div style="background:#f0fbff; border-left:4px solid #1B9AE4; border-radius:8px; padding:20px;">
                            <p style="color:#0D5A8E; font-size:14px; font-weight:bold; margin:0 0 10px;">{{ __('emails.what_happens_after_heading') }}</p>
                            <p style="color:#444; font-size:14px; line-height:1.8; margin:0;">
                                {!! __('emails.what_happens_after_steps') !!}
                            </p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 0 40px 30px;">
                        <p style="color:#888; font-size:13px; line-height:1.7; margin:0;">
                            {{ __('emails.button_not_working_note') }}
                        </p>
                        <p style="color:#1B9AE4; font-size:12px; word-break:break-all; margin:8px 0 0;">
                            {{ $verificationUrl }}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 0 40px 30px;">
                        <div style="background:#fff8e8; border-left:4px solid #D97706; border-radius:8px; padding:16px;">
                            <p style="color:#D97706; font-size:13px; margin:0;">
                                {!! __('emails.security_notice_template') !!}
                            </p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="background:#f8f9fa; padding:30px; border-top:1px solid #e0e0e0;">
                        <p style="color:#0D5A8E; font-size:14px; font-weight:bold; margin:0 0 8px;">{{ __('emails.footer_brand') }}</p>
                        <p style="color:#888; font-size:12px; margin:0 0 4px;">{{ __('emails.footer_tagline_caps') }}</p>
                        <p style="color:#bbb; font-size:11px; margin:16px 0 0;">{{ __('emails.automated_email_note') }}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>