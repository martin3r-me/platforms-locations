<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('locations_addons')) {
            Schema::create('locations_addons', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('location_id')->constrained('locations_locations')->cascadeOnDelete();

                $table->string('label');
                $table->decimal('price_net', 10, 2);
                // Enum-by-convention: pro_tag | pro_va_tag | einmalig | pro_stueck
                $table->string('unit', 30)->default('pro_tag');
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->index('location_id');
                $table->index(['location_id', 'sort_order']);
                $table->index(['location_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('locations_addons');
    }
};
