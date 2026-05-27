<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pro-Location konfigurierbare Booklet-Optionen.
 *
 * Map aus `show_*`-Flags (siehe Location::BOOKLET_OPTION_KEYS). Wenn ein
 * Key fehlt oder die ganze Spalte NULL ist, gilt der jeweilige Default
 * (alles an) — dadurch muss bei Bestands-Locations nichts migriert
 * werden, das Booklet sieht identisch aus wie vorher.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('locations_locations')) {
            return;
        }

        Schema::table('locations_locations', function (Blueprint $table) {
            if (!Schema::hasColumn('locations_locations', 'booklet_options')) {
                $table->json('booklet_options')->nullable()->after('booklet_share_expires_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('locations_locations')) {
            return;
        }

        Schema::table('locations_locations', function (Blueprint $table) {
            if (Schema::hasColumn('locations_locations', 'booklet_options')) {
                $table->dropColumn('booklet_options');
            }
        });
    }
};
