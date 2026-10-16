<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signalement_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signalement_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score_urgence');
            $table->string('urgence_suggeree', 20);
            $table->json('indicateurs_urgence')->nullable();
            $table->json('termes_urgents')->nullable();
            $table->json('signalements_lies')->nullable();
            $table->char('empreinte_description', 64);
            $table->string('version_modele', 30);
            $table->timestamp('analysee_le');
            $table->timestamps();

            $table->index('urgence_suggeree');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signalement_analyses');
    }
};
