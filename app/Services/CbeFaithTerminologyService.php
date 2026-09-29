<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

// CHANGED 11 Sep 2026 — per Chris: this used to be a fixed PHP array of 5
// wording sets (TAOIST/CHRISTIAN_PROTESTANT/CATHOLIC/OTHER_RELIGIOUS/NONE)
// — adding a 6th needed a code change every time, which Chris's standing
// rule now forbids without asking him first. The wording sets now live in
// the cbe_faith_practice_types table (Admin-manageable from the
// "Appointment Position Types" screen — see AdminFaithPracticeTypeController).
//
// CHANGED 12 Sep 2026 — per Chris: "why this is hardcoded? i can add
// others like Advisor, Councilor, Consultant, in house Legal Advisor,
// Volunteer Lawyer, Medical Advisor... it should have multiple choice
// because one CBE may have few other position appointed in house." A
// community is no longer limited to ONE wording set / position —
// group_labels.faith_practice_type (single value) is replaced by the
// group_label_appointment_types pivot table, so a community can enable
// any number of positions at once (e.g. both "Legal Advisor" and
// "Medical Advisor"). Each position also now carries its OWN
// admin-editable list of appointment reasons (reason_options), since a
// temple's reasons (Prayer, Blessing) make no sense for a Legal Advisor.
//
// Nothing about the group's actual religion, or any fixed business
// type, is stored or implied here — this only controls which WORDS the
// Appointments tab uses for whichever position(s) a community turned on.
//
// terms()/positionsForGroup() return plain, already-final text (not
// translation keys) — callers pass the values straight through __() for
// historical reasons; Laravel's __() simply returns a string unchanged
// when it isn't a real translation-file key, so this stays compatible
// with every existing call site without needing to touch them.
class CbeFaithTerminologyService
{
    /**
     * Every position a community has ENABLED (checked on the Group Name
     * edit screen), each with its own wording set and reason list. Used
     * by the Appointments tab / Log Appointment forms so a member can
     * pick which position they're booking with. Always returns at
     * least one row (a generic fallback) so a caller never has to
     * special-case an empty list.
     *
     * @return array<int, array{id: ?string, code: string, tab_label_key: string, practitioner_label_key: string, duty_label_key: string, log_form_title_key: string, reasons: array<int, string>}>
     */
    public static function positionsForGroup(?string $groupLabelId): array
    {
        if ($groupLabelId) {
            $rows = DB::table('group_label_appointment_types as gat')
                ->join('cbe_faith_practice_types as t', 't.id', '=', 'gat.practice_type_id')
                ->where('gat.group_label_id', $groupLabelId)
                ->where('gat.is_active', true)
                ->where('t.is_active', true)
                ->orderBy('gat.sort_order')
                ->orderBy('t.sort_order')
                ->select('t.*')
                ->get();

            if ($rows->isNotEmpty()) {
                return $rows->map(fn ($row) => self::mapRow($row))->all();
            }
        }

        return [self::fallbackRow()];
    }

    /**
     * Single wording set by catalog id or code — kept for the handful of
     * places that only ever deal with one position at a time (e.g. the
     * catalog admin screen itself). Prefer positionsForGroup() for
     * anything member-facing, since a community may have more than one
     * position enabled.
     *
     * @return array{id: ?string, code: string, tab_label_key: string, practitioner_label_key: string, duty_label_key: string, log_form_title_key: string, reasons: array<int, string>}
     */
    public static function terms(?string $idOrCode): array
    {
        $row = null;
        if ($idOrCode) {
            $row = DB::table('cbe_faith_practice_types')
                ->where(function ($w) use ($idOrCode) {
                    $w->where('id', $idOrCode)->orWhere('code', $idOrCode);
                })
                ->where('is_active', true)
                ->first();
        }

        if (! $row) {
            $row = DB::table('cbe_faith_practice_types')->where('code', 'NONE')->first();
        }

        return $row ? self::mapRow($row) : self::fallbackRow();
    }

    /**
     * Full catalog for the multi-select checkboxes on the Group Name
     * edit screen, as [catalog id => display label]. Fully
     * Admin-editable — see AdminFaithPracticeTypeController — never
     * hardcode a new choice here.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return DB::table('cbe_faith_practice_types')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('option_label', 'id')
            ->all();
    }

    /**
     * Full catalog rows (not just id => label) for screens that need
     * more than the label — e.g. rendering checkboxes with each row's
     * own code/id for the pivot table.
     */
    public static function catalogRows()
    {
        return DB::table('cbe_faith_practice_types')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    private static function mapRow($row): array
    {
        return [
            'id' => $row->id,
            'code' => $row->code,
            'tab_label_key' => $row->tab_label,
            'practitioner_label_key' => $row->practitioner_label,
            'duty_label_key' => $row->duty_label,
            'log_form_title_key' => $row->log_form_title,
            'reasons' => array_values(array_filter(array_map('trim', explode(',', (string) ($row->reason_options ?? ''))))) ?: ['Consultation', 'Other'],
        ];
    }

    private static function fallbackRow(): array
    {
        return [
            'id' => null,
            'code' => 'NONE',
            'tab_label_key' => 'Appointment',
            'practitioner_label_key' => 'Advisor',
            'duty_label_key' => 'Appointment',
            'log_form_title_key' => 'Log Appointment',
            'reasons' => ['Consultation', 'Follow-up', 'Other'],
        ];
    }
}
