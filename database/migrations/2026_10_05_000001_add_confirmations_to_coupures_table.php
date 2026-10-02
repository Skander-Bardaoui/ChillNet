<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Compteur « je confirme » du module 2 : chaque habitant peut confirmer
     * une coupure EN COURS qu'il constate aussi (crédibilise le signalement).
     */
    public function up(): void
    {
        Schema::table('coupures', function (Blueprint $table) {
            $table->unsignedInteger('confirmations')->default(0)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('coupures', function (Blueprint $table) {
            $table->dropColumn('confirmations');
        });
    }
};
