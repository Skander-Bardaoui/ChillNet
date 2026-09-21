<?php

namespace Database\Seeders;

use App\Entities\Quartier;
use App\Entities\Residence;
use App\Enums\Role;
use App\Models\User;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(EntityManagerInterface $em): void
    {
        $quartierCentre = new Quartier();
        $quartierCentre->setNom('Centre-Ville')
            ->setVille('Tunis')
            ->setCodePostal('1000')
            ->setDescription('Quartier central, forte densité de population.');

        $quartierLac = new Quartier();
        $quartierLac->setNom('Les Berges du Lac')
            ->setVille('Tunis')
            ->setCodePostal('1053')
            ->setDescription('Quartier résidentiel et d\'affaires.');

        $em->persist($quartierCentre);
        $em->persist($quartierLac);
        $em->flush();

        $residenceA = new Residence();
        $residenceA->setNom('Résidence Les Oliviers')
            ->setAdresse('12 Avenue Habib Bourguiba')
            ->setNombreLogements(48)
            ->setSalleClimatisee(true)
            ->setPointFraicheur(true)
            ->setQuartier($quartierCentre);

        $residenceB = new Residence();
        $residenceB->setNom('Résidence El Manar')
            ->setAdresse('5 Rue de la Liberté')
            ->setNombreLogements(30)
            ->setSalleClimatisee(false)
            ->setPointFraicheur(false)
            ->setQuartier($quartierCentre);

        $residenceC = new Residence();
        $residenceC->setNom('Résidence Lac View')
            ->setAdresse('20 Rue du Lac Léman')
            ->setNombreLogements(60)
            ->setSalleClimatisee(true)
            ->setPointFraicheur(true)
            ->setQuartier($quartierLac);

        $em->persist($residenceA);
        $em->persist($residenceB);
        $em->persist($residenceC);
        $em->flush();

        User::factory()->create([
            'name' => 'Admin ChillNet',
            'email' => 'admin@chillnet.test',
            'role' => Role::Admin,
        ]);

        User::factory()->create([
            'name' => 'Gestionnaire Oliviers',
            'email' => 'gestionnaire@chillnet.test',
            'role' => Role::Gestionnaire,
            'residence_id' => $residenceA->getId(),
        ]);

        User::factory()->create([
            'name' => 'Habitant Test',
            'email' => 'habitant@chillnet.test',
            'role' => Role::Habitant,
            'residence_id' => $residenceA->getId(),
        ]);
    }
}
