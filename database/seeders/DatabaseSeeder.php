<?php

namespace Database\Seeders;

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
     * Amorce le réseau ChillNet avec deux quartiers, trois résidences et
     * un compte par rôle. Rejouable sans créer de doublons.
     */
    public function run(): void
    {
        $centreVille = Quartier::updateOrCreate(
            ['nom' => 'Centre-Ville', 'ville' => 'Tunis'],
            [
                'code_postal' => '1000',
                'description' => 'Quartier central, forte densité de population.',
            ],
        );

        $bergesDuLac = Quartier::updateOrCreate(
            ['nom' => 'Les Berges du Lac', 'ville' => 'Tunis'],
            [
                'code_postal' => '1053',
                'description' => "Quartier résidentiel et d'affaires.",
            ],
        );

        $oliviers = Residence::updateOrCreate(
            ['nom' => 'Résidence Les Oliviers', 'quartier_id' => $centreVille->id],
            [
                'adresse' => '12 Avenue Habib Bourguiba',
                'nombre_logements' => 48,
                'salle_climatisee' => true,
                'point_fraicheur' => true,
            ],
        );

        Residence::updateOrCreate(
            ['nom' => 'Résidence El Manar', 'quartier_id' => $centreVille->id],
            [
                'adresse' => '5 Rue de la Liberté',
                'nombre_logements' => 30,
                'salle_climatisee' => false,
                'point_fraicheur' => false,
            ],
        );

        Residence::updateOrCreate(
            ['nom' => 'Résidence Lac View', 'quartier_id' => $bergesDuLac->id],
            [
                'adresse' => '20 Rue du Lac Léman',
                'nombre_logements' => 60,
                'salle_climatisee' => true,
                'point_fraicheur' => true,
            ],
        );

        $comptes = [
            ['name' => 'Admin ChillNet', 'email' => 'admin@chillnet.test', 'role' => Role::Admin, 'residence_id' => null],
            ['name' => 'Gestionnaire Oliviers', 'email' => 'gestionnaire@chillnet.test', 'role' => Role::Gestionnaire, 'residence_id' => $oliviers->id],
            ['name' => 'Habitant Test', 'email' => 'habitant@chillnet.test', 'role' => Role::Habitant, 'residence_id' => $oliviers->id],
        ];

        foreach ($comptes as $compte) {
            if (User::where('email', $compte['email'])->exists()) {
                continue;
            }

            User::factory()->create($compte);
        }
    }
}
