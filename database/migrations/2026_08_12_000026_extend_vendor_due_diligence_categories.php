<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 12 Aug 2026 — per Chris: "what type of dilligent check status
// (legal case, scammer, social media complaints news, illegal offences,
// court case, director and shareholder bankruptcy check etc)? risk
// score and detail of your report and comments?" The original 3-check
// report (identity/sanctions/negative-news) is expanded into the
// specific categories Chris asked for. Kept HONEST, same rule as the
// original build: Legal/Court Case, Scammer/Fraud, Social Media & News
// Complaints, and Illegal Offences are all AI-web-search best-effort
// (Claude searching public sources — never an authoritative registry).
// Director & Shareholder Bankruptcy is stored but ALWAYS reported
// UNAVAILABLE — GeneralLink has no connection to Malaysia's Jabatan
// Insolvensi Malaysia (JIM) e-Insolvency registry or any paid
// bankruptcy/court-record data source, so faking a "clear" result here
// would be actively dangerous. Also adds a transparent, explainable
// risk_score + risk_band computed from every category above (see
// VendorDueDiligenceService::computeRisk()) — never a black-box number.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_due_diligence_assessments', function (Blueprint $table) {
            // AI web search — court judgments, lawsuits, litigation.
            $table->string('legal_case_status', 20)->nullable()->after('negative_news_note'); // CLEAR, CONCERNS_FOUND, UNAVAILABLE
            $table->text('legal_case_note')->nullable()->after('legal_case_status');

            // AI web search — scam reports, fraud complaints, consumer warnings.
            $table->string('scammer_status', 20)->nullable()->after('legal_case_note'); // CLEAR, CONCERNS_FOUND, UNAVAILABLE
            $table->text('scammer_note')->nullable()->after('scammer_status');

            // AI web search — negative social media / news consumer complaints.
            $table->string('social_media_status', 20)->nullable()->after('scammer_note'); // CLEAR, CONCERNS_FOUND, UNAVAILABLE
            $table->text('social_media_note')->nullable()->after('social_media_status');

            // AI web search — regulatory action, illegal operation, licence revocation.
            $table->string('illegal_offences_status', 20)->nullable()->after('social_media_note'); // CLEAR, CONCERNS_FOUND, UNAVAILABLE
            $table->text('illegal_offences_note')->nullable()->after('illegal_offences_status');

            // Deliberately always UNAVAILABLE — no data source connected.
            $table->string('bankruptcy_status', 20)->nullable()->after('illegal_offences_note'); // UNAVAILABLE only, honestly
            $table->text('bankruptcy_note')->nullable()->after('bankruptcy_status');

            // Transparent composite risk score — see computeRisk() for the
            // exact, visible point breakdown behind this number.
            $table->unsignedTinyInteger('risk_score')->nullable()->after('bankruptcy_note'); // 0-100, higher = higher risk
            $table->string('risk_band', 20)->nullable()->after('risk_score'); // LOW, MEDIUM, HIGH, CRITICAL
        });
    }

    public function down(): void
    {
        Schema::table('vendor_due_diligence_assessments', function (Blueprint $table) {
            $table->dropColumn([
                'legal_case_status', 'legal_case_note',
                'scammer_status', 'scammer_note',
                'social_media_status', 'social_media_note',
                'illegal_offences_status', 'illegal_offences_note',
                'bankruptcy_status', 'bankruptcy_note',
                'risk_score', 'risk_band',
            ]);
        });
    }
};
