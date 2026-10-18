<?php

namespace Tests\Feature\Front;

use App\Enums\LieuType;
use App\Enums\Role;
use App\Enums\StatutCoupure;
use App\Models\Coupure;
use App\Models\Quartier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bandeau « Mon foyer » (module 2, front) : l'habitant connecté voit si un de
 * ses lieux personnels est touché par une coupure en cours.
 */
class CoupureFoyerStatusTest extends TestCase
{
    use RefreshDatabase;

    private function quartier(): Quartier
    {
        return Quartier::create([
            'nom' => 'Centre-Ville',
            'ville' => 'Tunis',
            'code_postal' => '1000',
            'latitude' => 36.8008,
            'longitude' => 10.1800,
        ]);
    }

    /**
     * @param  array<string, mixed>  $lieuData
     * @return array{0: User, 1: \App\Models\Lieu}
     */
    private function habitantAvecLieu(array $lieuData = []): array
    {
        $user = User::factory()->create(['role' => Role::Habitant]);

        $lieu = $user->lieux()->create(array_merge([
            'nom' => 'Domicile',
            'type' => LieuType::Domicile,
            'adresse' => '12 Avenue Habib Bourguiba',
            'latitude' => 36.8008,
            'longitude' => 10.1800,
            'est_principal' => true,
        ], $lieuData));

        return [$user, $lieu];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function coupure(array $data = []): Coupure
    {
        return Coupure::create(array_merge([
            'type' => 'panne',
            'statut' => StatutCoupure::EnCours,
            'debut' => now()->subHour(),
            'description' => 'Coupure de test',
        ], $data));
    }

    public function test_the_household_banner_flags_a_lieu_hit_by_an_ongoing_coupure(): void
    {
        $quartier = $this->quartier();
        [$user] = $this->habitantAvecLieu(['quartier_id' => $quartier->id]);
        $this->coupure(['quartier_id' => $quartier->id]);

        $this->actingAs($user)
            ->get(route('coupures.index'))
            ->assertOk()
            ->assertSee('Mon foyer')
            ->assertSee('Domicile')
            ->assertSee('Coupé');
    }

    public function test_the_household_banner_says_all_is_ok_without_a_matching_coupure(): void
    {
        $quartier = $this->quartier();
        [$user] = $this->habitantAvecLieu(['quartier_id' => $quartier->id]);

        $this->actingAs($user)
            ->get(route('coupures.index'))
            ->assertOk()
            ->assertSee('Mon foyer')
            ->assertSee("Aucun de vos lieux");
    }

    public function test_the_household_banner_flags_a_nearby_point_coupure(): void
    {
        // Ni le lieu ni la coupure n'ont de quartier : seule la distance compte.
        [$user] = $this->habitantAvecLieu(['latitude' => 36.8008, 'longitude' => 10.1800]);
        $this->coupure(['latitude' => 36.8030, 'longitude' => 10.1800]);

        $this->actingAs($user)
            ->get(route('coupures.index'))
            ->assertOk()
            ->assertSee('Coupé');
    }

    public function test_the_household_banner_ignores_a_resolved_coupure(): void
    {
        $quartier = $this->quartier();
        [$user] = $this->habitantAvecLieu(['quartier_id' => $quartier->id]);
        $this->coupure([
            'quartier_id' => $quartier->id,
            'statut' => StatutCoupure::Resolue,
            'fin' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('coupures.index'))
            ->assertOk()
            ->assertSee("Aucun de vos lieux");
    }

    public function test_the_household_banner_is_hidden_from_anonymous_visitors(): void
    {
        $this->quartier();

        $this->get(route('coupures.index'))
            ->assertOk()
            ->assertDontSee('Mon foyer');
    }

    public function test_the_household_banner_is_hidden_from_a_gestionnaire(): void
    {
        $gestionnaire = User::factory()->create(['role' => Role::Gestionnaire]);

        $this->actingAs($gestionnaire)
            ->get(route('coupures.index'))
            ->assertOk()
            ->assertDontSee('Mon foyer');
    }
}