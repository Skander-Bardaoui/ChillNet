<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * « Lieux » personnels d'un habitant (Domicile, Travail, Autre) : un
     * endroit géolocalisé que le foyer gère lui-même. Remplace le quartier
     * dans TOUTE l'interface habitant.
     *
     * `quartier_id` reste renseigné en interne (auto : le quartier le plus
     * proche du point) pour que coupures / périmètre gestionnaire / pages
     * publiques continuent de fonctionner — sans que l'habitant ne voie
     * jamais le mot « quartier ».
     */
    public function up(): void
    {
        Schema::create('lieux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nom', 120);
            $table->string('type', 20)->default('domicile');
            $table->string('adresse', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('quartier_id')->nullable()->constrained('quartiers')->nullOnDelete();
            $table->boolean('est_principal')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'est_principal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lieux');
    }
};
