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
}
