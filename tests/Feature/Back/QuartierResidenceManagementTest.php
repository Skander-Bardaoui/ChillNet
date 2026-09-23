<?php

namespace Tests\Feature\Back;

use App\Enums\Role;
use App\Models\Quartier;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuartierResidenceManagementTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(Role $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_admin_can_create_update_and_delete_a_quartier(): void
    {
        $admin = $this->userWithRole(Role::Admin);

        $this->actingAs($admin)
            ->post(route('back.quartiers.store'), [
                'nom' => 'Centre-Ville',
                'ville' => 'Tunis',
                'code_postal' => '1000',
                'description' => 'Quartier central.',
            ])
            ->assertRedirect(route('back.quartiers.index'));

        $quartier = Quartier::where('nom', 'Centre-Ville')->firstOrFail();
        $this->assertSame('Tunis', $quartier->ville);

        $this->actingAs($admin)
            ->put(route('back.quartiers.update', $quartier->id), [
                'nom' => 'Centre-Ville Nord',
                'ville' => 'Tunis',
                'code_postal' => '1001',
            ])
            ->assertRedirect(route('back.quartiers.index'));

        $this->assertDatabaseHas('quartiers', ['id' => $quartier->id, 'nom' => 'Centre-Ville Nord']);

        $this->actingAs($admin)
            ->delete(route('back.quartiers.destroy', $quartier->id))
            ->assertRedirect(route('back.quartiers.index'));

        $this->assertDatabaseMissing('quartiers', ['id' => $quartier->id]);
    }

    public function test_a_quartier_holding_residences_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $quartier = Quartier::create(['nom' => 'Centre-Ville', 'ville' => 'Tunis', 'code_postal' => '1000']);
        Residence::create([
            'nom' => 'Résidence Les Oliviers',
            'adresse' => '12 Avenue Habib Bourguiba',
            'nombre_logements' => 48,
            'quartier_id' => $quartier->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('back.quartiers.destroy', $quartier->id))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('quartiers', ['id' => $quartier->id]);
    }

    public function test_gestionnaire_can_create_a_residence_and_unchecked_options_are_false(): void
    {
        $gestionnaire = $this->userWithRole(Role::Gestionnaire);
        $quartier = Quartier::create(['nom' => 'Les Berges du Lac', 'ville' => 'Tunis', 'code_postal' => '1053']);

        $this->actingAs($gestionnaire)
            ->post(route('back.residences.store'), [
                'nom' => 'Résidence Lac View',
                'adresse' => '20 Rue du Lac Léman',
                'quartier_id' => $quartier->id,
                'nombre_logements' => 60,
                'salle_climatisee' => '1',
                // point_fraicheur volontairement absent (case décochée)
            ])
            ->assertRedirect(route('back.residences.index'));

        $residence = Residence::where('nom', 'Résidence Lac View')->firstOrFail();

        $this->assertSame($quartier->id, $residence->quartier_id);
        $this->assertTrue($residence->salle_climatisee);
        $this->assertFalse($residence->point_fraicheur);
        $this->assertSame(60, $residence->nombre_logements);
    }

    public function test_a_residence_requires_an_existing_quartier(): void
    {
        $this->actingAs($this->userWithRole(Role::Gestionnaire))
            ->post(route('back.residences.store'), [
                'nom' => 'Résidence Fantôme',
                'adresse' => '1 Rue Inexistante',
                'quartier_id' => 9999,
                'nombre_logements' => 10,
            ])
            ->assertSessionHasErrors('quartier_id');
    }

    public function test_habitant_cannot_access_the_back_office(): void
    {
        $this->actingAs($this->userWithRole(Role::Habitant))
            ->get(route('back.dashboard'))
            ->assertForbidden();
    }

    public function test_the_back_office_screens_render(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $quartier = Quartier::create(['nom' => 'Centre-Ville', 'ville' => 'Tunis', 'code_postal' => '1000']);
        $residence = Residence::create([
            'nom' => 'Résidence Les Oliviers',
            'adresse' => '12 Avenue Habib Bourguiba',
            'nombre_logements' => 48,
            'salle_climatisee' => true,
            'point_fraicheur' => true,
            'quartier_id' => $quartier->id,
        ]);

        $this->actingAs($admin)->get(route('back.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('back.quartiers.index'))->assertOk()->assertSee($quartier->nom);
        $this->actingAs($admin)->get(route('back.quartiers.create'))->assertOk();
        $this->actingAs($admin)->get(route('back.quartiers.edit', $quartier->id))->assertOk();
        $this->actingAs($admin)->get(route('back.residences.index'))->assertOk()->assertSee($residence->nom);
        $this->actingAs($admin)->get(route('back.residences.create'))->assertOk();
        $this->actingAs($admin)->get(route('back.residences.edit', $residence->id))->assertOk();
    }

    public function test_the_database_seeder_builds_the_network_and_is_replayable(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(2, Quartier::count());
        $this->assertSame(3, Residence::count());
        $this->assertSame(3, User::count());
        $this->assertSame(2, Residence::pointFraicheur()->count());
        $this->assertSame(1, User::where('role', Role::Admin->value)->count());
    }
}
