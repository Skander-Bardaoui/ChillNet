<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rue / lieu précis de l'alerte, rempli automatiquement par géocodage
     * inverse quand le gestionnaire pose un point sur la carte.
     */
    public function up(): void
    {
        Schema::table('alertes', function (Blueprint $table) {
            $table->string('lieu', 255)->nullable()->after('rayon_metres');
        });
    }

    public function down(): void
    {
        Schema::table('alertes', function (Blueprint $table) {
            $table->dropColumn('lieu');
        });
    }
};
