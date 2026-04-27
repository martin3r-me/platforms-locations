<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('locations_seating_options')) {
            Schema::create('locations_seating_options', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('location_id')->constrained('locations_locations')->cascadeOnDelete();

                $table->string('label');
                $table->unsignedSmallInteger('pax_max_ca');
                $table->unsignedSmallInteger('sort_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->index('location_id');
                $table->index(['location_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('locations_seating_options');
    }
};
