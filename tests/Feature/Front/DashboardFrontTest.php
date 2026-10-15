<?php

namespace Tests\Feature\Front;

use App\Enums\LieuType;
use App\Enums\Role;
use App\Models\Lieu;
use App\Models\Quartier;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardFrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        // Aucun appel IA / météo réel par défaut.
        config(['services.groq.key' => null, 'services.weather.key' => null]);
    }

    private function quartier(?float $lat = null, ?float $lng = null): Quartier
    {
        return Quartier::create([
            'nom' => 'Centre-Ville',
            'ville' => 'Tunis',
            'code_postal' => '1000',
            'latitude' => $lat,
            'longitude' => $lng,
        ]);
    }

    private function habitant(): User
    {
        return User::factory()->create(['role' => Role::Habitant]);
    }

    /**
     * Lieu principal du foyer : c'est lui qui porte le point de référence.
     */
    private function lieu(User $user, ?float $lat = null, ?float $lng = null, ?Quartier $quartier = null): Lieu
    {
        return $user->lieux()->create([
            'nom' => 'Domicile',
            'type' => LieuType::Domicile,
            'adresse' => '12 Avenue Habib Bourguiba',
            'latitude' => $lat,
            'longitude' => $lng,
            'quartier_id' => $quartier?->id,
            'est_principal' => true,
        ]);
    }

    private function pointFraicheur(Quartier $quartier, string $nom, float $lat, float $lng, bool $climatisee): Residence
    {
        return Residence::create([
            'nom' => $nom,
            'adresse' => 'Adresse '.$nom,
            'quartier_id' => $quartier->id,
            'latitude' => $lat,
            'longitude' => $lng,
            'salle_climatisee' => $climatisee,
            'point_fraicheur' => true,
        ]);
    }

    private function fakeForecast(float $temperature = 38.0): void
    {
        $hours = [];
        for ($i = 0; $i < 26; $i++) {
            $moment = now()->addHours($i + 1);
            $hours[] = [
                'time_epoch' => $moment->getTimestamp(),
                'time' => $moment->format('Y-m-d H:i'),
                'temp_c' => $temperature,
                'feelslike_c' => $temperature + 1,
                'humidity' => 45,
                'wind_kph' => 12,
                'condition' => ['text' => 'Ensoleillé'],
            ];
        }

        Http::fake([
            'api.weatherapi.com/*' => Http::response([
                'location' => ['name' => 'Tunis', 'tz_id' => 'Africa/Tunis'],
                'forecast' => ['forecastday' => [['hour' => $hours]]],
            ]),
        ]);
    }

    public function test_the_dashboard_renders_the_timeline_and_the_refuge_map(): void
    {
        config(['services.weather.key' => 'fake-key']);
        $this->fakeForecast();

        $quartier = $this->quartier(36.8008, 10.1800);
        $habitant = $this->habitant();
        $this->lieu($habitant, 36.8008, 10.1800, $quartier);

        $this->pointFraicheur($quartier, 'Médiathèque Ibn Abi Rabiaa', 36.8035, 10.1760, true);
        $this->pointFraicheur($quartier, 'Parc ombragé du Belvédère', 36.7975, 10.1830, false);

        $this->actingAs($habitant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Chronologie 24h')
            ->assertSee("Zones d'Ombre", false)
            ->assertSee('Médiathèque Ibn Abi Rabiaa')
            ->assertSee('Parc ombragé du Belvédère')
            ->assertSee('PIC'); // tuile « pic canicule » issue de la météo live
    }

    public function test_refuges_beyond_eight_hundred_metres_are_excluded(): void
    {
        config(['services.weather.key' => 'fake-key']);
        $this->fakeForecast();

        $quartier = $this->quartier(36.8008, 10.1800);
        $habitant = $this->habitant();
        $this->lieu($habitant, 36.8008, 10.1800, $quartier);

        $this->pointFraicheur($quartier, 'Refuge tout proche', 36.8035, 10.1760, true);
        // ~13 km au nord-est : hors du périmètre de 800 m.
        $this->pointFraicheur($quartier, 'Refuge très lointain', 36.8900, 10.2900, true);

        $this->actingAs($habitant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Refuge tout proche')
            ->assertDontSee('Refuge très lointain');
    }

    public function test_the_dashboard_degrades_gracefully_without_coordinates(): void
    {
        $quartier = $this->quartier(); // pas de coordonnées
        $habitant = $this->habitant();
        $this->lieu($habitant, null, null, $quartier); // lieu sans position

        $this->actingAs($habitant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ajoutez un lieu avec sa position')
            ->assertSee('Prévision météo indisponible');
    }

    public function test_the_dashboard_invites_to_create_a_lieu_when_the_household_has_none(): void
    {
        $habitant = $this->habitant(); // aucun lieu

        $this->actingAs($habitant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ajoutez votre premier lieu');
    }

    public function test_the_dashboard_degrades_gracefully_when_the_weather_api_is_down(): void
    {
        config(['services.weather.key' => 'fake-key']);
        Http::fake(['api.weatherapi.com/*' => Http::response('boom', 500)]);

        $quartier = $this->quartier(36.8008, 10.1800);
        $habitant = $this->habitant();
        $this->lieu($habitant, 36.8008, 10.1800, $quartier);

        $this->actingAs($habitant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Prévision météo indisponible');
    }
}
