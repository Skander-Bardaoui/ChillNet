<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Coupure ciblée librement : un point (latitude/longitude) peut être posé
     * n'importe où, sans quartier existant. Le quartier devient facultatif
     * (déduit du point côté métier quand c'est possible) et les coupures
     * historiques restent valides.
     */
    public function up(): void
    {
        Schema::table('coupures', function (Blueprint $table) {
            $table->foreignId('quartier_id')->nullable()->change();
            $table->decimal('latitude', 10, 7)->nullable()->after('quartier_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('coupures', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
            $table->foreignId('quartier_id')->nullable(false)->change();
        });
    }
};
