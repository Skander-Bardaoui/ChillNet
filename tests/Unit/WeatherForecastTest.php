<?php

namespace Tests\Unit;

use App\Services\MeteoHoraire;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherForecastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function service(): WeatherService
    {
        return app(WeatherService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $nbHeures = 30, float $temperature = 30.0): array
    {
        $hours = [];
        for ($i = 0; $i < $nbHeures; $i++) {
            $moment = now()->addHours($i + 1); // strictement dans le futur
            $hours[] = [
                'time_epoch' => $moment->getTimestamp(),
                'time' => $moment->format('Y-m-d H:i'),
                'temp_c' => $temperature + $i,
                'feelslike_c' => $temperature + $i + 1,
                'humidity' => 50,
                'wind_kph' => 10,
                'condition' => ['text' => 'Ensoleillé'],
            ];
        }

        return [
            'location' => ['name' => 'Tunis', 'tz_id' => 'Africa/Tunis'],
            'forecast' => ['forecastday' => [['hour' => $hours]]],
        ];
    }

    public function test_previsions_returns_the_next_hours_from_the_api(): void
    {
        config(['services.weather.key' => 'fake-key']);
        Http::fake(['api.weatherapi.com/*' => Http::response($this->payload())]);

        $heures = $this->service()->previsions(36.8008, 10.1800, 24);

        $this->assertCount(24, $heures);
        $this->assertInstanceOf(MeteoHoraire::class, $heures[0]);
        $this->assertGreaterThanOrEqual(30.0, $heures[0]->temperature);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/forecast.json'));
    }

    public function test_previsions_returns_empty_without_api_key(): void
    {
        config(['services.weather.key' => null]);

        $this->assertSame([], $this->service()->previsions(36.8008, 10.1800));
    }

    public function test_previsions_returns_empty_without_coordinates(): void
    {
        config(['services.weather.key' => 'fake-key']);

        $this->assertSame([], $this->service()->previsions(null, null));
    }

    public function test_previsions_returns_empty_when_the_api_fails(): void
    {
        config(['services.weather.key' => 'fake-key']);
        Http::fake(['api.weatherapi.com/*' => Http::response('boom', 500)]);

        $this->assertSame([], $this->service()->previsions(35.1000, 9.5000));
    }

    public function test_previsions_returns_empty_on_an_unexpected_payload(): void
    {
        config(['services.weather.key' => 'fake-key']);
        Http::fake(['api.weatherapi.com/*' => Http::response(['location' => ['name' => 'Tunis']])]);

        $this->assertSame([], $this->service()->previsions(34.2000, 9.1000));
    }

    public function test_previsions_memoises_a_success(): void
    {
        config(['services.weather.key' => 'fake-key']);
        Http::fake(['api.weatherapi.com/*' => Http::response($this->payload())]);

        $service = $this->service();

        $this->assertCount(24, $service->previsions(36.8008, 10.1800, 24));
        $this->assertCount(24, $service->previsions(36.8008, 10.1800, 24));
        // Le second appel est servi par le cache : un seul appel HTTP.
        Http::assertSentCount(1);
    }

    public function test_previsions_does_not_memoise_a_failure(): void
    {
        config(['services.weather.key' => 'fake-key']);
        Http::fake(['api.weatherapi.com/*' => Http::response('boom', 500)]);

        $service = $this->service();

        $this->assertSame([], $service->previsions(35.1000, 9.5000, 24));
        $this->assertSame([], $service->previsions(35.1000, 9.5000, 24));
        // L'échec n'est jamais figé : chaque appel retente.
        Http::assertSentCount(2);
    }

    public function test_actuel_cache_returns_the_reading_and_memoises_the_success(): void
    {
        config(['services.weather.key' => 'fake-key']);
        Http::fake([
            'api.weatherapi.com/*' => Http::response([
                'location' => ['name' => 'Tunis'],
                'current' => [
                    'temp_c' => 33.2,
                    'feelslike_c' => 35.0,
                    'humidity' => 40,
                    'wind_kph' => 9,
                    'condition' => ['text' => 'Ensoleillé'],
                ],
            ]),
        ]);

        $service = $this->service();
        $premier = $service->actuelCache(36.8008, 10.1800);
        $second = $service->actuelCache(36.8008, 10.1800);

        $this->assertNotNull($premier);
        $this->assertSame(33.2, $premier->temperature);
        $this->assertSame(33.2, $second->temperature);
        // Le second appel est servi par le cache : un seul appel HTTP.
        Http::assertSentCount(1);
    }

    public function test_actuel_cache_does_not_memoise_a_failure(): void
    {
        config(['services.weather.key' => 'fake-key']);
        Http::fake(['api.weatherapi.com/*' => Http::response('boom', 500)]);

        $service = $this->service();

        $this->assertNull($service->actuelCache(35.1000, 9.5000));
        $this->assertNull($service->actuelCache(35.1000, 9.5000));
        // L'échec n'est jamais figé : chaque appel retente.
        Http::assertSentCount(2);
    }
}
