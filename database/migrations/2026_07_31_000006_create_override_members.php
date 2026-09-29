<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 31 Jul 2026 — Vendor Override Members. Per Chris: this is a
// COMPLETELY DIFFERENT concept from Override Recipient Profile
// (agent_commission_overrides, built earlier the same day) — that one
// pays an extra slice to a named AGENT inside our own hierarchy. This
// one represents people on the VENDOR'S side — for an insurance
// company like AIA, their Country Director, Regional Directors,
// Marketing Director, or in other industries a distribution/
// manufacturer rep or franchise owner's counterpart. They are NOT
// agents: no role, no rank, no parent_id, no login, no wallet. On
// purpose kept entirely separate from the `agents` table so none of
// that machinery (rank promotion, hierarchy drilldown, DataScopeService
// visibility, reward points) ever has to know these rows exist.
//
// Each Override Member belongs to exactly one vendor (the company they
// represent) AND one Special Privilege Group (group_labels — prihatin2u
// / rela2u / PVATM) — their override is calculated only off that
// group's book of business with that vendor, confirmed explicitly by
// Chris. A vendor can have many override members at once (AIA's
// Country Director + North/South/East Region Directors, each with
// their own separate deal).
//
// Settlement (how the calculated override money actually gets
// reconciled) is set PER MEMBER, not per vendor or per group — per
// Chris: "the deduction is not automatic... some agree deduct directly
// some no." One AIA director might have a direct-deduction arrangement
// with prihatin2u while another only gets a claim-back report.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('override_members', function (Blueprint $table) {
            $table->uuid('override_member_id')->primary();

            // Human-readable reference code, same convention as
            // agent_code / vendor_code / product_code (e.g. OVM-00001).
            $table->string('override_member_code', 20)->unique()->nullable();

            $table->string('full_name');
            $table->string('position_title'); // free text: "Country Director", "North Region Director", etc.

            $table->uuid('vendor_id');       // which company they represent
            $table->uuid('group_label_id');  // which Special Privilege Group's book they override on

            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Set per member — never automatic/inherited from the
            // vendor or group.
            $table->enum('settlement_method', ['DEDUCT_FROM_CLAIM', 'CLAIM_BACK_REPORT']);

            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'group_label_id', 'is_active']);

            $table->foreign('vendor_id')->references('vendor_id')->on('vendors')->cascadeOnDelete();
            $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->cascadeOnDelete();
            $table->foreign('created_by')->references('agent_id')->on('agents')->nullOnDelete();
        });

        // Eligibility/payout criteria — scoped one level BELOW the
        // member, per PRODUCT. Per Chris: "product motor is different
        // with fire and PA" — the same Country Director can have a
        // completely different rate per product, not one blanket %
        // across everything that vendor sells. product_id nullable =
        // applies to any of that vendor's products not otherwise given
        // their own specific row (a fallback, same convention used
        // elsewhere in this app for "unless overridden more specifically").
        Schema::create('override_member_eligibility_rules', function (Blueprint $table) {
            $table->uuid('rule_id')->primary();
            $table->uuid('override_member_id');
            $table->uuid('product_id')->nullable();

            $table->enum('criteria_type', ['PERCENTAGE', 'FIXED_AMOUNT', 'SALES_TARGET', 'CUSTOM_KPI']);

            // PERCENTAGE: % of sales/earning income.
            // FIXED_AMOUNT: flat RM per period.
            // SALES_TARGET: minimum RM the group's business with this
            //   vendor+product must reach before this rule pays anything,
            //   paired with sales_metric/period below.
            // CUSTOM_KPI: no formula — custom_kpi_description explains
            //   what to check, amount is entered manually each period
            //   (Chris: "any KPI" the vendor sets doesn't always reduce
            //   to a clean %/amount/target).
            $table->decimal('threshold_value', 15, 4)->nullable();
            $table->enum('sales_metric', ['PREMIUM', 'EARNING_INCOME'])->nullable();
            $table->integer('period_months')->nullable();
            $table->text('custom_kpi_description')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['override_member_id', 'product_id', 'is_active'], 'omer_member_product_active_idx');

            $table->foreign('override_member_id')->references('override_member_id')->on('override_members')->cascadeOnDelete();
            $table->foreign('product_id')->references('product_id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('override_member_eligibility_rules');
        Schema::dropIfExists('override_members');
    }
};
