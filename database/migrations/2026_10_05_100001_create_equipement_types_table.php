<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `equipement_types` — catalogue des types d'équipements sensibles
 * géré par l'admin (réfrigérateur, respirateur, climatiseur…).
 *
 * Module 4 — Ghazi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipement_types', function (Blueprint $table) {
            $table->id();
            $table->string('nom');                          // Ex. "Respirateur médical"
            $table->string('slug')->unique();               // Ex. "respirateur"
            $table->string('icone')->default('devices');    // Icône Material Symbols
            $table->boolean('medical')->default(false);     // Impose contact_urgence
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->unsignedInteger('ordre')->default(0);   // Ordre d'affichage
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipement_types');
    }
};
