<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une résidence ou un quartier déclaré par un habitant à l'inscription n'a pas
 * forcément d'adresse / de code postal connus. On les rend facultatifs, la
 * saisie back-office continuant de les exiger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residences', function (Blueprint $table) {
            $table->string('adresse', 255)->nullable()->change();
        });

        Schema::table('quartiers', function (Blueprint $table) {
            $table->string('code_postal', 10)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('residences')->whereNull('adresse')->update(['adresse' => '']);
        DB::table('quartiers')->whereNull('code_postal')->update(['code_postal' => '']);

        Schema::table('residences', function (Blueprint $table) {
            $table->string('adresse', 255)->nullable(false)->change();
        });

        Schema::table('quartiers', function (Blueprint $table) {
            $table->string('code_postal', 10)->nullable(false)->change();
        });
    }
};
