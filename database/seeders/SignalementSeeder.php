<?php

namespace Database\Seeders;

use App\Models\Residence;
use App\Models\Signalement;
use App\Models\User;
use Illuminate\Database\Seeder;

class SignalementSeeder extends Seeder
{
    /**
     * Crée trois signalements de démonstration pour le compte habitant seedé.
     */
    public function run(): void
    {
        $habitant = User::where('email', 'habitant@chillnet.test')->first();
        $residence = Residence::where('nom', 'Résidence El Manar')->first();

        if (! $habitant || ! $residence) {
            $this->command?->warn('Compte habitant ou résidence de démo absent : aucun signalement de démo créé.');

            return;
        }

        $signalements = [
            [
                'categorie' => 'fuite',
                'urgence' => 'prioritaire',
                'description' => 'Une fuite d’eau est visible dans les parties communes près du hall.',
                'statut' => 'nouveau',
                'jours' => 2,
            ],
            [
                'categorie' => 'panne_locale',
                'urgence' => 'normale',
                'description' => 'L’éclairage du couloir du deuxième étage ne fonctionne plus.',
                'statut' => 'en_traitement',
                'jours' => 1,
            ],
            [
                'categorie' => 'personne_vulnerable',
                'urgence' => 'vitale',
                'description' => 'Un voisin âgé semble isolé et pourrait avoir besoin d’assistance.',
                'statut' => 'resolu',
                'jours' => 3,
            ],
        ];

        foreach ($signalements as $signalement) {
            $date = now()->subDays($signalement['jours'])->startOfDay();
            unset($signalement['jours']);

            Signalement::updateOrCreate(
                [
                    'user_id' => $habitant->id,
                    'residence_id' => $residence->id,
                    'categorie' => $signalement['categorie'],
                    'date_signalement' => $date,
                ],
                $signalement + ['date_signalement' => $date],
            );
        }
    }
}
