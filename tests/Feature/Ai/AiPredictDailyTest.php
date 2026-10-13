<?php

namespace Tests\Feature\Ai;

use App\Models\AiPrediction;
use App\Models\Alerte;
use App\Models\Quartier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * `ai:predict-daily` : produit uniquement des BROUILLONS (jamais publiés) et
 * ne crée pas de doublon quand une alerte de même niveau couvre déjà le créneau.
 *
 * Le microservice IA est simulé (Http::fake) : la réponse `/analyze` classe la
 * journée en rouge, `/message` renvoie un texte figé.
 */
class AiPredictDailyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'services.weather.key' => 'test-key',
            'services.groq.key' => null,
            'ai.enabled' => true,
            'ai.service_url' => 'http://ai.test',
            'ai.token' => 'test-token',
        ]);

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, 'forecast.json')) {
                return Http::response($this->forecastPayload());
            }

            if (str_contains($url, 'ai.test/analyze')) {
                return Http::response($this->analyzePayload());
            }

            if (str_contains($url, 'ai.test/message')) {
                return Http::response(['message' => 'Bois de l\'eau et reste au frais. Appelle le 190 en cas de malaise.']);
            }

            return Http::response($this->currentPayload());
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function currentPayload(): array
    {
        return [
            'location' => ['name' => 'Tunis', 'tz_id' => 'UTC'],
            'current' => [
                'temp_c' => 41.0,
                'feelslike_c' => 44.0,
                'humidity' => 45,
                'wind_kph' => 12,
                'condition' => ['text' => 'Ensoleillé'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function forecastPayload(): array
    {
        $base = now()->startOfHour()->timestamp;
        $heures = [];

        for ($i = 0; $i < 24; $i++) {
            $heures[] = [
                'time_epoch' => $base + $i * 3600,
                'temp_c' => 40.0 + ($i % 3),
                'feelslike_c' => 43.0,
                'humidity' => 40,
                'wind_kph' => 10,
                'condition' => ['text' => 'Ensoleillé'],
            ];
        }

        return [
            'location' => ['name' => 'Tunis', 'tz_id' => 'UTC'],
            'forecast' => ['forecastday' => [['hour' => $heures]]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzePayload(): array
    {
        return [
            'niveau_final' => 'rouge',
            'niveau_modele' => 'rouge',
            'niveau_regles' => 'rouge',
            'desaccord' => false,
            'probabilites' => ['normal' => 0.01, 'jaune' => 0.04, 'orange' => 0.15, 'rouge' => 0.80],
            'confiance' => 0.80,
            'facteurs' => [
                ['feature' => 'temp', 'label' => 'Température', 'valeur_affichee' => '41 °C', 'impact' => 0.4],
            ],
            'raison' => 'Température 41 °C et humidité élevée → vigilance rouge.',
            'source' => 'modele',
            'model_version' => '1.0.0',
        ];
    }

    private function quartierGeolocalise(): Quartier
    {
        return Quartier::create([
            'nom' => 'Centre-Ville', 'ville' => 'Tunis', 'code_postal' => '1000',
            'latitude' => 36.8065, 'longitude' => 10.1815,
        ]);
    }

    public function test_it_creates_a_draft_alert_with_a_linked_prediction_trace(): void
    {
        $this->quartierGeolocalise();

        $this->artisan('ai:predict-daily')->assertSuccessful();

        $alerte = Alerte::firstOrFail();

        $this->assertFalse($alerte->validee, 'Une alerte auto ne doit jamais être publiée.');
        $this->assertSame('weatherapi', $alerte->source_meteo);
        $this->assertTrue($alerte->hasCoordinates());

        $trace = AiPrediction::firstOrFail();
        $this->assertSame($alerte->id, $trace->alerte_id);
        $this->assertNull($trace->niveau_final);
        $this->assertSame('rouge', $trace->niveau_regles);
        $this->assertSame('modele', $trace->source);
        $this->assertIsArray($trace->context);
        $this->assertArrayHasKey('temperature', $trace->context);
    }

    public function test_it_does_not_create_a_duplicate_on_a_second_run(): void
    {
        $this->quartierGeolocalise();

        $this->artisan('ai:predict-daily')->assertSuccessful();
        $this->artisan('ai:predict-daily')->assertSuccessful();

        $this->assertSame(1, Alerte::count(), 'Le second passage ne doit pas créer de doublon.');
    }

    public function test_dry_run_creates_nothing(): void
    {
        $this->quartierGeolocalise();

        $this->artisan('ai:predict-daily', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, Alerte::count());
    }
}
