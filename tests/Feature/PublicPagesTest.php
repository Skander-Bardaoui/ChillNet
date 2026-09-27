<?php

namespace Tests\Feature;

use App\Models\Quartier;
use App\Models\Residence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    private function network(): array
    {
        $quartier = Quartier::create([
            'nom' => 'Centre-Ville',
            'ville' => 'Tunis',
            'code_postal' => '1000',
            'description' => 'Quartier central.',
        ]);

        $residence = Residence::create([
            'nom' => 'Résidence Les Oliviers',
            'adresse' => '12 Avenue Habib Bourguiba',
            'nombre_logements' => 48,
            'salle_climatisee' => true,
            'point_fraicheur' => true,
            'quartier_id' => $quartier->id,
        ]);

        return [$quartier, $residence];
    }

    public function test_the_landing_page_lists_quartiers_and_cooling_points(): void
    {
        [$quartier, $residence] = $this->network();

        $this->get('/')
            ->assertOk()
            ->assertSee($quartier->nom)
            ->assertSee($residence->nom)
            ->assertSee($residence->adresse);
    }

    public function test_a_quartier_page_lists_its_residences(): void
    {
        [$quartier, $residence] = $this->network();

        $this->get(route('quartiers.show', $quartier->id))
            ->assertOk()
            ->assertSee($quartier->nom)
            ->assertSee($residence->nom);
    }

    public function test_the_registration_form_lists_the_residences(): void
    {
        [, $residence] = $this->network();

        $this->get('/register')
            ->assertOk()
            ->assertSee($residence->nom);
    }
}
