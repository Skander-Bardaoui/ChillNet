<?php

namespace Tests\Feature;

use App\Models\Quartier;
use App\Models\Residence;
use App\Models\Signalement;
use App\Models\User;
use Database\Seeders\SignalementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignalementSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_signalement_factory_creates_a_signalement_with_its_relations(): void
    {
        $signalement = Signalement::factory()->create();

        $this->assertNotNull($signalement->habitant);
        $this->assertNotNull($signalement->residence);
        $this->assertNotNull($signalement->residence->quartier);
    }

    public function test_signalement_seeder_is_repeatable(): void
    {
        $quartier = Quartier::create([
            'nom' => 'Centre-Ville',
            'ville' => 'Tunis',
            'code_postal' => '1000',
        ]);
        Residence::create([
            'nom' => 'Résidence El Manar',
            'adresse' => '5 Rue de la Liberté',
            'quartier_id' => $quartier->id,
        ]);
        User::factory()->create([
            'name' => 'Habitant Test',
            'email' => 'habitant@chillnet.test',
        ]);

        $this->seed(SignalementSeeder::class);
        $this->seed(SignalementSeeder::class);

        $this->assertSame(3, Signalement::count());
        $this->assertDatabaseHas('signalements', ['categorie' => 'fuite', 'statut' => 'nouveau']);
        $this->assertDatabaseHas('signalements', ['categorie' => 'panne_locale', 'statut' => 'en_traitement']);
        $this->assertDatabaseHas('signalements', ['categorie' => 'personne_vulnerable', 'statut' => 'resolu']);
    }
}
