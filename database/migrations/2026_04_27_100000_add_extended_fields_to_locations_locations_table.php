<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations_locations', function (Blueprint $table) {
            if (!Schema::hasColumn('locations_locations', 'groesse_qm')) {
                $table->decimal('groesse_qm', 8, 2)->nullable()->after('mehrfachbelegung');
            }
            if (!Schema::hasColumn('locations_locations', 'hallennummer')) {
                $table->string('hallennummer', 30)->nullable()->after('groesse_qm');
            }
            if (!Schema::hasColumn('locations_locations', 'barrierefrei')) {
                $table->boolean('barrierefrei')->default(false)->after('hallennummer');
            }
            if (!Schema::hasColumn('locations_locations', 'besonderheit')) {
                $table->text('besonderheit')->nullable()->after('barrierefrei');
            }
            if (!Schema::hasColumn('locations_locations', 'anlaesse')) {
                $table->json('anlaesse')->nullable()->after('besonderheit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('locations_locations', function (Blueprint $table) {
            foreach (['anlaesse', 'besonderheit', 'barrierefrei', 'hallennummer', 'groesse_qm'] as $col) {
                if (Schema::hasColumn('locations_locations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
