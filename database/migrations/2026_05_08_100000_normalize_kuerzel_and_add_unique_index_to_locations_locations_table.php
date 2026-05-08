<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalisiert kuerzel (TRIM + UPPER) auf bestehenden Locations und legt
 * danach einen UNIQUE INDEX (team_id, kuerzel) an.
 *
 * Bricht ab, wenn nach der Normalisierung Duplikate verbleiben — der
 * Operator muss diese vorher manuell auflösen, weil eine stille
 * Bevorzugung dieselbe Anti-Pattern waere wie ein Silent-Pick zur
 * Resolve-Zeit.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Bestandsdaten normalisieren (TRIM + UPPER), nur aktive Zeilen.
        // Soft-deleted bleiben unangetastet — sie blockieren den UNIQUE INDEX
        // sonst, falls ein altes Kuerzel im Papierkorb liegt und neu vergeben wurde.
        DB::table('locations_locations')
            ->whereNotNull('kuerzel')
            ->whereNull('deleted_at')
            ->update(['kuerzel' => DB::raw('UPPER(TRIM(kuerzel))')]);

        $duplicates = DB::table('locations_locations')
            ->select('team_id', 'kuerzel', DB::raw('COUNT(*) as cnt'))
            ->whereNull('deleted_at')
            ->groupBy('team_id', 'kuerzel')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $lines = $duplicates->map(function ($row) {
                $ids = DB::table('locations_locations')
                    ->where('team_id', $row->team_id)
                    ->where('kuerzel', $row->kuerzel)
                    ->whereNull('deleted_at')
                    ->pluck('id')
                    ->implode(',');
                return "  team_id={$row->team_id} kuerzel='{$row->kuerzel}' (count={$row->cnt}, ids=[{$ids}])";
            })->implode("\n");

            throw new \RuntimeException(
                "Migration abgebrochen: doppelte (team_id, kuerzel) bei aktiven Locations gefunden. " .
                "Bitte manuell aufloesen (umbenennen oder loeschen) und Migration erneut starten.\n{$lines}"
            );
        }

        // Konflikt aktive Zeile vs. soft-deleted: MySQL UNIQUE INDEX matcht ALLE Zeilen,
        // also auch soft-deleted. Wir surface das hier mit klarer Anleitung,
        // statt MySQL einen kryptischen Fehler werfen zu lassen.
        $softConflicts = DB::table('locations_locations as a')
            ->join('locations_locations as b', function ($join) {
                $join->on('a.team_id', '=', 'b.team_id')
                     ->on('a.kuerzel', '=', 'b.kuerzel')
                     ->whereColumn('a.id', '<>', 'b.id');
            })
            ->whereNull('a.deleted_at')
            ->whereNotNull('b.deleted_at')
            ->select('a.team_id', 'a.kuerzel', 'a.id as active_id', 'b.id as deleted_id')
            ->get();

        if ($softConflicts->isNotEmpty()) {
            $lines = $softConflicts->map(fn ($r) =>
                "  team_id={$r->team_id} kuerzel='{$r->kuerzel}' (active id={$r->active_id}, soft-deleted id={$r->deleted_id})"
            )->implode("\n");

            throw new \RuntimeException(
                "Migration abgebrochen: aktive Location kollidiert mit soft-deleted Eintrag. " .
                "Force-delete den alten Eintrag oder benenne das Kuerzel um, dann erneut starten.\n{$lines}"
            );
        }

        Schema::table('locations_locations', function (Blueprint $table) {
            $table->unique(['team_id', 'kuerzel'], 'locations_locations_team_kuerzel_unique');
        });
    }

    public function down(): void
    {
        Schema::table('locations_locations', function (Blueprint $table) {
            $table->dropUnique('locations_locations_team_kuerzel_unique');
        });
    }
};
