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
     * Couvre volontairement TOUS les scénarios d'affichage :
     *  - les 3 niveaux (jaune / orange / rouge) et les 3 statuts dérivés
     *    (active / programmée / terminée) ;
     *  - brouillon NON validée (visible en back office, jamais côté habitant) ;
     *  - les deux ciblages : cercle géographique (point + rayon) et quartier
     *    hérité (pivot), y compris une alerte multi-quartiers ;
     *  - une alerte géolocalisée SANS quartier : la visibilité ne dépend alors
     *    que de la proximité (couvrePoint), pas du pivot ;
     *  - source météo weatherapi vs manuel.
     *
     * Idempotent (clé = titre) et SANS appel réseau : tout est figé à la main
     * pour que les comptes de démo voient un état stable (dont une alerte ROUGE
     * active sur Centre-Ville, visible par habitant@chillnet.test).
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
            // ── 1. Rouge ACTIVE, cercle 1,5 km sur Centre-Ville ──────────────
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
            // ── 2. Orange PROGRAMMÉE (demain), cercle 1,2 km sur Les Berges ──
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
                'latitude' => 36.8325,
                'longitude' => 10.2800,
                'rayon_metres' => 1200,
                'message' => 'Vigilance orange pour demain aux Berges du Lac : préparez volets, réserves d\'eau fraîche '
                    .'et rechargez vos équipements sensibles avant le pic de l\'après-midi.',
                'validee' => true,
                'quartiers' => [$berges->id],
            ],
            // ── 3. Jaune TERMINÉE (historique) sur Centre-Ville ─────────────
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
            // ── 4. Orange ACTIVE, héritée MULTI-QUARTIERS (sans cercle) ─────
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
            // ── 5. Rouge BROUILLON (non validée) : back office uniquement ───
            [
                'titre' => 'Vigilance Rouge — Brouillon gestionnaire (non publié)',
                'niveau' => NiveauAlerte::Rouge->value,
                'seuil_temperature' => 36.0,
                'temperature_actuelle' => 40.5,
                'temperature_ressentie' => 43.0,
                'humidite' => 70,
                'source_meteo' => 'weatherapi',
                'debut' => now()->subMinutes(30),
                'fin' => now()->addHours(8),
                'message' => 'Brouillon en attente de validation : à ne PAS diffuser aux habitants tant qu\'il n\'est pas validé.',
                'validee' => false,
                'quartiers' => [$centreVille->id],
            ],
            // ── 6. Jaune ACTIVE, cercle 900 m sur Les Berges (source API) ───
            [
                'titre' => 'Vigilance Jaune — Chaleur modérée en cours',
                'niveau' => NiveauAlerte::Jaune->value,
                'seuil_temperature' => 35.0,
                'temperature_actuelle' => 35.5,
                'temperature_ressentie' => 36.5,
                'humidite' => 62,
                'source_meteo' => 'weatherapi',
                'debut' => now()->subHours(2),
                'fin' => now()->addHours(5),
                'latitude' => 36.8325,
                'longitude' => 10.2800,
                'rayon_metres' => 900,
                'message' => 'Chaleur modérée aux Berges du Lac : restez attentif aux personnes fragiles, sans mesure exceptionnelle.',
                'validee' => true,
                'quartiers' => [$berges->id],
            ],
            // ── 7. Orange ACTIVE, cercle SANS quartier (visibilité proximité) ─
            [
                'titre' => 'Vigilance Orange — Cercle hors zones (visible par proximité)',
                'niveau' => NiveauAlerte::Orange->value,
                'seuil_temperature' => 34.0,
                'temperature_actuelle' => 37.5,
                'temperature_ressentie' => 39.5,
                'humidite' => 66,
                'source_meteo' => 'manuel',
                'debut' => now()->subHours(1),
                'fin' => now()->addHours(7),
                // Cercle posé SANS rattachement quartier : seul couvrePoint décide.
                'latitude' => 36.8015,
                'longitude' => 10.1790,
                'rayon_metres' => 800,
                'message' => 'Alerte ciblée par cercle uniquement : toute personne résidant dans le rayon est concernée, '
                    .'indépendamment du découpage en quartiers.',
                'validee' => true,
                'quartiers' => [],
            ],
            // ── 8. Rouge TERMINÉE (historique) sur Les Berges ───────────────
            [
                'titre' => 'Vigilance Rouge — Épisode passé aux Berges',
                'niveau' => NiveauAlerte::Rouge->value,
                'seuil_temperature' => 35.0,
                'temperature_actuelle' => 42.0,
                'temperature_ressentie' => 45.0,
                'humidite' => 58,
                'source_meteo' => 'manuel',
                'debut' => now()->subDays(6)->setTime(11, 0),
                'fin' => now()->subDays(6)->setTime(20, 0),
                'message' => 'Épisode rouge de la semaine passée, clos depuis. Conservé pour l\'historique du back office.',
                'validee' => true,
                'quartiers' => [$berges->id],
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
