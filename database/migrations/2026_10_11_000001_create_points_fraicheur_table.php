<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Module 3 (Dhia) : points de fraîcheur (parc, salle climatisée, fontaine).
     * Un habitant peut en proposer un (statut en_attente) : l'admin le valide
     * ou le refuse. Le quartier est déduit des coordonnées (le plus proche).
     */
    public function up(): void
    {
        Schema::create('points_fraicheur', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150);
            $table->string('type', 30);
            $table->text('description')->nullable();
            $table->string('adresse', 255);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('capacite')->nullable();
            $table->boolean('ouvert_24h')->default(false);
            $table->time('heure_ouverture')->nullable();
            $table->time('heure_fermeture')->nullable();
            $table->string('photo')->nullable();

            // Préférences exploitées par le moteur de recommandation.
            $table->boolean('accessible_pmr')->default(false);
            $table->boolean('climatise')->default(false);
            $table->boolean('ombrage')->default(false);
            $table->boolean('eau_potable')->default(false);

            // Modération.
            $table->string('statut', 20)->default('en_attente')->index();
            $table->string('motif_refus', 500)->nullable();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('valide_le')->nullable();

            $table->foreignId('quartier_id')->nullable()->constrained('quartiers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_fraicheur');
    }
};
