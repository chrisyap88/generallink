<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 21 Jul 2026 — one row per agent per notice they've seen. Powers
// the red "unread" badge on the sidebar Notice Board link and the
// per-notice "NEW" flag — an agent stops seeing either the moment they
// open the Notice Board page and this notice is on it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notice_reads', function (Blueprint $table) {
            $table->uuid('read_id')->primary();
            $table->uuid('notice_id');
            $table->uuid('agent_id');
            $table->timestamp('read_at')->useCurrent();

            $table->foreign('notice_id')->references('notice_id')->on('notices')->onDelete('cascade');
            $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
            $table->unique(['notice_id', 'agent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_reads');
    }
};
