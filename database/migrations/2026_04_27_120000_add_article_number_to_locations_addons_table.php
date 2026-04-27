<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations_addons', function (Blueprint $table) {
            if (!Schema::hasColumn('locations_addons', 'article_number')) {
                // Lose Kopplung an events_articles.article_number (im aktuellen Team).
                // KEIN FK — Locations-Modul kennt Events nicht direkt; Resolving
                // erfolgt zur Apply-Zeit im Events-LocationPricingApplicator.
                $table->string('article_number', 30)->nullable()->after('unit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('locations_addons', function (Blueprint $table) {
            if (Schema::hasColumn('locations_addons', 'article_number')) {
                $table->dropColumn('article_number');
            }
        });
    }
};
