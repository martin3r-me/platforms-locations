<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('locations_pricings')) {
            Schema::create('locations_pricings', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('location_id')->constrained('locations_locations')->cascadeOnDelete();

                // Volltext-Match gegen events_settings.day_types (z. B. "Veranstaltungstag", "Aufbautag")
                $table->string('day_type_label', 100);
                $table->decimal('price_net', 10, 2);
                $table->string('label')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->index('location_id');
                $table->index(['location_id', 'day_type_label']);
                $table->index(['location_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('locations_pricings');
    }
};
