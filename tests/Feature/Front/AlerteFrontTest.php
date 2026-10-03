<?php

namespace Tests\Feature\Front;

use App\Enums\LieuType;
use App\Enums\NiveauAlerte;
use App\Enums\Role;
use App\Models\Alerte;
use App\Models\Lieu;
use App\Models\Quartier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlerteFrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Aucun appel IA/ météo réel pendant les tests front.
        config(['services.groq.key' => null]);
    }

    private function quartier(string $nom, string $ville = 'Tunis'): Quartier
    {
        return Quartier::create(['nom' => $nom, 'ville' => $ville, 'code_postal' => '1000']);
    }

    private function habitant(): User
    {
        return User::factory()->create([
            'role' => Role::Habitant,
            'profil_vulnerabilites' => ['personne_agee'],
        ]);
    }

    /**
     * Lieu principal du foyer rattaché (en interne) à un quartier.
     */
    private function lieu(User $user, Quartier $quartier, ?float $lat = null, ?float $lng = null): Lieu
    {
        return $user->lieux()->create([
            'nom' => 'Domicile',
            'type' => LieuType::Domicile,
            'latitude' => $lat,
            'longitude' => $lng,
            'quartier_id' => $quartier->id,
            'est_principal' => true,
        ]);
    }

    public function test_a_habitant_sees_the_validated_active_alerts_of_his_lieu(): void
    {
        $centre = $this->quartier('Centre-Ville');
        $habitant = $this->habitant();
        $this->lieu($habitant, $centre);

        Alerte::factory()->validee()->active()->rouge()->create(['titre' => 'Alerte Rouge Centre'])
            ->quartiers()->sync([$centre->id]);

        $this->actingAs($habitant)
            ->get(route('alertes.index'))
            ->assertOk()
            ->assertSee('Alerte Rouge Centre')
            ->assertSee('0800 06 66 66'); // message personnalisé (secours) rendu
    }

    public function test_a_habitant_does_not_see_alerts_from_other_quartiers_or_unvalidated_ones(): void
    {
        $centre = $this->quartier('Centre-Ville');
        $berges = $this->quartier('Les Berges du Lac');
        $habitant = $this->habitant();
        $this->lieu($habitant, $centre);

        // Autre quartier.
        Alerte::factory()->validee()->active()->orange()->create(['titre' => 'Alerte Autre Quartier'])
            ->quartiers()->sync([$berges->id]);

        // Même quartier mais NON validée (brouillon).
        Alerte::factory()->active()->rouge()->create(['titre' => 'Alerte Brouillon Centre', 'validee' => false])
            ->quartiers()->sync([$centre->id]);

        $this->actingAs($habitant)
            ->get(route('alertes.index'))
            ->assertOk()
            ->assertDontSee('Alerte Autre Quartier')
            ->assertDontSee('Alerte Brouillon Centre');
    }

    public function test_the_dashboard_shows_the_active_vigilance_banner(): void
    {
        $centre = $this->quartier('Centre-Ville');
        $habitant = $this->habitant();
        $this->lieu($habitant, $centre);

        Alerte::factory()->validee()->active()->rouge()->create(['titre' => 'Vigilance Rouge Dashboard'])
            ->quartiers()->sync([$centre->id]);

        $this->actingAs($habitant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Vigilance Rouge Dashboard');
    }

    public function test_the_dashboard_shows_an_empty_state_without_active_alert(): void
    {
        $centre = $this->quartier('Centre-Ville');
        $habitant = $this->habitant();
        $this->lieu($habitant, $centre);

        $this->actingAs($habitant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Aucune vigilance en cours');
    }

    public function test_a_manager_cannot_access_the_habitant_alertes_page(): void
    {
        $gestionnaire = User::factory()->create(['role' => Role::Gestionnaire]);

        $this->actingAs($gestionnaire)->get(route('alertes.index'))->assertForbidden();
    }

    public function test_a_manager_is_redirected_from_the_dashboard_to_the_back_office(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertRedirect(route('back.dashboard'));
    }

    public function test_a_habitant_without_lieu_sees_an_invitation_to_create_one(): void
    {
        $habitant = $this->habitant(); // aucun lieu

        $this->actingAs($habitant)
            ->get(route('alertes.index'))
            ->assertOk()
            ->assertSee('Ajoutez un lieu');
    }

    public function test_a_habitant_sees_an_alert_whose_circle_covers_his_lieu(): void
    {
        $centre = Quartier::create([
            'nom' => 'Centre-Ville',
            'ville' => 'Tunis',
            'code_postal' => '1000',
            'latitude' => 36.8008,
            'longitude' => 10.1800,
        ]);
        $habitant = $this->habitant();
        $this->lieu($habitant, $centre, 36.8008, 10.1800);

        // Aucun quartier attaché : la visibilité vient uniquement du cercle.
        Alerte::factory()->validee()->active()->rouge()->geo(36.8008, 10.1800, 1000)
            ->create(['titre' => 'Cercle Centre-Ville']);

        $this->actingAs($habitant)
            ->get(route('alertes.index'))
            ->assertOk()
            ->assertSee('Cercle Centre-Ville');
    }

    public function test_a_habitant_does_not_see_an_alert_whose_circle_is_far_away(): void
    {
        $centre = Quartier::create([
            'nom' => 'Centre-Ville',
            'ville' => 'Tunis',
            'code_postal' => '1000',
            'latitude' => 36.8008,
            'longitude' => 10.1800,
        ]);
        $habitant = $this->habitant();
        $this->lieu($habitant, $centre, 36.8008, 10.1800);

        Alerte::factory()->validee()->active()->orange()->geo(36.9000, 10.3000, 500)
            ->create(['titre' => 'Cercle Lointain']);

        $this->actingAs($habitant)
            ->get(route('alertes.index'))
            ->assertOk()
            ->assertDontSee('Cercle Lointain');
    }
}
