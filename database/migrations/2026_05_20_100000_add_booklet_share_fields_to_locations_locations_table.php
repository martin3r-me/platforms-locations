<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('locations_locations')) {
            return;
        }

        Schema::table('locations_locations', function (Blueprint $table) {
            if (!Schema::hasColumn('locations_locations', 'booklet_share_token')) {
                $table->string('booklet_share_token', 64)->nullable()->after('beschreibung');
                $table->unique('booklet_share_token');
            }
            if (!Schema::hasColumn('locations_locations', 'booklet_share_expires_at')) {
                $table->timestamp('booklet_share_expires_at')->nullable()->after('booklet_share_token');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('locations_locations')) {
            return;
        }

        Schema::table('locations_locations', function (Blueprint $table) {
            if (Schema::hasColumn('locations_locations', 'booklet_share_token')) {
                $table->dropUnique(['booklet_share_token']);
                $table->dropColumn('booklet_share_token');
            }
            if (Schema::hasColumn('locations_locations', 'booklet_share_expires_at')) {
                $table->dropColumn('booklet_share_expires_at');
            }
        });
    }
};
