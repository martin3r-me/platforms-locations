<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('locations_locations')) {
            Schema::create('locations_locations', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();

                $table->string('name');
                $table->string('kuerzel', 20);
                $table->string('gruppe')->nullable();
                $table->unsignedSmallInteger('pax_min')->nullable();
                $table->unsignedSmallInteger('pax_max')->nullable();
                $table->boolean('mehrfachbelegung')->default(false);
                $table->string('adresse')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->index('team_id');
                $table->index(['team_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('locations_locations');
    }
};
