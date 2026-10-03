<?php

namespace Database\Seeders;

use App\Enums\NiveauAlerte;
use App\Models\Alerte;
use App\Models\Quartier;
use App\Models\User;
use Illuminate\Database\Seeder;

class AlerteSeeder extends Seeder
{
    /**
     * Jeu de démo du Module 1 (alertes météo / vigilance canicule).
     *
     * Idempotent (updateOrCreate + sync) et SANS appel réseau : les niveaux
     * et messages sont fixés à la main pour que les comptes de démo voient
     * un état stable (dont une alerte ROUGE active sur Centre-Ville, visible
     * par habitant@chillnet.test).
     */
    public function run(): void
    {
        $centreVille = Quartier::where('nom', 'Centre-Ville')->first();
        $berges = Quartier::where('nom', 'Les Berges du Lac')->first();

        if (! $centreVille || ! $berges) {
            $this->command?->warn('Quartiers de démo absents : lancez DatabaseSeeder avant AlerteSeeder.');

            return;
        }

        $gestionnaire = User::where('email', 'gestionnaire@chillnet.test')->first();

        $demo = [
            [
                'titre' => 'Vigilance Rouge Canicule — Pic à 41°C',
                'niveau' => NiveauAlerte::Rouge->value,
                'seuil_temperature' => 35.0,
                'temperature_actuelle' => 41.0,
                'temperature_ressentie' => 44.5,
                'humidite' => 72,
                'source_meteo' => 'manuel',
                'debut' => now()->subHours(3),
                'fin' => now()->addHours(9),
                // Ciblage géographique : cercle de 1,5 km centré sur Centre-Ville.
                'latitude' => 36.8008,
                'longitude' => 10.1800,
                'rayon_metres' => 1500,
                'message' => 'Vigilance rouge canicule en cours sur Centre-Ville : pic attendu à 41°C cet après-midi. '
                    .'Restez dans la pièce la plus fraîche, buvez régulièrement sans attendre la soif et évitez toute '
                    .'sortie entre 11h et 17h. Prenez des nouvelles de vos voisins isolés. Urgences : 190 / 198, '
                    .'plateforme canicule 0800 06 66 66.',
                'validee' => true,
                'quartiers' => [$centreVille->id],
            ],
            [
                'titre' => 'Vigilance Orange — Chaleur annoncée demain',
                'niveau' => NiveauAlerte::Orange->value,
                'seuil_temperature' => 35.0,
                'temperature_actuelle' => 37.0,
                'temperature_ressentie' => 39.0,
                'humidite' => 68,
                'source_meteo' => 'manuel',
                'debut' => now()->addDay()->setTime(10, 0),
                'fin' => now()->addDay()->setTime(20, 0),
                // Ciblage géographique : cercle de 1,2 km centré sur Les Berges du Lac.
                'latitude' => 36.8325,
                'longitude' => 10.2800,
                'rayon_metres' => 1200,
                'message' => 'Vigilance orange pour demain aux Berges du Lac : préparez volets, réserves d\'eau fraîche '
                    .'et rechargez vos équipements sensibles avant le pic de l\'après-midi.',
                'validee' => true,
                'quartiers' => [$berges->id],
            ],
            [
                'titre' => 'Vigilance Jaune — Épisode passé',
                'niveau' => NiveauAlerte::Jaune->value,
                'seuil_temperature' => 35.0,
                'temperature_actuelle' => 36.0,
                'temperature_ressentie' => 37.0,
                'humidite' => 55,
                'source_meteo' => 'manuel',
                'debut' => now()->subDays(3)->setTime(11, 0),
                'fin' => now()->subDays(3)->setTime(19, 0),
                'message' => 'Épisode de chaleur modéré désormais terminé : les températures sont revenues à la normale.',
                'validee' => true,
                'quartiers' => [$centreVille->id],
            ],
            [
                'titre' => 'Vigilance Orange — Vague de chaleur multi-quartiers',
                'niveau' => NiveauAlerte::Orange->value,
                'seuil_temperature' => 35.0,
                'temperature_actuelle' => 38.0,
                'temperature_ressentie' => 40.0,
                'humidite' => 74,
                'source_meteo' => 'manuel',
                'debut' => now()->subHour(),
                'fin' => now()->addHours(6),
                'message' => 'Vague de chaleur durable : trois jours consécutifs au-dessus du seuil. Hydratez-vous '
                    .'régulièrement et limitez les efforts physiques aux heures fraîches.',
                'validee' => true,
                'quartiers' => [$centreVille->id, $berges->id],
            ],
        ];

        foreach ($demo as $row) {
            $quartiers = $row['quartiers'];
            unset($row['quartiers']);

            $alerte = Alerte::updateOrCreate(
                ['titre' => $row['titre']],
                $row + [
                    'user_id' => $gestionnaire?->id,
                    'validee_le' => $row['validee'] ? now() : null,
                    'validee_par' => $row['validee'] ? $gestionnaire?->id : null,
                ],
            );

            $alerte->quartiers()->sync($quartiers);
        }
    }
}
