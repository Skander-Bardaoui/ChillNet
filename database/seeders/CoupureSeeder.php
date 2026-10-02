<?php

namespace Database\Seeders;

use App\Enums\StatutCoupure;
use App\Enums\TypeCoupure;
use App\Models\Coupure;
use App\Models\Quartier;
use Illuminate\Database\Seeder;

class CoupureSeeder extends Seeder
{
    /**
     * Jeu de démo : 1 coupure en cours + 1 prévue + 1 résolue,
     * réparties sur les quartiers déjà seedés.
     */
    public function run(): void
    {
        $centreVille = Quartier::where('nom', 'Centre-Ville')->first();
        $berges = Quartier::where('nom', 'Les Berges du Lac')->first();

        if (! $centreVille || ! $berges) {
            $this->command?->warn('Quartiers de démo absents : lancez DatabaseSeeder avant CoupureSeeder.');

            return;
        }

        $demo = [
            [
                'quartier_id' => $centreVille->id,
                'type' => TypeCoupure::Delestage->value,
                'statut' => StatutCoupure::EnCours->value,
                'debut' => now()->subHour(),
                'fin' => now()->addHours(2),
                'description' => 'Délestage en cours — rue des Lilas, prévoyez vos équipements sensibles.',
                'lieu' => 'Rue des Lilas',
                'confirmations' => 3,
            ],
            [
                'quartier_id' => $berges->id,
                'type' => TypeCoupure::Maintenance->value,
                'statut' => StatutCoupure::Prevue->value,
                'debut' => now()->addDay()->setTime(8, 0),
                'fin' => now()->addDay()->setTime(12, 0),
                'description' => 'Maintenance planifiée sur le réseau électrique, quai des Brumes.',
                'lieu' => 'Quai des Brumes',
            ],
            [
                'quartier_id' => $centreVille->id,
                'type' => TypeCoupure::Panne->value,
                'statut' => StatutCoupure::Resolue->value,
                'debut' => now()->subDays(2)->setTime(17, 0),
                'fin' => now()->subDays(2)->setTime(18, 15),
                'description' => 'Panne résolue place du Capitole, réseau rétabli.',
                'lieu' => 'Place du Capitole',
            ],
        ];

        foreach ($demo as $row) {
            Coupure::updateOrCreate(
                [
                    'quartier_id' => $row['quartier_id'],
                    'type' => $row['type'],
                    'statut' => $row['statut'],
                    'debut' => $row['debut'],
                ],
                $row,
            );
        }
    }
}
