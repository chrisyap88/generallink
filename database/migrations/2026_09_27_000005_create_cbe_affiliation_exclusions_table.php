<?php

// NEW 27 Sep 2026 — per Chris: Affiliate Group tab auto-identifies the HQ
// entities in a branch's district and auto-saves them. An entity the user
// UNTICKS is remembered here, so the next automatic identification never
// re-adds it. Ticking it again removes the row.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cbe_affiliation_exclusions')) {
            return;
        }
        Schema::create('cbe_affiliation_exclusions', function (Blueprint $t) {
            $t->id();
            $t->uuid('node_id');          // the entity (e.g. a temple)
            $t->uuid('anchor_node_id');   // the branch it must NOT be auto-affiliated to
            $t->timestamps();
            $t->unique(['node_id', 'anchor_node_id'], 'cae_node_anchor_uq');
            $t->index('anchor_node_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_affiliation_exclusions');
    }
};
