<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 31 Jul 2026 — "Override Recipient Profile" (personal profile),
// per Chris's spec. Distinct from the existing commission_rank_overrides
// table (which is configured PER RANK and applies automatically to
// whoever currently holds that rank — see role_ranks/agents.rank_id).
// This new table is configured PER NAMED AGENT instead: a specific
// person (e.g. a Regional Director you designate — that role doesn't
// have to exist as a system Role; the recipient can be any existing
// agent) gets a personal override entitlement that draws from a given
// source Role (optionally narrowed to one specific Rank within that
// role), for a given company/product (or all of them), as a % or a
// fixed RM amount, between an effective and expiry date, switchable
// Active/Inactive. An agent can have MULTIPLE rows (multiple override
// sources) — per Chris's confirmed answer, all active/matching rows
// stack; priority is a display/tie-break order only, not exclusion.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_commission_overrides', function (Blueprint $table) {
            $table->uuid('override_id')->primary();

            // The personal profile owner — who actually receives the money.
            $table->uuid('recipient_agent_id');

            // Which role's pool this override draws from. Kept as the
            // real system Role enum (not a new "Role Group" layer — per
            // Chris, Regional Director/Branch Manager etc. are just
            // example names, no new grouping tier needed).
            $table->enum('source_role', ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER']);

            // Optional — narrow the draw to one specific Rank within
            // that role (e.g. only from "Team Leader Rank 2"), instead
            // of the role broadly. Null = any/no rank distinction.
            $table->uuid('source_rank_id')->nullable();

            // Applicable company/product — null = all. Per Chris:
            // "per company per product, so new affiliate partners can
            // be added without code changes" — this scopes the same way.
            $table->uuid('vendor_id')->nullable();
            $table->uuid('product_id')->nullable();

            $table->enum('override_type', ['PERCENTAGE', 'FIXED_AMOUNT']);
            $table->decimal('override_value', 15, 4); // % (0-100) or a flat RM amount, depending on override_type

            $table->date('effective_date');
            $table->date('expiry_date')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');

            // Tie-break/display order only — does NOT exclude other
            // matching rules. All active, in-scope overrides stack.
            $table->integer('priority')->default(0);

            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['recipient_agent_id', 'status']);
            $table->index(['source_role', 'vendor_id', 'product_id'], 'aco_source_vendor_product_idx');

            $table->foreign('recipient_agent_id')->references('agent_id')->on('agents')->cascadeOnDelete();
            $table->foreign('source_rank_id')->references('rank_id')->on('role_ranks')->nullOnDelete();
            $table->foreign('vendor_id')->references('vendor_id')->on('vendors')->nullOnDelete();
            $table->foreign('product_id')->references('product_id')->on('products')->nullOnDelete();
            $table->foreign('created_by')->references('agent_id')->on('agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_commission_overrides');
    }
};
