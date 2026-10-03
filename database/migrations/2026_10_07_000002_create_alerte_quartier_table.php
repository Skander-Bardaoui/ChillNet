<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table pivot Alerte N --- N Quartier : une alerte peut couvrir
     * un ou plusieurs quartiers, un quartier peut recevoir plusieurs alertes.
     */
    public function up(): void
    {
        Schema::create('alerte_quartier', function (Blueprint $table) {
            $table->foreignId('alerte_id')->constrained('alertes')->cascadeOnDelete();
            $table->foreignId('quartier_id')->constrained('quartiers')->cascadeOnDelete();
            $table->primary(['alerte_id', 'quartier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerte_quartier');
    }
};
