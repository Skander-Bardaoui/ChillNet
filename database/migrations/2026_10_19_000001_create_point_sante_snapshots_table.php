<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instantanés quotidiens de la santé prédite des points de fraîcheur (module 3).
 *
 * Boucle de retour d'expérience : `ai:snapshot-fraicheur` fige chaque jour le
 * contexte (features) et la prédiction ; 14 jours plus tard,
 * `ai:label-fraicheur` inscrit le résultat RÉEL (au moins 2 avis négatifs
 * reçus ?), puis `ai:export-fraicheur` réinjecte ces exemples dans
 * l'entraînement Python.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_sante_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('point_fraicheur_id')->constrained('points_fraicheur')->cascadeOnDelete();
            $table->timestamp('captured_at')->index();
            $table->json('features')->nullable();
            $table->float('p14')->nullable();
            $table->unsignedTinyInteger('score')->default(0);
            $table->string('niveau', 20);
            $table->string('source', 10)->default('regles');
            $table->string('model_version', 30)->nullable();
            $table->boolean('outcome_14j')->nullable();
            $table->timestamp('labeled_at')->nullable()->index();
            $table->timestamps();

            $table->index(['point_fraicheur_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_sante_snapshots');
    }
};
