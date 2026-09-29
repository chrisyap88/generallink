<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_profiles', function (Blueprint $table) {
            $table->char('profile_id', 36)->primary();
            $table->char('agent_id', 36)->unique();
            $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postcode', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_profiles');
    }
};
