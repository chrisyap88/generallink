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
    .status-badge { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: bold; margin: 16px 30px 0; }
    .status-pending { background: #fff8e1; color: #D97706; border: 1px solid #D97706; }
    .status-active { background: #e8f5e9; color: #38A169; border: 1px solid #38A169; }
    .body { padding: 20px 30px; }
    .section-title { font-size: 12px; font-weight: bold; color: #0D5A8E; text-transform: uppercase; letter-spacing: 0.06em; margin: 16px 0 8px; padding-bottom: 4px; border-bottom: 1.5px solid #b2ebf2; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    td { padding: 7px 10px; border-bottom: 1px solid #f0f0f0; }
    td:first-child { color: #718096; font-weight: 600; width: 40%; }
    td:last-child { color: #2D3748; }
    .footer { background: #f7fdff; padding: 16px 30px; text-align: center; font-size: 11px; color: #b0bec5; border-top: 1px solid #e2e8f0; }
</style>
</head>
<body>
<div class="card">
    <div class="header">
        <h1>{{ __('emails.new_agent_registration_title') }}</h1>
        <p>{{ __('emails.admin_notification_subtitle') }}</p>
    </div>

    <div style="padding: 0 30px;">
        <span class="status-badge {{ $status === 'ACTIVE' ? 'status-active' : 'status-pending' }}">
            {{ $status }}
        </span>
    </div>

    <div class="body">
        <div class="section-title">{{ __('emails.personal_details_heading') }}</div>
        <table>
            <tr><td>{{ __('masterfile.full_name') }}</td><td>{{ $agent->full_name }}</td></tr>
            <tr><td>{{ __('emails.nric_mykad_label') }}</td><td>{{ $agent->nric_encrypted }}</td></tr>
            <tr><td>{{ __('emails.email_address_label') }}</td><td>{{ $agent->email }}</td></tr>
            <tr><td>{{ __('emails.mobile_phone_label') }}</td><td>{{ $agent->phone }}</td></tr>
            <tr><td>{{ __('emails.full_address_label') }}</td><td>{{ $agent->address }}</td></tr>
        </table>

        <div class="section-title">{{ __('emails.bank_details_heading') }}</div>
        <table>
            <tr><td>{{ __('masterfile.bank_name') }}</td><td>{{ $agent->bank_name }}</td></tr>
            <tr><td>{{ __('emails.bank_account_label') }}</td><td>{{ $agent->bank_account_encrypted }}</td></tr>
        </table>

        <div class="section-title">{{ __('emails.upline_registration_info_heading') }}</div>
        <table>
            <tr><td>{{ __('emails.upline_type_label') }}</td><td>{{ str_replace('_', ' ', $uplineType) }}</td></tr>
            <tr><td>{{ __('emails.upline_name_label') }}</td><td>{{ $uplineAgent?->full_name ?? __('emails.pending_admin_assignment') }}</td></tr>
            <tr><td>{{ __('emails.upline_code_label') }}</td><td>{{ $uplineAgent?->agent_code ?? __('emails.pending_admin_assignment') }}</td></tr>
            <tr><td>{{ __('emails.role_assigned_label') }}</td><td>{{ \App\Services\RoleLabelService::label('INTRODUCER') }}</td></tr>
            <tr><td>{{ __('network.status') }}</td><td>{{ $status }}</td></tr>
            <tr><td>{{ __('emails.submitted_at_label') }}</td><td>{{ $submittedAt }}</td></tr>
        </table>

        @if($uplineType === 'ADMIN_ASSIGN')
        <div style="background:#fff8e1;border-left:3px solid #D97706;border-radius:8px;padding:12px 16px;margin-top:16px;font-size:13px;color:#92400e;">
            {!! __('emails.action_required_note_template', ['role' => \App\Services\RoleLabelService::label('GROUP_LEADER')]) !!}
        </div>
        @endif
    </div>

    <div class="footer">
        {{ __('emails.footer_tagline') }}<br>
        {{ __('emails.automated_notification_note') }}
    </div>
</div>
</body>
</html>
