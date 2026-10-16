<?php

namespace Database\Seeders;

use App\Enums\StatutCoupure;
use App\Enums\TypeCoupure;
use App\Models\Coupure;
use App\Models\Quartier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoupureSeeder extends Seeder
{
    /**
     * Jeu de démo du Module 2 (coupures / carte) couvrant TOUS les scénarios :
     *  - les 3 statuts (en cours / prévue / résolue) et les 4 types
     *    (délestage, surcharge, panne, maintenance) ;
     *  - ciblage par quartier ET ciblage libre par point (sans quartier) ;
     *  - confirmations voisines (« Je confirme ») et historique résolu ;
     *  - un AFFLUX de signalements sur Centre-Ville (créés il y a quelques
     *    minutes) qui déclenche la détection d'anomalie du module IA.
     *
     * Idempotent : la clé naturelle est la `description` (stable). Les
     * signalements d'afflux voient leur `created_at` repositionné à chaque
     * exécution pour que la démo reste parlante.
     */
    public function run(): void
    {
        $centreVille = Quartier::where('nom', 'Centre-Ville')->first();
        $berges = Quartier::where('nom', 'Les Berges du Lac')->first();

        if (! $centreVille || ! $berges) {
            $this->command?->warn('Quartiers de démo absents : lancez DatabaseSeeder avant CoupureSeeder.');

            return;
        }

        $gestionnaire = User::where('email', 'gestionnaire@chillnet.test')->first();
        $habitant = User::where('email', 'habitant@chillnet.test')->first();

        $demo = [
            // ── En cours : délestage sur Centre-Ville ──────────────────────
            [
                'quartier_id' => $centreVille->id,
                'latitude' => null,
                'longitude' => null,
                'type' => TypeCoupure::Delestage->value,
                'statut' => StatutCoupure::EnCours->value,
                'debut' => now()->subHour(),
                'fin' => now()->addHours(2),
                'description' => 'Délestage en cours — rue des Lilas, prévoyez vos équipements sensibles.',
                'lieu' => 'Rue des Lilas',
                'confirmations' => 3,
                'user_id' => $gestionnaire?->id,
            ],
            // ── Prévue : maintenance planifiée aux Berges ──────────────────
            [
                'quartier_id' => $berges->id,
                'latitude' => null,
                'longitude' => null,
                'type' => TypeCoupure::Maintenance->value,
                'statut' => StatutCoupure::Prevue->value,
                'debut' => now()->addDay()->setTime(8, 0),
                'fin' => now()->addDay()->setTime(12, 0),
                'description' => 'Maintenance planifiée sur le réseau électrique, quai des Brumes.',
                'lieu' => 'Quai des Brumes',
                'confirmations' => 0,
                'user_id' => $gestionnaire?->id,
            ],
            // ── Résolue : panne historique sur Centre-Ville ────────────────
            [
                'quartier_id' => $centreVille->id,
                'latitude' => null,
                'longitude' => null,
                'type' => TypeCoupure::Panne->value,
                'statut' => StatutCoupure::Resolue->value,
                'debut' => now()->subDays(2)->setTime(17, 0),
                'fin' => now()->subDays(2)->setTime(18, 15),
                'description' => 'Panne résolue place du Capitole, réseau rétabli.',
                'lieu' => 'Place du Capitole',
                'confirmations' => 5,
                'user_id' => $gestionnaire?->id,
            ],
            // ── En cours : surcharge ciblée par POINT, sans quartier ────────
            [
                'quartier_id' => null,
                'latitude' => 36.8105,
                'longitude' => 10.1905,
                'type' => TypeCoupure::Surcharge->value,
                'statut' => StatutCoupure::EnCours->value,
                'debut' => now()->subMinutes(40),
                'fin' => now()->addHours(3),
                'description' => 'Surcharge signalée avenue de la République : transformateur en surchauffe.',
                'lieu' => 'Avenue de la République',
                'confirmations' => 1,
                'user_id' => $gestionnaire?->id,
            ],
            // ── Prévue : délestage programmé aux Berges ────────────────────
            [
                'quartier_id' => $berges->id,
                'latitude' => null,
                'longitude' => null,
                'type' => TypeCoupure::Delestage->value,
                'statut' => StatutCoupure::Prevue->value,
                'debut' => now()->addDays(2)->setTime(14, 0),
                'fin' => now()->addDays(2)->setTime(17, 0),
                'description' => 'Délestage programmé rue du Lac Léman, créneau de faible consommation.',
                'lieu' => 'Rue du Lac Léman',
                'confirmations' => 0,
                'user_id' => $gestionnaire?->id,
            ],
            // ── Résolue : maintenance historique aux Berges ────────────────
            [
                'quartier_id' => $berges->id,
                'latitude' => null,
                'longitude' => null,
                'type' => TypeCoupure::Maintenance->value,
                'statut' => StatutCoupure::Resolue->value,
                'debut' => now()->subDays(5)->setTime(9, 0),
                'fin' => now()->subDays(5)->setTime(11, 30),
                'description' => 'Maintenance résolue rue du Lac Turkana, interventions terminées.',
                'lieu' => 'Rue du Lac Turkana',
                'confirmations' => 4,
                'user_id' => $gestionnaire?->id,
            ],
            // ── AFFLUX de signalements habitants (déclenche l'anomalie) ─────
            [
                'quartier_id' => $centreVille->id,
                'latitude' => 36.8022,
                'longitude' => 10.1772,
                'type' => TypeCoupure::Panne->value,
                'statut' => StatutCoupure::EnCours->value,
                'debut' => now()->subMinutes(45),
                'fin' => null,
                'description' => 'Signalement habitant : plus de courant rue de Marseille depuis 45 min.',
                'lieu' => 'Rue de Marseille',
                'confirmations' => 2,
                'user_id' => $habitant?->id,
                '_minutes_ago' => 45,
            ],
            [
                'quartier_id' => $centreVille->id,
                'latitude' => 36.8029,
                'longitude' => 10.1785,
                'type' => TypeCoupure::Panne->value,
                'statut' => StatutCoupure::EnCours->value,
                'debut' => now()->subMinutes(30),
                'fin' => null,
                'description' => 'Signalement habitant : coupure avenue de Carthage, quartier entier impacté.',
                'lieu' => 'Avenue de Carthage',
                'confirmations' => 1,
                'user_id' => $habitant?->id,
                '_minutes_ago' => 30,
            ],
            [
                'quartier_id' => $centreVille->id,
                'latitude' => 36.7998,
                'longitude' => 10.1812,
                'type' => TypeCoupure::Surcharge->value,
                'statut' => StatutCoupure::EnCours->value,
                'debut' => now()->subMinutes(15),
                'fin' => null,
                'description' => 'Signalement habitant : micro-coupures répétées rue d\'Alger.',
                'lieu' => "Rue d'Alger",
                'confirmations' => 0,
                'user_id' => $habitant?->id,
                '_minutes_ago' => 15,
            ],
        ];

        foreach ($demo as $row) {
            $minutesAgo = $row['_minutes_ago'] ?? null;
            unset($row['_minutes_ago']);

            $coupure = Coupure::updateOrCreate(['description' => $row['description']], $row);

            if ($minutesAgo !== null) {
                // La détection d'anomalie du module IA s'appuie sur `created_at` :
                // on le repositionne pour garder la démo parlante après re-seed.
                DB::table('coupures')->where('id', $coupure->id)->update([
                    'created_at' => now()->subMinutes($minutesAgo),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
