<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations_blockings', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('location_id')->constrained('locations_locations')->cascadeOnDelete();

            // Tagesgenaue Sperre (inkl. beider Grenzen). Zeitfenster innerhalb
            // eines Tages sind bewusst nicht abgebildet — Buchungen im
            // Events-Modul sind ebenfalls tagesbasiert.
            $table->date('start_date');
            $table->date('end_date');

            $table->string('reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('team_id');
            $table->index(['location_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations_blockings');
    }
};
