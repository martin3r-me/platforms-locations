<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Platform\Locations\Models\Location;
use Platform\Locations\Models\LocationSite;

/**
 * Migriert bestehende `gruppe`-Werte zu LocationSite-Eintraegen und droppt
 * danach die Spalte. Reihenfolge:
 *
 *   1. Fuer jeden Location-Record mit nicht-leerer `gruppe` und ohne
 *      `site_id` wird (firstOrCreate) eine LocationSite pro (team_id, name)
 *      angelegt; `site_id` der Location wird darauf gesetzt.
 *   2. Spalte `gruppe` wird gedroppt.
 *
 * Schritt 1 ist idempotent (firstOrCreate), Schritt 2 nutzt
 * `Schema::hasColumn`-Guard. Wenn die Spalte zwischenzeitlich fehlt,
 * ueberspringt up() den Schritt sauber.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('locations_locations')) {
            return;
        }

        // Nur migrieren, wenn die Spalte noch existiert.
        if (Schema::hasColumn('locations_locations', 'gruppe')) {
            // Direktes DB-Query, damit wir auch fuer Locations mit fillable-
            // Restriktionen migrieren koennen. Locations mit bereits gesetzter
            // site_id werden uebersprungen — Site hat dort schon Vorrang.
            DB::table('locations_locations')
                ->whereNotNull('gruppe')
                ->where('gruppe', '!=', '')
                ->whereNull('site_id')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        $name = trim((string) $row->gruppe);
                        if ($name === '') {
                            continue;
                        }

                        $site = LocationSite::firstOrCreate(
                            ['team_id' => $row->team_id, 'name' => $name],
                            ['user_id' => $row->user_id]
                        );

                        DB::table('locations_locations')
                            ->where('id', $row->id)
                            ->update(['site_id' => $site->id, 'updated_at' => now()]);
                    }
                });

            Schema::table('locations_locations', function (Blueprint $table) {
                $table->dropColumn('gruppe');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('locations_locations')) {
            return;
        }

        Schema::table('locations_locations', function (Blueprint $table) {
            if (!Schema::hasColumn('locations_locations', 'gruppe')) {
                $table->string('gruppe')->nullable()->after('kuerzel');
            }
        });

        // Daten werden NICHT zurueckgeschrieben — Site-Zuordnungen bleiben,
        // gruppe-Spalte bleibt nach Rollback leer. Manuelle Reparatur falls
        // noetig.
    }
};
