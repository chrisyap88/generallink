<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// NEW 28 Aug 2026 — per Chris: "you should have a meeting type, example
// committee meeting, annual anniversary event meeting minutes, AGM
// Meeting minutes, Membership meeting etc." Same admin-configurable
// pattern as cbe_transaction_categories (per Chris's standing rule
// "fees/settings MUST NOT hardcode") — group_label_id nullable = a global
// default type available to every CBE community; set it to scope a type
// to one specific community instead. Seeded with 6 sensible global
// defaults below so the dropdown isn't empty on day one, but Admin can
// add/deactivate more via the Manage Meeting Types screen exactly like
// Finance Categories.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_meeting_types')) {
            Schema::create('cbe_meeting_types', function (Blueprint $table) {
                $table->uuid('meeting_type_id')->primary();
                $table->uuid('group_label_id')->nullable();
                $table->string('type_name', 150);
                $table->string('type_name_zh', 150)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();

                $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->onDelete('cascade');
                $table->index(['group_label_id', 'is_active']);
            });

            $defaults = [
                ['en' => 'Committee Meeting', 'zh' => '理事会会议'],
                ['en' => 'Annual General Meeting (AGM)', 'zh' => '年度大会'],
                ['en' => 'Annual Anniversary / Event Meeting', 'zh' => '周年纪念/活动会议'],
                ['en' => 'Membership Meeting', 'zh' => '会员大会'],
                ['en' => 'Special / Extraordinary Meeting', 'zh' => '特别会议'],
                ['en' => 'Board Meeting', 'zh' => '董事会会议'],
            ];
            foreach ($defaults as $i => $d) {
                DB::table('cbe_meeting_types')->insert([
                    'meeting_type_id' => (string) Str::uuid(),
                    'group_label_id'  => null,
                    'type_name'       => $d['en'],
                    'type_name_zh'    => $d['zh'],
                    'is_active'       => true,
                    'display_order'   => $i,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_meeting_types');
    }
};
