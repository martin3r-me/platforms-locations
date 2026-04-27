<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations_locations', function (Blueprint $table) {
            if (!Schema::hasColumn('locations_locations', 'beschreibung')) {
                // Langer Beschreibungstext (Marketing / Historie / Kundeninfo).
                // Klar abgegrenzt von 'besonderheit' (kurze, prägnante Hervorhebung).
                $table->text('beschreibung')->nullable()->after('besonderheit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('locations_locations', function (Blueprint $table) {
            if (Schema::hasColumn('locations_locations', 'beschreibung')) {
                $table->dropColumn('beschreibung');
            }
        });
    }
};
