<?php

namespace Tests\Feature\Front;

use App\Enums\Role;
use App\Models\Coupure;
use App\Models\Quartier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Page publique des coupures : le score de risque IA par zone et la bannière
 * d'anomalie (afflux de signalements) sont visibles par tous.
 */
class CoupureRiskTest extends TestCase
{
    use RefreshDatabase;

    private function quartier(string $nom): Quartier
    {
        return Quartier::create([
            'nom' => $nom,
            'ville' => 'Tunis',
            'code_postal' => '1000',
        ]);
    }

    public function test_the_public_page_shows_the_ia_risk_panel(): void
    {
        $this->quartier('Centre-Ville');

        $this->get(route('coupures.index'))
            ->assertOk()
            ->assertSee('Risque de coupure par zone')
            ->assertSee('Centre-Ville');
    }

    public function test_the_public_page_flags_a_signalement_burst(): void
    {
        $quartier = $this->quartier('Incident');

        Coupure::factory()->count(3)->create([
            'quartier_id' => $quartier->id,
            'statut' => 'en_cours',
            'debut' => now()->subMinutes(10),
        ]);

        $this->get(route('coupures.index'))
            ->assertOk()
            ->assertSee('Anomalie détectée par')
            ->assertSee('possible incident majeur non déclaré');
    }

    public function test_the_back_office_page_shows_the_risk_panel(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->quartier('Centre-Ville');

        $this->actingAs($admin)
            ->get(route('back.coupures.index'))
            ->assertOk()
            ->assertSee('Risque de coupure par zone');
    }
}
