<?php

namespace Tests\Feature\Back;

use App\Enums\Role;
use App\Models\Coupure;
use App\Models\Quartier;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Back-office des coupures (module 2) : le gestionnaire / admin peuvent poser
 * un point n'importe où sur la carte, sans dépendre d'un quartier existant.
 */
class CoupureManagementTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(Role $role, ?int $residenceId = null): User
    {
        return User::factory()->create(['role' => $role, 'residence_id' => $residenceId]);
    }

    private function quartier(string $nom, ?float $lat = null, ?float $lng = null): Quartier
    {
        return Quartier::create([
            'nom' => $nom,
            'ville' => 'Tunis',
            'code_postal' => '1000',
            'latitude' => $lat,
            'longitude' => $lng,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'maintenance',
            'statut' => 'prevue',
            'debut' => now()->format('Y-m-d H:i:s'),
            'fin' => now()->addHours(4)->format('Y-m-d H:i:s'),
            'description' => 'Maintenance planifiée.',
        ], $overrides);
    }

    public function test_admin_can_create_a_coupure_from_a_map_point_without_choosing_a_quartier(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $this->quartier('Centre-Ville', 36.8008, 10.1800);

        $this->actingAs($admin)
            ->post(route('back.coupures.store'), $this->payload([
                'latitude' => 36.8012,
                'longitude' => 10.1815,
                'lieu' => 'rue des Lilas',
            ]))
            ->assertRedirect(route('back.coupures.index'));

        $coupure = Coupure::firstOrFail();

        $this->assertTrue($coupure->hasCoordinates());
        $this->assertEqualsWithDelta(36.8012, $coupure->latitude, 0.0000001);
        $this->assertEqualsWithDelta(10.1815, $coupure->longitude, 0.0000001);
        // Le quartier le plus proche est déduit du point (interne).
        $this->assertNotNull($coupure->quartier_id);
    }

    public function test_a_coupure_can_be_created_without_any_quartier_when_none_is_geolocated(): void
    {
        $admin = $this->userWithRole(Role::Admin);

        // Aucun quartier géolocalisé : le point suffit, le quartier reste nul.
        $this->quartier('Centre-Ville');

        $this->actingAs($admin)
            ->post(route('back.coupures.store'), $this->payload([
                'latitude' => 36.8012,
                'longitude' => 10.1815,
            ]))
            ->assertRedirect(route('back.coupures.index'));

        $coupure = Coupure::firstOrFail();

        $this->assertTrue($coupure->hasCoordinates());
        $this->assertNull($coupure->quartier_id);
    }

    public function test_a_coupure_requires_a_target_quartier_or_point(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $this->quartier('Centre-Ville');

        $this->actingAs($admin)
            ->post(route('back.coupures.store'), $this->payload())
            ->assertSessionHasErrors('quartier_id');

        $this->assertSame(0, Coupure::count());
    }

    public function test_an_empty_form_reports_every_missing_required_field(): void
    {
        $admin = $this->userWithRole(Role::Admin);

        $this->actingAs($admin)
            ->post(route('back.coupures.store'), [])
            ->assertSessionHasErrors(['type', 'statut', 'debut', 'quartier_id']);

        $this->assertSame(0, Coupure::count());
    }

    public function test_the_create_form_offers_a_placeholder_for_type_and_statut(): void
    {
        $admin = $this->userWithRole(Role::Admin);

        $this->actingAs($admin)
            ->get(route('back.coupures.create'))
            ->assertOk()
            ->assertSee('— Choisir un type —')
            ->assertSee('— Choisir un statut —');
    }

    public function test_the_create_form_wires_the_address_and_quartier_autofill(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $this->quartier('Centre-Ville', 36.8008, 10.1800);

        $this->actingAs($admin)
            ->get(route('back.coupures.create'))
            ->assertOk()
            ->assertSee('remplirDepuisPoint')
            ->assertSee('bloc-nouveau-quartier')
            ->assertSee('ce nouveau quartier sera créé automatiquement');
    }

    public function test_the_new_quartier_block_is_not_offered_to_a_gestionnaire(): void
    {
        $quartier = $this->quartier('Ma Zone', 36.8008, 10.1800);
        $residence = Residence::create([
            'nom' => 'Résidence gestionnaire',
            'quartier_id' => $quartier->id,
            'adresse' => '1 rue de la Zone',
        ]);
        $gestionnaire = $this->userWithRole(Role::Gestionnaire, $residence->id);

        $this->actingAs($gestionnaire)
            ->get(route('back.coupures.create'))
            ->assertOk()
            ->assertDontSee('ce nouveau quartier sera créé automatiquement');
    }

    public function test_a_resolue_coupure_requires_an_end_time(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $this->quartier('Centre-Ville', 36.8008, 10.1800);

        $this->actingAs($admin)
            ->post(route('back.coupures.store'), $this->payload([
                'statut' => 'resolue',
                'fin' => null,
                'latitude' => 36.8012,
                'longitude' => 10.1815,
            ]))
            ->assertSessionHasErrors('fin');

        $this->assertSame(0, Coupure::count());
    }

    public function test_a_resolue_coupure_is_accepted_with_an_end_time(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $this->quartier('Centre-Ville', 36.8008, 10.1800);

        $this->actingAs($admin)
            ->post(route('back.coupures.store'), $this->payload([
                'statut' => 'resolue',
                'latitude' => 36.8012,
                'longitude' => 10.1815,
            ]))
            ->assertRedirect(route('back.coupures.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('resolue', Coupure::firstOrFail()->statut->value);
    }

    public function test_a_coupure_creates_the_proposed_quartier_when_the_point_is_far_from_any_zone(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $this->quartier('Centre-Ville', 36.8008, 10.1800);

        $this->actingAs($admin)
            ->post(route('back.coupures.store'), $this->payload([
                'latitude' => 37.5000000,
                'longitude' => 10.9000000,
                'nouveau_quartier_nom' => 'Sidi Bou',
                'nouveau_quartier_ville' => 'Bizerte',
                'nouveau_quartier_code_postal' => '7000',
            ]))
            ->assertRedirect(route('back.coupures.index'))
            ->assertSessionHasNoErrors();

        $coupure = Coupure::firstOrFail();

        $this->assertSame('Sidi Bou', $coupure->quartier->nom);
        $this->assertDatabaseHas('quartiers', ['nom' => 'Sidi Bou', 'ville' => 'Bizerte', 'code_postal' => '7000']);
    }

    public function test_the_proposed_quartier_is_reused_instead_of_duplicated(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $this->quartier('Centre-Ville', 36.8008, 10.1800);

        $cible = [
            'latitude' => 37.5000000,
            'longitude' => 10.9000000,
            'nouveau_quartier_nom' => 'Sidi Bou',
            'nouveau_quartier_ville' => 'Bizerte',
            'nouveau_quartier_code_postal' => '7000',
        ];

        $this->actingAs($admin)
            ->post(route('back.coupures.store'), $this->payload($cible))
            ->assertRedirect(route('back.coupures.index'));

        // Deuxième coupure sur le même point mais un autre créneau (pas de chevauchement).
        $this->actingAs($admin)
            ->post(route('back.coupures.store'), $this->payload(array_merge($cible, [
                'debut' => now()->addDay()->format('Y-m-d H:i:s'),
                'fin' => now()->addDay()->addHours(4)->format('Y-m-d H:i:s'),
            ])))
            ->assertRedirect(route('back.coupures.index'));

        $this->assertSame(1, Quartier::where('nom', 'Sidi Bou')->count());
        $this->assertSame(2, Coupure::count());
    }

    public function test_a_gestionnaire_coupure_is_forced_to_his_zone(): void
    {
        $quartier = $this->quartier('Ma Zone', 36.8008, 10.1800);
        $autre = $this->quartier('Ailleurs', 36.9000, 10.3000);
        $residence = Residence::create([
            'nom' => 'Résidence gestionnaire',
            'quartier_id' => $quartier->id,
            'adresse' => '1 rue de la Zone',
        ]);
        $gestionnaire = $this->userWithRole(Role::Gestionnaire, $residence->id);

        $this->actingAs($gestionnaire)
            ->post(route('back.coupures.store'), $this->payload([
                'quartier_id' => $autre->id,
                'latitude' => 36.9005,
                'longitude' => 10.3005,
            ]))
            ->assertRedirect(route('back.coupures.index'));

        $this->assertSame($quartier->id, Coupure::firstOrFail()->quartier_id);
    }

    public function test_the_create_form_renders_the_map(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $this->quartier('Centre-Ville', 36.8008, 10.1800);

        $this->actingAs($admin)
            ->get(route('back.coupures.create'))
            ->assertOk()
            ->assertSee('carte-coupure-form')
            ->assertSee('Point sur la carte');
    }

    public function test_the_edit_form_renders_for_a_coupure_linked_to_a_quartier(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $quartier = $this->quartier('Centre-Ville', 36.8008, 10.1800);

        $coupure = Coupure::create([
            'quartier_id' => $quartier->id,
            'type' => 'panne',
            'statut' => 'en_cours',
            'debut' => now(),
            'latitude' => 36.8012,
            'longitude' => 10.1815,
            'lieu' => 'rue des Lilas',
        ]);

        $this->actingAs($admin)
            ->get(route('back.coupures.edit', $coupure))
            ->assertOk()
            ->assertSee('Centre-Ville')
            ->assertSee('rue des Lilas');
    }

    public function test_the_edit_form_renders_for_a_point_only_coupure_without_a_quartier(): void
    {
        $admin = $this->userWithRole(Role::Admin);

        $coupure = Coupure::create([
            'quartier_id' => null,
            'type' => 'panne',
            'statut' => 'en_cours',
            'debut' => now(),
            'latitude' => 36.8012,
            'longitude' => 10.1815,
        ]);

        $this->actingAs($admin)
            ->get(route('back.coupures.edit', $coupure))
            ->assertOk()
            ->assertSee('Point sur la carte');
    }

    public function test_the_show_page_renders_the_coupure_detail(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $quartier = $this->quartier('Centre-Ville', 36.8008, 10.1800);

        $coupure = Coupure::create([
            'quartier_id' => $quartier->id,
            'type' => 'panne',
            'statut' => 'en_cours',
            'debut' => now(),
            'latitude' => 36.8012,
            'longitude' => 10.1815,
            'lieu' => 'rue des Lilas',
            'description' => 'Message de test détail.',
        ]);

        $this->actingAs($admin)
            ->get(route('back.coupures.show', $coupure))
            ->assertOk()
            ->assertSee('Centre-Ville')
            ->assertSee('rue des Lilas')
            ->assertSee('Message de test détail.');
    }

    public function test_a_gestionnaire_cannot_view_a_coupure_outside_his_zone(): void
    {
        $sien = $this->quartier('Ma Zone', 36.8008, 10.1800);
        $autre = $this->quartier('Ailleurs', 36.9000, 10.3000);
        $residence = Residence::create([
            'nom' => 'Résidence gestionnaire',
            'quartier_id' => $sien->id,
            'adresse' => '1 rue de la Zone',
        ]);
        $gestionnaire = $this->userWithRole(Role::Gestionnaire, $residence->id);

        $coupureAilleurs = Coupure::create([
            'quartier_id' => $autre->id,
            'type' => 'panne',
            'statut' => 'en_cours',
            'debut' => now(),
        ]);

        $this->actingAs($gestionnaire)->get(route('back.coupures.show', $coupureAilleurs->id))->assertForbidden();
    }
}
