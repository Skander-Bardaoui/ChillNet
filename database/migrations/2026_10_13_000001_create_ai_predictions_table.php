<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Boucle de retour d'expérience IA canicule.
     *
     * Chaque « pré-remplissage » IA enregistre une ligne : le CONTEXTE BRUT
     * transmis au microservice Python, sa sortie (niveaux modèle / règles /
     * final + probabilités), et — quand le gestionnaire enregistre/valide une
     * alerte — le niveau réellement retenu (niveau_final) relié à l'alerte.
     *
     * `ai:export-corrections` réinjecte ces corrections dans l'entraînement
     * suivant (voir ai-service/scripts/train.py).
     */
    public function up(): void
    {
        Schema::create('ai_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alerte_id')->nullable()->constrained('alertes')->nullOnDelete();
            $table->json('quartier_ids')->nullable();
            $table->json('context')->nullable();
            $table->string('niveau_modele', 10)->nullable();
            $table->json('probabilites')->nullable();
            $table->string('niveau_regles', 10)->nullable();
            $table->string('niveau_final', 10)->nullable();
            $table->string('model_version', 30)->nullable();
            $table->string('source', 20)->nullable();
            $table->timestamps();

            $table->index('alerte_id');
            $table->index('niveau_final');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_predictions');
    }
};
