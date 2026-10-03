<?php

namespace Tests\Feature\Front;

use App\Enums\Role;
use App\Models\Lieu;
use App\Models\Quartier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LieuManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        // Aucun appel IA / météo réel par défaut.
        config(['services.groq.key' => null, 'services.weather.key' => null]);
    }

    private function habitant(): User
    {
        return User::factory()->create(['role' => Role::Habitant]);
    }

    private function lieu(User $user, array $overrides = []): Lieu
    {
        return $user->lieux()->create(array_merge([
            'nom' => 'Domicile',
            'type' => 'domicile',
            'latitude' => 36.8008,
            'longitude' => 10.1800,
            'est_principal' => true,
        ], $overrides));
    }

    public function test_the_lieux_screen_can_be_rendered(): void
    {
        $habitant = $this->habitant();
        $this->lieu($habitant);

        $this->actingAs($habitant)
            ->get(route('lieux.index'))
            ->assertOk()
            ->assertSee('Mes lieux')
            ->assertSee('Domicile');
    }

    public function test_the_lieux_screen_shows_the_local_weather_and_an_ai_advice(): void
    {
        config(['services.weather.key' => 'fake-key']);
        Http::fake([
            'api.weatherapi.com/*' => Http::response([
                'location' => ['name' => 'Tunis', 'tz_id' => 'Africa/Tunis'],
                'current' => [
                    'temp_c' => 38.4,
                    'feelslike_c' => 40.1,
                    'humidity' => 42,
                    'wind_kph' => 11,
                    'condition' => ['text' => 'Ensoleillé'],
                ],
            ]),
        ]);

        $habitant = $this->habitant();
        $this->lieu($habitant);

        $this->actingAs($habitant)
            ->get(route('lieux.index'))
            ->assertOk()
            ->assertSee('Conseil IA')
            ->assertSee('38,4')
            ->assertSee('Humidité 42%')
            ->assertSee('Ensoleillé');
    }

    public function test_the_lieux_screen_degrades_gracefully_when_the_weather_is_unavailable(): void
    {
        $habitant = $this->habitant();
        $this->lieu($habitant);

        $this->actingAs($habitant)
            ->get(route('lieux.index'))
            ->assertOk()
            ->assertSee('Météo indisponible');
    }

    public function test_a_habitant_can_add_a_lieu(): void
    {
        $habitant = $this->habitant();

        $this->actingAs($habitant)
            ->post(route('lieux.store'), [
                'nom' => 'Travail',
                'type' => 'travail',
                'adresse' => '10 avenue de la République',
                'latitude' => 36.8325,
                'longitude' => 10.2800,
            ])
            ->assertRedirect(route('lieux.index'));

        $this->assertSame(1, $habitant->lieux()->count());
        $lieu = $habitant->lieux()->first();
        $this->assertSame('Travail', $lieu->nom);
        // Le premier lieu du foyer devient d'office principal.
        $this->assertTrue($lieu->est_principal);
    }

    public function test_a_lieu_requires_a_point(): void
    {
        $habitant = $this->habitant();

        $this->actingAs($habitant)
            ->post(route('lieux.store'), ['nom' => 'Sans point', 'type' => 'autre'])
            ->assertSessionHasErrors(['latitude', 'longitude']);

        $this->assertSame(0, $habitant->lieux()->count());
    }

    public function test_a_habitant_can_update_his_lieu(): void
    {
        $habitant = $this->habitant();
        $lieu = $this->lieu($habitant);

        $this->actingAs($habitant)
            ->patch(route('lieux.update', $lieu), [
                'nom' => 'Maison',
                'type' => 'domicile',
                'adresse' => 'Nouvelle adresse',
                'latitude' => 36.8100,
                'longitude' => 10.1900,
            ])
            ->assertRedirect(route('lieux.index'));

        $this->assertSame('Maison', $lieu->fresh()->nom);
    }

    public function test_a_habitant_cannot_touch_another_households_lieu(): void
    {
        $autre = $this->habitant();
        $lieu = $this->lieu($autre);

        $intrus = $this->habitant();

        $this->actingAs($intrus)
            ->patch(route('lieux.update', $lieu), [
                'nom' => 'Piraté',
                'type' => 'autre',
                'latitude' => 36.0,
                'longitude' => 10.0,
            ])
            ->assertForbidden();

        $this->actingAs($intrus)
            ->delete(route('lieux.destroy', $lieu))
            ->assertForbidden();
    }

    public function test_the_last_lieu_cannot_be_deleted(): void
    {
        $habitant = $this->habitant();
        $lieu = $this->lieu($habitant);

        $this->actingAs($habitant)
            ->delete(route('lieux.destroy', $lieu))
            ->assertRedirect();

        $this->assertSame(1, $habitant->lieux()->count());
    }

    public function test_deleting_the_principal_promotes_another_lieu(): void
    {
        $habitant = $this->habitant();
        $principal = $this->lieu($habitant, ['nom' => 'Domicile', 'est_principal' => true]);
        $secondaire = $this->lieu($habitant, ['nom' => 'Travail', 'est_principal' => false]);

        $this->actingAs($habitant)
            ->delete(route('lieux.destroy', $principal))
            ->assertRedirect(route('lieux.index'));

        $this->assertSame(1, $habitant->lieux()->count());
        $this->assertTrue($secondaire->fresh()->est_principal);
    }

    public function test_only_one_lieu_is_principal_at_a_time(): void
    {
        $habitant = $this->habitant();
        $this->lieu($habitant, ['nom' => 'Domicile', 'est_principal' => true]);
        $travail = $this->lieu($habitant, ['nom' => 'Travail', 'est_principal' => false]);

        $this->actingAs($habitant)
            ->patch(route('lieux.principal', $travail))
            ->assertRedirect(route('lieux.index'));

        $this->assertSame(1, $habitant->lieux()->where('est_principal', true)->count());
        $this->assertTrue($travail->fresh()->est_principal);
    }

    public function test_a_lieu_is_linked_to_the_nearest_quartier_internally(): void
    {
        $quartier = Quartier::create([
            'nom' => 'Centre-Ville', 'ville' => 'Tunis',
            'latitude' => 36.8008, 'longitude' => 10.1800,
        ]);

        $habitant = $this->habitant();
        $lieu = $this->lieu($habitant, ['latitude' => 36.8010, 'longitude' => 10.1805]);

        $this->assertSame($quartier->id, $lieu->quartier_id);
    }
}
