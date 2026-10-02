<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table backing the App\Models\Coupure Eloquent model.
     * Une coupure touche une zone précise = un quartier (quartier_id).
     */
    public function up(): void
    {
        Schema::create('coupures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quartier_id')->constrained('quartiers')->cascadeOnDelete();
            $table->string('type', 20)->default('panne');
            $table->string('statut', 20)->default('en_cours');
            $table->dateTime('debut');
            $table->dateTime('fin')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupures');
    }
};
