@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_ops.ns_page_title'))

@section('content')

{{-- NEW 20 Jul 2026 — per Chris: replaces the dead "#" sidebar
     placeholder. Chris asked several times where all the different
     "reminder" types he'd discussed could be seen/configured in one
     place — there wasn't one. This screen is both: a real settings
     form for the reminders that have tunable timing, and an honest
     inventory of every other reminder type, including the ones
     discussed before but never actually built, so nothing is silently
     missing. --}}
{{-- REBUILT 20 Jul 2026 per Chris ("i dont know how to use this...
     display in one screen folder type"): split into 2 folder-style
     tabs (Reminder Types / Settings), same tab pattern used on the
     Customer Detail screen, so each half fits on one screen without
     scrolling instead of one long stacked page. Reminder Types is the
     "what exists" reference list; Settings is the "change the numbers"
     form — a short how-to note sits at the top of Settings. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px 0; box-sizing:border-box;">

<div style="flex-shrink:0;">
    {{-- REMOVED 8 Aug 2026 per Chris: strict rule — no back/prev control
         anywhere except the bottom Prev/Next pair (and this screen has no
         list to page through, so N/A here). Use the sidebar to leave. --}}

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:4px 10px; font-size:10px; color:#b71c1c; margin-bottom:6px;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    <div style="display:flex; align-items:center; gap:3px; margin:0 2px;">
        <button type="button" class="nsTabBtn" data-tab="nsTypes" style="background:#fff; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9.5px; font-weight:700; color:#1565C0; cursor:pointer; position:relative; top:1px;">{{ __('admin_ops.ns_tab_reminder_types') }}</button>
        <button type="button" class="nsTabBtn" data-tab="nsSettings" style="background:#F7FAFC; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9.5px; font-weight:700; color:#6b7280; cursor:pointer; position:relative; top:1px;">{{ __('admin_ops.tab_settings') }}</button>
    </div>
</div>

{{-- CHANGED 8 Aug 2026 per Chris: strict no-scroll rule — was
     overflow-y:auto. Both tabs are fixed-length content (7-row reference
     table, 3 settings cards), not a growable list, so no scroll or
     pagination is needed once it isn't allowed to scroll past the fold. --}}
<div style="flex:1 1 auto; min-height:0; overflow:hidden; padding-bottom:10px;">

    {{-- TAB 1 — REMINDER TYPES: read-only reference list, every
         reminder type in the app with an honest status badge. --}}
    <div id="nsTypes" class="nsTabPanel" style="background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:8px;">
        <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
            <thead>
                <tr style="background:#f0f9ff;">
                    <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_ops.ns_col_reminder') }}</th>
                    <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_ops.ns_col_what_it_does') }}</th>
                    <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase; width:110px;">{{ __('network.status') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:5px 8px; font-weight:600;">{{ __('admin_ops.ns_renewal_reminder_label') }}</td>
                    <td style="padding:5px 8px; color:#4b5563;">{{ __('admin_ops.ns_renewal_reminder_desc') }}</td>
                    <td style="padding:5px 8px;"><span style="background:#d1fae5; color:#065f46; padding:1px 7px; border-radius:20px; font-weight:600; font-size:8.5px;">{{ __('admin_ops.ns_configurable_settings_badge') }}</span></td>
                </tr>
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:5px 8px; font-weight:600;">{{ __('admin_ops.ns_renewal_quotation_escalation_label') }}</td>
                    <td style="padding:5px 8px; color:#4b5563;">{{ __('admin_ops.ns_quotation_escalation_desc', ['role' => \App\Services\RoleLabelService::label('INTRODUCER')]) }}</td>
                    <td style="padding:5px 8px;"><span style="background:#d1fae5; color:#065f46; padding:1px 7px; border-radius:20px; font-weight:600; font-size:8.5px;">{{ __('admin_ops.ns_configurable_settings_badge') }}</span></td>
                </tr>
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:5px 8px; font-weight:600;">{{ __('admin_ops.ns_follow_up_reminder_label') }}</td>
                    <td style="padding:5px 8px; color:#4b5563;">{{ __('admin_ops.ns_follow_up_reminder_desc') }}</td>
                    <td style="padding:5px 8px;"><span style="background:#DBEAFE; color:#1e40af; padding:1px 7px; border-radius:20px; font-weight:600; font-size:8.5px;">{{ __('admin_ops.ns_no_setting_needed_badge') }}</span></td>
                </tr>
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:5px 8px; font-weight:600;">{{ __('admin_ops.ns_unclaimed_commission_reminder_label') }}</td>
                    <td style="padding:5px 8px; color:#4b5563;">{{ __('admin_ops.ns_unclaimed_commission_desc') }}</td>
                    <td style="padding:5px 8px;"><span style="background:#d1fae5; color:#065f46; padding:1px 7px; border-radius:20px; font-weight:600; font-size:8.5px;">{{ __('admin_ops.ns_configurable_settings_badge') }}</span></td>
                </tr>
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:5px 8px; font-weight:600;">{{ __('admin_ops.ns_approval_reminder_escalation_label') }}</td>
                    <td style="padding:5px 8px; color:#4b5563;">{{ __('admin_ops.ns_approval_reminder_desc') }}</td>
                    <td style="padding:5px 8px;">
                        <span style="background:#d1fae5; color:#065f46; padding:1px 7px; border-radius:20px; font-weight:600; font-size:8.5px;">{{ __('admin_ops.ns_configurable_badge') }}</span>
                        <a href="{{ route('admin.approvals.index') }}" style="font-size:8.5px; color:#1565C0; display:block; margin-top:2px;">{{ __('admin_ops.ns_edit_on_approvals_link') }}</a>
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:5px 8px; font-weight:600;">{{ __('admin_ops.ns_verification_email_reminder_label') }}</td>
                    <td style="padding:5px 8px; color:#4b5563;">{{ __('admin_ops.ns_verification_email_desc') }}</td>
                    <td style="padding:5px 8px;">
                        <span style="background:#fef3c7; color:#92400e; padding:1px 7px; border-radius:20px; font-weight:600; font-size:8.5px;">{{ __('admin_ops.ns_manual_only_badge') }}</span>
                        <a href="{{ route('admin.masterfile.pending-verifications') }}" style="font-size:8.5px; color:#1565C0; display:block; margin-top:2px;">{{ __('admin_ops.ns_go_resend_link') }}</a>
                    </td>
                </tr>
                <tr>
                    <td style="padding:5px 8px; font-weight:600;">{{ __('admin_ops.ns_unmatched_claim_reminder_label') }}</td>
                    <td style="padding:5px 8px; color:#4b5563;">{{ __('admin_ops.ns_unmatched_claim_desc') }}</td>
                    <td style="padding:5px 8px;"><span style="background:#f3f4f6; color:#6b7280; padding:1px 7px; border-radius:20px; font-weight:600; font-size:8.5px;">{{ __('admin_ops.ns_not_built_badge') }}</span></td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- TAB 2 — SETTINGS: the actual editable form, with a short
         how-to note at the top per Chris ("i dont know how to use
         this"). --}}
    <div id="nsSettings" class="nsTabPanel" style="display:none; background:#F7FAFC; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:8px;">

        <div style="background:#E6F1FB; border:1px solid #85B7EB; border-radius:6px; padding:6px 10px; font-size:9.5px; color:#0C447C; margin-bottom:8px;">
            {!! __('admin_ops.ns_howto_note') !!}
        </div>

        <form method="POST" action="{{ route('admin.notification-setup.update') }}">
            @csrf

            <div style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:8px 10px; margin-bottom:6px;">
                <div style="font-size:9.5px; font-weight:700; color:#1565C0; margin-bottom:6px;">{{ __('admin_ops.ns_renewal_reminder_label') }}</div>
                <div>
                    <label style="font-size:8.5px; color:#374151; display:block; margin-bottom:2px;">{{ __('admin_ops.ns_remind_customer_days_label') }}</label>
                    <input type="number" name="renewal_reminder_days" value="{{ old('renewal_reminder_days', $values['renewal_reminder_days']) }}" min="1" max="365" required style="width:90px; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:9.5px;">
                </div>
            </div>

            <div style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:8px 10px; margin-bottom:6px;">
                <div style="font-size:9.5px; font-weight:700; color:#1565C0; margin-bottom:1px;">{{ __('admin_ops.ns_renewal_quotation_escalation_label') }}</div>
                <div style="font-size:8.5px; color:#9ca3af; margin-bottom:6px;">{{ __('admin_ops.ns_escalation_days_note') }}</div>
                <div style="display:flex; align-items:end; gap:12px; flex-wrap:wrap;">
                    <div>
                        <label style="font-size:8.5px; color:#374151; display:block; margin-bottom:2px;">{{ __('admin_ops.ns_remind_role_days_label', ['role' => \App\Services\RoleLabelService::label('INTRODUCER')]) }}</label>
                        <input type="number" name="quotation_escalation_day1" value="{{ old('quotation_escalation_day1', $values['quotation_escalation_day1']) }}" min="1" max="365" required style="width:70px; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:9.5px;">
                    </div>
                    <div>
                        <label style="font-size:8.5px; color:#374151; display:block; margin-bottom:2px;">{{ __('admin_ops.ns_notify_role_days_label', ['role' => \App\Services\RoleLabelService::label('TEAM_LEADER')]) }}</label>
                        <input type="number" name="quotation_escalation_day2" value="{{ old('quotation_escalation_day2', $values['quotation_escalation_day2']) }}" min="1" max="365" required style="width:70px; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:9.5px;">
                    </div>
                    <div>
                        <label style="font-size:8.5px; color:#374151; display:block; margin-bottom:2px;">{{ __('admin_ops.ns_notify_role_days_label', ['role' => \App\Services\RoleLabelService::label('GROUP_LEADER')]) }}</label>
                        <input type="number" name="quotation_escalation_day3" value="{{ old('quotation_escalation_day3', $values['quotation_escalation_day3']) }}" min="1" max="365" required style="width:70px; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:9.5px;">
                    </div>
                    <div>
                        <label style="font-size:8.5px; color:#374151; display:block; margin-bottom:2px;">{{ __('admin_ops.ns_escalate_admin_days_label') }}</label>
                        <input type="number" name="quotation_escalation_day4" value="{{ old('quotation_escalation_day4', $values['quotation_escalation_day4']) }}" min="1" max="365" required style="width:70px; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:9.5px;">
                    </div>
                </div>
            </div>

            <div style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; padding:8px 10px; margin-bottom:8px;">
                <div style="font-size:9.5px; font-weight:700; color:#1565C0; margin-bottom:6px;">{{ __('admin_ops.ns_unclaimed_commission_reminder_label') }}</div>
                <div style="display:flex; align-items:end; gap:12px; flex-wrap:wrap;">
                    <div>
                        <label style="font-size:8.5px; color:#374151; display:block; margin-bottom:2px;">{{ __('admin_ops.ns_turned_on_label') }}</label>
                        <select name="unclaimed_commission_enabled" style="width:100px; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:9.5px; background:#fff;">
                            <option value="1" {{ old('unclaimed_commission_enabled', $values['unclaimed_commission_enabled']) == '1' ? 'selected' : '' }}>{{ __('admin_ops.ns_on_option') }}</option>
                            <option value="0" {{ old('unclaimed_commission_enabled', $values['unclaimed_commission_enabled']) == '0' ? 'selected' : '' }}>{{ __('admin_ops.ns_off_option') }}</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:8.5px; color:#374151; display:block; margin-bottom:2px;">{{ __('admin_ops.ns_remind_again_days_label') }}</label>
                        <input type="number" name="unclaimed_commission_threshold_days" value="{{ old('unclaimed_commission_threshold_days', $values['unclaimed_commission_threshold_days']) }}" min="1" max="365" required style="width:80px; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:9.5px;">
                    </div>
                    <div>
                        <label style="font-size:8.5px; color:#374151; display:block; margin-bottom:2px;">{{ __('admin_ops.ns_only_if_balance_label') }}</label>
                        <input type="number" step="0.01" name="unclaimed_commission_min_amount" value="{{ old('unclaimed_commission_min_amount', $values['unclaimed_commission_min_amount']) }}" min="0" required style="width:90px; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:9.5px;">
                    </div>
                </div>
            </div>

            <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 20px; font-size:11px; font-weight:700; cursor:pointer;">{{ __('growth.save_settings_button') }}</button>
        </form>
    </div>

</div>
</div>

<script>
(function() {
    var tabBtns = document.querySelectorAll('.nsTabBtn');
    var tabPanels = document.querySelectorAll('.nsTabPanel');

    function activateTab(tabId) {
        tabPanels.forEach(function(p) { p.style.display = (p.id === tabId) ? 'block' : 'none'; });
        tabBtns.forEach(function(b) {
            var active = b.dataset.tab === tabId;
            b.style.background = active ? '#fff' : '#F7FAFC';
            b.style.color = active ? '#1565C0' : '#6b7280';
        });
    }
    tabBtns.forEach(function(b) {
        b.addEventListener('click', function() { activateTab(b.dataset.tab); });
    });

    activateTab('nsTypes');
})();
</script>

@endsection
