<?php

namespace Database\Seeders;

use App\Enums\LieuType;
use App\Enums\Role;
use App\Models\Quartier;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Amorce le réseau ChillNet avec deux quartiers, trois résidences,
     * trois coupures de démo et un compte par rôle.
     * Rejouable sans créer de doublons.
     */
    public function run(): void
    {
        $centreVille = Quartier::updateOrCreate(
            ['nom' => 'Centre-Ville', 'ville' => 'Tunis'],
            [
                'code_postal' => '1000',
                'description' => 'Quartier central, forte densité de population.',
                'latitude' => 36.8008,
                'longitude' => 10.1800,
            ],
        );

        $bergesDuLac = Quartier::updateOrCreate(
            ['nom' => 'Les Berges du Lac', 'ville' => 'Tunis'],
            [
                'code_postal' => '1053',
                'description' => "Quartier résidentiel et d'affaires.",
                'latitude' => 36.8325,
                'longitude' => 10.2800,
            ],
        );

        $oliviers = Residence::updateOrCreate(
            ['nom' => 'Résidence Les Oliviers', 'quartier_id' => $centreVille->id],
            [
                'adresse' => '12 Avenue Habib Bourguiba',
                'latitude' => 36.8008,
                'longitude' => 10.1800,
                'nombre_logements' => 48,
                'salle_climatisee' => true,
                'point_fraicheur' => true,
            ],
        );

        Residence::updateOrCreate(
            ['nom' => 'Résidence El Manar', 'quartier_id' => $centreVille->id],
            [
                'adresse' => '5 Rue de la Liberté',
                'latitude' => 36.8020,
                'longitude' => 10.1815,
                'nombre_logements' => 30,
                'salle_climatisee' => false,
                'point_fraicheur' => false,
            ],
        );

        $lacView = Residence::updateOrCreate(
            ['nom' => 'Résidence Lac View', 'quartier_id' => $bergesDuLac->id],
            [
                'adresse' => '20 Rue du Lac Léman',
                'latitude' => 36.8325,
                'longitude' => 10.2800,
                'nombre_logements' => 60,
                'salle_climatisee' => true,
                'point_fraicheur' => true,
            ],
        );

        // Points de fraîcheur / zones d'ombre autour de Centre-Ville : ils
        // alimentent la carte OpenStreetMap du périmètre 800 m de l'espace
        // habitant. Coordonnées de démonstration proches du foyer de test.
        $pointsFraicheur = [
            ['nom' => 'Médiathèque Omar Ibn Abi Rabiaa', 'adresse' => 'Avenue de la République', 'latitude' => 36.8035, 'longitude' => 10.1760, 'salle_climatisee' => true],
            ['nom' => 'Parc du Belvédère — Entrée Nord', 'adresse' => 'Rue du Parc', 'latitude' => 36.7975, 'longitude' => 10.1830, 'salle_climatisee' => false],
            ['nom' => 'Maison de la Culture Ibn Rachiq', 'adresse' => 'Place de la Culture', 'latitude' => 36.8048, 'longitude' => 10.1845, 'salle_climatisee' => true],
            ['nom' => 'Fontaine & zone d\'ombre de la Kasbah', 'adresse' => 'Rue de la Kasbah', 'latitude' => 36.7962, 'longitude' => 10.1785, 'salle_climatisee' => false],
        ];

        foreach ($pointsFraicheur as $point) {
            Residence::updateOrCreate(
                ['nom' => $point['nom'], 'quartier_id' => $centreVille->id],
                [
                    'adresse' => $point['adresse'],
                    'latitude' => $point['latitude'],
                    'longitude' => $point['longitude'],
                    'nombre_logements' => 0,
                    'salle_climatisee' => $point['salle_climatisee'],
                    'point_fraicheur' => true,
                ],
            );
        }

        // Deux gestionnaires sur DEUX zones distinctes : permet de vérifier
        // l'isolation par zone (chacun ne voit que son quartier).
        $comptes = [
            ['name' => 'Admin ChillNet', 'email' => 'admin@chillnet.test', 'role' => Role::Admin, 'residence_id' => null],
            ['name' => 'Gestionnaire Oliviers', 'email' => 'gestionnaire@chillnet.test', 'role' => Role::Gestionnaire, 'residence_id' => $oliviers->id],
            ['name' => 'Gestionnaire Berges', 'email' => 'gestionnaire.berges@chillnet.test', 'role' => Role::Gestionnaire, 'residence_id' => $lacView->id],
            ['name' => 'Habitant Test', 'email' => 'habitant@chillnet.test', 'role' => Role::Habitant, 'residence_id' => $oliviers->id, 'profil_vulnerabilites' => ['personne_agee']],
        ];

        foreach ($comptes as $compte) {
            if (User::where('email', $compte['email'])->exists()) {
                continue;
            }

            User::factory()->create($compte);
        }

        // Lieu principal du foyer de démo : l'espace habitant est centré sur
        // les lieux (Domicile, Travail…), pas sur le quartier. Le quartier
        // est auto-assigné (le plus proche) par le modèle Lieu.
        $habitant = User::where('email', 'habitant@chillnet.test')->first();

        if ($habitant) {
            // Deux lieux du foyer : le sélecteur « Mes lieux » et la carte
            // (périmètre 800 m) disposent ainsi de deux points à comparer.
            // `quartier_id` est fixé explicitement : ce seeder tourne sous
            // WithoutModelEvents, donc le hook d'auto-assignation du modèle
            // Lieu n'est pas déclenché.
            $habitant->lieux()->updateOrCreate(
                ['nom' => 'Domicile'],
                [
                    'type' => LieuType::Domicile,
                    'adresse' => '12 Avenue Habib Bourguiba',
                    'latitude' => 36.8008,
                    'longitude' => 10.1800,
                    'quartier_id' => Quartier::plusProche(36.8008, 10.1800)?->id,
                    'est_principal' => true,
                ],
            );

            $habitant->lieux()->updateOrCreate(
                ['nom' => 'Travail'],
                [
                    'type' => LieuType::Travail,
                    'adresse' => '20 Rue du Lac Léman',
                    'latitude' => 36.8325,
                    'longitude' => 10.2800,
                    'quartier_id' => Quartier::plusProche(36.8325, 10.2800)?->id,
                    'est_principal' => false,
                ],
            );
        }

        // Module 2 (coupures) : après les quartiers, on amorce 3 coupures démo.
        $this->call(CoupureSeeder::class);

        // Module 1 (alertes / vigilance) : après les quartiers et les comptes.
        $this->call(AlerteSeeder::class);
    }
}
