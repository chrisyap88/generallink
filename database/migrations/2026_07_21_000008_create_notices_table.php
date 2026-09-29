<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 21 Jul 2026 — Notice Board: Admin broadcasts one-way announcements
// to every agent (important updates, promotions, holiday/festive
// greetings, admin contact info, company bank account number, customer
// hotline, etc). Per Chris: auto-expire — expires_at is optional; leave
// it blank for permanent info (contact/bank details), set a date for
// anything time-bound (a holiday notice) and it drops off the active
// board by itself once that date passes. Read/unread tracking lives in
// notice_reads below, so the sidebar can show a red "new" indicator.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table) {
            $table->uuid('notice_id')->primary();
            $table->string('title', 150);
            $table->text('body');
            $table->enum('category', ['IMPORTANT_UPDATE', 'PROMOTION', 'HOLIDAY_FESTIVE', 'CONTACT_INFO', 'GENERAL'])->default('GENERAL');
            $table->string('attachment_file_name')->nullable();
            $table->string('attachment_file_path')->nullable();
            $table->date('expires_at')->nullable();
            $table->uuid('posted_by_agent_id');
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->foreign('posted_by_agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
            $table->index(['is_deleted', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notices');
    }
};
