<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 15 Sep 2026 — per Chris: "add contact etc" — a variable-length
// list of phone numbers for the group's own head-office contact, same
// "+ Add Contact" pattern already used for cbe_hierarchy_node_phones
// on the individual entity Profile tab (Entity Maintenance).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('group_label_phones')) {
            Schema::create('group_label_phones', function (Blueprint $table) {
                $table->uuid('phone_id')->primary();
                $table->uuid('group_label_id');
                $table->string('phone_number', 30);
                $table->string('contact_note', 100)->nullable();
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();

                $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('group_label_phones');
    }
};
