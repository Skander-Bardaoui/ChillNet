<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('signalements')
            ->select('user_id', 'residence_id', 'categorie', 'date_signalement')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('user_id', 'residence_id', 'categorie', 'date_signalement')
            ->having('total', '>', 1)
            ->get()
            ->each(function (object $duplicate): void {
                $duplicateIds = DB::table('signalements')
                    ->where('user_id', $duplicate->user_id)
                    ->where('residence_id', $duplicate->residence_id)
                    ->where('categorie', $duplicate->categorie)
                    ->where('date_signalement', $duplicate->date_signalement)
                    ->orderBy('id')
                    ->pluck('id');
                $idsToDelete = $duplicateIds->slice(1);

                DB::table('signalements')->whereIn('id', $idsToDelete)->delete();
            });

        Schema::table('signalements', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'residence_id', 'categorie', 'date_signalement'],
                'signalements_unique_category_per_day'
            );
        });
    }

    public function down(): void
    {
        Schema::table('signalements', function (Blueprint $table) {
            $table->dropUnique('signalements_unique_category_per_day');
        });
    }
};