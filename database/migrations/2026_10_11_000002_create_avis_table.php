<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Avis d'un habitant sur un point de fraîcheur (1 point --- N avis).
     * Un seul avis par habitant et par point (il peut le modifier).
     * `sentiment` / `score_sentiment` sont calculés par l'IA à l'enregistrement.
     */
    public function up(): void
    {
        Schema::create('avis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('point_fraicheur_id')->constrained('points_fraicheur')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('note');
            $table->text('commentaire')->nullable();
            $table->string('affluence', 20)->nullable();
            $table->string('sentiment', 20)->nullable()->index();
            $table->decimal('score_sentiment', 4, 3)->nullable();
            $table->timestamps();

            $table->unique(['point_fraicheur_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avis');
    }
};
