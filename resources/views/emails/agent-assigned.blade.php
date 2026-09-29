<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Arial, sans-serif; background: #f0f9ff; margin: 0; padding: 20px; }
    .card { background: #fff; border-radius: 12px; max-width: 600px; margin: 0 auto; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    .header { background: linear-gradient(90deg, #1B9AE4, #0D5A8E); padding: 24px 30px; }
    .header h1 { color: #fff; font-size: 20px; margin: 0; }
    .header p { color: rgba(255,255,255,0.8); font-size: 13px; margin: 4px 0 0; }
    .body { padding: 24px 30px; }
    .info-box { background: #f0f9ff; border-radius: 10px; padding: 16px; margin: 16px 0; border-left: 3px solid #1B9AE4; }
    .info-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px; }
    .info-label { color: #718096; font-weight: 500; }
    .info-value { font-weight: 600; color: #0D5A8E; }
    .btn { display: inline-block; background: linear-gradient(90deg, #1B9AE4, #0D5A8E); color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 14px; margin-top: 16px; }
    .footer { background: #f7fdff; padding: 16px 30px; text-align: center; font-size: 11px; color: #b0bec5; border-top: 1px solid #e2e8f0; }
</style>
</head>
<body>
<div class="card">
    <div class="header">
        <h1>{{ __('emails.account_assignment_confirmed_title') }}</h1>
        <p>GeneralLink Digital Ecosystem</p>
    </div>
    <div class="body">
        <p style="font-size:14px;color:#2D3748;">{!! __('emails.dear_greeting_template', ['name' => $agent->full_name]) !!}</p>
        <p style="font-size:13px;color:#4A5568;margin-top:12px;">{{ __('emails.account_assigned_note_template', ['role' => \App\Services\RoleLabelService::label('GROUP_LEADER')]) }}</p>

        <div class="info-box">
            <div class="info-row"><span class="info-label">{{ __('emails.your_name_label') }}</span><span class="info-value">{{ $agent->full_name }}</span></div>
            <div class="info-row"><span class="info-label">{{ __('emails.your_affiliate_code_label') }}</span><span class="info-value">{{ $agent->agent_code ?? __('emails.generating_placeholder') }}</span></div>
            <div class="info-row"><span class="info-label">{{ __('emails.assigned_role_template', ['role' => \App\Services\RoleLabelService::label('GROUP_LEADER')]) }}</span><span class="info-value">{{ $gl->full_name }}</span></div>
            <div class="info-row"><span class="info-label">{{ __('emails.role_affiliate_code_template', ['role' => \App\Services\RoleLabelService::shortLabel('GROUP_LEADER')]) }}</span><span class="info-value">{{ $gl->agent_code }}</span></div>
            <div class="info-row"><span class="info-label">{{ __('emails.assignment_date_label') }}</span><span class="info-value">{{ now()->format('d M Y, h:i A') }}</span></div>
        </div>

        <p style="font-size:13px;color:#4A5568;">{{ __('emails.login_dashboard_note') }}</p>
        <a href="{{ url('/') }}" class="btn">{{ __('emails.login_to_dashboard_button') }}</a>
    </div>
    <div class="footer">
        {{ __('emails.footer_tagline') }}<br>
        {{ __('emails.automated_notification_note') }}
    </div>
</div>
</body>
</html>
