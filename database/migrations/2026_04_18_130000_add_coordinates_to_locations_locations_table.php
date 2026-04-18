<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations_locations', function (Blueprint $table) {
            if (!Schema::hasColumn('locations_locations', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('adresse');
            }
            if (!Schema::hasColumn('locations_locations', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('locations_locations', function (Blueprint $table) {
            if (Schema::hasColumn('locations_locations', 'longitude')) {
                $table->dropColumn('longitude');
            }
            if (Schema::hasColumn('locations_locations', 'latitude')) {
                $table->dropColumn('latitude');
            }
        });
    }
};
