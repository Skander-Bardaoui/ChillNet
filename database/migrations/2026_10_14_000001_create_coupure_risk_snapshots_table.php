<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Boucle de retour d'expérience IA du module 2 (coupures).
     *
     * Chaque « snapshot » capture le CONTEXTE BRUT d'une zone (features du
     * modèle) et la prédiction courante (p24/p48, score, version). Quand la
     * fenêtre d'observation s'est écoulée, `ai:label-snapshots` y inscrit le
     * RÉSULTAT RÉEL (`outcome_24h` / `outcome_48h` : une coupure a-t-elle
     * démarré dans la fenêtre ?) — c'est le matériel d'entraînement réel.
     */
    public function up(): void
    {
        Schema::create('coupure_risk_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quartier_id')->nullable()->constrained('quartiers')->nullOnDelete();
            $table->timestamp('captured_at')->index();
            $table->json('features')->nullable();
            $table->float('p24')->nullable();
            $table->float('p48')->nullable();
            $table->integer('score')->nullable();
            $table->string('model_version', 30)->nullable();
            $table->boolean('outcome_24h')->nullable();
            $table->boolean('outcome_48h')->nullable();
            $table->timestamp('labeled_at')->nullable();
            $table->timestamps();

            $table->index(['quartier_id', 'captured_at']);
            $table->index('labeled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupure_risk_snapshots');
    }
};
