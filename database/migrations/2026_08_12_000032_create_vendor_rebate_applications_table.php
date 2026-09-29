<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 12 Aug 2026 — per Chris: "is the system auto generate when vendor
// submit the application, as for resubmission with same rebate program
// then the system should have the version number to keep track the
// revised version." Vendors now have a real, numbered application flow
// for proposing a rebate program (the previous "Offer Request" feature
// was deliberately scrapped 8 Aug 2026 for being confusing — this is the
// proper redo). Every version of the SAME program shares one
// application_number; version_number distinguishes the revisions;
// superseded_at marks every version except the current one. On approval,
// a rebate_program_number is issued and a live row is created/updated in
// the existing vendor_rebate_offers table so agents see it in Rebate
// Offer Search immediately.
return new class extends Migration
{
    public function up(): void
    {
        // SAFETY 12 Aug 2026 — a previous run of this migration created
        // the table successfully but then failed on the index name (MySQL
        // 64-char limit), so Laravel never marked it as migrated. Drop
        // that orphaned, empty table first so this run starts clean.
        Schema::dropIfExists('vendor_rebate_applications');

        Schema::create('vendor_rebate_applications', function (Blueprint $table) {
            $table->uuid('application_id')->primary();
            $table->string('application_number', 20); // shared across every version of the same program, e.g. RBA-2026-0001
            $table->uuid('vendor_id');
            $table->uuid('product_id')->nullable();
            $table->unsignedSmallInteger('version_number')->default(1);
            $table->text('rebate_details');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->text('rejection_reason')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rebate_program_number', 20)->nullable(); // set on first approval, carried forward through later revisions of the same program
            $table->timestamp('superseded_at')->nullable(); // set on every version once a newer one is submitted — null means "this is the current version"
            $table->timestamps();

            $table->foreign('vendor_id')->references('vendor_id')->on('vendors')->onDelete('cascade');
            $table->foreign('product_id')->references('product_id')->on('products')->onDelete('set null');
            $table->foreign('reviewed_by')->references('agent_id')->on('agents')->onDelete('set null');
            $table->index(['application_number', 'version_number'], 'vra_appnum_ver_idx');
            $table->index(['vendor_id', 'status'], 'vra_vendor_status_idx');
        });

        Schema::table('vendor_rebate_offers', function (Blueprint $table) {
            $table->string('rebate_program_number', 20)->nullable()->after('rebate_offer_id');
            $table->uuid('source_application_id')->nullable()->after('rebate_program_number');
            $table->foreign('source_application_id')->references('application_id')->on('vendor_rebate_applications')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_rebate_offers', function (Blueprint $table) {
            $table->dropForeign(['source_application_id']);
            $table->dropColumn(['rebate_program_number', 'source_application_id']);
        });
        Schema::dropIfExists('vendor_rebate_applications');
    }
};
