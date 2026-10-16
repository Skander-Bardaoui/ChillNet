<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `conseils` — base de conseils gérée par l'admin.
 * Un conseil appartient à une catégorie et peut être lié à plusieurs
 * types d'équipements (table pivot `conseil_equipement_type`).
 *
 * Module 4 — Ghazi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conseils', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->string('categorie');                    // Enum CategorieConseil (hydratation, energie, equipements, medical, general)
            $table->text('contenu');
            $table->string('icone')->default('tips_and_updates'); // Material Symbol
            $table->boolean('actif')->default(true);
            $table->unsignedInteger('ordre')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // Auteur admin
            $table->timestamps();
        });

        // Pivot Conseil ↔ EquipementType : un conseil s'applique à 0‑N types d'équipements.
        Schema::create('conseil_equipement_type', function (Blueprint $table) {
            $table->foreignId('conseil_id')->constrained('conseils')->cascadeOnDelete();
            $table->foreignId('equipement_type_id')->constrained('equipement_types')->cascadeOnDelete();
            $table->primary(['conseil_id', 'equipement_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conseil_equipement_type');
        Schema::dropIfExists('conseils');
    }
};
