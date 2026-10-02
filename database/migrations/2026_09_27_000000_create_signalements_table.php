<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signalements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('residence_id')->constrained()->cascadeOnDelete();
            $table->string('categorie', 50);
            $table->string('urgence', 20)->default('normale');
            $table->text('description');
            $table->string('photo_path')->nullable();
            $table->string('statut', 20)->default('nouveau');
            $table->date('date_signalement');
            $table->timestamps();

            $table->unique(['user_id', 'residence_id', 'date_signalement']);
            $table->index(['statut', 'urgence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signalements');
    }
};