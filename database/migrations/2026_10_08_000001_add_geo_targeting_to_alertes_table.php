<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ciblage géographique des alertes canicule : un point (latitude/longitude)
     * et un rayon (mètres) forment un cercle. La cible principale devient ce
     * cercle ; l'association aux quartiers (table pivot `alerte_quartier`)
     * reste possible mais devient facultative.
     *
     * Nullable : les alertes existantes (ciblées par quartier uniquement)
     * restent valides, et le rayon par défaut est appliqué côté modèle.
     */
    public function up(): void
    {
        Schema::table('alertes', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('message');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('rayon_metres')->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('alertes', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'rayon_metres']);
        });
    }
};
