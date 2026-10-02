<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signalements', function (Blueprint $table) {
            $table->dropUnique('signalements_user_id_residence_id_date_signalement_unique');
        });
    }

    public function down(): void
    {
        Schema::table('signalements', function (Blueprint $table) {
            $table->unique(['user_id', 'residence_id', 'date_signalement']);
        });
    }
};