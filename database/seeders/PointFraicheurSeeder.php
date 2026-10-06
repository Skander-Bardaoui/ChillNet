<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Avis;
use App\Models\PointFraicheur;
use App\Models\Quartier;
use App\Models\User;
use Illuminate\Database\Seeder;

class PointFraicheurSeeder extends Seeder
{
    /**
     * Jeu de démo du Module 3 (points de fraîcheur + avis) :
     *  - 8 points VALIDÉS (parcs, salles climatisées, fontaines) autour de
     *    Centre-Ville et des Berges du Lac, avec photos facultatives ;
     *  - 2 propositions d'habitant EN ATTENTE (file de modération admin)
     *    et 1 proposition REFUSÉE avec motif ;
     *  - des avis via AvisFactory (relation PointFraicheur 1 --- N Avis),
     *    dont 2 points volontairement mal notés pour déclencher la détection
     *    IA des « points mal notés récurrents ».
     *
     * Idempotent : points repérés par leur nom, avis des voisins de démo
     * régénérés à chaque passage. Appelé par DatabaseSeeder (php artisan db:seed)
     * ou seul : php artisan db:seed --class=PointFraicheurSeeder
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@chillnet.test')->first();
        $habitant = User::where('email', 'habitant@chillnet.test')->first();

        // Voisins votants : un avis par habitant et par point (contrainte unique).
        $voisins = collect(range(1, 8))->map(fn (int $i): User => User::firstOrCreate(
            ['email' => "voisin{$i}@chillnet.test"],
            User::factory()->raw(['role' => Role::Habitant->value, 'email' => "voisin{$i}@chillnet.test"]),
        ));

        // [nom, type, adresse, lat, lng, capacité, 24h, ouverture, fermeture, pmr, clim, ombre, eau, avis [positifs, neutres, négatifs]]
        $valides = [
            ['Parc du Belvédère — entrée principale', 'parc', 'Avenue Taïeb Mhiri, Tunis', 36.8190, 10.1760, 500, false, '07:00', '20:00', true, false, true, true, [5, 1, 0]],
            ['Médiathèque Ibn Khaldoun — espace fraîcheur', 'salle_climatisee', 'Rue de Rome, Tunis', 36.7990, 10.1790, 80, false, '09:00', '21:00', true, true, false, true, [4, 1, 0]],
            ['Fontaine de la Place Barcelone', 'fontaine', 'Place Barcelone, Tunis', 36.7960, 10.1820, null, true, null, null, true, false, false, true, [2, 1, 1]],
            ['Maison de la Culture Ibn Rachiq', 'salle_climatisee', 'Avenue de Paris, Tunis', 36.8045, 10.1850, 60, false, '10:00', '18:00', false, true, false, false, [1, 0, 4]],
            ['Jardin Habib Thameur', 'parc', 'Avenue Habib Thameur, Tunis', 36.8060, 10.1750, 200, false, '06:00', '22:00', true, false, true, false, [3, 2, 0]],
            ['Fontaine de l\'avenue Habib Bourguiba', 'fontaine', 'Avenue Habib Bourguiba, Tunis', 36.8000, 10.1830, null, true, null, null, true, false, true, true, [0, 1, 3]],
            ['Salle climatisée municipale des Berges', 'salle_climatisee', 'Rue du Lac Windermere, Les Berges du Lac', 36.8330, 10.2780, 150, false, '10:00', '22:00', true, true, false, true, [3, 0, 0]],
            ['Promenade ombragée du Lac', 'parc', 'Promenade du Lac, Les Berges du Lac', 36.8360, 10.2700, 400, true, null, null, true, false, true, true, [2, 1, 0]],
        ];

        foreach ($valides as [$nom, $type, $adresse, $lat, $lng, $cap, $h24, $ouv, $ferm, $pmr, $clim, $ombre, $eau, [$pos, $neu, $neg]]) {
            $point = PointFraicheur::updateOrCreate(['nom' => $nom], [
                'type' => $type,
                'adresse' => $adresse,
                'latitude' => $lat,
                'longitude' => $lng,
                'capacite' => $cap,
                'ouvert_24h' => $h24,
                'heure_ouverture' => $ouv,
                'heure_fermeture' => $ferm,
                'accessible_pmr' => $pmr,
                'climatise' => $clim,
                'ombrage' => $ombre,
                'eau_potable' => $eau,
                'statut' => 'valide',
                'motif_refus' => null,
                'valide_par' => $admin?->id,
                'valide_le' => now()->subDays(20),
                'user_id' => $admin?->id,
                // Explicite : le hook du modèle est muet sous WithoutModelEvents.
                'quartier_id' => Quartier::plusProche($lat, $lng)?->id,
            ]);

            // Seuls les avis des voisins de démo sont régénérés (ceux des vrais
            // comptes sont conservés) ; chaque voisin ne note qu'une fois ce point.
            $point->avis()->whereIn('user_id', $voisins->pluck('id'))->delete();
            $votants = $voisins->shuffle()->values();
            $rang = 0;

            foreach (['positif' => $pos, 'neutre' => $neu, 'negatif' => $neg] as $etat => $nombre) {
                for ($i = 0; $i < $nombre && $rang < $votants->count(); $i++, $rang++) {
                    Avis::factory()
                        ->{$etat}()
                        ->par($votants[$rang])
                        ->for($point, 'pointFraicheur')
                        ->create(['created_at' => now()->subDays(random_int(1, 40))]);
                }
            }
        }

        if (! $habitant) {
            $this->command?->warn('Compte habitant@chillnet.test absent : propositions de démo ignorées.');

            return;
        }

        // Propositions d'habitant à modérer (back office admin).
        $propositions = [
            ['Fontaine de la rue de Marseille', 'fontaine', 'Rue de Marseille, Tunis', 36.8015, 10.1795, null, true, null, null, 'en_attente', null],
            ['Square ombragé de Bab Saadoun', 'parc', 'Place Bab Saadoun, Tunis', 36.8105, 10.1650, 120, false, '07:00', '21:00', 'en_attente', null],
            ['Café climatisé du Passage', 'salle_climatisee', 'Rue du Passage, Tunis', 36.8075, 10.1805, 25, false, '08:00', '23:00', 'refuse', 'Établissement commercial avec consommation obligatoire : ce n\'est pas un lieu public gratuit.'],
        ];

        foreach ($propositions as [$nom, $type, $adresse, $lat, $lng, $cap, $h24, $ouv, $ferm, $statut, $motif]) {
            PointFraicheur::updateOrCreate(['nom' => $nom], [
                'type' => $type,
                'adresse' => $adresse,
                'latitude' => $lat,
                'longitude' => $lng,
                'capacite' => $cap,
                'ouvert_24h' => $h24,
                'heure_ouverture' => $ouv,
                'heure_fermeture' => $ferm,
                'accessible_pmr' => false,
                'climatise' => $type === 'salle_climatisee',
                'ombrage' => $type === 'parc',
                'eau_potable' => $type === 'fontaine',
                'statut' => $statut,
                'motif_refus' => $motif,
                'valide_par' => $statut === 'refuse' ? $admin?->id : null,
                'valide_le' => $statut === 'refuse' ? now()->subDay() : null,
                'user_id' => $habitant->id,
                'quartier_id' => Quartier::plusProche($lat, $lng)?->id,
                'description' => 'Proposé par un habitant du quartier.',
            ]);
        }

        // Points supplémentaires générés (factory) pour remplir la pagination.
        $marqueur = 'Point généré pour la démonstration.';

        if (PointFraicheur::where('description', $marqueur)->doesntExist()) {
            PointFraicheur::factory()
                ->count(6)
                ->valide()
                ->has(Avis::factory()->count(2)->sequence(
                    fn ($seq) => ['user_id' => $voisins[$seq->index % $voisins->count()]->id],
                ), 'avis')
                ->create(['description' => $marqueur, 'valide_par' => $admin?->id, 'user_id' => $admin?->id]);
        }
    }
}
