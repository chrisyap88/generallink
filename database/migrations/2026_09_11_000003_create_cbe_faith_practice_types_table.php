<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// NEW 11 Sep 2026 — per Chris: "Faith Practice Type" was hardcoded to 5
// fixed choices in CbeFaithTerminologyService (a PHP const array) — adding
// a 6th (e.g. a proper Buddhist or Muslim wording set) required a code
// change and a deployment every time. Chris's rule going forward: nothing
// in this app may offer only a few hardcoded choices without asking him
// first — so this turns the wording sets into a real Admin-editable
// catalog, same pattern already used for cbe_glade_membership_tiers.
//
// Each row is a full "wording set": which words this CBE group's
// Appointments tab uses (tab name, practitioner title, duty name, log
// form title) for whichever wording set the group selects. It does NOT
// store or imply the group's actual religion — it only controls terminology.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbe_faith_practice_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique(); // internal stable identifier, auto-derived from the name
            $table->string('option_label', 150);   // shown in the dropdown itself on the Group Name edit screen
            $table->string('tab_label', 100);       // Appointments tab name
            $table->string('practitioner_label', 100); // e.g. "Sensei", "Confessor", "Priest"
            $table->string('duty_label', 100);      // e.g. "Prayer & Counseling", "Confession"
            $table->string('log_form_title', 150);  // e.g. "Log Appointment with Sensei"
            // is_system = true for the 5 original wording sets this table
            // is seeded with below — kept editable (Admin can fix wording
            // any time) but never deletable, since existing CBE groups may
            // already be using them.
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $seed = [
            [
                'code' => 'NONE',
                'option_label' => 'Not applicable (charity / enterprise / government-linked / business)',
                'tab_label' => 'Appointment',
                'practitioner_label' => 'Advisor',
                'duty_label' => 'Appointment',
                'log_form_title' => 'Log Appointment',
                'sort_order' => 1,
            ],
            [
                'code' => 'TAOIST',
                'option_label' => 'Taoist (Sensei / spirit medium)',
                'tab_label' => 'Prayer & Counseling',
                'practitioner_label' => 'Sensei',
                'duty_label' => 'Prayer & Counseling',
                'log_form_title' => 'Log Appointment with Sensei',
                'sort_order' => 2,
            ],
            [
                'code' => 'CHRISTIAN_PROTESTANT',
                'option_label' => 'Christian — Protestant (Confession)',
                'tab_label' => 'Confession',
                'practitioner_label' => 'Confessor',
                'duty_label' => 'Confession',
                'log_form_title' => 'Log Confession Appointment',
                'sort_order' => 3,
            ],
            [
                'code' => 'CATHOLIC',
                'option_label' => 'Catholic (Sacrament of Reconciliation)',
                'tab_label' => 'Sacrament of Reconciliation',
                'practitioner_label' => 'Priest',
                'duty_label' => 'Sacrament of Reconciliation',
                'log_form_title' => 'Log Sacrament of Reconciliation Appointment',
                'sort_order' => 4,
            ],
            [
                'code' => 'OTHER_RELIGIOUS',
                'option_label' => 'Other religion (Buddhist, Muslim congregation, Hindu, etc.)',
                'tab_label' => 'Faith Consultation',
                'practitioner_label' => 'Officiant',
                'duty_label' => 'Faith Consultation',
                'log_form_title' => 'Log Faith Consultation Appointment',
                'sort_order' => 5,
            ],
        ];

        foreach ($seed as $row) {
            DB::table('cbe_faith_practice_types')->insert([
                'id' => (string) Str::uuid(),
                'code' => $row['code'],
                'option_label' => $row['option_label'],
                'tab_label' => $row['tab_label'],
                'practitioner_label' => $row['practitioner_label'],
                'duty_label' => $row['duty_label'],
                'log_form_title' => $row['log_form_title'],
                'is_system' => true,
                'is_active' => true,
                'sort_order' => $row['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_faith_practice_types');
    }
};
