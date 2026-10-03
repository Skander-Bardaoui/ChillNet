<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table backing the App\Models\Alerte Eloquent model.
     *
     * Une alerte canicule porte un niveau de vigilance (jaune/orange/rouge),
     * un créneau [debut, fin] (fin > debut), un seuil de température, et la
     * mesure météo (température / humidité) qui a servi à classer le risque.
     * L'association aux quartiers se fait via la table pivot `alerte_quartier`.
     */
    public function up(): void
    {
        Schema::create('alertes', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 150);
            $table->string('niveau', 10)->default('jaune')->index();
            $table->decimal('seuil_temperature', 4, 1);
            $table->decimal('temperature_actuelle', 4, 1)->nullable();
            $table->decimal('temperature_ressentie', 4, 1)->nullable();
            $table->unsignedTinyInteger('humidite')->nullable();
            $table->string('source_meteo', 20)->default('manuel');
            $table->dateTime('debut');
            $table->dateTime('fin');
            $table->text('message')->nullable();
            $table->boolean('validee')->default(false);
            $table->timestamp('validee_le')->nullable();
            $table->foreignId('validee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['validee', 'debut', 'fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertes');
    }
};
