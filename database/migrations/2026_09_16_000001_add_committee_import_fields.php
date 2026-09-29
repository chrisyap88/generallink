<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 16 Sep 2026 — per Chris: importing his real committee list
// (12 committee members + himself) from an Excel file. The Excel has
// NRIC, occupation, date of birth, and home address — none of which
// agents currently has room for. Per Chris's explicit decision this
// session: NRIC IS now stored, but only encrypted (same AES-256 +
// SHA-256 dedupe-hash pattern already used on the customers table —
// see CustomerResolutionService), never in plain text. office_phone
// is added alongside the existing single `phone` column because the
// Excel gives a separate mobile (HP No) and office/fix number and
// neither should be dropped.
//
// Also adds email + fax to cbe_hierarchy_nodes (an entity/branch's own
// contact profile) — Chris's Excel header carries a real email and fax
// for his branch's letterhead and there was previously nowhere to put
// either.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            if (! Schema::hasColumn('agents', 'nric_encrypted')) {
                $table->text('nric_encrypted')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('agents', 'nric_hash')) {
                $table->string('nric_hash', 64)->nullable()->unique()->after('nric_encrypted');
            }
            if (! Schema::hasColumn('agents', 'occupation')) {
                $table->string('occupation', 150)->nullable()->after('nric_hash');
            }
            if (! Schema::hasColumn('agents', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('occupation');
            }
            if (! Schema::hasColumn('agents', 'address')) {
                $table->text('address')->nullable()->after('date_of_birth');
            }
            if (! Schema::hasColumn('agents', 'office_phone')) {
                $table->string('office_phone', 30)->nullable()->after('address');
            }
        });

        Schema::table('cbe_hierarchy_nodes', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_hierarchy_nodes', 'email')) {
                $table->string('email', 200)->nullable()->after('contact_phone');
            }
            if (! Schema::hasColumn('cbe_hierarchy_nodes', 'fax')) {
                $table->string('fax', 30)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            foreach (['office_phone', 'address', 'date_of_birth', 'occupation', 'nric_hash', 'nric_encrypted'] as $col) {
                if (Schema::hasColumn('agents', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('cbe_hierarchy_nodes', function (Blueprint $table) {
            foreach (['fax', 'email'] as $col) {
                if (Schema::hasColumn('cbe_hierarchy_nodes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
