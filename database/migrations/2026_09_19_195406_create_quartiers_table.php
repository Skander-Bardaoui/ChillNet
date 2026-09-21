<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table backing the App\Entities\Quartier Doctrine entity.
     * Schema kept in sync manually with app/Entities/Quartier.php since
     * Doctrine's schema-tool manages the whole DB and would drop Laravel's
     * own tables (users, sessions, ...) if run against this shared database.
     */
    public function up(): void
    {
        Schema::create('quartiers', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 120);
            $table->string('ville', 120);
            $table->string('code_postal', 10);
            $table->text('description')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quartiers');
    }
};
