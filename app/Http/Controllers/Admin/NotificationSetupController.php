<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 20 Jul 2026 — per Chris: the sidebar has had a "Notification
// Setup" link since 17 Jul 2026 that pointed nowhere ("#" placeholder).
// Chris kept expecting to find every reminder type (renewal,
// quotation escalation, follow-up, unclaimed commission, approvals,
// etc.) listed and configurable in one place, and this is that screen
// — both a real settings form for the reminders that have tunable
// timing, and an honest inventory of every other reminder type so
// nothing is silently missing, including the ones that were discussed
// before but never actually built (unmatched claim reminder — see the
// note in the view for why that one specifically isn't here yet).
// -------------------------------------------------------
class NotificationSetupController extends Controller
{
    public function index()
    {
        $settings = DB::table('system_settings')->pluck('setting_value', 'setting_key');

        $values = [
            'renewal_reminder_days'            => $settings['renewal_reminder_days'] ?? 30,
            'quotation_escalation_day1'         => $settings['quotation_escalation_day1'] ?? 3,
            'quotation_escalation_day2'         => $settings['quotation_escalation_day2'] ?? 5,
            'quotation_escalation_day3'         => $settings['quotation_escalation_day3'] ?? 7,
            'quotation_escalation_day4'         => $settings['quotation_escalation_day4'] ?? 10,
            'unclaimed_commission_enabled'      => $settings['unclaimed_commission_enabled'] ?? '1',
            'unclaimed_commission_threshold_days' => $settings['unclaimed_commission_threshold_days'] ?? 60,
            'unclaimed_commission_min_amount'  => $settings['unclaimed_commission_min_amount'] ?? 50,
            'approval_reminder_hours'          => $settings['approval_reminder_hours'] ?? 24,
            'approval_escalation_hours'        => $settings['approval_escalation_hours'] ?? 48,
        ];

        $isDirector = Auth::guard('agent')->user()->department === 'DIRECTOR';

        return view('admin.notification-setup', compact('values', 'isDirector'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'renewal_reminder_days'              => ['required', 'integer', 'min:1', 'max:365'],
            'quotation_escalation_day1'           => ['required', 'integer', 'min:1', 'max:365'],
            'quotation_escalation_day2'           => ['required', 'integer', 'min:1', 'max:365'],
            'quotation_escalation_day3'           => ['required', 'integer', 'min:1', 'max:365'],
            'quotation_escalation_day4'           => ['required', 'integer', 'min:1', 'max:365'],
            'unclaimed_commission_enabled'        => ['required', 'in:0,1'],
            'unclaimed_commission_threshold_days' => ['required', 'integer', 'min:1', 'max:365'],
            'unclaimed_commission_min_amount'     => ['required', 'numeric', 'min:0'],
        ]);

        // Guard against an escalation timeline that doesn't actually
        // escalate (e.g. GL notified before TL) — each step must come
        // strictly after the one before it.
        if (!($request->quotation_escalation_day1 < $request->quotation_escalation_day2
            && $request->quotation_escalation_day2 < $request->quotation_escalation_day3
            && $request->quotation_escalation_day3 < $request->quotation_escalation_day4)) {
            return back()->withInput()->withErrors([
                'quotation_escalation_day1' => 'The 4 escalation days must increase in order (e.g. 3, 5, 7, 10) — each step must be a later day than the one before it.',
            ]);
        }

        $agentId = Auth::guard('agent')->id();
        $keys = [
            'renewal_reminder_days',
            'quotation_escalation_day1',
            'quotation_escalation_day2',
            'quotation_escalation_day3',
            'quotation_escalation_day4',
            'unclaimed_commission_enabled',
            'unclaimed_commission_threshold_days',
            'unclaimed_commission_min_amount',
        ];

        foreach ($keys as $key) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $request->input($key), 'updated_by' => $agentId, 'updated_at' => now()]
            );
        }

        return redirect()->route('admin.notification-setup.index')->with('success', 'Notification settings saved.');
    }
}
