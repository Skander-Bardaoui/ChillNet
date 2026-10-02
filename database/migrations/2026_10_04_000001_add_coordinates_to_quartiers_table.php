<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Coordonnées pour la carte Leaflet du module 2 (coupures).
     * Nullable pour ne pas casser les quartiers déjà créés par les habitants.
     */
    public function up(): void
    {
        Schema::table('quartiers', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('code_postal');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('quartiers', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
