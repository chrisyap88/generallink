<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #390) — per Chris: the master Chart of Accounts
// stays shared per CBE community (group_label_id, unchanged), but an
// individual temple/branch/state should be able to add its OWN "local"
// accounts on top of that shared master — accounts only it can see and
// use, invisible to every other node in the same federation. Example:
// one temple sells joss sticks and wants an "Joss Stick Sales" income
// account; the rest of the federation doesn't need it cluttering their
// own Chart of Accounts screen.
//
// cbe_node_id nullable: NULL = shared master account (visible to the
// whole community, same as today); a specific node_id = a local add-on
// account belonging only to that node. Only the community's root/HQ
// node (parent_node_id IS NULL on cbe_hierarchy_nodes) is allowed to
// create NULL/shared accounts — enforced in the controller, not here.
//
// The old cbe_coa_group_code_unique unique index (group_label_id,
// account_code) is dropped: MySQL unique indexes treat every NULL as
// distinct from every other NULL, so it was never going to correctly
// stop two different shared accounts from re-using the same code
// anyway, and it WOULD have wrongly blocked two different temples from
// each having their own local account with the same code once
// cbe_node_id exists. Account-code uniqueness (shared-vs-shared, and
// local-vs-shared within a temple's own visible list) is now enforced
// in the application layer in CbeAccountingController, which can
// correctly express "unique among what this node can actually see."
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cbe_chart_of_accounts', 'cbe_node_id')) {
            Schema::table('cbe_chart_of_accounts', function (Blueprint $table) {
                $table->uuid('cbe_node_id')->nullable()->after('group_label_id');
            });
            Schema::table('cbe_chart_of_accounts', function (Blueprint $table) {
                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->index(['group_label_id', 'cbe_node_id'], 'cbe_coa_group_node_idx');
            });
        }

        if (Schema::hasTable('cbe_chart_of_accounts')) {
            try {
                Schema::table('cbe_chart_of_accounts', function (Blueprint $table) {
                    $table->dropUnique('cbe_coa_group_code_unique');
                });
            } catch (\Throwable $e) {
                // Already dropped (e.g. migration re-run) — safe to ignore.
            }
        }
    }

    public function down(): void
    {
        Schema::table('cbe_chart_of_accounts', function (Blueprint $table) {
            $table->unique(['group_label_id', 'account_code'], 'cbe_coa_group_code_unique');
        });
        Schema::table('cbe_chart_of_accounts', function (Blueprint $table) {
            $table->dropForeign(['cbe_node_id']);
            $table->dropIndex('cbe_coa_group_node_idx');
            $table->dropColumn('cbe_node_id');
        });
    }
};
