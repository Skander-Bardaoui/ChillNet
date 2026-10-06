<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `equipements` — équipements sensibles déclarés par un habitant.
 * Liés à un type catalogué et scopés à l'utilisateur.
 *
 * Module 4 — Ghazi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('equipement_type_id')->constrained('equipement_types')->restrictOnDelete();

            $table->string('nom');                          // Nom libre : "Mon respirateur nocturne"
            $table->string('criticite');                    // Enum CriticitéEquipement : normale | elevee | vitale
            $table->text('notes')->nullable();              // Emplacement, remarques…

            // Champ conditionnel : obligatoire si type médical ou criticité vitale.
            $table->string('contact_urgence')->nullable();  // "Fille — 06 12 34 56 78"

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipements');
    }
};
