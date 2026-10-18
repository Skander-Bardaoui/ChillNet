<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('signalement_analyses')) {
            Schema::create('signalement_analyses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('signalement_id')->unique()->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('score_urgence');
                $table->string('urgence_suggeree', 20);
                $table->json('indicateurs_urgence')->nullable();
                $table->json('termes_urgents')->nullable();
                $table->json('signalements_lies')->nullable();
                $table->char('empreinte_description', 64);
                $table->string('version_modele', 30);
                $table->timestamp('analysee_le');
                $table->timestamps();

                $table->index('urgence_suggeree');
            });

            return;
        }

        Schema::table('signalement_analyses', function (Blueprint $table) {
            if (! Schema::hasColumn('signalement_analyses', 'indicateurs_urgence')) {
                $table->json('indicateurs_urgence')->nullable();
            }
            if (! Schema::hasColumn('signalement_analyses', 'termes_urgents')) {
                $table->json('termes_urgents')->nullable();
            }
            if (! Schema::hasColumn('signalement_analyses', 'version_modele')) {
                $table->string('version_modele', 30)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (
            Schema::hasTable('signalement_analyses')
            && (
                Schema::hasColumn('signalement_analyses', 'model_run_id')
                || Schema::hasColumn('signalement_analyses', 'confiance')
                || Schema::hasColumn('signalement_analyses', 'probabilites')
            )
        ) {
            Schema::table('signalement_analyses', function (Blueprint $table) {
                $colonnes = array_filter(
                    ['indicateurs_urgence', 'termes_urgents', 'version_modele'],
                    static fn (string $colonne): bool => Schema::hasColumn('signalement_analyses', $colonne),
                );
                if ($colonnes !== []) {
                    $table->dropColumn($colonnes);
                }
            });

            return;
        }

        Schema::dropIfExists('signalement_analyses');
    }
};
