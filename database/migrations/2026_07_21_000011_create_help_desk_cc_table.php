<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// REDESIGNED 21 Jul 2026 — per Chris: Cc options are scoped to agents
// reachable by BOTH the sender's and the recipient's own upline/downline
// chain (see DataScopeService::helpDeskCcOptionsFor()) — this is what
// lets "the 4 parties under a group" loop each other in, without ever
// opening the door to an unrelated peer. CC'd agents get read-only
// visibility on the thread.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_desk_cc', function (Blueprint $table) {
            $table->uuid('cc_id')->primary();
            $table->uuid('thread_id');
            $table->uuid('agent_id');
            $table->timestamps();

            $table->foreign('thread_id')->references('thread_id')->on('help_desk_threads')->onDelete('cascade');
            $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
            $table->unique(['thread_id', 'agent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_cc');
    }
};
