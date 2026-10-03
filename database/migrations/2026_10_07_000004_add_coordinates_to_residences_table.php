<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Coordonnées d'une résidence, pour le périmètre 800 m des refuges
     * (carte Leaflet de l'espace habitant). Nullable pour ne pas casser
     * les résidences existantes ou déclarées sans position.
     */
    public function up(): void
    {
        Schema::table('residences', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('adresse');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('residences', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
