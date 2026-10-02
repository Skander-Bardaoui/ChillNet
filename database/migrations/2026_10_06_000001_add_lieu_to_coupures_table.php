<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rue / lieu précis du module 2 : un grand quartier peut avoir 2 coupures
     * simultanées dans 2 rues différentes — l'anti-doublon compare désormais
     * la rue en plus de la zone et du créneau (voir Coupure::chevauche).
     */
    public function up(): void
    {
        Schema::table('coupures', function (Blueprint $table) {
            $table->string('lieu', 255)->nullable()->after('quartier_id');
        });
    }

    public function down(): void
    {
        Schema::table('coupures', function (Blueprint $table) {
            $table->dropColumn('lieu');
        });
    }
};
