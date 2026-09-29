<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// NEW 17 Jul 2026 — coverage_start/coverage_end on sales_transactions only
// make sense for insurance (a workshop invoice or restaurant receipt has
// no "coverage period"). Per Chris's instruction, these move into their
// own industry-specific table instead of living on the generic core
// sales_transactions table, keeping the core table usable by any future
// vendor industry without insurance-only columns getting in the way.
//
// Auto-created by SalesTransactionController::store() only when the
// submitted product's vendor industry = INSURANCE — no manual step for
// the agent, and no separate "upload program" needed for this piece.
//
// The old coverage_start/coverage_end columns on sales_transactions are
// NOT dropped here — left in place (deprecated) until every screen that
// reads them is confirmed switched over to this table, matching the
// same non-destructive approach used for document_reference_number.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_renewal_schedules', function (Blueprint $table) {
            $table->uuid('renewal_id')->primary();
            $table->uuid('policy_id');

            $table->date('coverage_start');
            $table->date('coverage_end');
            $table->date('renewal_date')->nullable();

            $table->enum('status', [
                'UPCOMING',   // coverage_end is in the future, not yet due
                'DUE',        // inside the reminder window, not yet renewed
                'OVERDUE',    // coverage_end has passed, not renewed
                'RENEWED',    // a new policy replaced this one
                'LAPSED',     // overdue and never renewed
            ])->default('UPCOMING');

            $table->timestamps();

            $table->index('policy_id');
            $table->index('coverage_end');
            $table->index('status');

            $table->foreign('policy_id')
                  ->references('policy_id')
                  ->on('sales_transactions')
                  ->cascadeOnDelete();
        });

        // Backfill: any existing sales_transactions row for an insurance
        // vendor that already has coverage dates gets a matching renewal
        // record created here, so nothing existing loses its data.
        $existing = DB::table('sales_transactions as st')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->where('v.industry', 'INSURANCE')
            ->whereNotNull('st.coverage_start')
            ->whereNotNull('st.coverage_end')
            ->select('st.policy_id', 'st.coverage_start', 'st.coverage_end', 'st.renewal_date')
            ->get();

        foreach ($existing as $row) {
            DB::table('insurance_renewal_schedules')->insert([
                'renewal_id'     => (string) \Illuminate\Support\Str::uuid(),
                'policy_id'      => $row->policy_id,
                'coverage_start' => $row->coverage_start,
                'coverage_end'   => $row->coverage_end,
                'renewal_date'   => $row->renewal_date,
                'status'         => 'UPCOMING',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_renewal_schedules');
    }
};
