<?php

namespace Tests\Feature\Back;

use App\Enums\NiveauAlerte;
use App\Enums\Role;
use App\Models\Alerte;
use App\Models\Quartier;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AlerteManagementTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(Role $role, ?int $residenceId = null): User
    {
        return User::factory()->create(['role' => $role, 'residence_id' => $residenceId]);
    }

    private function quartier(string $nom = 'Centre-Ville'): Quartier
    {
        return Quartier::create(['nom' => $nom, 'ville' => 'Tunis', 'code_postal' => '1000']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'titre' => 'Vigilance forte chaleur',
            'niveau' => NiveauAlerte::Orange->value,
            'seuil_temperature' => 35,
            'debut' => now()->format('Y-m-d H:i:s'),
            'fin' => now()->addHours(8)->format('Y-m-d H:i:s'),
            'message' => 'Restez hydratés.',
        ], $overrides);
    }

    public function test_admin_can_create_an_alerte_covering_multiple_quartiers(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $a = $this->quartier('Centre-Ville');
        $b = $this->quartier('Les Berges du Lac');

        $this->actingAs($admin)
            ->post(route('back.alertes.store'), $this->payload(['quartier_ids' => [$a->id, $b->id]]))
            ->assertRedirect(route('back.alertes.index'));

        $alerte = Alerte::where('titre', 'Vigilance forte chaleur')->firstOrFail();

        $this->assertSame(2, $alerte->quartiers()->count());
        $this->assertTrue($alerte->quartiers->contains('id', $a->id));
        $this->assertTrue($alerte->quartiers->contains('id', $b->id));
        $this->assertFalse($alerte->validee);
        $this->assertSame($admin->id, $alerte->user_id);
    }

    public function test_updating_an_alerte_syncs_the_quartiers(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $a = $this->quartier('Centre-Ville');
        $b = $this->quartier('Les Berges du Lac');

        $alerte = Alerte::factory()->create();
        $alerte->quartiers()->sync([$a->id, $b->id]);

        $this->actingAs($admin)
            ->put(route('back.alertes.update', $alerte->id), $this->payload(['quartier_ids' => [$a->id]]))
            ->assertRedirect(route('back.alertes.index'));

        $this->assertSame([$a->id], $alerte->fresh()->quartiers()->pluck('quartiers.id')->all());
    }

    public function test_deleting_an_alerte_removes_its_pivot_rows(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $quartier = $this->quartier();

        $alerte = Alerte::factory()->create();
        $alerte->quartiers()->sync([$quartier->id]);

        $this->actingAs($admin)
            ->delete(route('back.alertes.destroy', $alerte->id))
            ->assertRedirect(route('back.alertes.index'));

        $this->assertDatabaseMissing('alertes', ['id' => $alerte->id]);
        $this->assertDatabaseMissing('alerte_quartier', ['alerte_id' => $alerte->id]);
    }

    public function test_a_manager_can_validate_an_alerte(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $alerte = Alerte::factory()->create(['validee' => false]);

        $this->actingAs($admin)
            ->patch(route('back.alertes.valider', $alerte->id))
            ->assertRedirect(route('back.alertes.index'));

        $alerte->refresh();
        $this->assertTrue($alerte->validee);
        $this->assertNotNull($alerte->validee_le);
        $this->assertSame($admin->id, $alerte->validee_par);
    }

    public function test_fin_must_be_after_debut(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $quartier = $this->quartier();

        $this->actingAs($admin)
            ->post(route('back.alertes.store'), $this->payload([
                'quartier_ids' => [$quartier->id],
                'debut' => now()->addDay()->format('Y-m-d H:i:s'),
                'fin' => now()->format('Y-m-d H:i:s'),
            ]))
            ->assertSessionHasErrors('fin');

        $this->assertSame(0, Alerte::count());
    }

    public function test_an_alerte_requires_a_quartier_or_a_map_point(): void
    {
        $admin = $this->userWithRole(Role::Admin);

        // Ni quartier ni coordonnées → erreur.
        $this->actingAs($admin)
            ->post(route('back.alertes.store'), $this->payload(['quartier_ids' => []]))
            ->assertSessionHasErrors('quartier_ids');

        // Quartier inexistant → erreur.
        $this->actingAs($admin)
            ->post(route('back.alertes.store'), $this->payload(['quartier_ids' => [9999]]))
            ->assertSessionHasErrors('quartier_ids.0');

        $this->assertSame(0, Alerte::count());
    }

    public function test_an_alerte_can_be_created_with_only_a_map_point(): void
    {
        $admin = $this->userWithRole(Role::Admin);

        $this->actingAs($admin)
            ->post(route('back.alertes.store'), $this->payload([
                'quartier_ids' => [],
                'latitude' => 36.8008,
                'longitude' => 10.1800,
                'rayon_metres' => 1200,
            ]))
            ->assertRedirect(route('back.alertes.index'));

        $alerte = Alerte::where('titre', 'Vigilance forte chaleur')->firstOrFail();

        $this->assertSame(0, $alerte->quartiers()->count());
        $this->assertTrue($alerte->hasCoordinates());
        $this->assertSame(1200, $alerte->rayonMetres());
        $this->assertTrue($alerte->couvrePoint(36.8008, 10.1800));
    }

    public function test_overlapping_same_level_alerts_on_the_same_circle_are_rejected(): void
    {
        $admin = $this->userWithRole(Role::Admin);

        Alerte::factory()->geo(36.8008, 10.1800, 1500)->create([
            'niveau' => NiveauAlerte::Orange->value,
            'debut' => now()->subHour(),
            'fin' => now()->addHours(5),
        ]);

        $this->actingAs($admin)
            ->post(route('back.alertes.store'), $this->payload([
                'quartier_ids' => [],
                'latitude' => 36.8008,
                'longitude' => 10.1800,
                'rayon_metres' => 1000,
                'niveau' => NiveauAlerte::Orange->value,
                'debut' => now()->format('Y-m-d H:i:s'),
                'fin' => now()->addHours(3)->format('Y-m-d H:i:s'),
            ]))
            ->assertSessionHasErrors('quartier_ids');

        $this->assertSame(1, Alerte::count());
    }

    public function test_niveau_and_seuil_are_validated(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $quartier = $this->quartier();

        $this->actingAs($admin)
            ->post(route('back.alertes.store'), $this->payload([
                'quartier_ids' => [$quartier->id],
                'niveau' => 'violet',
                'seuil_temperature' => 10,
            ]))
            ->assertSessionHasErrors(['niveau', 'seuil_temperature']);
    }

    public function test_prefill_classifies_a_manual_temperature_without_calling_weather(): void
    {
        Http::fake();

        $admin = $this->userWithRole(Role::Admin);
        $quartier = $this->quartier();

        $this->actingAs($admin)
            ->postJson(route('back.alertes.prefill'), [
                'quartier_ids' => [$quartier->id],
                'seuil_temperature' => 35,
                'temperature_actuelle' => 41,
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'niveau' => NiveauAlerte::Rouge->value,
                'source' => 'manuel',
            ]);

        // Override manuel : aucun appel WeatherAPI.
        Http::assertNothingSent();
    }

    public function test_prefill_degrades_gracefully_when_weather_is_unavailable(): void
    {
        config(['services.weather.key' => null]);

        $admin = $this->userWithRole(Role::Admin);
        $quartier = $this->quartier(); // sans coordonnées → météo indisponible

        $this->actingAs($admin)
            ->postJson(route('back.alertes.prefill'), ['quartier_ids' => [$quartier->id]])
            ->assertOk()
            ->assertJson(['ok' => false]);
    }

    public function test_prefill_can_generate_a_personalized_message(): void
    {
        config(['services.groq.key' => null]); // message de secours déterministe

        $admin = $this->userWithRole(Role::Admin);
        $quartier = $this->quartier();

        $this->actingAs($admin)
            ->postJson(route('back.alertes.prefill'), [
                'quartier_ids' => [$quartier->id],
                'seuil_temperature' => 35,
                'temperature_actuelle' => 39,
                'avec_message' => true,
                'profil' => 'personne_agee',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', fn ($message) => is_string($message) && str_contains($message, '0800 06 66 66'));
    }

    public function test_a_gestionnaire_is_forced_to_his_own_quartier(): void
    {
        $sien = $this->quartier('Centre-Ville');
        $autre = $this->quartier('Les Berges du Lac');
        $residence = Residence::create(['nom' => 'Ma résidence', 'adresse' => '1 rue', 'quartier_id' => $sien->id]);
        $gestionnaire = $this->userWithRole(Role::Gestionnaire, $residence->id);

        // Le formulaire trafiqué vise un autre quartier : on garde le sien.
        $this->actingAs($gestionnaire)
            ->post(route('back.alertes.store'), $this->payload(['quartier_ids' => [$autre->id]]))
            ->assertRedirect(route('back.alertes.index'));

        $alerte = Alerte::firstOrFail();
        $this->assertSame([$sien->id], $alerte->quartiers()->pluck('quartiers.id')->all());
    }

    public function test_a_gestionnaire_cannot_touch_an_alerte_outside_his_zone(): void
    {
        $sien = $this->quartier('Centre-Ville');
        $autre = $this->quartier('Les Berges du Lac');
        $residence = Residence::create(['nom' => 'Ma résidence', 'adresse' => '1 rue', 'quartier_id' => $sien->id]);
        $gestionnaire = $this->userWithRole(Role::Gestionnaire, $residence->id);

        $alerteAilleurs = Alerte::factory()->create();
        $alerteAilleurs->quartiers()->sync([$autre->id]);

        $this->actingAs($gestionnaire)->get(route('back.alertes.show', $alerteAilleurs->id))->assertForbidden();
        $this->actingAs($gestionnaire)->get(route('back.alertes.edit', $alerteAilleurs->id))->assertForbidden();
        $this->actingAs($gestionnaire)->delete(route('back.alertes.destroy', $alerteAilleurs->id))->assertForbidden();
        $this->actingAs($gestionnaire)->patch(route('back.alertes.valider', $alerteAilleurs->id))->assertForbidden();

        $this->assertDatabaseHas('alertes', ['id' => $alerteAilleurs->id]);
    }

    public function test_an_habitant_cannot_access_the_alertes_back_office(): void
    {
        $habitant = $this->userWithRole(Role::Habitant);
        $quartier = $this->quartier();

        $this->actingAs($habitant)->get(route('back.alertes.index'))->assertForbidden();
        $this->actingAs($habitant)
            ->post(route('back.alertes.store'), $this->payload(['quartier_ids' => [$quartier->id]]))
            ->assertForbidden();

        $this->assertSame(0, Alerte::count());
    }

    public function test_the_alertes_back_office_screens_render(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $quartier = $this->quartier();

        $alerte = Alerte::factory()->create(['titre' => 'Alerte écran']);
        $alerte->quartiers()->sync([$quartier->id]);

        $this->actingAs($admin)->get(route('back.alertes.index'))->assertOk()->assertSee('Alerte écran');
        $this->actingAs($admin)->get(route('back.alertes.create'))->assertOk()->assertSee('Assistant IA canicule');
        $this->actingAs($admin)->get(route('back.alertes.edit', $alerte->id))->assertOk()->assertSee('Alerte écran');
        $this->actingAs($admin)->get(route('back.alertes.show', $alerte->id))->assertOk()->assertSee('Alerte écran')->assertSee('Centre-Ville');

        // Chaque mode de tri doit rendre sans erreur.
        foreach (['recent', 'ancien', 'niveau', 'statut', 'zone'] as $tri) {
            $this->actingAs($admin)
                ->get(route('back.alertes.index', ['tri' => $tri]))
                ->assertOk();
        }
    }

    public function test_overlapping_same_level_alerts_on_a_quartier_are_rejected(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $quartier = $this->quartier();

        Alerte::factory()->create([
            'niveau' => NiveauAlerte::Orange->value,
            'debut' => now()->subHour(),
            'fin' => now()->addHours(5),
        ])->quartiers()->sync([$quartier->id]);

        $this->actingAs($admin)
            ->post(route('back.alertes.store'), $this->payload([
                'quartier_ids' => [$quartier->id],
                'niveau' => NiveauAlerte::Orange->value,
                'debut' => now()->format('Y-m-d H:i:s'),
                'fin' => now()->addHours(3)->format('Y-m-d H:i:s'),
            ]))
            ->assertSessionHasErrors('quartier_ids');

        $this->assertSame(1, Alerte::count());
    }
}
