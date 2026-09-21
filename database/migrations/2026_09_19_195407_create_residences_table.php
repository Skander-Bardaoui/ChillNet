<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table backing the App\Entities\Residence Doctrine entity.
     * Schema kept in sync manually with app/Entities/Residence.php — see note
     * in the quartiers migration about why doctrine:schema:update isn't used here.
     */
    public function up(): void
    {
        Schema::create('residences', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150);
            $table->string('adresse', 255);
            $table->unsignedInteger('nombre_logements')->default(0);
            $table->boolean('salle_climatisee')->default(false);
            $table->boolean('point_fraicheur')->default(false);
            $table->foreignId('quartier_id')->constrained('quartiers')->cascadeOnDelete();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('residences');
    }
};
