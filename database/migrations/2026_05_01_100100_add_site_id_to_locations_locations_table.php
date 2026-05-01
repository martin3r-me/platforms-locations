<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations_locations', function (Blueprint $table) {
            $table->foreignId('site_id')
                ->nullable()
                ->after('team_id')
                ->constrained('locations_sites')
                ->nullOnDelete();

            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::table('locations_locations', function (Blueprint $table) {
            $table->dropForeign(['site_id']);
            $table->dropIndex(['site_id']);
            $table->dropColumn('site_id');
        });
    }
};
