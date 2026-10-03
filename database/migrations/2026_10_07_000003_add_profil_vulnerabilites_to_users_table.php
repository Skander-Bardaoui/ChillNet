<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profil de vulnérabilité du foyer (multi-choix) — sert à personnaliser
     * le message d'alerte canicule généré par l'IA :
     *   ['personne_agee', 'enfant', 'equipement_medical']
     * Un tableau vide (ou null) = foyer standard.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('profil_vulnerabilites')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profil_vulnerabilites');
        });
    }
};
