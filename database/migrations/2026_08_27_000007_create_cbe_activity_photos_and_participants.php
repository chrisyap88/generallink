<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 27 Aug 2026 — per Chris: Activity Reports tab needs MULTIPLE
// photos per activity (cbe_activities previously allowed only one
// attachment) and a structured participants list. Chris confirmed
// participants can be existing Members/Customers/Donors OR free-text
// external names (VIP, authorities, guests — e.g. opening-ceremony /
// ribbon-cutting attendees who are not in the member database at all) —
// so agent_id/customer_id/donor_id are all nullable; when all three are
// null, external_name (+ optional external_org) is used instead.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_activity_photos')) {
            Schema::create('cbe_activity_photos', function (Blueprint $table) {
                $table->uuid('photo_id')->primary();
                $table->uuid('activity_id');
                $table->string('photo_path', 500);
                $table->string('photo_original_name', 255)->nullable();
                $table->uuid('uploaded_by')->nullable();
                $table->timestamps();

                $table->foreign('activity_id')->references('activity_id')->on('cbe_activities')->onDelete('cascade');
                $table->foreign('uploaded_by')->references('agent_id')->on('agents')->onDelete('set null');
                $table->index('activity_id');
            });
        }

        if (! Schema::hasTable('cbe_activity_participants')) {
            Schema::create('cbe_activity_participants', function (Blueprint $table) {
                $table->uuid('participant_id')->primary();
                $table->uuid('activity_id');
                $table->uuid('agent_id')->nullable();
                $table->uuid('customer_id')->nullable();
                $table->uuid('donor_id')->nullable();
                $table->string('external_name', 200)->nullable(); // set when not an existing Member/Customer/Donor
                $table->string('external_org', 200)->nullable();
                $table->timestamps();

                $table->foreign('activity_id')->references('activity_id')->on('cbe_activities')->onDelete('cascade');
                $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->foreign('customer_id')->references('customer_id')->on('customers')->onDelete('cascade');
                $table->foreign('donor_id')->references('donor_id')->on('cbe_donors')->onDelete('cascade');
                $table->index('activity_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_activity_participants');
        Schema::dropIfExists('cbe_activity_photos');
    }
};
